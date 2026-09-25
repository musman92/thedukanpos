<?php

namespace Addons\Bookings\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingProductSetting extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'is_bookable',
        'price_mode',
        'requires_address',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_bookable' => 'boolean',
            'requires_address' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
