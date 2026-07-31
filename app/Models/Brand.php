<?php

namespace App\Models;

use App\Services\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'code', 'image', 'is_active'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return app(ImageUploadService::class)->url($this->image);
    }

    /**
     * Next sequential code: B01, B02, … B99, B100, …
     */
    public static function nextAutoCode(): string
    {
        $codes = static::query()
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^b0*(\d+)$/i', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'B'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    public static function resolveCode(?string $code): string
    {
        $code = trim((string) $code);

        return $code !== '' ? strtoupper($code) : static::nextAutoCode();
    }
}
