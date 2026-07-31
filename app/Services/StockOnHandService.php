<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\Category;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockOnHandService
{
    /**
     * @param  array{
     *   q?:string|null,
     *   category_id?:int|string|null,
     *   low?:bool|int|string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   stocks: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   categories: Collection,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $categoryId = $filters['category_id'] !== null && $filters['category_id'] !== ''
            ? (int) $filters['category_id']
            : null;
        $low = filter_var($filters['low'] ?? false, FILTER_VALIDATE_BOOLEAN);
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $stocks = BranchStock::query()
            ->with([
                'variant.product.category:id,name',
                'variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->whereHas('variant.product', fn (Builder $p) => $p->where('track_stock', true))
            ->when($categoryId, function (Builder $query) use ($categoryId) {
                $query->whereHas(
                    'variant.product',
                    fn (Builder $p) => $p->where('category_id', $categoryId),
                );
            })
            ->when($q !== '', function (Builder $query) use ($q) {
                $terms = $this->searchTerms($q);
                if ($terms === []) {
                    return;
                }

                $query->whereHas('variant', function (Builder $v) use ($terms) {
                    $v->where(function (Builder $inner) use ($terms) {
                        foreach ($terms as $term) {
                            $inner->orWhere(function (Builder $termQuery) use ($term) {
                                $termQuery->search($term);
                            });
                        }
                    });
                });
            })
            ->when($low, function (Builder $query) {
                $query->whereHas('variant.product', fn (Builder $p) => $p->where('min_qty_alert', '>', 0))
                    ->whereRaw(
                        'branch_stocks.quantity <= (
                            select products.min_qty_alert
                            from product_variants
                            inner join products on products.id = product_variants.product_id
                            where product_variants.id = branch_stocks.variant_id
                            limit 1
                        )',
                    );
            })
            ->tap(fn (Builder $query) => $this->applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BranchStock $stock) => $this->serialize($stock));

        return [
            'stocks' => $stocks,
            'filters' => [
                'q' => $q,
                'category_id' => $categoryId,
                'low' => $low,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return list<string>
     */
    protected function searchTerms(string $search): array
    {
        $parts = preg_split('/\s*,\s*/', $search) ?: [];

        return array_values(array_filter(array_map(
            static fn ($part) => trim((string) $part),
            $parts,
        ), static fn ($part) => $part !== ''));
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'quantity', 'average_cost', 'product'];
        $sort = strtolower(trim((string) ($sort ?? 'product')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'product';
        }

        $direction = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return [$sort, $direction];
    }

    protected function applySort(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'product') {
            $query->orderBy(
                \App\Models\Product::query()
                    ->select('name')
                    ->whereColumn('products.id', 'branch_stocks.product_id')
                    ->limit(1),
                $direction,
            )->orderBy('id');

            return;
        }

        $query->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $q) => $q->orderBy('id'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(BranchStock $stock): array
    {
        $variant = $stock->variant;
        $product = $variant?->product ?? $stock->product;
        $qty = round((float) $stock->quantity, 4);
        $minAlert = round((float) ($product?->min_qty_alert ?? 0), 4);
        $isLow = $minAlert > 0 && $qty <= $minAlert;
        $unit = $variant?->saleUnit;

        return [
            'id' => $stock->id,
            'quantity' => round($qty, 2),
            'average_cost' => round((float) $stock->average_cost, 2),
            'min_qty_alert' => $minAlert,
            'is_low' => $isLow,
            'status' => $isLow ? 'low' : 'in_stock',
            'variant' => $variant
                ? [
                    'id' => $variant->id,
                    'name' => $variant->displayName(),
                    'short_code' => $variant->short_code,
                    'barcode' => $variant->barcode,
                    'sku' => $variant->sku,
                    'sale_price' => round((float) ($variant->sale_price ?? 0), 2),
                ]
                : null,
            'category' => $product?->category
                ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ]
                : null,
            'unit' => $unit
                ? [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ]
                : null,
        ];
    }
}
