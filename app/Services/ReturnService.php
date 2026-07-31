<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        protected InventoryService $inventory,
        protected CustomerService $customers,
    ) {}

    /**
     * @param  array{
     *   branch_id:int,
     *   purchase_id?:int|null,
     *   supplier_id?:int|null,
     *   return_date:string,
     *   notes?:string|null,
     *   items: list<array{variant_id:int, unit_id:int, quantity:float|int|string, unit_cost?:float|int|string}>
     * }  $data
     */
    public function purchaseReturn(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $doc = PurchaseReturn::query()->create([
                'number' => $this->nextNumber('PR'),
                'branch_id' => $data['branch_id'],
                'purchase_id' => $data['purchase_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $total = 0;

            foreach ($data['items'] as $row) {
                $variant = ProductVariant::query()->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                $unitId = (int) $row['unit_id'];
                $qtySale = $variant->toSaleQuantity($qty, $unitId);
                $unitCost = isset($row['unit_cost'])
                    ? (float) $row['unit_cost']
                    : (float) $this->inventory->getOrCreateStock((int) $data['branch_id'], $variant)->average_cost;

                // unit_cost is per sale unit when converting
                $lineTotal = $qtySale * $unitCost;

                $item = PurchaseReturnItem::query()->create([
                    'purchase_return_id' => $doc->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'conversion_rate' => $variant->conversion_rate,
                    'quantity_in_sale_unit' => $qtySale,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $this->inventory->deduct(
                    (int) $data['branch_id'],
                    $variant,
                    $qtySale,
                    $item,
                    "Purchase return {$doc->number}",
                    'purchase_return',
                );

                $total += $lineTotal;
            }

            $doc->update(['total' => $total]);

            app(ActivityLogger::class)->log(
                'return.purchase',
                "Purchase return {$doc->number}",
                $doc,
            );

            return $doc->load(['items.variant', 'supplier']);
        });
    }

    /**
     * @param  array{
     *   branch_id:int,
     *   sale_id:int,
     *   shift_id?:int|null,
     *   return_date:string,
     *   notes?:string|null,
     *   items: list<array{sale_item_id:int, quantity:float|int|string}>
     * }  $data
     */
    public function saleReturn(array $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::query()->with('items')->findOrFail($data['sale_id']);

            $doc = SaleReturn::query()->create([
                'number' => $this->nextNumber('SR'),
                'branch_id' => $data['branch_id'],
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'shift_id' => $data['shift_id'] ?? $sale->shift_id,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $subtotal = 0;
            $taxTotal = 0;

            foreach ($data['items'] as $row) {
                /** @var SaleItem $saleItem */
                $saleItem = $sale->items->firstWhere('id', (int) $row['sale_item_id'])
                    ?? SaleItem::query()->where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);

                $qty = (float) $row['quantity'];
                if ($qty <= 0 || $qty > (float) $saleItem->quantity) {
                    throw new \RuntimeException('Invalid return quantity for a sale line.');
                }

                $ratio = $qty / (float) $saleItem->quantity;
                $lineTotal = (float) $saleItem->line_total * $ratio;
                $taxAmount = (float) $saleItem->tax_amount * $ratio;
                $lineNet = $lineTotal - $taxAmount;
                $qtySale = (float) $saleItem->quantity_in_sale_unit * $ratio;

                $variant = ProductVariant::query()->findOrFail($saleItem->variant_id);

                $item = SaleReturnItem::query()->create([
                    'sale_return_id' => $doc->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'unit_id' => $saleItem->unit_id,
                    'quantity' => $qty,
                    'quantity_in_sale_unit' => $qtySale,
                    'unit_price' => $saleItem->unit_price,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                ]);

                $this->inventory->receive(
                    (int) $data['branch_id'],
                    $variant,
                    $qtySale,
                    (float) $saleItem->cost_per_unit * $qtySale,
                    $item,
                    "Sale return {$doc->number}",
                    'sale_return',
                );

                $subtotal += $lineNet;
                $taxTotal += $taxAmount;
            }

            $total = $subtotal + $taxTotal;
            $doc->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'refunded_total' => $total,
            ]);

            // Reduce customer balance for the unpaid portion of the original sale.
            $saleUnpaid = max(0, (float) $sale->total - (float) $sale->paid_total);
            if ($saleUnpaid > 0.01 && $sale->customer_id && (float) $sale->total > 0) {
                $creditBack = round($saleUnpaid * ($total / (float) $sale->total), 4);
                $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
                if ($customer) {
                    $this->customers->credit($customer, $creditBack);
                }
            }

            app(ActivityLogger::class)->log(
                'return.sale',
                "Sale return {$doc->number}",
                $doc,
            );

            return $doc->load(['items.variant', 'sale']);
        });
    }

    protected function nextNumber(string $prefix): string
    {
        $full = $prefix.'-'.now()->format('Ymd').'-';
        $model = $prefix === 'PR' ? PurchaseReturn::class : SaleReturn::class;
        $last = $model::query()->where('number', 'like', $full.'%')->orderByDesc('id')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $full.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
