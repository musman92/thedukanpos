<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;

    public const INTERVALS = ['monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'product_limit',
        'user_limit',
        'branch_limit',
        'ads_enabled',
        'monthly_price',
        'quarterly_price',
        'yearly_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_limit' => 'integer',
            'user_limit' => 'integer',
            'branch_limit' => 'integer',
            'ads_enabled' => 'boolean',
            'monthly_price' => 'decimal:2',
            'quarterly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }

    public function priceFor(string $interval): float
    {
        $value = match ($interval) {
            'quarterly' => $this->quarterly_price,
            'yearly' => $this->yearly_price,
            default => $this->monthly_price,
        };

        return round((float) $value, 2);
    }
}
