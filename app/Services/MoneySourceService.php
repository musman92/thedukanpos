<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Shift;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MoneySourceService
{
    /**
     * Ensure Cash + Owner Withdrawal defaults exist (idempotent).
     */
    public function seedDefaults(): void
    {
        MoneySource::query()->firstOrCreate(
            ['code' => 'cash'],
            [
                'name' => 'Cash',
                'type' => 'CASH',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'exclude_from_dashboard_profit' => false,
                'is_system' => false,
            ],
        );

        MoneySource::query()->firstOrCreate(
            ['code' => 'card'],
            [
                'name' => 'Card',
                'type' => 'BANK',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'exclude_from_dashboard_profit' => false,
                'is_system' => false,
            ],
        );

        MoneySource::query()->firstOrCreate(
            ['system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL],
            [
                'name' => 'Owner Withdrawal',
                'code' => 'owner_withdrawal',
                'type' => 'OWNER_DRAW',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'exclude_from_dashboard_profit' => false,
                'is_system' => true,
            ],
        );

        $branchIds = Branch::query()->where('is_active', true)->pluck('id');
        if ($branchIds->isEmpty()) {
            return;
        }

        MoneySource::query()
            ->whereDoesntHave('branches')
            ->get()
            ->each(fn (MoneySource $source) => $source->branches()->syncWithoutDetaching($branchIds->all()));
    }

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{
     *   money_sources: LengthAwarePaginator,
     *   system_sources: Collection<int, MoneySource>,
     *   filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string},
     *   branches: Collection<int, array{id:int,name:string}>
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->seedDefaults();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $moneySources = MoneySource::query()
            ->operational()
            ->with(['branches:id,name'])
            ->withCount('branches')
            ->when($branchId = BranchContext::id(), function ($query) use ($branchId) {
                $query->forBranch((int) $branchId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('type', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MoneySource $source) => $this->serializeSource($source));

        $systemSources = MoneySource::query()
            ->where('is_system', true)
            ->orderBy('name')
            ->get()
            ->map(fn (MoneySource $source) => $this->serializeSource($source));

        return [
            'money_sources' => $moneySources,
            'system_sources' => $systemSources,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name', 'type', 'opening_balance', 'balance', 'is_active'];
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
     * @param  array{name:string, type:string, opening_balance?:float|int|string, is_active?:bool, exclude_from_dashboard_profit?:bool, branch_ids?:list<int>}  $data
     */
    public function create(array $data): MoneySource
    {
        $name = trim((string) $data['name']);
        $type = strtoupper((string) $data['type']);
        $this->assertCreatableType($type);
        $this->assertNameAvailable($name);

        $opening = round((float) ($data['opening_balance'] ?? 0), 4);
        if ($opening < 0) {
            throw ValidationException::withMessages([
                'opening_balance' => 'Opening balance cannot be negative.',
            ]);
        }

        $source = MoneySource::query()->create([
            'name' => $name,
            'type' => $type,
            'opening_balance' => $opening,
            'balance' => $opening,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'exclude_from_dashboard_profit' => (bool) ($data['exclude_from_dashboard_profit'] ?? false),
            'is_system' => false,
        ]);

        $branchIds = $this->resolveBranchIds($data['branch_ids'] ?? null);
        $source->branches()->sync($branchIds);

        return $source->load('branches');
    }

    /**
     * @param  array{name:string, type:string, is_active?:bool, exclude_from_dashboard_profit?:bool, branch_ids?:list<int>}  $data
     */
    public function update(MoneySource $moneySource, array $data): MoneySource
    {
        if ($moneySource->is_system) {
            throw ValidationException::withMessages([
                'money_source' => 'System money sources cannot be edited.',
            ]);
        }

        $name = trim((string) $data['name']);
        $type = strtoupper((string) $data['type']);
        $this->assertCreatableType($type);
        $this->assertNameAvailable($name, $moneySource->id);

        $moneySource->update([
            'name' => $name,
            'type' => $type,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $moneySource->is_active,
            'exclude_from_dashboard_profit' => array_key_exists('exclude_from_dashboard_profit', $data)
                ? (bool) $data['exclude_from_dashboard_profit']
                : $moneySource->exclude_from_dashboard_profit,
        ]);

        if (array_key_exists('branch_ids', $data)) {
            $moneySource->branches()->sync($this->resolveBranchIds($data['branch_ids']));
        }

        return $moneySource->refresh()->load('branches');
    }

    public function delete(MoneySource $moneySource): void
    {
        if ($moneySource->is_system) {
            throw ValidationException::withMessages([
                'money_source' => 'System money sources cannot be deleted.',
            ]);
        }

        $inActiveShift = Shift::query()
            ->whereNull('closed_at')
            ->whereHas('moneySources', fn ($q) => $q->where('money_source_id', $moneySource->id))
            ->exists();

        if ($inActiveShift) {
            throw ValidationException::withMessages([
                'money_source' => "Money source '{$moneySource->name}' cannot be deleted as it is being used in an active shift.",
            ]);
        }

        $moneySource->delete();
    }

    /**
     * @return list<array{id:int,name:string,type:string,balance:float}>
     */
    public function operationalOptions(?int $branchId = null): array
    {
        $branchId ??= BranchContext::ensure()->id;

        return MoneySource::query()
            ->forPayments()
            ->forBranch($branchId)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'balance'])
            ->map(fn (MoneySource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'balance' => round((float) $s->balance, 2),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSource(MoneySource $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'code' => $source->code,
            'type' => $source->type,
            'opening_balance' => round((float) $source->opening_balance, 2),
            'balance' => round((float) $source->balance, 2),
            'is_active' => (bool) $source->is_active,
            'exclude_from_dashboard_profit' => (bool) $source->exclude_from_dashboard_profit,
            'is_system' => (bool) $source->is_system,
            'system_key' => $source->system_key,
            'branches_count' => (int) ($source->branches_count ?? $source->branches->count()),
            'branch_ids' => $source->relationLoaded('branches')
                ? $source->branches->pluck('id')->all()
                : [],
            'branches' => $source->relationLoaded('branches')
                ? $source->branches->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->values()->all()
                : [],
        ];
    }

    protected function assertCreatableType(string $type): void
    {
        if (! in_array($type, MoneySource::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => 'Type must be Cash, Bank, or App.',
            ]);
        }
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = MoneySource::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This money source name is already taken.',
            ]);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<int>
     */
    protected function resolveBranchIds(?array $branchIds): array
    {
        if (is_array($branchIds) && count($branchIds) > 0) {
            $ids = collect($branchIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
            $valid = Branch::query()->whereIn('id', $ids)->pluck('id');

            if ($valid->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Select at least one valid branch.',
                ]);
            }

            return $valid->all();
        }

        $active = BranchContext::id() ?? Branch::query()->where('is_active', true)->value('id');
        if (! $active) {
            throw ValidationException::withMessages([
                'branch_ids' => 'No active branch available to assign.',
            ]);
        }

        return [(int) $active];
    }
}
