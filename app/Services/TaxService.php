<?php

namespace App\Services;

use App\Models\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TaxService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{taxes: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $taxes = Tax::query()
            ->withCount(['products', 'categories'])
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
            ->through(function (Tax $tax) {
                $tax->setAttribute(
                    'usage_count',
                    (int) $tax->products_count + (int) $tax->categories_count,
                );

                return $tax;
            });

        return [
            'taxes' => $taxes,
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
        $allowed = ['id', 'name', 'code', 'rate', 'is_active'];
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
     * @param  array{
     *   name:string,
     *   code?:string|null,
     *   rate:float|int|string,
     *   is_inclusive?:bool,
     *   is_active?:bool
     * }  $data
     */
    public function create(array $data): Tax
    {
        $name = trim((string) $data['name']);
        $this->assertNameAvailable($name);

        $code = Tax::resolveCode($data['code'] ?? null);
        $this->assertCodeAvailable($code);

        return Tax::query()->create([
            'name' => $name,
            'code' => $code,
            'rate' => (float) $data['rate'],
            'is_inclusive' => array_key_exists('is_inclusive', $data)
                ? (bool) $data['is_inclusive']
                : false,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true,
        ]);
    }

    /**
     * @param  array{
     *   name?:string,
     *   code?:string|null,
     *   rate?:float|int|string,
     *   is_inclusive?:bool,
     *   is_active?:bool
     * }  $data
     */
    public function update(Tax $tax, array $data): Tax
    {
        $name = trim((string) ($data['name'] ?? $tax->name));
        $this->assertNameAvailable($name, $tax->id);

        $code = array_key_exists('code', $data)
            ? Tax::resolveCode($data['code'])
            : (string) $tax->code;
        $this->assertCodeAvailable($code, $tax->id);

        $tax->update([
            'name' => $name,
            'code' => $code,
            'rate' => array_key_exists('rate', $data) ? (float) $data['rate'] : (float) $tax->rate,
            'is_inclusive' => array_key_exists('is_inclusive', $data)
                ? (bool) $data['is_inclusive']
                : $tax->is_inclusive,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $tax->is_active,
        ]);

        return $tax->refresh();
    }

    public function delete(Tax $tax): void
    {
        $usage = $this->usageCount($tax);

        if ($usage > 0) {
            throw ValidationException::withMessages([
                'tax' => 'Cannot delete this tax because it is assigned to products or categories.',
            ]);
        }

        $tax->delete();
    }

    public function usageCount(Tax $tax): int
    {
        return (int) $tax->products()->count() + (int) $tax->categories()->count();
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Tax::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This tax name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Tax::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This tax code is already taken.',
            ]);
        }
    }
}
