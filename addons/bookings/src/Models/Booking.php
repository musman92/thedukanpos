<?php

namespace Addons\Bookings\Models;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'done', 'cancelled', 'expired'];

    /** Statuses that still occupy the time, including a job marked done early. */
    public const HOLDING_STATUSES = ['pending', 'confirmed', 'done'];

    protected $appends = ['order_total'];

    protected $fillable = [
        'number',
        'product_id',
        'customer_id',
        'assigned_user_id',
        'branch_id',
        'sale_id',
        'customer_name',
        'customer_phone',
        'address',
        'notes',
        'service_name',
        'price_mode',
        'price',
        'duration_minutes',
        'scheduled_at',
        'ends_at',
        'status',
        'source',
        'google_event_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'scheduled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getOrderTotalAttribute(): float
    {
        return $this->orderTotal();
    }

    /**
     * The amount the order will charge, shown to staff before they convert.
     * Mirrors SaleService::writeItems for one unit with no discount.
     */
    public function orderTotal(): float
    {
        $price = (float) ($this->price ?? $this->product?->sale_price ?? 0);
        $tax = $this->product?->tax;

        if (! $tax || (float) $tax->rate <= 0 || $tax->is_inclusive) {
            return round($price, 4);
        }

        return round($price * (1 + ((float) $tax->rate / 100)), 4);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
