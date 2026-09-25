<?php

namespace Addons\Bookings\Http\Controllers\Api\V1;

use Addons\Bookings\Http\Requests\StoreBookingRequest;
use Addons\Bookings\Http\Requests\UpdateBookingRequest;
use Addons\Bookings\Http\Resources\BookingResource;
use Addons\Bookings\Models\Booking;
use Addons\Bookings\Services\BookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->bookings->paginate($request->only(['q', 'status', 'from', 'to', 'per_page']));

        return BookingResource::collection($result['bookings'])->additional([
            'filters' => $result['filters'],
            'services' => $result['services'],
        ]);
    }

    public function store(StoreBookingRequest $request): BookingResource
    {
        return new BookingResource($this->bookings->create($request->payload('staff')));
    }

    public function show(Booking $booking): BookingResource
    {
        return new BookingResource($booking->load(['product', 'customer', 'sale']));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookings->update($booking, $request->validated()));
    }

    public function destroy(Booking $booking): Response
    {
        $this->bookings->delete($booking);

        return response()->noContent();
    }
}
