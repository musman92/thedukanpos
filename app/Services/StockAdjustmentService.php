<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        protected StockDocumentService $documents,
        protected InventoryService $inventory,
    ) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   adjustments: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   variants: Collection,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $adjustments = StockAdjustment::query()
            ->with([
                'branch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhere('reason', 'like', "%{$q}%")
                        ->orWhereHas('items.variant', fn (Builder $v) => $v->search($q));
                });
            })
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockAdjustment $adj) => $this->serialize($adj));

        return [
            'adjustments' => $adjustments,
            'filters' => [
                'q' => $q,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'variants' => $this->variantOptions(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function variantOptions(): Collection
    {
        return ProductVariant::query()
            ->with([
                'product:id,name,track_stock,conversion_rate,purchase_unit_id,sale_unit_id',
                'product.purchaseUnit:id,name',
                'saleUnit:id,name',
                'purchaseUnit:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $p) => $p->where('track_stock', true))
            ->orderBy('short_code')
            ->get()
            ->map(function (ProductVariant $v) {
                $rate = (float) ($v->conversion_rate ?: $v->product?->conversion_rate ?: 1);
                $saleUnit = $v->saleUnit?->name ?? 'pcs';
                $purchaseUnit = $v->purchaseUnit?->name ?? $v->product?->purchaseUnit?->name;
                $hasDual = $rate > 0
                    && abs($rate - 1) > 0.0001
                    && $purchaseUnit
                    && $saleUnit
                    && (int) ($v->purchase_unit_id ?: 0) !== (int) ($v->sale_unit_id ?: 0);

                return [
                    'value' => $v->id,
                    'label' => $v->displayName(),
                    'meta' => $v->short_code,
                    'id' => $v->id,
                    'short_code' => $v->short_code,
                    'sale_unit_name' => $saleUnit,
                    'purchase_unit_name' => $purchaseUnit ?: $saleUnit,
                    'conversion_rate' => $rate > 0 ? $rate : 1,
                    'has_dual_units' => $hasDual,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function stockContext(int $variantId, ?int $excludeAdjustmentId = null): array
    {
        $branch = BranchContext::ensure();
        $variant = ProductVariant::query()
            ->with(['product.purchaseUnit', 'saleUnit', 'purchaseUnit'])
            ->findOrFail($variantId);

        if (! $variant->product?->track_stock) {
            throw ValidationException::withMessages([
                'variant_id' => 'This product does not track stock.',
            ]);
        }

        $stock = $this->inventory->getOrCreateStock($branch->id, $variant);
        $qtySale = (float) $stock->quantity;

        if ($excludeAdjustmentId) {
            $adjustment = StockAdjustment::query()
                ->with('items')
                ->where('branch_id', $branch->id)
                ->find($excludeAdjustmentId);
            if ($adjustment) {
                foreach ($adjustment->items as $item) {
                    if ((int) $item->variant_id === $variantId) {
                        // Undo this adjustment's effect to show stock "before".
                        $qtySale -= (float) $item->quantity;
                    }
                }
            }
        }

        $rate = (float) ($variant->conversion_rate ?: $variant->product?->conversion_rate ?: 1);
        if ($rate <= 0) {
            $rate = 1;
        }
        $saleUnit = $variant->saleUnit?->name ?? 'pcs';
        $purchaseUnit = $variant->purchaseUnit?->name
            ?? $variant->product?->purchaseUnit?->name
            ?? $saleUnit;
        $hasDual = $rate > 0
            && abs($rate - 1) > 0.0001
            && $purchaseUnit
            && $saleUnit
            && (int) ($variant->purchase_unit_id ?: 0) !== (int) ($variant->sale_unit_id ?: 0);

        return [
            'variant_id' => $variant->id,
            'quantity_sale' => round($qtySale, 4),
            'quantity_purchase' => round($qtySale / $rate, 4),
            'sale_unit_name' => $saleUnit,
            'purchase_unit_name' => $purchaseUnit,
            'conversion_rate' => $rate,
            'has_dual_units' => $hasDual,
        ];
    }

    /**
     * @param  array{
     *   variant_id:int,
     *   mode:string,
     *   unit:string,
     *   quantity:float,
     *   notes:string
     * }  $data
     */
    public function create(array $data): StockAdjustment
    {
        $branch = BranchContext::ensure();
        $signed = $this->resolveSignedQty($branch->id, $data);

        try {
            return $this->documents->adjust([
                'branch_id' => $branch->id,
                'reason' => null,
                'notes' => $data['notes'],
                'items' => [[
                    'variant_id' => (int) $data['variant_id'],
                    'quantity' => $signed,
                ]],
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{
     *   variant_id:int,
     *   mode:string,
     *   unit:string,
     *   quantity:float,
     *   notes:string
     * }  $data
     */
    public function update(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        $branch = BranchContext::ensure();
        if ((int) $adjustment->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'adjustment' => 'This adjustment belongs to another branch.',
            ]);
        }

        $adjustment->loadMissing('items');
        $originalVariantId = (int) ($adjustment->items->first()?->variant_id ?? 0);
        if ($originalVariantId && (int) $data['variant_id'] !== $originalVariantId) {
            throw ValidationException::withMessages([
                'variant_id' => 'Item cannot be changed when editing. Delete and create a new adjustment instead.',
            ]);
        }

        // Edit always applies as a change amount in sale units (FoodPOS edit locks mode/unit).
        $data['mode'] = 'change';
        $data['unit'] = 'sale';
        $data['variant_id'] = $originalVariantId ?: (int) $data['variant_id'];

        try {
            return DB::transaction(function () use ($adjustment, $data) {
                $this->documents->reverseAdjustment($adjustment);

                return $this->create($data);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }
    }

    public function delete(StockAdjustment $adjustment): void
    {
        try {
            $this->documents->reverseAdjustment($adjustment);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'adjustment' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{variant_id:int, mode:string, unit:string, quantity:float}  $data
     */
    protected function resolveSignedQty(int $branchId, array $data): float
    {
        $variant = ProductVariant::query()->with('product')->find((int) $data['variant_id']);
        if (! $variant || ! $variant->product?->track_stock) {
            throw ValidationException::withMessages([
                'variant_id' => 'Select a stock-tracked product.',
            ]);
        }

        $raw = round((float) $data['quantity'], 4);
        $unit = ($data['unit'] ?? 'sale') === 'purchase' ? 'purchase' : 'sale';
        $mode = ($data['mode'] ?? 'change') === 'exact' ? 'exact' : 'change';
        $rate = (float) ($variant->conversion_rate ?: $variant->product?->conversion_rate ?: 1);
        if ($rate <= 0) {
            $rate = 1;
        }

        $asSale = $unit === 'purchase' ? round($raw * $rate, 4) : $raw;

        if ($mode === 'exact') {
            if ($raw < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Exact quantity cannot be negative.',
                ]);
            }
            $onHand = (float) $this->inventory->getOrCreateStock($branchId, $variant)->quantity;
            $signed = round($asSale - $onHand, 4);
            if (abs($signed) < 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => 'Exact quantity matches current stock — nothing to adjust.',
                ]);
            }

            return $signed;
        }

        if (abs($asSale) < 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity change must be non-zero.',
            ]);
        }

        return $asSale;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'created_at', 'number'];
        $sort = strtolower(trim((string) ($sort ?? 'created_at')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(StockAdjustment $adjustment): array
    {
        $item = $adjustment->relationLoaded('items')
            ? $adjustment->items->first()
            : null;
        $qty = $item ? (float) $item->quantity : 0.0;
        $variant = $item?->variant;

        return [
            'id' => $adjustment->id,
            'number' => $adjustment->number,
            'notes' => $adjustment->notes,
            'created_at' => $adjustment->created_at?->format('Y-m-d H:i'),
            'branch' => $adjustment->branch
                ? ['id' => $adjustment->branch->id, 'name' => $adjustment->branch->name]
                : null,
            'creator' => $adjustment->creator
                ? [
                    'id' => $adjustment->creator->id,
                    'name' => $adjustment->creator->name ?: $adjustment->creator->username,
                ]
                : null,
            'variant_id' => $item?->variant_id,
            'quantity' => round(abs($qty), 2),
            'signed_quantity' => round($qty, 4),
            'direction' => $qty >= 0 ? 'in' : 'out',
            'unit_name' => $variant?->saleUnit?->name ?? 'pcs',
            'variant' => $variant
                ? [
                    'id' => $variant->id,
                    'name' => $variant->displayName(),
                    'short_code' => $variant->short_code,
                ]
                : null,
            'lines_count' => $adjustment->relationLoaded('items')
                ? $adjustment->items->count()
                : 0,
        ];
    }

    public function findForEdit(int $id): ?array
    {
        $branch = BranchContext::ensure();
        $adjustment = StockAdjustment::query()
            ->with([
                'branch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->find($id);

        return $adjustment ? $this->serialize($adjustment) : null;
    }
}
