<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variation extends Model
{
    protected $fillable = ['name', 'code', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VariationOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Next sequential code: V01, V02, …
     */
    public static function nextAutoCode(): string
    {
        $codes = static::query()
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^v0*(\d+)$/i', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'V'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    public static function resolveCode(?string $code): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtoupper($code) : static::nextAutoCode();
    }
}
