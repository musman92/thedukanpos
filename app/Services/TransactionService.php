<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\Shift;
use App\Support\BranchContext;
use App\Support\MoneyBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   direction?:string|null,
     *   account_id?:int|string|null,
     *   money_source_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   reference_type?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   sort_direction?:string|null
     * }  $filters
     * @return array{
     *   transactions: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   accounts: Collection,
     *   money_sources: Collection,
     *   reference_types: list<string>,
     *   form_reference_types: list<array{value:string,label:string}>,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->finance->seedDefaultAccounts();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $directionFilter = strtolower(trim((string) ($filters['direction'] ?? '')));
        if (! in_array($directionFilter, ['in', 'out'], true)) {
            $directionFilter = '';
        }
        $accountId = $filters['account_id'] !== null && $filters['account_id'] !== ''
            ? (int) $filters['account_id']
            : null;
        $moneySourceId = $filters['money_source_id'] !== null && $filters['money_source_id'] !== ''
            ? (int) $filters['money_source_id']
            : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $referenceType = trim((string) ($filters['reference_type'] ?? ''));
        [$sort, $sortDirection] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['sort_direction'] ?? null,
        );

        $branch = BranchContext::ensure();

        $transactions = LedgerTransaction::query()
            ->with([
                'account:id,name,type',
                'moneySource:id,name,type',
                'creator:id,name,username',
                'branch:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhere('reference_type', 'like', "%{$q}%")
                        ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('moneySource', fn ($m) => $m->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($directionFilter !== '', fn ($query) => $query->where('direction', $directionFilter))
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
            ->when($moneySourceId, fn ($query) => $query->where('money_source_id', $moneySourceId))
            ->when($from, fn ($query) => $query->whereDate('txn_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('txn_date', '<=', $to))
            ->when($referenceType !== '', fn ($query) => $query->where('reference_type', $referenceType))
            ->orderBy($sort, $sortDirection)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LedgerTransaction $txn) => $this->serialize($txn));

        return [
            'transactions' => $transactions,
            'filters' => [
                'q' => $q,
                'direction' => $directionFilter,
                'account_id' => $accountId,
                'money_source_id' => $moneySourceId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'reference_type' => $referenceType,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'sort_direction' => $sortDirection,
            ],
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
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
            'reference_types' => LedgerTransaction::query()
                ->whereNotNull('reference_type')
                ->distinct()
                ->orderBy('reference_type')
                ->pluck('reference_type')
                ->values()
                ->all(),
            'form_reference_types' => collect(LedgerTransaction::MANUAL_REFERENCE_TYPES)
                ->map(fn (string $value) => [
                    'value' => $value,
                    'label' => ucfirst(str_replace('_', ' ', $value)),
                ])
                ->values()
                ->all(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'txn_date', 'amount', 'direction'];
        $sort = strtolower(trim((string) ($sort ?? 'txn_date')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'txn_date';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{
     *   account_id:int,
     *   money_source_id?:int|null,
     *   direction:string,
     *   amount:float|int|string,
     *   txn_date:string,
     *   reference_type?:string|null,
     *   reference_id?:int|null,
     *   notes?:string|null
     * }  $data
     */
    public function create(array $data): LedgerTransaction
    {
        $branch = BranchContext::ensure();
        $amount = round((float) $data['amount'], 4);
        $direction = (string) $data['direction'];
        $moneySourceId = ! empty($data['money_source_id']) ? (int) $data['money_source_id'] : null;
        $referenceType = ! empty($data['reference_type']) ? (string) $data['reference_type'] : null;
        $referenceId = ! empty($data['reference_id']) ? (int) $data['reference_id'] : null;

        $this->assertAccount((int) $data['account_id']);
        if ($moneySourceId) {
            $this->assertMoneySource($moneySourceId);
        }

        $shiftId = Shift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->value('id');

        return DB::transaction(function () use ($data, $branch, $amount, $direction, $moneySourceId, $shiftId, $referenceType, $referenceId) {
            if ($moneySourceId && $direction === 'out') {
                $source = MoneySource::query()->lockForUpdate()->findOrFail($moneySourceId);
                try {
                    $amount = MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['amount' => $e->getMessage()]);
                }
            }

            $txn = LedgerTransaction::query()->create([
                'branch_id' => $branch->id,
                'account_id' => (int) $data['account_id'],
                'money_source_id' => $moneySourceId,
                'shift_id' => $shiftId,
                'direction' => $direction,
                'amount' => $amount,
                'txn_date' => $data['txn_date'] ?? now()->toDateString(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'is_manual' => true,
            ]);

            if ($moneySourceId) {
                $source = MoneySource::query()->lockForUpdate()->findOrFail($moneySourceId);
                $source->balance = (float) $source->balance + ($direction === 'in' ? $amount : -$amount);
                $source->save();
            }

            return $txn;
        });
    }

    /**
     * @param  array{
     *   account_id:int,
     *   money_source_id?:int|null,
     *   direction:string,
     *   amount:float|int|string,
     *   txn_date:string,
     *   reference_type?:string|null,
     *   reference_id?:int|null,
     *   notes?:string|null
     * }  $data
     */
    public function update(LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        $this->assertManual($transaction);

        $amount = round((float) $data['amount'], 4);
        $direction = (string) $data['direction'];
        $moneySourceId = ! empty($data['money_source_id']) ? (int) $data['money_source_id'] : null;
        $referenceType = ! empty($data['reference_type']) ? (string) $data['reference_type'] : null;
        $referenceId = ! empty($data['reference_id']) ? (int) $data['reference_id'] : null;

        $this->assertAccount((int) $data['account_id']);
        if ($moneySourceId) {
            $this->assertMoneySource($moneySourceId);
        }

        return DB::transaction(function () use ($transaction, $data, $amount, $direction, $moneySourceId, $referenceType, $referenceId) {
            $txn = LedgerTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            $this->reverseMoneySourceImpact($txn);

            if ($moneySourceId && $direction === 'out') {
                $source = MoneySource::query()->lockForUpdate()->findOrFail($moneySourceId);
                try {
                    $amount = MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['amount' => $e->getMessage()]);
                }
            }

            $txn->update([
                'account_id' => (int) $data['account_id'],
                'money_source_id' => $moneySourceId,
                'direction' => $direction,
                'amount' => $amount,
                'txn_date' => $data['txn_date'] ?? $txn->txn_date,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($moneySourceId) {
                $source = MoneySource::query()->lockForUpdate()->findOrFail($moneySourceId);
                $source->balance = (float) $source->balance + ($direction === 'in' ? $amount : -$amount);
                $source->save();
            }

            return $txn->refresh();
        });
    }

    public function delete(LedgerTransaction $transaction): void
    {
        $this->assertManual($transaction);

        DB::transaction(function () use ($transaction) {
            $txn = LedgerTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->reverseMoneySourceImpact($txn);
            $txn->delete();
        });
    }

    protected function reverseMoneySourceImpact(LedgerTransaction $txn): void
    {
        if (! $txn->money_source_id) {
            return;
        }

        $source = MoneySource::query()->lockForUpdate()->find($txn->money_source_id);
        if (! $source) {
            return;
        }

        $amount = (float) $txn->amount;
        // Undo original effect
        $source->balance = (float) $source->balance + ($txn->direction === 'in' ? -$amount : $amount);
        $source->save();
    }

    protected function assertManual(LedgerTransaction $transaction): void
    {
        if (! $transaction->canBeModified()) {
            throw ValidationException::withMessages([
                'transaction' => 'System-generated transactions cannot be edited or deleted.',
            ]);
        }
    }

    protected function assertAccount(int $accountId): void
    {
        $exists = Account::query()->where('id', $accountId)->where('is_active', true)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'account_id' => 'Select a valid active account.',
            ]);
        }
    }

    protected function assertMoneySource(int $moneySourceId): void
    {
        $source = MoneySource::query()->find($moneySourceId);
        if (! $source || ! $source->isSelectableForPayment()) {
            throw ValidationException::withMessages([
                'money_source_id' => 'Select a valid money source.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(LedgerTransaction $txn): array
    {
        return [
            'id' => $txn->id,
            'txn_date' => $txn->txn_date?->format('Y-m-d'),
            'direction' => $txn->direction,
            'amount' => round((float) $txn->amount, 2),
            'notes' => $txn->notes,
            'reference_type' => $txn->reference_type,
            'reference_id' => $txn->reference_id,
            'is_manual' => (bool) $txn->is_manual,
            'account_id' => $txn->account_id,
            'money_source_id' => $txn->money_source_id,
            'account' => $txn->account
                ? ['id' => $txn->account->id, 'name' => $txn->account->name, 'type' => $txn->account->type]
                : null,
            'money_source' => $txn->moneySource
                ? ['id' => $txn->moneySource->id, 'name' => $txn->moneySource->name, 'type' => $txn->moneySource->type]
                : null,
            'branch' => $txn->branch
                ? ['id' => $txn->branch->id, 'name' => $txn->branch->name]
                : null,
            'creator' => $txn->creator
                ? [
                    'id' => $txn->creator->id,
                    'name' => $txn->creator->name ?: $txn->creator->username,
                ]
                : null,
        ];
    }
}
