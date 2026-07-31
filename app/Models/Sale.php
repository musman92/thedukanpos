<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_PARKED = 'parked';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'number',
        'branch_id',
        'shift_id',
        'customer_id',
        'cashier_id',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'paid_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_total' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function isParked(): bool
    {
        return $this->status === self::STATUS_PARKED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_total);
    }
}
