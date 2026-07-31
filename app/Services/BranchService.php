<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockDamage;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BranchService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{branches: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $branches = Branch::query()
            ->withCount(['users', 'stocks'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Branch $branch) => $this->serialize($branch));

        return [
            'branches' => $branches,
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
        $allowed = ['id', 'name', 'code', 'created_at'];
        $sort = strtolower(trim((string) ($sort ?? 'name')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'name';
        }

        $direction = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sort === 'id' ? 'desc' : 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{name:string, code?:string|null, phone?:string|null, address?:string|null, is_active?:bool}  $data
     */
    public function create(array $data): Branch
    {
        $name = trim((string) $data['name']);
        $code = Branch::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        return Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'phone' => $this->nullableString($data['phone'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, phone?:string|null, address?:string|null, is_active?:bool}  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($branch->code ?: Branch::resolveCode(null));

        $this->assertNameAvailable($name, $branch->id);
        $this->assertCodeAvailable($code, $branch->id);

        $isActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : $branch->is_active;

        if (! $isActive && $branch->is_active) {
            $this->assertCanDeactivate($branch);
        }

        $branch->update([
            'name' => $name,
            'code' => $code,
            'phone' => $this->nullableString($data['phone'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'is_active' => $isActive,
        ]);

        return $branch->refresh();
    }

    public function delete(Branch $branch): void
    {
        $this->assertCanDelete($branch);
        $branch->delete();
    }

    protected function assertCanDeactivate(Branch $branch): void
    {
        $otherActive = Branch::query()
            ->where('is_active', true)
            ->where('id', '!=', $branch->id)
            ->exists();

        if (! $otherActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Cannot deactivate the only active branch.',
            ]);
        }
    }

    protected function assertCanDelete(Branch $branch): void
    {
        $activeCount = Branch::query()->where('is_active', true)->count();
        if ($branch->is_active && $activeCount <= 1) {
            throw ValidationException::withMessages([
                'branch' => 'Cannot delete the only active branch.',
            ]);
        }

        $reasons = [];

        $assignedUsers = User::query()
            ->where(function ($q) use ($branch) {
                $q->where('branch_id', $branch->id)
                    ->orWhereHas('branches', fn ($inner) => $inner->where('branches.id', $branch->id));
            })
            ->count();
        if ($assignedUsers > 0) {
            $reasons[] = "{$assignedUsers} user(s)";
        }

        $stockRows = BranchStock::query()->where('branch_id', $branch->id)->count();
        if ($stockRows > 0) {
            $reasons[] = "{$stockRows} stock record(s)";
        }

        $sales = Sale::query()->where('branch_id', $branch->id)->count();
        if ($sales > 0) {
            $reasons[] = "{$sales} sale(s)";
        }

        $purchases = Purchase::query()->where('branch_id', $branch->id)->count();
        if ($purchases > 0) {
            $reasons[] = "{$purchases} purchase(s)";
        }

        $shifts = Shift::query()->where('branch_id', $branch->id)->count();
        if ($shifts > 0) {
            $reasons[] = "{$shifts} shift(s)";
        }

        $movements = StockMovement::query()->where('branch_id', $branch->id)->count();
        if ($movements > 0) {
            $reasons[] = "{$movements} stock movement(s)";
        }

        $adjustments = StockAdjustment::query()->where('branch_id', $branch->id)->count();
        if ($adjustments > 0) {
            $reasons[] = "{$adjustments} adjustment(s)";
        }

        $damages = StockDamage::query()->where('branch_id', $branch->id)->count();
        if ($damages > 0) {
            $reasons[] = "{$damages} damage record(s)";
        }

        $transfers = StockTransfer::query()
            ->where(function ($q) use ($branch) {
                $q->where('from_branch_id', $branch->id)
                    ->orWhere('to_branch_id', $branch->id);
            })
            ->count();
        if ($transfers > 0) {
            $reasons[] = "{$transfers} transfer(s)";
        }

        if ($reasons !== []) {
            throw ValidationException::withMessages([
                'branch' => 'Cannot delete this branch because it has '.implode(', ', $reasons).'. Deactivate it instead.',
            ]);
        }
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Branch::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This branch name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Branch::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This branch code is already taken.',
            ]);
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'phone' => $branch->phone,
            'address' => $branch->address,
            'is_active' => (bool) $branch->is_active,
            'users_count' => (int) ($branch->users_count ?? 0),
            'stocks_count' => (int) ($branch->stocks_count ?? 0),
            'created_at' => $branch->created_at?->format('Y-m-d H:i'),
        ];
    }
}
