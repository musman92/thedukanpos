<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockDamage;
use App\Models\StockDamageItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockDocumentService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * @param  array{branch_id:int, reason?:string, notes?:string, items: list<array{variant_id:int, quantity:float|int|string}>}  $data
     */
    public function adjust(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            $doc = StockAdjustment::query()->create([
                'number' => $this->nextNumber('ADJ'),
                'branch_id' => $data['branch_id'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                $variant = ProductVariant::query()->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                $stock = $this->inventory->getOrCreateStock((int) $data['branch_id'], $variant);

                $item = StockAdjustmentItem::query()->create([
                    'stock_adjustment_id' => $doc->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_cost' => $stock->average_cost,
                ]);

                $this->inventory->adjust(
                    (int) $data['branch_id'],
                    $variant,
                    $qty,
                    $item,
                    "Adjustment {$doc->number}",
                );
            }

            return $doc->load('items.variant');
        });
    }

    /**
     * @param  array{from_branch_id:int, to_branch_id:int, notes?:string, items: list<array{variant_id:int, quantity:float|int|string}>}  $data
     */
    public function transfer(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $doc = StockTransfer::query()->create([
                'number' => $this->nextNumber('TR'),
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                $variant = ProductVariant::query()->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $fromStock = $this->inventory->getOrCreateStock((int) $data['from_branch_id'], $variant);

                $item = StockTransferItem::query()->create([
                    'stock_transfer_id' => $doc->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_cost' => $fromStock->average_cost,
                ]);

                $this->inventory->transfer(
                    (int) $data['from_branch_id'],
                    (int) $data['to_branch_id'],
                    $variant,
                    $qty,
                    $item,
                    "Transfer {$doc->number}",
                );
            }

            return $doc->load(['items.variant', 'fromBranch', 'toBranch']);
        });
    }

    public function reverseTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::query()
                ->with('items.variant')
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            foreach ($transfer->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }

                $qty = (float) $item->quantity;
                if ($qty < 0.0001) {
                    $item->delete();

                    continue;
                }

                // Move stock back: destination → origin.
                $this->inventory->transfer(
                    (int) $transfer->to_branch_id,
                    (int) $transfer->from_branch_id,
                    $variant,
                    $qty,
                    $item,
                    "Reverse transfer {$transfer->number}",
                );

                $item->delete();
            }

            $transfer->delete();
        });
    }

    public function reverseAdjustment(StockAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            $adjustment = StockAdjustment::query()
                ->with('items.variant')
                ->lockForUpdate()
                ->findOrFail($adjustment->id);

            foreach ($adjustment->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }

                $qty = (float) $item->quantity;
                if (abs($qty) < 0.0001) {
                    continue;
                }

                // Reverse the original signed adjustment.
                $this->inventory->adjust(
                    (int) $adjustment->branch_id,
                    $variant,
                    -$qty,
                    $item,
                    "Reverse adjustment {$adjustment->number}",
                );

                $item->delete();
            }

            $adjustment->delete();
        });
    }

    /**
     * @param  array{branch_id:int, reason:string, notes?:string|null, items: list<array{variant_id:int, quantity:float|int|string}>}  $data
     */
    public function damage(array $data): StockDamage
    {
        return DB::transaction(function () use ($data) {
            $doc = StockDamage::query()->create([
                'number' => $this->nextNumber('DMG'),
                'branch_id' => $data['branch_id'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                $variant = ProductVariant::query()->findOrFail($row['variant_id']);
                $qty = (float) $row['quantity'];
                if ($qty < 0.0001) {
                    continue;
                }

                $stock = $this->inventory->getOrCreateStock((int) $data['branch_id'], $variant);

                $item = StockDamageItem::query()->create([
                    'stock_damage_id' => $doc->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_cost' => $stock->average_cost,
                ]);

                $this->inventory->damage(
                    (int) $data['branch_id'],
                    $variant,
                    $qty,
                    $item,
                    "Damage {$doc->number}",
                );
            }

            return $doc->load(['items.variant', 'branch']);
        });
    }

    public function reverseDamage(StockDamage $damage): void
    {
        DB::transaction(function () use ($damage) {
            $damage = StockDamage::query()
                ->with('items.variant')
                ->lockForUpdate()
                ->findOrFail($damage->id);

            foreach ($damage->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }

                $qty = (float) $item->quantity;
                if ($qty < 0.0001) {
                    $item->delete();

                    continue;
                }

                $unitCost = (float) ($item->unit_cost ?? 0);

                // Restore stock that was written off.
                $this->inventory->receive(
                    (int) $damage->branch_id,
                    $variant,
                    $qty,
                    $unitCost * $qty,
                    $item,
                    "Reverse damage {$damage->number}",
                    'damage_reversal',
                );

                $item->delete();
            }

            $damage->delete();
        });
    }

    protected function nextNumber(string $prefix): string
    {
        $full = $prefix.'-'.now()->format('Ymd').'-';
        $model = match ($prefix) {
            'ADJ' => StockAdjustment::class,
            'TR' => StockTransfer::class,
            'DMG' => StockDamage::class,
            default => throw new \InvalidArgumentException("Unknown document prefix [{$prefix}]."),
        };
        $last = $model::query()->where('number', 'like', $full.'%')->orderByDesc('id')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $full.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
