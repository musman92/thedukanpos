<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\BranchContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductLedgerService
{
    public function build(array $input): array
    {
        $activeBranch = BranchContext::ensure();
        $branchId = isset($input['branch_id']) && $input['branch_id'] !== ''
            ? (int) $input['branch_id']
            : (int) $activeBranch->id;

        $branch = Branch::query()->where('is_active', true)->find($branchId)
            ?? $activeBranch;

        $from = (string) ($input['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($input['to'] ?? now()->toDateString());
        $productId = isset($input['product_id']) && $input['product_id'] !== ''
            ? (int) $input['product_id']
            : null;
        $perPage = resolve_page_limit($input['per_page'] ?? null, company_page_limit());

        $product = $productId ? Product::query()->find($productId) : null;
        $rows = null;
        $summary = null;

        if ($product) {
            $query = StockMovement::query()
                ->with(['variant.product', 'variant.saleUnit:id,name', 'product:id,name'])
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->orderBy('created_at')
                ->orderBy('id');

            $totals = (clone $query)
                ->selectRaw('
                    COUNT(*) as movement_count,
                    COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END), 0) as total_in,
                    COALESCE(SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END), 0) as total_out
                ')
                ->first();

            $summary = [
                'movement_count' => (int) ($totals->movement_count ?? 0),
                'total_in' => round((float) ($totals->total_in ?? 0), 4),
                'total_out' => round((float) ($totals->total_out ?? 0), 4),
            ];

            $rows = $query
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (StockMovement $m) => $this->serialize($m));
        }

        return [
            'filters' => [
                'branch_id' => (int) $branch->id,
                'from' => $from,
                'to' => $to,
                'product_id' => $product?->id,
                'per_page' => $perPage,
                'company_page_limit' => company_page_limit(),
            ],
            'summary' => $summary,
            'rows' => $rows,
            'product' => $product ? ['id' => $product->id, 'name' => $product->name] : null,
            'products' => $this->productOptions(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
                ->values()
                ->all(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    protected function productOptions(): Collection
    {
        return Product::query()
            ->where('track_stock', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'short_code', 'sku', 'barcode'])
            ->map(fn (Product $p) => [
                'value' => $p->id,
                'label' => $p->name,
                'meta' => $p->short_code ?: $p->sku ?: $p->barcode,
            ]);
    }

    protected function serialize(StockMovement $m): array
    {
        $qty = (float) $m->quantity;

        return [
            'id' => $m->id,
            'created_at' => format_company_datetime($m->created_at),
            'variant' => $m->variant?->displayName() ?: ($m->product?->name ?? '—'),
            'type' => $m->type,
            'type_label' => Str::of((string) $m->type)->replace('_', ' ')->title()->toString(),
            'qty_in' => $qty > 0 ? round($qty, 4) : 0.0,
            'qty_out' => $qty < 0 ? round(abs($qty), 4) : 0.0,
            'balance_after' => $m->balance_after !== null ? round((float) $m->balance_after, 4) : null,
            'notes' => $m->notes,
        ];
    }
}
