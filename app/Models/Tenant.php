<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public function addons(): HasMany
    {
        return $this->hasMany(TenantAddon::class, 'tenant_id');
    }

    public function planRecord(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

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
            'plan_id',
            'billing_status',
            'monthly_fee',
            'billing_interval',
            'override_limits',
            'product_limit',
            'user_limit',
            'branch_limit',
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
        'plan_id' => 'integer',
        'monthly_fee' => 'decimal:2',
        'override_limits' => 'boolean',
        'product_limit' => 'integer',
        'user_limit' => 'integer',
        'branch_limit' => 'integer',
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
