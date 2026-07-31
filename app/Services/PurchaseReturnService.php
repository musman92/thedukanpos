<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   supplier_id?:int|string|null,
     *   purchase_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   returns: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   suppliers: Collection,
     *   branch: array{id:int, name:string}
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $supplierId = $filters['supplier_id'] !== null && $filters['supplier_id'] !== ''
            ? (int) $filters['supplier_id']
            : null;
        $purchaseId = $filters['purchase_id'] !== null && $filters['purchase_id'] !== ''
            ? (int) $filters['purchase_id']
            : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $returns = PurchaseReturn::query()
            ->with(['supplier:id,name', 'purchase:id,number', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('purchase', fn ($p) => $p->where('number', 'like', "%{$q}%"));
                });
            })
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($purchaseId, fn ($query) => $query->where('purchase_id', $purchaseId))
            ->when($from, fn ($query) => $query->whereDate('return_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('return_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PurchaseReturn $doc) => $this->serializeList($doc));

        return [
            'returns' => $returns,
            'filters' => [
                'q' => $q,
                'supplier_id' => $supplierId,
                'purchase_id' => $purchaseId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name])
                ->values(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   suppliers: Collection,
     *   purchases: Collection,
     *   selected_purchase: array<string, mixed>|null,
     *   form_supplier_id: int|null,
     *   branch: array{id:int, name:string}
     * }
     */
    public function formOptions(
        ?int $purchaseId = null,
        ?int $supplierId = null,
        ?PurchaseReturn $editingReturn = null,
    ): array {
        $branch = BranchContext::ensure();

        $extraReturned = [];
        if ($editingReturn) {
            $editingReturn->loadMissing('items');
            foreach ($editingReturn->items as $returnItem) {
                $pid = (int) $returnItem->purchase_item_id;
                $extraReturned[$pid] = ($extraReturned[$pid] ?? 0) + (float) $returnItem->quantity;
            }
            $purchaseId = $purchaseId ?: (int) $editingReturn->purchase_id;
            $supplierId = $supplierId ?: ($editingReturn->supplier_id ? (int) $editingReturn->supplier_id : null);
        }

        $selected = null;
        if ($purchaseId) {
            $purchase = Purchase::query()
                ->with([
                    'supplier:id,name',
                    'items.product:id,name',
                    'items.variant:id,name,short_code',
                    'items.unit:id,name,code',
                ])
                ->where('branch_id', $branch->id)
                ->find($purchaseId);

            if ($purchase) {
                $supplierId = $supplierId ?: ($purchase->supplier_id ? (int) $purchase->supplier_id : null);
                $selected = [
                    'id' => $purchase->id,
                    'number' => $purchase->number,
                    'purchase_date' => $purchase->purchase_date?->format('Y-m-d'),
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier?->name,
                    'balance_due' => round($purchase->balanceDue(), 2),
                    'items' => $purchase->items
                        ->map(function (PurchaseItem $item) use ($extraReturned) {
                            $extra = $extraReturned[$item->id] ?? 0.0;
                            $returnable = round($item->returnableQuantity() + $extra, 4);

                            return [
                                'id' => $item->id,
                                'product_name' => $item->product?->name,
                                'variant_name' => $item->variant?->name,
                                'unit_code' => $item->unit?->code,
                                'unit_id' => $item->unit_id,
                                'variant_id' => $item->variant_id,
                                'quantity' => round((float) $item->quantity, 4),
                                'quantity_returned' => round((float) $item->quantity_returned, 4),
                                'returnable_quantity' => $returnable,
                                'unit_price' => round((float) $item->unit_price, 4),
                            ];
                        })
                        ->filter(fn (array $row) => $row['returnable_quantity'] > 0.0001)
                        ->values(),
                ];
            }
        }

        $purchases = collect();
        if ($supplierId) {
            $purchases = Purchase::query()
                ->with(['supplier:id,name', 'items'])
                ->where('branch_id', $branch->id)
                ->where('supplier_id', $supplierId)
                ->latest('id')
                ->limit(100)
                ->get()
                ->filter(function (Purchase $p) use ($purchaseId, $extraReturned) {
                    if ($purchaseId && (int) $p->id === (int) $purchaseId) {
                        return true;
                    }

                    return $p->items->contains(function (PurchaseItem $item) use ($extraReturned) {
                        $extra = $extraReturned[$item->id] ?? 0.0;

                        return ($item->returnableQuantity() + $extra) > 0.0001;
                    });
                })
                ->map(fn (Purchase $p) => [
                    'id' => $p->id,
                    'number' => $p->number,
                    'purchase_date' => $p->purchase_date?->format('Y-m-d'),
                    'supplier_id' => $p->supplier_id,
                    'supplier_name' => $p->supplier?->name,
                    'total' => round((float) $p->total, 2),
                ])
                ->values();
        }

        return [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name])
                ->values(),
            'purchases' => $purchases,
            'selected_purchase' => $selected,
            'form_supplier_id' => $supplierId,
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForForm(PurchaseReturn $doc): array
    {
        $doc->loadMissing([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'purchase:id,number,supplier_id',
            'supplier:id,name',
        ]);

        $qtyByPurchaseItem = [];
        foreach ($doc->items as $item) {
            $pid = (int) $item->purchase_item_id;
            $qtyByPurchaseItem[$pid] = ($qtyByPurchaseItem[$pid] ?? 0) + (float) $item->quantity;
        }

        return [
            'id' => $doc->id,
            'number' => $doc->number,
            'supplier_id' => $doc->supplier_id,
            'purchase_id' => $doc->purchase_id,
            'return_date' => $doc->return_date?->format('Y-m-d'),
            'notes' => $doc->notes ?? '',
            'items' => collect($qtyByPurchaseItem)->map(fn ($qty, $purchaseItemId) => [
                'purchase_item_id' => (int) $purchaseItemId,
                'quantity' => (string) round((float) $qty, 4),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(PurchaseReturn $doc): array
    {
        $doc->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'supplier:id,name',
            'purchase:id,number',
            'branch:id,name',
        ]);

        return [
            'return' => $this->serializeDetail($doc),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ];
    }

    /**
     * @param  array{
     *   purchase_id:int,
     *   return_date:string,
     *   notes?:string|null,
     *   items: list<array{purchase_item_id:int, quantity:float|int|string}>
     * }  $data
     */
    public function create(array $data): PurchaseReturn
    {
        $branch = BranchContext::ensure();

        return DB::transaction(function () use ($data, $branch) {
            $purchase = Purchase::query()
                ->with('items.variant')
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->findOrFail($data['purchase_id']);

            $lines = collect($data['items'] ?? [])
                ->filter(fn ($row) => (float) ($row['quantity'] ?? 0) > 0)
                ->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a return quantity for at least one line.',
                ]);
            }

            $doc = PurchaseReturn::query()->create([
                'number' => $this->nextNumber(),
                'branch_id' => $branch->id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'total' => 0,
                'settlement_type' => 'reduce_payable',
                'payable_reduction_amount' => 0,
                'credit_amount' => 0,
            ]);

            $total = 0.0;

            foreach ($lines as $row) {
                /** @var PurchaseItem|null $purchaseItem */
                $purchaseItem = $purchase->items->firstWhere('id', (int) $row['purchase_item_id']);
                if (! $purchaseItem) {
                    throw ValidationException::withMessages([
                        'items' => 'A return line does not belong to this purchase.',
                    ]);
                }

                $qty = (float) $row['quantity'];
                $returnable = $purchaseItem->returnableQuantity();
                if ($qty > $returnable + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "Return qty exceeds returnable amount for a line (max {$returnable}).",
                    ]);
                }

                $variant = $purchaseItem->variant
                    ?? ProductVariant::query()->findOrFail($purchaseItem->variant_id);

                $unitId = (int) $purchaseItem->unit_id;
                $qtySale = $variant->toSaleQuantity($qty, $unitId);
                $unitCost = (float) $purchaseItem->unit_price;
                $lineTotal = round($qty * $unitCost, 4);

                $item = PurchaseReturnItem::query()->create([
                    'purchase_return_id' => $doc->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'product_id' => $purchaseItem->product_id,
                    'variant_id' => $purchaseItem->variant_id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'conversion_rate' => $purchaseItem->conversion_rate,
                    'quantity_in_sale_unit' => $qtySale,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $this->inventory->deduct(
                    $branch->id,
                    $variant,
                    $qtySale,
                    $item,
                    "Purchase return {$doc->number}",
                    'purchase_return',
                );

                $purchaseItem->update([
                    'quantity_returned' => round((float) $purchaseItem->quantity_returned + $qty, 4),
                ]);

                $total += $lineTotal;
            }

            $total = round($total, 4);
            $unpaidBefore = $purchase->balanceDue();
            $payableReduction = min($total, $unpaidBefore);
            $creditAmount = max(0, $total - $payableReduction);
            $settlement = match (true) {
                $creditAmount > 0.0001 && $payableReduction > 0.0001 => 'mixed',
                $creditAmount > 0.0001 => 'supplier_credit',
                default => 'reduce_payable',
            };

            $doc->update([
                'total' => $total,
                'settlement_type' => $settlement,
                'payable_reduction_amount' => round($payableReduction, 4),
                'credit_amount' => round($creditAmount, 4),
            ]);

            $purchase->update([
                'returned_amount' => round((float) $purchase->returned_amount + $total, 4),
            ]);
            $purchase->refreshPaymentStatus();

            if ($purchase->supplier_id && $total > 0) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($purchase->supplier_id);
                $supplier->balance = round((float) $supplier->balance - $total, 4);
                $supplier->save();
            }

            app(ActivityLogger::class)->log(
                'return.purchase',
                "Purchase return {$doc->number}",
                $doc,
            );

            return $doc->fresh(['items', 'supplier', 'purchase']);
        });
    }

    /**
     * @param  array{
     *   purchase_id?:int,
     *   return_date:string,
     *   notes?:string|null,
     *   items: list<array{purchase_item_id:int, quantity:float|int|string}>
     * }  $data
     */
    public function update(PurchaseReturn $doc, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($doc, $data) {
            $doc = PurchaseReturn::query()->lockForUpdate()->findOrFail($doc->id);
            $this->reverseEffects($doc);
            $doc->items()->delete();

            $branch = BranchContext::ensure();
            $purchase = Purchase::query()
                ->with('items.variant')
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->findOrFail($doc->purchase_id);

            $lines = collect($data['items'] ?? [])
                ->filter(fn ($row) => (float) ($row['quantity'] ?? 0) > 0)
                ->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a return quantity for at least one line.',
                ]);
            }

            $doc->update([
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'total' => 0,
                'settlement_type' => 'reduce_payable',
                'payable_reduction_amount' => 0,
                'credit_amount' => 0,
            ]);

            $total = $this->applyLines($doc, $purchase, $lines, $branch->id);

            $purchase->refresh();
            $unpaidBefore = $purchase->balanceDue();
            $payableReduction = min($total, $unpaidBefore);
            $creditAmount = max(0, $total - $payableReduction);
            $settlement = match (true) {
                $creditAmount > 0.0001 && $payableReduction > 0.0001 => 'mixed',
                $creditAmount > 0.0001 => 'supplier_credit',
                default => 'reduce_payable',
            };

            $doc->update([
                'total' => $total,
                'settlement_type' => $settlement,
                'payable_reduction_amount' => round($payableReduction, 4),
                'credit_amount' => round($creditAmount, 4),
            ]);

            $purchase->update([
                'returned_amount' => round((float) $purchase->returned_amount + $total, 4),
            ]);
            $purchase->refreshPaymentStatus();

            if ($purchase->supplier_id && $total > 0) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($purchase->supplier_id);
                $supplier->balance = round((float) $supplier->balance - $total, 4);
                $supplier->save();
            }

            app(ActivityLogger::class)->log(
                'return.purchase.update',
                "Purchase return {$doc->number} updated",
                $doc,
            );

            return $doc->fresh(['items', 'supplier', 'purchase']);
        });
    }

    public function delete(PurchaseReturn $doc): void
    {
        DB::transaction(function () use ($doc) {
            $doc = PurchaseReturn::query()->lockForUpdate()->findOrFail($doc->id);
            $number = $doc->number;
            $this->reverseEffects($doc);
            $doc->items()->delete();
            $doc->delete();

            app(ActivityLogger::class)->log(
                'return.purchase.delete',
                "Purchase return {$number} deleted",
                null,
            );
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{purchase_item_id:int, quantity:float|int|string}>  $lines
     */
    protected function applyLines(
        PurchaseReturn $doc,
        Purchase $purchase,
        $lines,
        int $branchId,
    ): float {
        $total = 0.0;

        foreach ($lines as $row) {
            /** @var PurchaseItem|null $purchaseItem */
            $purchaseItem = $purchase->items->firstWhere('id', (int) $row['purchase_item_id']);
            if (! $purchaseItem) {
                throw ValidationException::withMessages([
                    'items' => 'A return line does not belong to this purchase.',
                ]);
            }

            $qty = (float) $row['quantity'];
            $returnable = $purchaseItem->returnableQuantity();
            if ($qty > $returnable + 0.0001) {
                throw ValidationException::withMessages([
                    'items' => "Return qty exceeds returnable amount for a line (max {$returnable}).",
                ]);
            }

            $variant = $purchaseItem->variant
                ?? ProductVariant::query()->findOrFail($purchaseItem->variant_id);

            $unitId = (int) $purchaseItem->unit_id;
            $qtySale = $variant->toSaleQuantity($qty, $unitId);
            $unitCost = (float) $purchaseItem->unit_price;
            $lineTotal = round($qty * $unitCost, 4);

            $item = PurchaseReturnItem::query()->create([
                'purchase_return_id' => $doc->id,
                'purchase_item_id' => $purchaseItem->id,
                'product_id' => $purchaseItem->product_id,
                'variant_id' => $purchaseItem->variant_id,
                'unit_id' => $unitId,
                'quantity' => $qty,
                'conversion_rate' => $purchaseItem->conversion_rate,
                'quantity_in_sale_unit' => $qtySale,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ]);

            $this->inventory->deduct(
                $branchId,
                $variant,
                $qtySale,
                $item,
                "Purchase return {$doc->number}",
                'purchase_return',
            );

            $purchaseItem->update([
                'quantity_returned' => round((float) $purchaseItem->quantity_returned + $qty, 4),
            ]);

            $total += $lineTotal;
        }

        return round($total, 4);
    }

    protected function reverseEffects(PurchaseReturn $doc): void
    {
        $doc->loadMissing(['items.variant', 'purchase']);

        $purchase = Purchase::query()
            ->with('items')
            ->lockForUpdate()
            ->findOrFail($doc->purchase_id);

        $total = (float) $doc->total;

        foreach ($doc->items as $item) {
            $variant = $item->variant
                ?? ProductVariant::query()->findOrFail($item->variant_id);
            $qtySale = (float) $item->quantity_in_sale_unit;

            if ($qtySale > 0.0001) {
                $this->inventory->receive(
                    branchId: (int) $doc->branch_id,
                    variant: $variant,
                    qtySaleUnits: $qtySale,
                    lineCostTotal: (float) $item->line_total,
                    reference: $item,
                    notes: "Reverse purchase return {$doc->number}",
                    type: 'purchase_return_void',
                );
            }

            $purchaseItem = $purchase->items->firstWhere('id', $item->purchase_item_id);
            if ($purchaseItem) {
                $purchaseItem->update([
                    'quantity_returned' => max(
                        0,
                        round((float) $purchaseItem->quantity_returned - (float) $item->quantity, 4),
                    ),
                ]);
            }
        }

        $purchase->update([
            'returned_amount' => max(0, round((float) $purchase->returned_amount - $total, 4)),
        ]);
        $purchase->refreshPaymentStatus();

        if ($doc->supplier_id && $total > 0.0001) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($doc->supplier_id);
            $supplier->balance = round((float) $supplier->balance + $total, 4);
            $supplier->save();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'return_date', 'number', 'total'];
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

    protected function nextNumber(): string
    {
        $prefix = 'PR-'.now()->format('Ymd').'-';
        $last = PurchaseReturn::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeList(PurchaseReturn $doc): array
    {
        return [
            'id' => $doc->id,
            'number' => $doc->number,
            'return_date' => $doc->return_date?->format('Y-m-d'),
            'total' => round((float) $doc->total, 2),
            'settlement_type' => $doc->settlement_type,
            'supplier' => $doc->supplier
                ? ['id' => $doc->supplier->id, 'name' => $doc->supplier->name]
                : null,
            'purchase' => $doc->purchase
                ? ['id' => $doc->purchase->id, 'number' => $doc->purchase->number]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetail(PurchaseReturn $doc): array
    {
        return [
            ...$this->serializeList($doc),
            'notes' => $doc->notes,
            'payable_reduction_amount' => round((float) $doc->payable_reduction_amount, 2),
            'credit_amount' => round((float) $doc->credit_amount, 2),
            'branch' => $doc->branch
                ? ['id' => $doc->branch->id, 'name' => $doc->branch->name]
                : null,
            'items' => $doc->items->map(fn (PurchaseReturnItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'unit_code' => $item->unit?->code,
                'quantity' => round((float) $item->quantity, 4),
                'unit_cost' => round((float) $item->unit_cost, 4),
                'line_total' => round((float) $item->line_total, 2),
            ])->values(),
        ];
    }
}
