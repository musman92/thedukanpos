<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\ProductVariant;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\Section;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockTransferItem;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Variation;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected InventoryService $inventory,
        protected ImageUploadService $images,
    ) {}

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null, branch_id?:int|null}  $filters
     * @return array{products: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $branchId = (int) ($filters['branch_id'] ?? BranchContext::ensure()->id);

        $products = Product::query()
            ->with(['brand', 'tax', 'category', 'variants.purchaseUnit', 'variants.saleUnit'])
            ->with(['variants.locations' => fn ($query) => $query->where('branch_id', $branchId)->with(['section', 'rack'])])
            ->with(['variants.stocks' => fn ($query) => $query->where('branch_id', $branchId)])
            ->withCount('variants')
            ->when($q !== '', fn ($query) => $query->search($q))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'products' => $products,
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
        $allowed = ['id', 'name', 'short_code', 'sale_price'];
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
     * @param  array{product: array<string, mixed>, variants: list<array<string, mixed>>}  $payload
     */
    public function create(array $payload, ?int $branchId = null): Product
    {
        $branchId ??= BranchContext::ensure()->id;
        [$productData, $variants] = $this->preparePayload($payload['product'], $payload['variants']);
        $this->assertProductCodeAvailable($productData['short_code']);
        $this->assertVariantUniqueness($variants);
        $this->assertBarcodesAvailable($variants);

        return DB::transaction(function () use ($productData, $variants, $branchId, $payload) {
            if (($payload['image'] ?? null) instanceof UploadedFile) {
                $productData['image'] = $this->images->storeCompressed($payload['image'], 'products');
            }

            $product = Product::query()->create($productData);
            $synced = $this->syncVariants($product, $variants, $branchId);
            $this->applyOpeningStock($synced, $variants, $branchId);

            return $this->loadForBranch($product->fresh(), $branchId);
        });
    }

    /**
     * @param  array{product: array<string, mixed>, variants: list<array<string, mixed>>}  $payload
     */
    public function update(Product $product, array $payload, ?int $branchId = null): Product
    {
        $branchId ??= BranchContext::ensure()->id;
        $productData = $payload['product'];
        // Type is immutable after create.
        $productData['type'] = $product->type ?: 'single';

        [$productData, $variants] = $this->preparePayload($productData, $payload['variants'], $product);
        $this->assertProductCodeAvailable($productData['short_code'], $product->id);
        $this->assertVariantUniqueness($variants);
        $this->assertBarcodesAvailable($variants);

        return DB::transaction(function () use ($product, $productData, $variants, $branchId, $payload) {
            $imagePath = $product->image;

            if (! empty($payload['remove_image'])) {
                $this->images->delete($product->image);
                $imagePath = null;
            }

            if (($payload['image'] ?? null) instanceof UploadedFile) {
                $this->images->delete($product->image);
                $imagePath = $this->images->storeCompressed($payload['image'], 'products');
            }

            $productData['image'] = $imagePath;
            $product->update($productData);
            $this->syncVariants($product, $variants, $branchId);

            return $this->loadForBranch($product->fresh(), $branchId);
        });
    }

    /**
     * Clone product + variants (and current-branch locations). Does not copy stock or serials.
     */
    public function duplicate(Product $product, ?int $branchId = null): Product
    {
        $branchId ??= BranchContext::ensure()->id;

        $product->load([
            'variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'variants.locations' => fn ($q) => $q->where('branch_id', $branchId),
        ]);

        return DB::transaction(function () use ($product, $branchId) {
            $reserved = [];
            $newCode = Product::nextAutoCode($reserved);
            $reserved[] = $newCode;

            $imagePath = null;
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                $ext = pathinfo($product->image, PATHINFO_EXTENSION) ?: 'jpg';
                $imagePath = 'products/'.Str::uuid()->toString().'.'.$ext;
                Storage::disk('public')->copy($product->image, $imagePath);
            }

            $copy = Product::query()->create([
                'name' => $this->duplicateName($product->name),
                'type' => $product->type ?: 'single',
                'short_code' => $newCode,
                'barcode' => null,
                'sku' => null,
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                'variation_id' => $product->variation_id,
                'tax_id' => $product->tax_id,
                'purchase_unit_id' => $product->purchase_unit_id,
                'sale_unit_id' => $product->sale_unit_id,
                'conversion_rate' => $product->conversion_rate,
                'sale_price' => $product->sale_price,
                'cost_per_unit' => $product->cost_per_unit,
                'min_qty_alert' => $product->min_qty_alert,
                'track_stock' => $product->track_stock,
                'is_active' => $product->is_active,
                'notes' => $product->notes,
                'image' => $imagePath,
            ]);

            $type = $copy->type ?: 'single';

            foreach ($product->variants as $index => $variant) {
                $variantCode = $type === 'single'
                    ? $newCode
                    : Product::nextAutoCode($reserved);
                $reserved[] = $variantCode;

                $newVariant = $copy->variants()->create([
                    'variation_option_id' => $variant->variation_option_id,
                    'name' => $variant->name,
                    'short_code' => $variantCode,
                    'barcode' => null,
                    'sku' => null,
                    'purchase_unit_id' => $variant->purchase_unit_id,
                    'sale_unit_id' => $variant->sale_unit_id,
                    'conversion_rate' => $variant->conversion_rate,
                    'sale_price' => $variant->sale_price,
                    'cost_per_unit' => $variant->cost_per_unit,
                    'is_active' => $variant->is_active,
                    'track_serial' => $variant->track_serial,
                    'sort_order' => $index,
                ]);

                $location = $variant->locations->first();
                if ($location) {
                    ProductLocation::query()->create([
                        'branch_id' => $branchId,
                        'product_id' => $copy->id,
                        'variant_id' => $newVariant->id,
                        'section_id' => $location->section_id,
                        'rack_id' => $location->rack_id,
                    ]);
                }
            }

            return $this->loadForBranch($copy->fresh(), $branchId);
        });
    }

    protected function duplicateName(string $name): string
    {
        $base = trim(preg_replace('/\s*\(Copy(?:\s+\d+)?\)\s*$/i', '', $name) ?? $name);
        $candidate = $base.' (Copy)';
        $n = 2;

        while (Product::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate)])->exists()) {
            $candidate = $base.' (Copy '.$n.')';
            $n++;
        }

        return $candidate;
    }

    public function delete(Product $product): void
    {
        $variantIds = $product->variants()->pluck('id');

        if ($product->stocks()->where('quantity', '!=', 0)->exists()) {
            throw ValidationException::withMessages([
                'product' => 'Cannot delete this product because it still has stock.',
            ]);
        }

        $usageChecks = [
            [SaleItem::class, 'sale'],
            [PurchaseItem::class, 'purchase'],
            [SaleReturnItem::class, 'sale return'],
            [PurchaseReturnItem::class, 'purchase return'],
            [StockMovement::class, 'stock movement'],
            [StockTransferItem::class, 'stock transfer'],
            [StockAdjustmentItem::class, 'stock adjustment'],
        ];

        foreach ($usageChecks as [$model, $label]) {
            $count = $model::query()
                ->where(function ($query) use ($product, $variantIds) {
                    $query->where('product_id', $product->id);
                    if ($variantIds->isNotEmpty()) {
                        $query->orWhereIn('variant_id', $variantIds);
                    }
                })
                ->count();

            if ($count > 0) {
                throw ValidationException::withMessages([
                    'product' => "Cannot delete this product because it is used on {$count} {$label} line(s).",
                ]);
            }
        }

        DB::transaction(function () use ($product) {
            $this->images->delete($product->image);
            $product->locations()->delete();
            $product->stocks()->delete();
            $product->variants()->delete();
            $product->delete();
        });
    }

    public function loadForBranch(Product $product, ?int $branchId = null): Product
    {
        $branchId ??= BranchContext::ensure()->id;

        $product->load([
            'brand',
            'category',
            'variation',
            'tax',
            'purchaseUnit',
            'saleUnit',
            'variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'variants.purchaseUnit',
            'variants.saleUnit',
            'variants.variationOption',
            'variants.locations' => fn ($q) => $q->where('branch_id', $branchId)->with(['section', 'rack']),
            'variants.stocks' => fn ($q) => $q->where('branch_id', $branchId),
        ])->loadCount('variants');

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_tax_id']),
            'taxes' => Tax::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'code']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'sections' => Section::query()
                ->where('is_active', true)
                ->with(['racks' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'variations' => Variation::query()
                ->where('is_active', true)
                ->with(['options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ];
    }

    /**
     * @param  array<string, mixed>  $productData
     * @param  list<array<string, mixed>>  $variants
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    protected function preparePayload(array $productData, array $variants, ?Product $existing = null): array
    {
        $type = ($productData['type'] ?? $existing?->type ?? 'single') === 'variant' ? 'variant' : 'single';
        $productData['type'] = $type;

        $reserved = [];
        $productCode = trim((string) ($productData['short_code'] ?? ''));
        if ($productCode === '' && $existing) {
            $productCode = (string) $existing->short_code;
        }
        $productCode = $productCode !== ''
            ? strtoupper($productCode)
            : Product::nextAutoCode($reserved);
        $reserved[] = $productCode;
        $productData['short_code'] = $productCode;

        if ($type === 'single') {
            $productData['variation_id'] = null;
            $first = $variants[0] ?? [];
            $variants = [[
                ...$first,
                'name' => null,
                'variation_option_id' => null,
                'short_code' => $productCode,
                'barcode' => ($productData['barcode'] ?? $first['barcode'] ?? null) ?: null,
                'purchase_unit_id' => $productData['purchase_unit_id'] ?? $first['purchase_unit_id'],
                'sale_unit_id' => $productData['sale_unit_id'] ?? $first['sale_unit_id'],
                'conversion_rate' => $productData['conversion_rate'] ?? $first['conversion_rate'] ?? 1,
                'sale_price' => $productData['sale_price'] ?? $first['sale_price'] ?? 0,
                'cost_per_unit' => $productData['cost_per_unit'] ?? $first['cost_per_unit'] ?? 0,
                'section_id' => $first['section_id'] ?? $productData['section_id'] ?? null,
                'rack_id' => $first['rack_id'] ?? $productData['rack_id'] ?? null,
                'is_active' => $productData['is_active'] ?? true,
                'track_serial' => $first['track_serial'] ?? false,
                'opening_stock' => $first['opening_stock'] ?? $productData['opening_stock'] ?? null,
                'opening_stock_date' => $first['opening_stock_date'] ?? $productData['opening_stock_date'] ?? null,
                'id' => $first['id'] ?? $existing?->variants()->orderBy('id')->value('id'),
            ]];
        } else {
            $productData['variation_id'] = ($productData['variation_id'] ?? null) ?: null;

            if (! $productData['variation_id']) {
                throw ValidationException::withMessages([
                    'variation_id' => 'Choose a variation type for this product.',
                ]);
            }

            if ($variants === []) {
                throw ValidationException::withMessages([
                    'variants' => 'Select at least one variation option.',
                ]);
            }

            foreach ($variants as $i => $row) {
                $optionId = isset($row['variation_option_id']) ? (int) $row['variation_option_id'] : 0;
                if ($optionId < 1) {
                    throw ValidationException::withMessages([
                        "variants.{$i}.variation_option_id" => 'Each variant must be linked to an option.',
                    ]);
                }

                $code = trim((string) ($row['short_code'] ?? ''));
                if ($code === '') {
                    $code = Product::nextAutoCode($reserved);
                } else {
                    $code = strtoupper($code);
                }
                $reserved[] = $code;
                $variants[$i]['short_code'] = $code;
                $variants[$i]['variation_option_id'] = $optionId;
                $variants[$i]['name'] = ($row['name'] ?? null) ?: null;
            }

            $first = $variants[0];
            $productData['barcode'] = ($first['barcode'] ?? null) ?: null;
            $productData['purchase_unit_id'] = $first['purchase_unit_id'];
            $productData['sale_unit_id'] = $first['sale_unit_id'];
            $productData['conversion_rate'] = $first['conversion_rate'];
            $productData['sale_price'] = $first['sale_price'];
            $productData['cost_per_unit'] = $first['cost_per_unit'] ?? 0;
        }

        $productData['barcode'] = ($productData['barcode'] ?? null) ?: null;
        $productData['min_qty_alert'] = isset($productData['min_qty_alert']) && $productData['min_qty_alert'] !== ''
            ? $productData['min_qty_alert']
            : null;

        return [$productData, array_values($variants)];
    }

    protected function assertProductCodeAvailable(string $code, ?int $ignoreProductId = null): void
    {
        $exists = Product::query()
            ->whereRaw('UPPER(short_code) = ?', [strtoupper($code)])
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'short_code' => 'This product code is already taken.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public function assertVariantUniqueness(array $variants): void
    {
        $shortCodes = collect($variants)
            ->pluck('short_code')
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter();

        if ($shortCodes->count() !== $shortCodes->unique()->count()) {
            throw ValidationException::withMessages([
                'variants' => 'Variant short codes must be unique within this product.',
            ]);
        }

        foreach (array_values($variants) as $index => $variant) {
            $short = strtoupper(trim((string) ($variant['short_code'] ?? '')));
            $ignoreId = isset($variant['id']) ? (int) $variant['id'] : null;

            $shortTaken = ProductVariant::query()
                ->whereRaw('UPPER(short_code) = ?', [$short])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($shortTaken) {
                throw ValidationException::withMessages([
                    "variants.{$index}.short_code" => 'This short code is already taken.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    protected function assertBarcodesAvailable(array $variants): void
    {
        $barcodes = collect($variants)
            ->map(fn ($v) => trim((string) ($v['barcode'] ?? '')))
            ->filter();

        if ($barcodes->count() !== $barcodes->unique()->count()) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode values must be unique.',
            ]);
        }

        foreach (array_values($variants) as $index => $variant) {
            $barcode = trim((string) ($variant['barcode'] ?? ''));
            if ($barcode === '') {
                continue;
            }

            $ignoreId = isset($variant['id']) ? (int) $variant['id'] : null;

            $taken = ProductVariant::query()
                ->where('barcode', $barcode)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
                || Product::query()
                    ->where('barcode', $barcode)
                    ->when($ignoreId, function ($q) use ($ignoreId) {
                        $productId = ProductVariant::query()->whereKey($ignoreId)->value('product_id');
                        if ($productId) {
                            $q->where('id', '!=', $productId);
                        }
                    })
                    ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    "variants.{$index}.barcode" => 'This barcode is already taken.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return list<ProductVariant>
     */
    protected function syncVariants(Product $product, array $variants, int $branchId): array
    {
        $keepIds = [];
        $synced = [];

        foreach (array_values($variants) as $index => $row) {
            $attrs = [
                'variation_option_id' => ($row['variation_option_id'] ?? null) ?: null,
                'name' => ($row['name'] ?? null) ?: null,
                'short_code' => strtoupper(trim((string) $row['short_code'])),
                'barcode' => ($row['barcode'] ?? null) ?: null,
                'purchase_unit_id' => $row['purchase_unit_id'],
                'sale_unit_id' => $row['sale_unit_id'],
                'conversion_rate' => $row['conversion_rate'],
                'sale_price' => $row['sale_price'],
                'cost_per_unit' => $row['cost_per_unit'] ?? 0,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                'track_serial' => array_key_exists('track_serial', $row) ? (bool) $row['track_serial'] : false,
                'sort_order' => $index,
            ];

            if (! empty($row['id'])) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey($row['id'])
                    ->firstOrFail();
                $variant->update($attrs);
            } else {
                $variant = $product->variants()->create($attrs);
            }

            $keepIds[] = $variant->id;
            $synced[] = $variant->fresh();

            ProductLocation::query()->updateOrCreate(
                ['branch_id' => $branchId, 'variant_id' => $variant->id],
                [
                    'product_id' => $product->id,
                    'section_id' => ($row['section_id'] ?? null) ?: null,
                    'rack_id' => ($row['rack_id'] ?? null) ?: null,
                ],
            );
        }

        $product->variants()->whereNotIn('id', $keepIds)->each(function (ProductVariant $variant) {
            if ($variant->stocks()->where('quantity', '!=', 0)->exists()) {
                $variant->update(['is_active' => false]);

                return;
            }

            $hasHistory = SaleItem::query()->where('variant_id', $variant->id)->exists()
                || PurchaseItem::query()->where('variant_id', $variant->id)->exists()
                || StockMovement::query()->where('variant_id', $variant->id)->exists();

            if ($hasHistory) {
                $variant->update(['is_active' => false]);

                return;
            }

            $variant->locations()->delete();
            $variant->stocks()->delete();
            $variant->delete();
        });

        return $synced;
    }

    /**
     * @param  list<ProductVariant>  $synced
     * @param  list<array<string, mixed>>  $rows
     */
    protected function applyOpeningStock(array $synced, array $rows, int $branchId): void
    {
        foreach ($synced as $index => $variant) {
            $row = $rows[$index] ?? [];
            $qty = (float) ($row['opening_stock'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unitCost = (float) ($row['cost_per_unit'] ?? $variant->cost_per_unit ?? 0);
            $date = trim((string) ($row['opening_stock_date'] ?? ''));
            $notes = 'Opening stock';
            if ($date !== '') {
                $notes .= ' ('.$date.')';
            }

            $stock = $this->inventory->receive(
                $branchId,
                $variant,
                $qty,
                $unitCost * $qty,
                $variant->product,
                $notes,
                'opening',
            );

            if ($date !== '') {
                $movement = StockMovement::query()
                    ->where('variant_id', $variant->id)
                    ->where('type', 'opening')
                    ->orderByDesc('id')
                    ->first();
                if ($movement) {
                    $at = Carbon::parse($date)->startOfDay();
                    $movement->created_at = $at;
                    $movement->updated_at = $at;
                    $movement->save();
                }
            }

            unset($stock);
        }
    }
}
