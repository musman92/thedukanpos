<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{accounts: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
     */
    public function paginate(array $filters = []): array
    {
        $this->finance->seedDefaultAccounts();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $accounts = Account::query()
            ->withCount('transactions')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('type', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Account $account) {
                $account->setAttribute('usage_count', (int) $account->transactions_count);

                return $account;
            });

        return [
            'accounts' => $accounts,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name', 'type', 'is_active'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sort === 'id' ? 'desc' : 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{name:string, type:string, is_active?:bool}  $data
     */
    public function create(array $data): Account
    {
        $name = trim((string) $data['name']);
        $type = (string) $data['type'];
        $this->assertNameAvailable($name);

        return Account::query()->create([
            'name' => $name,
            'type' => $type,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'is_system' => false,
        ]);
    }

    /**
     * @param  array{name?:string, type?:string, is_active?:bool}  $data
     */
    public function update(Account $account, array $data): Account
    {
        if ($account->is_system) {
            $account->update([
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $account->is_active,
            ]);

            return $account->refresh();
        }

        $name = trim((string) ($data['name'] ?? $account->name));
        $type = (string) ($data['type'] ?? $account->type);
        $this->assertNameAvailable($name, $account->id);

        $account->update([
            'name' => $name,
            'type' => $type,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $account->is_active,
        ]);

        return $account->refresh();
    }

    public function delete(Account $account): void
    {
        if ($account->is_system) {
            throw ValidationException::withMessages([
                'account' => 'System accounts cannot be deleted.',
            ]);
        }

        $usage = $this->usageCount($account);

        if ($usage > 0) {
            throw ValidationException::withMessages([
                'account' => "Cannot delete this account because it is used by {$usage} transaction(s).",
            ]);
        }

        $account->delete();
    }

    public function usageCount(Account $account): int
    {
        return (int) $account->transactions()->count();
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Account::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This account name is already taken.',
            ]);
        }
    }
}
