<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'code', 'name', 'phone', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches')
            ->withPivot('is_primary');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public static function nextAutoCode(): string
    {
        $max = static::query()
            ->where('code', 'like', 'BR%')
            ->get(['code'])
            ->map(function (self $b) {
                if (preg_match('/^BR(\d+)$/i', (string) $b->code, $m)) {
                    return (int) $m[1];
                }

                return 0;
            })
            ->max() ?? 0;

        $next = $max + 1;

        return 'BR'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    public static function resolveCode(?string $code): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtoupper($code) : static::nextAutoCode();
    }
}
