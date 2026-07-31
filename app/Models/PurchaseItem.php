<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'quantity_returned',
        'bonus_quantity',
        'bonus_unit_id',
        'conversion_rate',
        'quantity_in_sale_unit',
        'unit_price',
        'line_total',
        'cost_per_sale_unit',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_returned' => 'decimal:4',
            'bonus_quantity' => 'decimal:4',
            'conversion_rate' => 'decimal:4',
            'quantity_in_sale_unit' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
            'cost_per_sale_unit' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function bonusUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'bonus_unit_id');
    }

    public function returnableQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_returned);
    }
}
