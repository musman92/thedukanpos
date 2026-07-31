<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneySource extends Model
{
    use SoftDeletes;

    public const SYSTEM_OWNER_WITHDRAWAL = 'owner_withdrawal';

    public const TYPES = ['CASH', 'BANK', 'APP'];

    protected $fillable = [
        'name',
        'code',
        'type',
        'opening_balance',
        'balance',
        'is_active',
        'exclude_from_dashboard_profit',
        'is_system',
        'system_key',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'balance' => 'decimal:4',
            'is_active' => 'boolean',
            'exclude_from_dashboard_profit' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_money_sources')
            ->withTimestamps();
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function fundMovementsOut(): HasMany
    {
        return $this->hasMany(MoneySourceFundMovement::class, 'from_money_source_id');
    }

    public function fundMovementsIn(): HasMany
    {
        return $this->hasMany(MoneySourceFundMovement::class, 'to_money_source_id');
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Cash, bank, and app sources usable for POS and payments.
     */
    public function scopeForPayments(Builder $query): Builder
    {
        return $query->operational()
            ->where('is_active', true)
            ->where('type', '!=', 'OWNER_DRAW')
            ->where(function (Builder $inner) {
                $inner->whereNull('system_key')
                    ->orWhere('system_key', '!=', self::SYSTEM_OWNER_WITHDRAWAL);
            });
    }

    /**
     * Sources assigned to a branch (via branch_money_sources).
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('branches', fn (Builder $q) => $q->where('branches.id', $branchId));
    }

    public function isOperational(): bool
    {
        return ! $this->is_system;
    }

    public function isOwnerWithdrawalBucket(): bool
    {
        return $this->system_key === self::SYSTEM_OWNER_WITHDRAWAL
            || strtoupper((string) $this->type) === 'OWNER_DRAW';
    }

    public function isSelectableForPayment(): bool
    {
        if (! $this->isOperational() || ! $this->is_active) {
            return false;
        }

        if ($this->isOwnerWithdrawalBucket()) {
            return false;
        }

        return strtoupper((string) $this->type) !== 'OWNER_DRAW';
    }

    public static function ownerWithdrawal(): ?self
    {
        return static::query()
            ->where('system_key', self::SYSTEM_OWNER_WITHDRAWAL)
            ->first();
    }

    public function isCash(): bool
    {
        return strtoupper((string) $this->type) === 'CASH';
    }

    /**
     * Display balance. DukanPOS keeps a denormalized `balance` column updated
     * by FinanceService; this matches FoodPOS "current balance" for the UI.
     */
    public function currentBalance(): float
    {
        return round((float) $this->balance, 2);
    }
}
