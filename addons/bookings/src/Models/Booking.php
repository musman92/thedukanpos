<?php

namespace Addons\Bookings\Models;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'done', 'cancelled'];

    protected $fillable = [
        'number',
        'product_id',
        'customer_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
