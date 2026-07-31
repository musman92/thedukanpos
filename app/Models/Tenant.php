<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'code',
            'name',
            'email',
            'phone',
            'address',
            'tax_id',
            'currency',
            'timezone',
            'is_active',
            'is_demo',
            'demo_seed',
            'plan',
            'billing_status',
            'monthly_fee',
            'trial_ends_at',
            'billing_notes',
            'created_at',
            'updated_at',
        ];
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'demo_seed' => 'array',
        'monthly_fee' => 'decimal:2',
        'trial_ends_at' => 'date',
    ];

    public static function findByCode(string $code): ?self
    {
        return static::query()
            ->where('code', strtolower($code))
            ->where('is_active', true)
            ->first();
    }
}
