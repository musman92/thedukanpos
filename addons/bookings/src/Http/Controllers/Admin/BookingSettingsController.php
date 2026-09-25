<?php

namespace Addons\Bookings\Http\Controllers\Admin;

use Addons\Bookings\Http\Requests\UpdateBookingSettingsRequest;
use Addons\Bookings\Models\BookingSetting;
use Addons\Bookings\Services\BookingService;
use Addons\Bookings\Services\GoogleCalendarService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BookingSettingsController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected GoogleCalendarService $google,
    ) {}

    public function edit(): Response
    {
        $settings = $this->bookings->settings();

        return Inertia::render('Addons/Bookings/Settings', [
            'settings' => $settings,
            'public_url' => route('bookings.public.show', tenant('code')),
            'google' => [
                'configured' => $this->google->configured(),
                'connected' => $this->google->connected($settings),
            ],
        ]);
    }

    public function update(UpdateBookingSettingsRequest $request): RedirectResponse
    {
        $this->bookings->updateSettings($request->payload());

        return back()->with('status', 'Booking settings saved.');
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->google->configured(), 404);
        $state = Str::random(40);
        $request->session()->put('bookings.google_state', $state);

        return redirect()->away($this->google->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('bookings.google_state');
        $providedState = $request->input('state');
        abort_unless(
            is_string($expectedState)
                && $expectedState !== ''
                && is_string($providedState)
                && hash_equals($expectedState, $providedState),
            403,
        );
        $request->validate(['code' => ['required', 'string']]);
        $this->google->connect((string) $request->input('code'), BookingSetting::current());

        return redirect()->route('admin.bookings.settings.edit')
            ->with('status', 'Google Calendar connected.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnect(BookingSetting::current());

        return back()->with('status', 'Google Calendar disconnected.');
    }
}
