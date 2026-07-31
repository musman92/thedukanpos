<?php

namespace App\Services;

use App\Models\EmployeePayment;
use App\Models\EmployeeProfile;
use App\Models\MoneySource;
use App\Models\PayrollItem;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EmployeePaymentService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   kind?:string|null,
     *   user_id?:int|string|null,
     *   money_source_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   payments: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   employees: Collection,
     *   money_sources: Collection,
     *   payable_payslips: Collection,
     *   kinds: list<array{value:string,label:string}>,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->finance->seedDefaultAccounts();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $kind = strtolower(trim((string) ($filters['kind'] ?? '')));
        if ($kind !== '' && ! in_array($kind, EmployeePayment::KINDS, true)) {
            $kind = '';
        }
        $userId = $filters['user_id'] !== null && $filters['user_id'] !== ''
            ? (int) $filters['user_id']
            : null;
        $moneySourceId = $filters['money_source_id'] !== null && $filters['money_source_id'] !== ''
            ? (int) $filters['money_source_id']
            : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $branch = BranchContext::ensure();

        $payments = EmployeePayment::query()
            ->with([
                'user:id,name,username',
                'moneySource:id,name,type',
                'creator:id,name,username',
                'branch:id,name',
                'payrollItem:id,payroll_run_id,net_pay,paid_amount,status',
                'payrollItem.payrollRun:id,number,period_start,period_end',
            ])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhere('kind', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('name', 'like', "%{$q}%")
                                ->orWhere('username', 'like', "%{$q}%");
                        })
                        ->orWhereHas('moneySource', fn ($m) => $m->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($moneySourceId, fn ($query) => $query->where('money_source_id', $moneySourceId))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmployeePayment $payment) => $this->serialize($payment));

        return [
            'payments' => $payments,
            'filters' => [
                'q' => $q,
                'kind' => $kind,
                'user_id' => $userId,
                'money_source_id' => $moneySourceId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'employees' => EmployeeProfile::query()
                ->active()
                ->with('user:id,name,username')
                ->orderBy('id')
                ->get()
                ->filter(fn (EmployeeProfile $p) => $p->user !== null)
                ->map(fn (EmployeeProfile $p) => [
                    'id' => $p->user_id,
                    'name' => $p->user->name ?: $p->user->username,
                    'employee_number' => $p->employee_number,
                    'designation' => $p->designation,
                ])
                ->values(),
            'money_sources' => MoneySource::query()
                ->forPayments()
                ->forBranch($branch->id)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance'])
                ->map(fn (MoneySource $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'type' => $s->type,
                    'balance' => round((float) $s->balance, 2),
                ]),
            'payable_payslips' => $this->payablePayslips(),
            'kinds' => collect(EmployeePayment::KINDS)
                ->map(fn (string $value) => [
                    'value' => $value,
                    'label' => EmployeePayment::kindLabel($value),
                ])
                ->values()
                ->all(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function payablePayslips(): Collection
    {
        return PayrollItem::query()
            ->with([
                'user:id,name,username',
                'payrollRun:id,number,period_start,period_end',
            ])
            ->whereIn('status', ['finalized', 'partial'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PayrollItem $item) => $item->remainingAmount() > 0.0001)
            ->map(function (PayrollItem $item) {
                $run = $item->payrollRun;
                $period = $run
                    ? ($run->period_start?->format('Y-m-d').' → '.$run->period_end?->format('Y-m-d'))
                    : '';

                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'employee_name' => $item->user
                        ? ($item->user->name ?: $item->user->username)
                        : 'Employee',
                    'run_number' => $run?->number,
                    'period' => $period,
                    'net_pay' => round((float) $item->net_pay, 2),
                    'paid_amount' => round((float) $item->paid_amount, 2),
                    'remaining' => round($item->remainingAmount(), 2),
                    'status' => $item->status,
                    'label' => trim(sprintf(
                        '%s · %s · rem %s',
                        $item->user?->name ?: $item->user?->username ?: 'Employee',
                        $run?->number ?? 'Payslip',
                        number_format($item->remainingAmount(), 2),
                    )),
                ];
            })
            ->values();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'payment_date', 'amount', 'kind'];
        $sort = strtolower(trim((string) ($sort ?? 'payment_date')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'payment_date';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{
     *   user_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   kind:string,
     *   payroll_item_id?:int|null,
     *   payment_date:string,
     *   notes?:string|null
     * }  $data
     */
    public function create(array $data): EmployeePayment
    {
        $branch = BranchContext::ensure();

        try {
            return $this->finance->payEmployee([
                ...$data,
                'branch_id' => $branch->id,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $message = $e->getMessage();
            $field = str_contains(strtolower($message), 'payslip')
                || str_contains(strtolower($message), 'payroll')
                ? 'payroll_item_id'
                : (str_contains(strtolower($message), 'payment type') ? 'kind' : 'amount');

            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }

    public function delete(EmployeePayment $payment): void
    {
        try {
            $this->finance->reverseEmployeePayment($payment);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'payment' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(EmployeePayment $payment): array
    {
        $kind = $payment->kind ?: 'wage';

        return [
            'id' => $payment->id,
            'kind' => $kind,
            'kind_label' => EmployeePayment::kindLabel($kind),
            'amount' => round((float) $payment->amount, 2),
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'notes' => $payment->notes,
            'user_id' => $payment->user_id,
            'money_source_id' => $payment->money_source_id,
            'payroll_item_id' => $payment->payroll_item_id,
            'user' => $payment->user
                ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name ?: $payment->user->username,
                ]
                : null,
            'money_source' => $payment->moneySource
                ? [
                    'id' => $payment->moneySource->id,
                    'name' => $payment->moneySource->name,
                    'type' => $payment->moneySource->type,
                ]
                : null,
            'payroll_item' => $payment->payrollItem
                ? [
                    'id' => $payment->payrollItem->id,
                    'run_number' => $payment->payrollItem->payrollRun?->number,
                    'remaining' => round($payment->payrollItem->remainingAmount(), 2),
                ]
                : null,
            'branch' => $payment->branch
                ? ['id' => $payment->branch->id, 'name' => $payment->branch->name]
                : null,
            'creator' => $payment->creator
                ? [
                    'id' => $payment->creator->id,
                    'name' => $payment->creator->name ?: $payment->creator->username,
                ]
                : null,
        ];
    }
}
