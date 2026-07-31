<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'ledger_transactions';

    /** Allowlist for validation (system + manual). */
    public const REFERENCE_TYPES = [
        'sale',
        'purchase',
        'refund',
        'expense',
        'customer_payment',
        'supplier_payment',
        'employee_payment',
        'transfer',
        'reconciliation',
        'adjustment',
    ];

    /** Options shown on the manual transaction form (FoodPOS parity). */
    public const MANUAL_REFERENCE_TYPES = [
        'sale',
        'purchase',
        'refund',
        'expense',
    ];

    protected $fillable = [
        'branch_id',
        'account_id',
        'money_source_id',
        'shift_id',
        'direction',
        'amount',
        'txn_date',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'txn_date' => 'date',
            'is_manual' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function moneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canBeModified(): bool
    {
        return (bool) $this->is_manual;
    }
}
