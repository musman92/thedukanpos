<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'variation_option_id',
        'name',
        'short_code',
        'barcode',
        'sku',
        'purchase_unit_id',
        'sale_unit_id',
        'conversion_rate',
        'sale_price',
        'cost_per_unit',
        'is_active',
        'track_serial',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'conversion_rate' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'cost_per_unit' => 'decimal:4',
            'is_active' => 'boolean',
            'track_serial' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variationOption(): BelongsTo
    {
        return $this->belongsTo(VariationOption::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class, 'variant_id');
    }

    public function locationForBranch(int $branchId): HasOne
    {
        return $this->hasOne(ProductLocation::class, 'variant_id')->where('branch_id', $branchId);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class, 'variant_id');
    }

    public function displayName(): string
    {
        $productName = $this->product?->name ?? 'Product';

        return $this->name ? "{$productName} — {$this->name}" : $productName;
    }

    public function hasDualUnits(): bool
    {
        return (float) $this->conversion_rate !== 1.0
            || $this->purchase_unit_id !== $this->sale_unit_id;
    }

    public function toSaleQuantity(float $qty, ?int $unitId = null): float
    {
        $unitId ??= $this->purchase_unit_id;

        if ($unitId === $this->sale_unit_id) {
            return $qty;
        }

        if ($unitId === $this->purchase_unit_id) {
            return $qty * (float) $this->conversion_rate;
        }

        return $qty;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('short_code', 'like', "%{$term}%")
                ->orWhere('barcode', $term)
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('product', function (Builder $pq) use ($term) {
                    $pq->where('name', 'like', "%{$term}%")
                        ->orWhere('short_code', 'like', "%{$term}%");
                });
        });
    }
}
