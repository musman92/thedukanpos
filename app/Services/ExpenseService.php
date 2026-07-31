<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        protected TransactionService $transactions,
        protected FinanceService $finance,
    ) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   account_id?:int|string|null,
     *   money_source_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   expenses: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   accounts: Collection,
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
        $accountId = $filters['account_id'] !== null && $filters['account_id'] !== ''
            ? (int) $filters['account_id']
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

        $expenses = LedgerTransaction::query()
            ->with([
                'account:id,name,type',
                'moneySource:id,name,type',
                'creator:id,name,username',
                'branch:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->where('direction', 'out')
            ->where('reference_type', 'expense')
            ->where('is_manual', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('moneySource', fn ($m) => $m->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
            ->when($moneySourceId, fn ($query) => $query->where('money_source_id', $moneySourceId))
            ->when($from, fn ($query) => $query->whereDate('txn_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('txn_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LedgerTransaction $txn) => $this->serialize($txn));

        return [
            'expenses' => $expenses,
            'filters' => [
                'q' => $q,
                'account_id' => $accountId,
                'money_source_id' => $moneySourceId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'accounts' => Account::query()
                ->where('type', 'expense')
                ->where('is_active', true)
                ->whereRaw('LOWER(name) != ?', ['salary'])
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
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'txn_date', 'amount'];
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
     *   money_source_id:int,
     *   amount:float|int|string,
     *   expense_date:string,
     *   notes?:string|null
     * }  $data
     */
    public function create(array $data): LedgerTransaction
    {
        $this->assertExpenseAccount((int) $data['account_id']);

        return $this->transactions->create([
            'account_id' => (int) $data['account_id'],
            'money_source_id' => (int) $data['money_source_id'],
            'direction' => 'out',
            'amount' => $data['amount'],
            'txn_date' => $data['expense_date'],
            'reference_type' => 'expense',
            'reference_id' => null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *   account_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   expense_date:string,
     *   notes?:string|null
     * }  $data
     */
    public function update(LedgerTransaction $expense, array $data): LedgerTransaction
    {
        $this->assertIsExpense($expense);
        $this->assertExpenseAccount((int) $data['account_id']);

        return $this->transactions->update($expense, [
            'account_id' => (int) $data['account_id'],
            'money_source_id' => (int) $data['money_source_id'],
            'direction' => 'out',
            'amount' => $data['amount'],
            'txn_date' => $data['expense_date'],
            'reference_type' => 'expense',
            'reference_id' => null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function delete(LedgerTransaction $expense): void
    {
        $this->assertIsExpense($expense);
        $this->transactions->delete($expense);
    }

    public function assertIsExpense(LedgerTransaction $txn): void
    {
        if (
            $txn->direction !== 'out'
            || $txn->reference_type !== 'expense'
            || ! $txn->is_manual
        ) {
            throw ValidationException::withMessages([
                'expense' => 'This record is not a manual expense.',
            ]);
        }
    }

    protected function assertExpenseAccount(int $accountId): void
    {
        $account = Account::query()->find($accountId);

        if (! $account || $account->type !== 'expense' || ! $account->is_active) {
            throw ValidationException::withMessages([
                'account_id' => 'Select an active expense account.',
            ]);
        }

        if (strcasecmp((string) $account->name, 'Salary') === 0) {
            throw ValidationException::withMessages([
                'account_id' => 'Use Employee payments for salary. Salary is not available as an expense.',
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
            'amount' => round((float) $txn->amount, 2),
            'expense_date' => $txn->txn_date?->format('Y-m-d'),
            'notes' => $txn->notes,
            'account_id' => $txn->account_id,
            'money_source_id' => $txn->money_source_id,
            'account' => $txn->account
                ? [
                    'id' => $txn->account->id,
                    'name' => $txn->account->name,
                    'type' => $txn->account->type,
                ]
                : null,
            'money_source' => $txn->moneySource
                ? [
                    'id' => $txn->moneySource->id,
                    'name' => $txn->moneySource->name,
                    'type' => $txn->moneySource->type,
                ]
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
