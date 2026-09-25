<?php

namespace Addons\Bookings\Http\Controllers;

use Addons\Bookings\Http\Requests\StoreBookingRequest;
use Addons\Bookings\Services\BookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function show(string $tenant_code): Response
    {
        $settings = $this->bookings->settings();
        abort_unless($settings->public_enabled, 404);

        return Inertia::render('Addons/Bookings/Public', [
            'services' => $this->bookings->bookableServices(),
            'business_hours' => $settings->business_hours,
            'tenant_code' => $tenant_code,
        ]);
    }

    public function store(StoreBookingRequest $request, string $tenant_code): RedirectResponse
    {
        if ($request->filled('website')) {
            return back()->with('status', 'Booking request received.');
        }

        $booking = $this->bookings->create($request->payload('public'));

        return redirect()->route('bookings.public.show', $tenant_code)
            ->with('status', "Booking {$booking->number} requested. The business will confirm it.");
    }
}
