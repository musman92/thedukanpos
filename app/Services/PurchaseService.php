<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\BranchContext;
use App\Support\MoneyBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FinanceService $finance,
    ) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   supplier_id?:int|string|null,
     *   payment_status?:string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   purchases: LengthAwarePaginator,
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
        $paymentStatus = strtolower(trim((string) ($filters['payment_status'] ?? '')));
        if (! in_array($paymentStatus, Purchase::PAYMENT_STATUSES, true)) {
            $paymentStatus = '';
        }
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'branch:id,name', 'moneySource:id,name', 'returns:id,purchase_id', 'items:id,purchase_id,quantity_returned'])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when($from, fn ($query) => $query->whereDate('purchase_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('purchase_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Purchase $purchase) => $this->serializeList($purchase));

        return [
            'purchases' => $purchases,
            'filters' => [
                'q' => $q,
                'supplier_id' => $supplierId,
                'payment_status' => $paymentStatus,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'suppliers' => $this->supplierOptions(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   suppliers: Collection,
     *   variants: Collection,
     *   money_sources: Collection,
     *   branch: array{id:int, name:string}
     * }
     */
    public function formOptions(): array
    {
        $branch = BranchContext::ensure();
        $this->finance->seedDefaultAccounts();

        return [
            'suppliers' => $this->supplierOptions(),
            'variants' => ProductVariant::query()
                ->with(['product:id,name', 'purchaseUnit:id,name,code', 'saleUnit:id,name,code'])
                ->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->orderBy('short_code')
                ->get()
                ->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'label' => $v->displayName(),
                    'short_code' => $v->short_code,
                    'purchase_unit_id' => $v->purchase_unit_id,
                    'sale_unit_id' => $v->sale_unit_id,
                    'conversion_rate' => (float) $v->conversion_rate,
                    'cost_per_unit' => round((float) $v->cost_per_unit, 4),
                    'purchase_unit' => $v->purchaseUnit
                        ? ['id' => $v->purchaseUnit->id, 'name' => $v->purchaseUnit->name, 'code' => $v->purchaseUnit->code]
                        : null,
                    'sale_unit' => $v->saleUnit
                        ? ['id' => $v->saleUnit->id, 'name' => $v->saleUnit->name, 'code' => $v->saleUnit->code]
                        : null,
                ])
                ->values(),
            'money_sources' => MoneySource::query()
                ->forPayments()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance'])
                ->map(fn (MoneySource $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'type' => $m->type,
                    'balance' => round((float) $m->balance, 2),
                ])
                ->values(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Purchase $purchase): array
    {
        $purchase->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'items.bonusUnit:id,name,code',
            'supplier:id,name,balance',
            'branch:id,name',
            'moneySource:id,name',
            'returns:id,number,return_date,total,settlement_type',
        ]);

        return [
            'purchase' => $this->serializeDetail($purchase),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ];
    }

    /**
     * @param  array{
     *   supplier_id?:int|null,
     *   number?:string|null,
     *   purchase_date:string,
     *   tax_total?:float|int|string,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   money_source_id?:int|null,
     *   paid_amount?:float|int|string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id:int,
     *     quantity:float|int|string,
     *     bonus_quantity?:float|int|string,
     *     bonus_unit_id?:int|null,
     *     unit_price:float|int|string
     *   }>
     * }  $data
     */
    public function create(array $data): Purchase
    {
        $branch = BranchContext::ensure();

        return DB::transaction(function () use ($data, $branch) {
            $items = $data['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one purchase line.',
                ]);
            }

            $subtotal = 0.0;
            foreach ($items as $row) {
                $subtotal += (float) $row['quantity'] * (float) $row['unit_price'];
            }

            $taxTotal = round((float) ($data['tax_total'] ?? 0), 4);
            $discountTotal = round((float) ($data['discount_total'] ?? 0), 4);
            $total = round(max(0, $subtotal + $taxTotal - $discountTotal), 4);

            $paidAmount = round(max(0, (float) ($data['paid_amount'] ?? 0)), 4);
            if ($paidAmount > $total + 0.0001) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Paid amount cannot exceed the purchase total.',
                ]);
            }

            $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' && $data['supplier_id'] !== null
                ? (int) $data['supplier_id']
                : null;

            if ($paidAmount + 0.0001 < $total && ! $supplierId) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Select a supplier when any amount is unpaid (credit / partial).',
                ]);
            }

            if ($paidAmount > 0.0001 && empty($data['money_source_id'])) {
                throw ValidationException::withMessages([
                    'money_source_id' => 'Select a money source for the payment.',
                ]);
            }

            $number = trim((string) ($data['number'] ?? ''));
            if ($number === '') {
                $number = $this->nextNumber();
            } elseif (Purchase::query()->where('number', $number)->exists()) {
                throw ValidationException::withMessages([
                    'number' => 'This ref no is already in use.',
                ]);
            }

            $paymentStatus = $this->statusFor($total, $paidAmount);

            $purchase = Purchase::query()->create([
                'number' => $number,
                'branch_id' => $branch->id,
                'supplier_id' => $supplierId,
                'purchase_date' => $data['purchase_date'],
                'status' => 'received',
                'subtotal' => round($subtotal, 4),
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_amount' => 0,
                'returned_amount' => 0,
                'payment_status' => $paymentStatus,
                'money_source_id' => $paidAmount > 0.0001 ? ($data['money_source_id'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $row) {
                $variant = ProductVariant::query()->with('product')->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                $unitPrice = (float) $row['unit_price'];
                $unitId = (int) $row['unit_id'];
                $bonusQty = (float) ($row['bonus_quantity'] ?? 0);
                $bonusUnitId = isset($row['bonus_unit_id']) && $row['bonus_unit_id'] !== '' && $row['bonus_unit_id'] !== null
                    ? (int) $row['bonus_unit_id']
                    : $variant->sale_unit_id;

                $paidInSale = $variant->toSaleQuantity($qty, $unitId);
                $bonusInSale = $bonusQty > 0
                    ? $variant->toSaleQuantity($bonusQty, $bonusUnitId)
                    : 0.0;
                $totalInSale = $paidInSale + $bonusInSale;

                $lineTotal = round($qty * $unitPrice, 4);
                $costPerSale = $totalInSale > 0 ? $lineTotal / $totalInSale : 0;

                $item = PurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'quantity_returned' => 0,
                    'bonus_quantity' => $bonusQty,
                    'bonus_unit_id' => $bonusQty > 0 ? $bonusUnitId : null,
                    'conversion_rate' => $variant->conversion_rate,
                    'quantity_in_sale_unit' => $totalInSale,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'cost_per_sale_unit' => $costPerSale,
                    'expiry_date' => $row['expiry_date'] ?? null,
                ]);

                if ($totalInSale > 0) {
                    $this->inventory->receive(
                        branchId: $branch->id,
                        variant: $variant,
                        qtySaleUnits: $totalInSale,
                        lineCostTotal: $lineTotal,
                        reference: $item,
                        notes: "Purchase {$purchase->number}",
                    );
                }
            }

            if ($supplierId && $total > 0) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
                $supplier->balance = round((float) $supplier->balance + $total, 4);
                $supplier->save();
            }

            if ($paidAmount > 0.0001) {
                if ($supplierId) {
                    $this->finance->paySupplier([
                        'supplier_id' => $supplierId,
                        'money_source_id' => (int) $data['money_source_id'],
                        'amount' => $paidAmount,
                        'branch_id' => $branch->id,
                        'purchase_id' => $purchase->id,
                        'apply_purchase_paid' => false,
                        'payment_date' => $data['purchase_date'],
                        'notes' => "Payment at purchase {$purchase->number}",
                    ]);
                } else {
                    $this->recordCashPurchasePayment(
                        $purchase,
                        (int) $data['money_source_id'],
                        $paidAmount,
                        $branch->id,
                        (string) $data['purchase_date'],
                    );
                }

                $purchase->update([
                    'paid_amount' => $paidAmount,
                    'payment_status' => $this->statusFor($total, $paidAmount),
                    'money_source_id' => (int) $data['money_source_id'],
                ]);
            }

            app(ActivityLogger::class)->log(
                'purchase.receive',
                "Purchase {$purchase->number} total {$total}",
                $purchase,
            );

            return $purchase->fresh(['items.product', 'items.variant', 'supplier', 'branch']);
        });
    }

    /**
     * @param  array{
     *   supplier_id?:int|null,
     *   number?:string|null,
     *   purchase_date:string,
     *   tax_total?:float|int|string,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   money_source_id?:int|null,
     *   paid_amount?:float|int|string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id:int,
     *     quantity:float|int|string,
     *     bonus_quantity?:float|int|string,
     *     bonus_unit_id?:int|null,
     *     unit_price:float|int|string,
     *     expiry_date?:string|null
     *   }>
     * }  $data
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->assertMutable($purchase);
            $this->reverseEffects($purchase);

            $branch = BranchContext::ensure();
            $items = $data['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one purchase line.',
                ]);
            }

            $subtotal = 0.0;
            foreach ($items as $row) {
                $subtotal += (float) $row['quantity'] * (float) $row['unit_price'];
            }

            $taxTotal = round((float) ($data['tax_total'] ?? 0), 4);
            $discountTotal = round((float) ($data['discount_total'] ?? 0), 4);
            $total = round(max(0, $subtotal + $taxTotal - $discountTotal), 4);

            $paidAmount = round(max(0, (float) ($data['paid_amount'] ?? 0)), 4);
            if ($paidAmount > $total + 0.0001) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Paid amount cannot exceed the purchase total.',
                ]);
            }

            $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' && $data['supplier_id'] !== null
                ? (int) $data['supplier_id']
                : null;

            if ($paidAmount + 0.0001 < $total && ! $supplierId) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Select a supplier when any amount is unpaid (credit / partial).',
                ]);
            }

            if ($paidAmount > 0.0001 && empty($data['money_source_id'])) {
                throw ValidationException::withMessages([
                    'money_source_id' => 'Select a money source for the payment.',
                ]);
            }

            $number = trim((string) ($data['number'] ?? ''));
            if ($number === '') {
                $number = $purchase->number;
            } elseif (
                Purchase::query()
                    ->where('number', $number)
                    ->where('id', '!=', $purchase->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'number' => 'This ref no is already in use.',
                ]);
            }

            $purchase->update([
                'number' => $number,
                'supplier_id' => $supplierId,
                'purchase_date' => $data['purchase_date'],
                'status' => 'received',
                'subtotal' => round($subtotal, 4),
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_amount' => 0,
                'returned_amount' => 0,
                'payment_status' => $this->statusFor($total, $paidAmount),
                'money_source_id' => $paidAmount > 0.0001 ? ($data['money_source_id'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchase->items()->delete();

            foreach ($items as $row) {
                $variant = ProductVariant::query()->with('product')->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                $unitPrice = (float) $row['unit_price'];
                $unitId = (int) $row['unit_id'];
                $bonusQty = (float) ($row['bonus_quantity'] ?? 0);
                $bonusUnitId = isset($row['bonus_unit_id']) && $row['bonus_unit_id'] !== '' && $row['bonus_unit_id'] !== null
                    ? (int) $row['bonus_unit_id']
                    : $variant->sale_unit_id;

                $paidInSale = $variant->toSaleQuantity($qty, $unitId);
                $bonusInSale = $bonusQty > 0
                    ? $variant->toSaleQuantity($bonusQty, $bonusUnitId)
                    : 0.0;
                $totalInSale = $paidInSale + $bonusInSale;

                $lineTotal = round($qty * $unitPrice, 4);
                $costPerSale = $totalInSale > 0 ? $lineTotal / $totalInSale : 0;

                $item = PurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'quantity_returned' => 0,
                    'bonus_quantity' => $bonusQty,
                    'bonus_unit_id' => $bonusQty > 0 ? $bonusUnitId : null,
                    'conversion_rate' => $variant->conversion_rate,
                    'quantity_in_sale_unit' => $totalInSale,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'cost_per_sale_unit' => $costPerSale,
                    'expiry_date' => $row['expiry_date'] ?? null,
                ]);

                if ($totalInSale > 0) {
                    $this->inventory->receive(
                        branchId: $branch->id,
                        variant: $variant,
                        qtySaleUnits: $totalInSale,
                        lineCostTotal: $lineTotal,
                        reference: $item,
                        notes: "Purchase {$purchase->number}",
                    );
                }
            }

            if ($supplierId && $total > 0) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
                $supplier->balance = round((float) $supplier->balance + $total, 4);
                $supplier->save();
            }

            if ($paidAmount > 0.0001) {
                if ($supplierId) {
                    $this->finance->paySupplier([
                        'supplier_id' => $supplierId,
                        'money_source_id' => (int) $data['money_source_id'],
                        'amount' => $paidAmount,
                        'branch_id' => $branch->id,
                        'purchase_id' => $purchase->id,
                        'apply_purchase_paid' => false,
                        'payment_date' => $data['purchase_date'],
                        'notes' => "Payment at purchase {$purchase->number}",
                    ]);
                } else {
                    $this->recordCashPurchasePayment(
                        $purchase,
                        (int) $data['money_source_id'],
                        $paidAmount,
                        $branch->id,
                        (string) $data['purchase_date'],
                    );
                }

                $purchase->update([
                    'paid_amount' => $paidAmount,
                    'payment_status' => $this->statusFor($total, $paidAmount),
                    'money_source_id' => (int) $data['money_source_id'],
                ]);
            }

            app(ActivityLogger::class)->log(
                'purchase.update',
                "Purchase {$purchase->number} updated · total {$total}",
                $purchase,
            );

            return $purchase->fresh(['items.product', 'items.variant', 'supplier', 'branch']);
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->assertMutable($purchase);
            $number = $purchase->number;
            $this->reverseEffects($purchase);
            $purchase->items()->delete();
            $purchase->delete();

            app(ActivityLogger::class)->log(
                'purchase.delete',
                "Purchase {$number} deleted",
                null,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForForm(Purchase $purchase): array
    {
        $purchase->loadMissing([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'returns:id',
        ]);

        $hasReturns = $purchase->returns->isNotEmpty()
            || $purchase->items->contains(fn (PurchaseItem $item) => (float) $item->quantity_returned > 0.0001);

        return [
            'id' => $purchase->id,
            'supplier_id' => $purchase->supplier_id,
            'number' => $purchase->number,
            'purchase_date' => $purchase->purchase_date?->format('Y-m-d'),
            'tax_total' => round((float) $purchase->tax_total, 2),
            'discount_total' => round((float) $purchase->discount_total, 2),
            'notes' => $purchase->notes ?? '',
            'money_source_id' => $purchase->money_source_id,
            'paid_amount' => round((float) $purchase->paid_amount, 2),
            'has_returns' => $hasReturns,
            'items' => $purchase->items->map(function (PurchaseItem $item) {
                $label = trim(($item->product?->name ?? '').(($item->variant?->name) ? ' – '.$item->variant->name : ''));

                return [
                    'variant_id' => (string) $item->variant_id,
                    'unit_id' => (string) $item->unit_id,
                    'quantity' => (string) round((float) $item->quantity, 4),
                    'bonus_quantity' => (string) round((float) $item->bonus_quantity, 4),
                    'bonus_unit_id' => $item->bonus_unit_id ? (string) $item->bonus_unit_id : '',
                    'unit_price' => (string) round((float) $item->unit_price, 4),
                    'expiry_date' => $item->expiry_date?->format('Y-m-d') ?? '',
                    'display_name' => $label !== '' ? $label : ($item->variant?->name ?? 'Item'),
                    'short_code' => $item->variant?->short_code ?? '',
                    'purchase_unit_label' => $item->unit?->code ?: ($item->unit?->name ?: '—'),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @deprecated Use create()
     */
    public function receive(array $data): Purchase
    {
        return $this->create([
            ...$data,
            'tax_total' => 0,
            'discount_total' => 0,
            'paid_amount' => 0,
        ]);
    }

    protected function assertMutable(Purchase $purchase): void
    {
        $purchase->loadMissing(['items', 'returns']);

        if ($purchase->returns->isNotEmpty()) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase has returns and cannot be edited or deleted.',
            ]);
        }

        if ($purchase->items->contains(fn (PurchaseItem $item) => (float) $item->quantity_returned > 0.0001)) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase has returned quantities and cannot be edited or deleted.',
            ]);
        }

        if ((float) $purchase->returned_amount > 0.0001) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase has returns and cannot be edited or deleted.',
            ]);
        }
    }

    protected function reverseEffects(Purchase $purchase): void
    {
        $purchase->loadMissing(['items.variant.product', 'items.variant']);

        $payments = SupplierPayment::query()
            ->where('purchase_id', $purchase->id)
            ->orderByDesc('id')
            ->get();

        foreach ($payments as $payment) {
            $this->finance->reverseSupplierPayment($payment);
        }

        if ($purchase->supplier_id && (float) $purchase->total > 0) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($purchase->supplier_id);
            $supplier->balance = round((float) $supplier->balance - (float) $purchase->total, 4);
            $supplier->save();
        }

        $this->reverseCashPurchasePayment($purchase);

        foreach ($purchase->items as $item) {
            $qtySale = (float) $item->quantity_in_sale_unit;
            if ($qtySale <= 0.0001 || ! $item->variant) {
                continue;
            }

            try {
                $this->inventory->deduct(
                    branchId: (int) $purchase->branch_id,
                    variant: $item->variant,
                    qtySaleUnits: $qtySale,
                    reference: $item,
                    notes: "Reverse purchase {$purchase->number}",
                    type: 'purchase_void',
                    allowNegative: false,
                );
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'purchase' => $e->getMessage().' Edit or delete is blocked until stock is available.',
                ]);
            }
        }

        $purchase->update([
            'paid_amount' => 0,
            'payment_status' => 'pending',
            'money_source_id' => null,
            'subtotal' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 0,
        ]);
    }

    protected function reverseCashPurchasePayment(Purchase $purchase): void
    {
        $ledgers = LedgerTransaction::query()
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->get();

        if ($ledgers->isNotEmpty()) {
            foreach ($ledgers as $txn) {
                if ($txn->money_source_id) {
                    $source = MoneySource::query()->lockForUpdate()->find($txn->money_source_id);
                    if ($source) {
                        $source->balance = round((float) $source->balance + (float) $txn->amount, 4);
                        $source->save();
                    }
                }
                $txn->delete();
            }

            return;
        }

        // Fallback when ledger was not written (e.g. missing Purchase account).
        if (
            ! $purchase->supplier_id
            && (float) $purchase->paid_amount > 0.0001
            && $purchase->money_source_id
        ) {
            $source = MoneySource::query()->lockForUpdate()->find($purchase->money_source_id);
            if ($source) {
                $source->balance = round((float) $source->balance + (float) $purchase->paid_amount, 4);
                $source->save();
            }
        }
    }

    protected function statusFor(float $total, float $paid): string
    {
        if ($total <= 0.0001 || $paid + 0.0001 >= $total) {
            return 'paid';
        }
        if ($paid > 0.0001) {
            return 'partial';
        }

        return 'pending';
    }

    protected function recordCashPurchasePayment(
        Purchase $purchase,
        int $moneySourceId,
        float $amount,
        int $branchId,
        string $paymentDate,
    ): void {
        $source = MoneySource::query()->lockForUpdate()->findOrFail($moneySourceId);
        if (! $source->isSelectableForPayment()) {
            throw ValidationException::withMessages([
                'money_source_id' => 'Invalid or inactive payment source.',
            ]);
        }

        try {
            $amount = MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'paid_amount' => $e->getMessage(),
            ]);
        }

        $source->balance = (float) $source->balance - $amount;
        $source->save();

        $purchaseAccount = Account::query()
            ->where('type', 'expense')
            ->where('name', 'Purchase')
            ->first();

        if ($purchaseAccount) {
            LedgerTransaction::query()->create([
                'branch_id' => $branchId,
                'account_id' => $purchaseAccount->id,
                'money_source_id' => $source->id,
                'direction' => 'out',
                'amount' => $amount,
                'txn_date' => $paymentDate,
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'notes' => "Payment at purchase {$purchase->number}",
                'created_by' => Auth::id(),
                'is_manual' => false,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'purchase_date', 'number', 'total', 'payment_status'];
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
     * @return Collection<int, array{id:int, name:string, balance:float}>
     */
    protected function supplierOptions(): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'balance'])
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'balance' => round((float) $s->balance, 2),
            ])
            ->values();
    }

    protected function nextNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';
        $last = Purchase::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeList(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'purchase_date' => $purchase->purchase_date?->format('Y-m-d'),
            'total' => round((float) $purchase->total, 2),
            'paid_amount' => round((float) $purchase->paid_amount, 2),
            'returned_amount' => round((float) $purchase->returned_amount, 2),
            'balance_due' => round($purchase->balanceDue(), 2),
            'payment_status' => $purchase->payment_status,
            'supplier' => $purchase->supplier
                ? ['id' => $purchase->supplier->id, 'name' => $purchase->supplier->name]
                : null,
            'can_edit' => $this->isMutable($purchase),
            'can_delete' => $this->isMutable($purchase),
        ];
    }

    protected function isMutable(Purchase $purchase): bool
    {
        if ((float) $purchase->returned_amount > 0.0001) {
            return false;
        }

        if ($purchase->relationLoaded('returns') && $purchase->returns->isNotEmpty()) {
            return false;
        }

        if ($purchase->relationLoaded('items')) {
            return ! $purchase->items->contains(
                fn (PurchaseItem $item) => (float) $item->quantity_returned > 0.0001
            );
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetail(Purchase $purchase): array
    {
        return [
            ...$this->serializeList($purchase),
            'subtotal' => round((float) $purchase->subtotal, 2),
            'tax_total' => round((float) $purchase->tax_total, 2),
            'discount_total' => round((float) $purchase->discount_total, 2),
            'notes' => $purchase->notes,
            'status' => $purchase->status,
            'branch' => $purchase->branch
                ? ['id' => $purchase->branch->id, 'name' => $purchase->branch->name]
                : null,
            'money_source' => $purchase->moneySource
                ? ['id' => $purchase->moneySource->id, 'name' => $purchase->moneySource->name]
                : null,
            'items' => $purchase->items->map(fn (PurchaseItem $item) => [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'unit_id' => $item->unit_id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'variant_code' => $item->variant?->short_code,
                'unit_code' => $item->unit?->code,
                'bonus_unit_code' => $item->bonusUnit?->code,
                'quantity' => round((float) $item->quantity, 4),
                'quantity_returned' => round((float) $item->quantity_returned, 4),
                'returnable_quantity' => round($item->returnableQuantity(), 4),
                'bonus_quantity' => round((float) $item->bonus_quantity, 4),
                'quantity_in_sale_unit' => round((float) $item->quantity_in_sale_unit, 4),
                'unit_price' => round((float) $item->unit_price, 4),
                'line_total' => round((float) $item->line_total, 2),
                'expiry_date' => $item->expiry_date?->format('Y-m-d'),
            ])->values(),
            'returns' => $purchase->returns->map(fn ($r) => [
                'id' => $r->id,
                'number' => $r->number,
                'return_date' => $r->return_date?->format('Y-m-d'),
                'total' => round((float) $r->total, 2),
                'settlement_type' => $r->settlement_type,
            ])->values(),
            'can_edit' => $this->isMutable($purchase),
            'can_delete' => $this->isMutable($purchase),
        ];
    }
}
