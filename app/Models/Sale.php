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

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_ASSIGNED = 'assigned';

    public const DELIVERY_OUT = 'out_for_delivery';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function deliveryStatuses(): array
    {
        return [
            self::DELIVERY_PENDING,
            self::DELIVERY_ASSIGNED,
            self::DELIVERY_OUT,
            self::DELIVERY_DELIVERED,
            self::DELIVERY_CANCELLED,
        ];
    }

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
        'is_delivery',
        'delivery_charge',
        'delivery_address',
        'delivery_status',
        'rider_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_total' => 'decimal:4',
            'is_delivery' => 'boolean',
            'delivery_charge' => 'decimal:4',
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

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
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
