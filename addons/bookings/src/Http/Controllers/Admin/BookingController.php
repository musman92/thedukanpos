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
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Addons/Bookings/Index', [
            ...$this->bookings->adminIndex($request->only([
                'q', 'status', 'from', 'to', 'per_page', 'view', 'month', 'assignee',
            ])),
            'can' => [
                'manage' => $request->user()->can('bookings.manage'),
            ],
        ]);
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
        try {
            $sale = $this->bookings->openOrder($booking);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.index', ['edit' => $sale->id]);
    }

    public function productSettings(int $product): JsonResponse
    {
        // The product form reads these fields so it can show and save them.
        // Anyone who can view bookings or edit products needs them; nobody else does.
        abort_unless(
            Gate::any(['bookings.view', 'products.store', 'products.update']),
            403,
        );

        $settings = BookingProductSetting::query()->find($product);

        return response()->json($settings ?? [
            'is_bookable' => false,
            'price_mode' => 'fixed',
            'requires_address' => false,
            'duration_minutes' => null,
        ]);
    }
}
