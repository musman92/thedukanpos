<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $all = static::allGrouped();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::forgetCache();
    }

    /**
     * @return array<string, string|null>
     */
    public static function allGrouped(): array
    {
        return Cache::remember(static::cacheKey(), now()->addDay(), function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(static::cacheKey());
    }

    protected static function cacheKey(): string
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : 'central';

        return "company_settings.{$tenantId}";
    }
}
