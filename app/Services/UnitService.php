<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UnitService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{units: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $units = Unit::query()
            ->withCount([
                'productsAsPurchase',
                'productsAsSale',
                'variantsAsPurchase',
                'variantsAsSale',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Unit $unit) {
                $unit->setAttribute(
                    'usage_count',
                    (int) $unit->products_as_purchase_count
                    + (int) $unit->products_as_sale_count
                    + (int) $unit->variants_as_purchase_count
                    + (int) $unit->variants_as_sale_count,
                );

                return $unit;
            });

        return [
            'units' => $units,
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
        $allowed = ['id', 'name', 'code'];
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
     * @param  array{name:string, code?:string|null, is_active?:bool}  $data
     */
    public function create(array $data): Unit
    {
        $name = trim((string) $data['name']);
        $code = Unit::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        return Unit::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, is_active?:bool}  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtolower($input)
            : ($unit->code ?: Unit::resolveCode(null));

        $this->assertNameAvailable($name, $unit->id);
        $this->assertCodeAvailable($code, $unit->id);

        $unit->update([
            'name' => $name,
            'code' => $code,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $unit->is_active,
        ]);

        return $unit->refresh();
    }

    public function delete(Unit $unit): void
    {
        $usage = $this->usageCount($unit);

        if ($usage > 0) {
            throw ValidationException::withMessages([
                'unit' => "Cannot delete this unit because it is used by {$usage} product/variant assignment(s).",
            ]);
        }

        $unit->delete();
    }

    public function usageCount(Unit $unit): int
    {
        $products = Product::query()
            ->where(function ($q) use ($unit) {
                $q->where('purchase_unit_id', $unit->id)
                    ->orWhere('sale_unit_id', $unit->id);
            })
            ->count();

        $variants = ProductVariant::query()
            ->where(function ($q) use ($unit) {
                $q->where('purchase_unit_id', $unit->id)
                    ->orWhere('sale_unit_id', $unit->id);
            })
            ->count();

        return $products + $variants;
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Unit::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This unit name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Unit::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This unit code is already taken.',
            ]);
        }
    }
}
