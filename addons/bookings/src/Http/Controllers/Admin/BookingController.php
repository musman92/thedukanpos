<?php

namespace Addons\Bookings\Http\Controllers\Admin;

use Addons\Bookings\Http\Requests\StoreBookingRequest;
use Addons\Bookings\Http\Requests\UpdateBookingRequest;
use Addons\Bookings\Models\Booking;
use Addons\Bookings\Models\BookingProductSetting;
use Addons\Bookings\Services\BookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Addons/Bookings/Index', $this->bookings->adminIndex($request->only([
            'q', 'status', 'from', 'to', 'per_page', 'view', 'month',
        ])));
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $booking = $this->bookings->create($request->payload('staff'));

        return back()->with('status', "Booking {$booking->number} created.");
    }

    public function update(UpdateBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookings->update($booking, $request->validated());

        return back()->with('status', 'Booking updated.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->bookings->delete($booking);

        return back()->with('status', 'Booking cancelled and removed.');
    }

    public function convert(Booking $booking): RedirectResponse
    {
        $sale = $this->bookings->convertToSale($booking);

        return redirect()->route('admin.orders.show', $sale)
            ->with('status', "Order {$sale->number} created from booking.");
    }

    public function productSettings(int $product): JsonResponse
    {
        $settings = BookingProductSetting::query()->find($product);

        return response()->json($settings ?? [
            'is_bookable' => false,
            'price_mode' => 'fixed',
            'requires_address' => false,
            'duration_minutes' => null,
        ]);
    }
}
