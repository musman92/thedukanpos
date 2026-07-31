<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function getOrCreateStock(int $branchId, ProductVariant $variant): BranchStock
    {
        return BranchStock::query()->firstOrCreate(
            ['branch_id' => $branchId, 'variant_id' => $variant->id],
            [
                'product_id' => $variant->product_id,
                'quantity' => 0,
                'average_cost' => 0,
            ],
        );
    }

    public function receive(
        int $branchId,
        ProductVariant $variant,
        float $qtySaleUnits,
        float $lineCostTotal,
        ?Model $reference = null,
        ?string $notes = null,
        string $type = 'purchase',
    ): BranchStock {
        return DB::transaction(function () use ($branchId, $variant, $qtySaleUnits, $lineCostTotal, $reference, $notes, $type) {
            $stock = $this->getOrCreateStock($branchId, $variant);
            $stock = BranchStock::query()->whereKey($stock->id)->lockForUpdate()->first();

            $oldQty = (float) $stock->quantity;
            $oldCost = (float) $stock->average_cost;
            $newQty = $oldQty + $qtySaleUnits;

            if ($newQty > 0 && $qtySaleUnits > 0 && $lineCostTotal >= 0) {
                $stock->average_cost = (($oldQty * $oldCost) + $lineCostTotal) / $newQty;
            }

            $stock->quantity = $newQty;
            $stock->save();

            $unitCost = $qtySaleUnits > 0 ? $lineCostTotal / $qtySaleUnits : (float) $stock->average_cost;

            $this->move(
                branchId: $branchId,
                variant: $variant,
                type: $type,
                quantity: $qtySaleUnits,
                unitCost: $unitCost,
                balanceAfter: $newQty,
                reference: $reference,
                notes: $notes,
            );

            if ($type === 'purchase' || $type === 'opening') {
                $variant->cost_per_unit = $stock->average_cost;
                $variant->save();

                $product = $variant->product ?? Product::query()->find($variant->product_id);
                if ($product) {
                    $product->cost_per_unit = $stock->average_cost;
                    $product->save();
                }
            }

            return $stock;
        });
    }

    public function deduct(
        int $branchId,
        ProductVariant $variant,
        float $qtySaleUnits,
        ?Model $reference = null,
        ?string $notes = null,
        string $type = 'sale',
        bool $allowNegative = false,
    ): BranchStock {
        return DB::transaction(function () use ($branchId, $variant, $qtySaleUnits, $reference, $notes, $type, $allowNegative) {
            $product = $variant->product ?? Product::query()->find($variant->product_id);
            $stock = $this->getOrCreateStock($branchId, $variant);
            $stock = BranchStock::query()->whereKey($stock->id)->lockForUpdate()->first();

            if (! $allowNegative && $product?->track_stock && (float) $stock->quantity < $qtySaleUnits) {
                throw new \RuntimeException("Insufficient stock for {$variant->displayName()}. Available: {$stock->quantity}");
            }

            $unitCost = (float) $stock->average_cost;
            $stock->quantity = (float) $stock->quantity - $qtySaleUnits;
            $stock->save();

            $this->move(
                branchId: $branchId,
                variant: $variant,
                type: $type,
                quantity: -$qtySaleUnits,
                unitCost: $unitCost,
                balanceAfter: (float) $stock->quantity,
                reference: $reference,
                notes: $notes,
            );

            return $stock;
        });
    }

    /**
     * Signed qty in sale units (+ increase, - decrease). Average cost unchanged on qty-only adjust.
     */
    public function adjust(
        int $branchId,
        ProductVariant $variant,
        float $signedQtySaleUnits,
        ?Model $reference = null,
        ?string $notes = null,
    ): BranchStock {
        if ($signedQtySaleUnits > 0) {
            $cost = (float) $this->getOrCreateStock($branchId, $variant)->average_cost;

            return $this->receive(
                $branchId,
                $variant,
                $signedQtySaleUnits,
                $cost * $signedQtySaleUnits,
                $reference,
                $notes,
                'adjustment',
            );
        }

        if ($signedQtySaleUnits < 0) {
            return $this->deduct(
                $branchId,
                $variant,
                abs($signedQtySaleUnits),
                $reference,
                $notes,
                'adjustment',
            );
        }

        return $this->getOrCreateStock($branchId, $variant);
    }

    /**
     * Record a stock loss (positive qty in sale units). Movement type: damage.
     */
    public function damage(
        int $branchId,
        ProductVariant $variant,
        float $qtySaleUnits,
        ?Model $reference = null,
        ?string $notes = null,
    ): BranchStock {
        if ($qtySaleUnits < 0.0001) {
            throw new \RuntimeException('Damage quantity must be greater than zero.');
        }

        return $this->deduct(
            $branchId,
            $variant,
            $qtySaleUnits,
            $reference,
            $notes,
            'damage',
        );
    }

    public function transfer(
        int $fromBranchId,
        int $toBranchId,
        ProductVariant $variant,
        float $qtySaleUnits,
        ?Model $reference = null,
        ?string $notes = null,
    ): void {
        if ($fromBranchId === $toBranchId) {
            throw new \RuntimeException('Cannot transfer to the same branch.');
        }

        DB::transaction(function () use ($fromBranchId, $toBranchId, $variant, $qtySaleUnits, $reference, $notes) {
            $fromStock = $this->deduct(
                $fromBranchId,
                $variant,
                $qtySaleUnits,
                $reference,
                $notes,
                'transfer_out',
            );

            $this->receive(
                $toBranchId,
                $variant,
                $qtySaleUnits,
                (float) $fromStock->average_cost * $qtySaleUnits,
                $reference,
                $notes,
                'transfer_in',
            );
        });
    }

    protected function move(
        int $branchId,
        ProductVariant $variant,
        string $type,
        float $quantity,
        ?float $unitCost,
        float $balanceAfter,
        ?Model $reference,
        ?string $notes,
    ): StockMovement {
        return StockMovement::query()->create([
            'branch_id' => $branchId,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
            'user_id' => Auth::id(),
        ]);
    }
}
