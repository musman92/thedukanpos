<?php

namespace Addons\Bookings\Listeners;

use Addons\Bookings\Models\Booking;
use Addons\Bookings\Services\BookingMailService;
use App\Events\SaleCompleted;
use App\Support\TenantAddons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarkBookingDone
{
    public function __construct(private BookingMailService $mail) {}

    public function handle(SaleCompleted $event): void
    {
        if (! TenantAddons::has('bookings') || ! Schema::hasTable('bookings')) {
            return;
        }

        Booking::query()
            ->with(['customer', 'branch'])
            ->where('sale_id', $event->sale->id)
            ->whereNotIn('status', ['cancelled', 'done'])
            ->orderBy('id')
            ->each(function (Booking $booking) {
                $booking->update(['status' => 'done']);
                $finished = $booking->fresh(['customer', 'branch']);
                DB::afterCommit(fn () => $this->mail->notifyCompleted($finished));
            });
    }
}
