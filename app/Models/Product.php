<?php

namespace App\Models;

use App\Services\ImageUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'type',
        'short_code',
        'barcode',
        'sku',
        'brand_id',
        'category_id',
        'variation_id',
        'tax_id',
        'purchase_unit_id',
        'sale_unit_id',
        'conversion_rate',
        'sale_price',
        'cost_per_unit',
        'min_qty_alert',
        'track_stock',
        'is_active',
        'notes',
        'image',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'conversion_rate' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'cost_per_unit' => 'decimal:4',
            'min_qty_alert' => 'decimal:4',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return app(ImageUploadService::class)->url($this->image);
    }

    public function isSingle(): bool
    {
        return ($this->type ?? 'single') === 'single';
    }

    /**
     * Next sequential code: P01, P02, … (checks products + variants + optional reserved).
     *
     * @param  list<string>  $reserved
     */
    public static function nextAutoCode(array $reserved = []): string
    {
        $codes = static::query()->whereNotNull('short_code')->pluck('short_code')
            ->merge(ProductVariant::query()->whereNotNull('short_code')->pluck('short_code'))
            ->merge($reserved);

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^p0*(\d+)$/i', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'P'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $reserved
     */
    public static function resolveCode(?string $code, array $reserved = []): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtoupper($code) : static::nextAutoCode($reserved);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('short_code', 'like', "%{$term}%")
                ->orWhere('barcode', $term)
                ->orWhereHas('variants', function (Builder $vq) use ($term) {
                    $vq->where('short_code', 'like', "%{$term}%")
                        ->orWhere('barcode', $term)
                        ->orWhere('name', 'like', "%{$term}%");
                });
        });
    }
}
