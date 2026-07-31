<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function productsAsPurchase(): HasMany
    {
        return $this->hasMany(Product::class, 'purchase_unit_id');
    }

    public function productsAsSale(): HasMany
    {
        return $this->hasMany(Product::class, 'sale_unit_id');
    }

    public function variantsAsPurchase(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'purchase_unit_id');
    }

    public function variantsAsSale(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'sale_unit_id');
    }

    /**
     * Next sequential code: u01, u02, … (lowercase — unit labels like pcs/kg).
     */
    public static function nextAutoCode(): string
    {
        $codes = static::query()
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^u0*(\d+)$/i', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'u'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    public static function resolveCode(?string $code): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtolower($code) : static::nextAutoCode();
    }
}
