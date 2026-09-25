<?php

namespace Addons\Bookings\Listeners;

use Addons\Bookings\Models\BookingProductSetting;
use App\Events\ProductSaved;
use App\Support\TenantAddons;
use Illuminate\Support\Facades\Schema;

class SaveBookingProductSettings
{
    public function handle(ProductSaved $event): void
    {
        if (! TenantAddons::has('bookings') || ! Schema::hasTable('booking_product_settings')) {
            return;
        }

        $data = $event->addonData['bookings'] ?? null;
        if (! is_array($data)) {
            return;
        }

        $priceMode = ($data['price_mode'] ?? null) === 'starting_from' ? 'starting_from' : 'fixed';
        $duration = filled($data['duration_minutes'] ?? null)
            ? max(5, min(1440, (int) $data['duration_minutes']))
            : null;

        BookingProductSetting::query()->updateOrCreate(
            ['product_id' => $event->product->id],
            [
                'is_bookable' => $event->product->kind === 'service' && (bool) ($data['is_bookable'] ?? false),
                'price_mode' => $priceMode,
                'requires_address' => (bool) ($data['requires_address'] ?? false),
                'duration_minutes' => $duration,
            ],
        );
    }
}
