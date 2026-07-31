<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{categories: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $categories = Category::query()
            ->with(['parent:id,name,code', 'defaultTax:id,name,code,rate'])
            ->withCount(['products', 'children'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'categories' => $categories,
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
     * @param  array{name:string, code?:string|null, parent_id?:int|null, default_tax_id?:int|null, is_active?:bool}  $data
     */
    public function create(array $data): Category
    {
        $name = trim((string) $data['name']);
        $code = Category::resolveCode($data['code'] ?? null);
        $parentId = $this->normalizeOptionalId($data['parent_id'] ?? null);
        $taxId = $this->normalizeOptionalId($data['default_tax_id'] ?? null);

        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);
        $this->assertParentValid($parentId);

        return Category::query()->create([
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'default_tax_id' => $taxId,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, parent_id?:int|null, default_tax_id?:int|null, is_active?:bool}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $taxId = array_key_exists('default_tax_id', $data)
            ? $this->normalizeOptionalId($data['default_tax_id'])
            : $category->default_tax_id;

        if ($category->is_system) {
            $category->update([
                'default_tax_id' => $taxId,
                'is_active' => true,
            ]);

            return $category->refresh();
        }

        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($category->code ?: Category::resolveCode(null));

        $parentId = array_key_exists('parent_id', $data)
            ? $this->normalizeOptionalId($data['parent_id'])
            : $category->parent_id;

        $this->assertNameAvailable($name, $category->id);
        $this->assertCodeAvailable($code, $category->id);
        $this->assertParentValid($parentId, $category->id);

        $category->update([
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'default_tax_id' => $taxId,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $category->is_active,
        ]);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'System categories cannot be deleted.',
            ]);
        }

        $childCount = $category->children()->count();
        if ($childCount > 0) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete this category because it has {$childCount} subcategor".($childCount === 1 ? 'y' : 'ies').'.',
            ]);
        }

        $productCount = $category->products()->count();
        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete this category because it is used by {$productCount} product(s).",
            ]);
        }

        $category->delete();
    }

    protected function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (int) $value;
    }

    protected function assertParentValid(?int $parentId, ?int $categoryId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($categoryId !== null && $parentId === $categoryId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $parent = Category::query()->find($parentId);
        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category is invalid.',
            ]);
        }

        if ($categoryId !== null && $this->isDescendantOf($parentId, $categoryId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot set a subcategory as the parent (circular reference).',
            ]);
        }
    }

    /**
     * True if $candidateId is $ancestorId or nested under it.
     */
    protected function isDescendantOf(int $candidateId, int $ancestorId): bool
    {
        $current = Category::query()->find($candidateId);
        $guard = 0;

        while ($current && $guard < 50) {
            if ((int) $current->id === $ancestorId) {
                return true;
            }
            if (! $current->parent_id) {
                return false;
            }
            $current = Category::query()->find($current->parent_id);
            $guard++;
        }

        return false;
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This category name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Category::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This category code is already taken.',
            ]);
        }
    }
}
