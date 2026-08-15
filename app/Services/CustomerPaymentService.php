<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\MoneySource;
use App\Models\Sale;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   customer_id?:int|string|null,
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
     *   customers: Collection,
     *   money_sources: Collection,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->finance->seedDefaultAccounts();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $customerId = $filters['customer_id'] !== null && $filters['customer_id'] !== ''
            ? (int) $filters['customer_id']
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

        $payments = CustomerPayment::query()
            ->with([
                'customer:id,name,balance',
                'moneySource:id,name,type',
                'receiver:id,name,username',
                'branch:id,name',
                'sales:id,number',
            ])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('moneySource', fn ($m) => $m->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($moneySourceId, fn ($query) => $query->where('money_source_id', $moneySourceId))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CustomerPayment $payment) => $this->serialize($payment));

        return [
            'payments' => $payments,
            'filters' => [
                'q' => $q,
                'customer_id' => $customerId,
                'money_source_id' => $moneySourceId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'balance'])
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'balance' => round((float) $c->balance, 2),
                ]),
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
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   form_customer_id: int|null,
     *   unpaid_sales: Collection,
     *   balance_summary: array{
     *     amount_owed: float,
     *     sales_pending: float,
     *     other_outstanding: float,
     *     prepayment_available: float
     *   }
     * }
     */
    public function formContext(?int $customerId = null, ?CustomerPayment $editing = null): array
    {
        $branch = BranchContext::ensure();
        $unpaidSales = collect();
        $salesPending = 0.0;
        $amountOwed = 0.0;
        $otherOutstanding = 0.0;
        $prepaymentAvailable = 0.0;

        /** @var array<int, float> $restoredAlloc */
        $restoredAlloc = [];
        if ($editing) {
            $editing->loadMissing('sales');
            foreach ($editing->sales as $sale) {
                $restoredAlloc[(int) $sale->id] = (float) $sale->pivot->amount;
            }
        }

        if ($customerId) {
            $customer = Customer::query()->find($customerId);
            if ($customer) {
                $balance = (float) $customer->balance;
                if ($editing && (int) $editing->customer_id === (int) $customerId) {
                    $balance += (float) $editing->amount + (float) ($editing->discount_amount ?? 0);
                }
                $amountOwed = round(max(0, $balance), 2);
                $prepaymentAvailable = round(max(0, -$balance), 2);
            }

            $restoredIds = array_keys($restoredAlloc);

            $unpaidSales = Sale::query()
                ->where('branch_id', $branch->id)
                ->where('customer_id', $customerId)
                ->where(function ($query) use ($restoredIds) {
                    $query->whereRaw('(total - paid_total) > 0.0001');
                    if ($restoredIds !== []) {
                        $query->orWhereIn('id', $restoredIds);
                    }
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(function (Sale $sale) use ($restoredAlloc) {
                    $restored = $restoredAlloc[(int) $sale->id] ?? 0.0;
                    $pending = round($sale->balanceDue() + $restored, 2);
                    if ($pending <= 0.0001) {
                        return null;
                    }

                    return [
                        'id' => $sale->id,
                        'number' => $sale->number,
                        'sale_date' => $sale->created_at?->format('Y-m-d'),
                        'total' => round((float) $sale->total, 2),
                        'paid_total' => round(max(0, (float) $sale->paid_total - $restored), 2),
                        'pending_amount' => $pending,
                    ];
                })
                ->filter()
                ->values();

            $salesPending = round((float) $unpaidSales->sum('pending_amount'), 2);
            $otherOutstanding = round(max(0, $amountOwed - $salesPending), 2);
        }

        return [
            'form_customer_id' => $customerId,
            'unpaid_sales' => $unpaidSales,
            'balance_summary' => [
                'amount_owed' => $amountOwed,
                'sales_pending' => $salesPending,
                'other_outstanding' => $otherOutstanding,
                'prepayment_available' => $prepaymentAvailable,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'payment_date', 'amount'];
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
     *   customer_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes?:string|null,
     *   total_amount?:float|int|string|null,
     *   discount_amount?:float|int|string|null,
     *   sale_amounts?:array<int|string, float|int|string|null>
     * }  $data
     */
    public function create(array $data): CustomerPayment
    {
        $branch = BranchContext::ensure();
        $customerId = (int) $data['customer_id'];
        $discountAmount = round(max(0, (float) ($data['discount_amount'] ?? 0)), 4);

        $unpaidSales = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('customer_id', $customerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Sale $s) => $s->balanceDue() > 0.0001)
            ->values();

        /** @var array<int, float> $allocMap */
        $allocMap = [];
        $cashAmount = 0.0;

        $hasTotal = isset($data['total_amount']) && $data['total_amount'] !== '' && $data['total_amount'] !== null;
        $saleAmounts = $data['sale_amounts'] ?? [];

        if ($hasTotal) {
            $cashAmount = round((float) $data['total_amount'], 4);
            if ($cashAmount <= 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Payment amount must be greater than zero.',
                ]);
            }

            // Cash + write-off clears unpaid sales oldest-first.
            $remaining = round($cashAmount + $discountAmount, 4);
            foreach ($unpaidSales as $sale) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $pending = $sale->balanceDue();
                $paymentAmount = round(min($remaining, $pending), 4);
                if ($paymentAmount > 0.0001) {
                    $allocMap[(int) $sale->id] = $paymentAmount;
                    $remaining = round($remaining - $paymentAmount, 4);
                }
            }
        } elseif (is_array($saleAmounts) && $saleAmounts !== []) {
            foreach ($saleAmounts as $saleId => $amount) {
                $amount = round((float) ($amount ?? 0), 4);
                if ($amount <= 0) {
                    continue;
                }

                $sale = $unpaidSales->firstWhere('id', (int) $saleId);
                if (! $sale) {
                    throw ValidationException::withMessages([
                        "sale_amounts.{$saleId}" => 'This sale is not unpaid for the selected customer.',
                    ]);
                }

                $pending = $sale->balanceDue();
                if ($amount > $pending + 0.01) {
                    throw ValidationException::withMessages([
                        "sale_amounts.{$saleId}" => 'Payment amount cannot exceed sale pending ('.format_amount($pending).'). Use total payment amount to pay opening balance or advance.',
                    ]);
                }

                $allocMap[(int) $saleId] = $amount;
                $cashAmount += $amount;
            }

            if ($cashAmount <= 0) {
                throw ValidationException::withMessages([
                    'sale_amounts' => 'Enter at least one sale payment amount, or a total payment amount.',
                ]);
            }

            // Apply write-off to remaining pending after line cash amounts.
            $remainingDiscount = $discountAmount;
            foreach ($unpaidSales as $sale) {
                if ($remainingDiscount <= 0.0001) {
                    break;
                }
                $saleId = (int) $sale->id;
                $already = $allocMap[$saleId] ?? 0.0;
                $stillDue = round($sale->balanceDue() - $already, 4);
                if ($stillDue <= 0.0001) {
                    continue;
                }
                $extra = round(min($remainingDiscount, $stillDue), 4);
                if ($extra > 0.0001) {
                    $allocMap[$saleId] = round($already + $extra, 4);
                    $remainingDiscount = round($remainingDiscount - $extra, 4);
                }
            }
        } else {
            throw ValidationException::withMessages([
                'total_amount' => 'Enter a total payment amount, or individual sale amounts.',
            ]);
        }

        $allocations = [];
        foreach ($allocMap as $saleId => $amount) {
            $allocations[] = [
                'sale_id' => $saleId,
                'amount' => $amount,
            ];
        }

        try {
            return $this->finance->receiveCustomerPayment([
                'customer_id' => $customerId,
                'money_source_id' => (int) $data['money_source_id'],
                'amount' => $cashAmount,
                'discount_amount' => $discountAmount,
                'branch_id' => $branch->id,
                'allocations' => $allocations,
                'apply_sale_paid' => true,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'total_amount' => $e->getMessage(),
            ]);
        }
    }

    public function delete(CustomerPayment $payment): void
    {
        try {
            $this->finance->reverseCustomerPayment($payment);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'payment' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{
     *   customer_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes?:string|null,
     *   total_amount?:float|int|string|null,
     *   discount_amount?:float|int|string|null,
     *   sale_amounts?:array<int|string, float|int|string|null>
     * }  $data
     */
    public function update(CustomerPayment $payment, array $data): CustomerPayment
    {
        try {
            return DB::transaction(function () use ($payment, $data) {
                $this->finance->reverseCustomerPayment($payment);

                return $this->create($data);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'total_amount' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => round((float) $payment->amount, 2),
            'discount_amount' => round((float) ($payment->discount_amount ?? 0), 2),
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'notes' => $payment->notes,
            'customer_id' => $payment->customer_id,
            'money_source_id' => $payment->money_source_id,
            'customer' => $payment->customer
                ? [
                    'id' => $payment->customer->id,
                    'name' => $payment->customer->name,
                    'balance' => round((float) $payment->customer->balance, 2),
                ]
                : null,
            'money_source' => $payment->moneySource
                ? [
                    'id' => $payment->moneySource->id,
                    'name' => $payment->moneySource->name,
                    'type' => $payment->moneySource->type,
                ]
                : null,
            'sales' => $payment->relationLoaded('sales')
                ? $payment->sales->map(fn (Sale $s) => [
                    'id' => $s->id,
                    'number' => $s->number,
                    'amount' => round((float) $s->pivot->amount, 2),
                ])->values()
                : [],
            'branch' => $payment->branch
                ? ['id' => $payment->branch->id, 'name' => $payment->branch->name]
                : null,
            'receiver' => $payment->receiver
                ? [
                    'id' => $payment->receiver->id,
                    'name' => $payment->receiver->name ?: $payment->receiver->username,
                ]
                : null,
        ];
    }
}
