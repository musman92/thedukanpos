<?php

namespace Addons\Bookings\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSetting extends Model
{
    protected $fillable = [
        'max_concurrent',
        'default_duration_minutes',
        'public_enabled',
        'allow_staff_overbook',
        'sales_ui',
        'business_hours',
        'google_calendar_id',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
    ];

    protected $hidden = [
        'google_access_token',
        'google_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'max_concurrent' => 'integer',
            'default_duration_minutes' => 'integer',
            'public_enabled' => 'boolean',
            'allow_staff_overbook' => 'boolean',
            'business_hours' => 'array',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'business_hours' => static::defaultHours(),
        ]);
    }

    /**
     * @return array<string, array{enabled:bool,open:string,close:string}>
     */
    public static function defaultHours(): array
    {
        return collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->mapWithKeys(fn (string $day) => [$day => [
                'enabled' => ! in_array($day, ['sunday'], true),
                'open' => '09:00',
                'close' => '17:00',
            ]])
            ->all();
    }
}
