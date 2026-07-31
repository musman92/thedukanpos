<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const CODE_WALK_IN = 'WALKIN';

    protected $fillable = [
        'name', 'code', 'phone', 'email', 'address', 'balance', 'is_active', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function isWalkIn(): bool
    {
        return (bool) $this->is_system
            && strtoupper((string) $this->code) === self::CODE_WALK_IN;
    }

    public static function walkIn(): ?self
    {
        return static::query()
            ->where('code', self::CODE_WALK_IN)
            ->where('is_system', true)
            ->first();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Next sequential code: C01, C02, …
     */
    public static function nextAutoCode(): string
    {
        $codes = static::query()
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^c0*(\d+)$/i', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'C'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    public static function resolveCode(?string $code): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtoupper($code) : static::nextAutoCode();
    }
}
