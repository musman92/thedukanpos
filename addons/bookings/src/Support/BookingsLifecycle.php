<?php

namespace Addons\Bookings\Support;

use Addons\Bookings\Models\Booking;
use Addons\Bookings\Services\GoogleCalendarService;
use App\Contracts\AddonLifecycle;
use Illuminate\Support\Facades\Schema;

class BookingsLifecycle implements AddonLifecycle
{
    public function __construct(protected GoogleCalendarService $google) {}

    public function afterInstall(): void
    {
        //
    }

    public function beforeRemove(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Booking::query()
            ->whereNotNull('google_event_id')
            ->orderBy('id')
            ->each(function (Booking $booking) {
                try {
                    $booking->status = 'cancelled';
                    $this->google->sync($booking);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
