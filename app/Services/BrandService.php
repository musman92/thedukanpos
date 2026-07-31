<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BrandService
{
    public function __construct(protected ImageUploadService $images) {}

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{brands: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $brands = Brand::query()
            ->withCount('products')
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
            'brands' => $brands,
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
     * @param  array{name:string, code?:string|null, is_active?:bool, image?:UploadedFile|null}  $data
     */
    public function create(array $data): Brand
    {
        $name = trim((string) $data['name']);
        $code = Brand::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        $imagePath = null;
        if (($data['image'] ?? null) instanceof UploadedFile) {
            $imagePath = $this->images->storeCompressed($data['image'], 'brands');
        }

        return Brand::query()->create([
            'name' => $name,
            'code' => $code,
            'image' => $imagePath,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, is_active?:bool, image?:UploadedFile|null, remove_image?:bool}  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($brand->code ?: Brand::resolveCode(null));

        $this->assertNameAvailable($name, $brand->id);
        $this->assertCodeAvailable($code, $brand->id);

        $imagePath = $brand->image;

        if (! empty($data['remove_image'])) {
            $this->images->delete($brand->image);
            $imagePath = null;
        }

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $this->images->delete($brand->image);
            $imagePath = $this->images->storeCompressed($data['image'], 'brands');
        }

        $brand->update([
            'name' => $name,
            'code' => $code,
            'image' => $imagePath,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $brand->is_active,
        ]);

        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $productCount = $brand->products()->count();

        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'brand' => "Cannot delete this brand because it is used by {$productCount} product(s).",
            ]);
        }

        $this->images->delete($brand->image);
        $brand->delete();
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This brand name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Brand::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This brand code is already taken.',
            ]);
        }
    }
}
