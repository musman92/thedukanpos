<?php

namespace Addons\Bookings\Services;

use Addons\Bookings\Models\Booking;
use Addons\Bookings\Models\BookingSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleCalendarService
{
    public function configured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'));
    }

    public function connected(?BookingSetting $settings = null): bool
    {
        $settings ??= BookingSetting::current();

        return $this->configured()
            && filled($settings->google_calendar_id)
            && (filled($settings->google_access_token) || filled($settings->google_refresh_token));
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(string $code, BookingSetting $settings): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        $settings->update([
            'google_access_token' => $response['access_token'],
            'google_refresh_token' => $response['refresh_token'] ?? $settings->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 3600) - 60),
            'google_calendar_id' => $settings->google_calendar_id ?: 'primary',
        ]);
    }

    public function disconnect(BookingSetting $settings): void
    {
        $settings->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_calendar_id' => null,
        ]);
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    public function busyPeriods(Carbon $start, Carbon $end, bool $failClosed = true): array
    {
        $settings = BookingSetting::current();
        if (! $this->connected($settings)) {
            return [];
        }

        try {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/'
                .rawurlencode($settings->google_calendar_id).'/events';
            $token = $this->accessToken($settings);
            $events = collect();
            $pageToken = null;

            do {
                $response = Http::withToken($token)->get($url, array_filter([
                    'timeMin' => $start->toIso8601String(),
                    'timeMax' => $end->toIso8601String(),
                    'singleEvents' => 'true',
                    'showDeleted' => 'false',
                    'maxResults' => 2500,
                    'pageToken' => $pageToken,
                ]))->throw()->json();
                $events->push(...($response['items'] ?? []));
                $pageToken = $response['nextPageToken'] ?? null;
            } while ($pageToken);

            $localIds = Booking::query()
                ->whereNotNull('google_event_id')
                ->where('scheduled_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->pluck('google_event_id')
                ->all();

            return $events
                ->reject(fn (array $event) => in_array($event['id'] ?? null, $localIds, true))
                ->reject(fn (array $event) => ($event['transparency'] ?? null) === 'transparent')
                ->map(function (array $event) {
                    $eventStart = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
                    $eventEnd = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;

                    return $eventStart && $eventEnd
                        ? ['start' => Carbon::parse($eventStart), 'end' => Carbon::parse($eventEnd)]
                        : null;
                })
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            if ($failClosed) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'Google Calendar availability could not be checked. Please try again.',
                ]);
            }

            report($e);

            return [];
        }
    }

    public function sync(Booking $booking): void
    {
        $settings = BookingSetting::current();
        if (! $this->connected($settings)) {
            return;
        }

        if ($booking->status === 'cancelled') {
            $this->delete($booking, $settings);

            return;
        }

        $payload = [
            'summary' => $booking->service_name.' — '.$booking->customer_name,
            'description' => trim(implode("\n", array_filter([
                $booking->customer_phone,
                $booking->address,
                $booking->notes,
                'DukanPOS booking '.$booking->number,
            ]))),
            'start' => ['dateTime' => $booking->scheduled_at->toIso8601String()],
            'end' => ['dateTime' => $booking->ends_at->toIso8601String()],
        ];

        $base = 'https://www.googleapis.com/calendar/v3/calendars/'
            .rawurlencode($settings->google_calendar_id).'/events';
        $request = Http::withToken($this->accessToken($settings));

        $response = $booking->google_event_id
            ? $request->put($base.'/'.rawurlencode($booking->google_event_id), $payload)
            : $request->post($base, $payload);

        $data = $response->throw()->json();
        if (! $booking->google_event_id && ! empty($data['id'])) {
            $booking->updateQuietly(['google_event_id' => $data['id']]);
        }
    }

    private function delete(Booking $booking, BookingSetting $settings): void
    {
        if (! $booking->google_event_id) {
            return;
        }

        Http::withToken($this->accessToken($settings))
            ->delete(
                'https://www.googleapis.com/calendar/v3/calendars/'
                .rawurlencode($settings->google_calendar_id)
                .'/events/'.rawurlencode($booking->google_event_id),
            )
            ->throw();

        $booking->updateQuietly(['google_event_id' => null]);
    }

    private function accessToken(BookingSetting $settings): string
    {
        if ($settings->google_access_token && $settings->google_token_expires_at?->isFuture()) {
            return $settings->google_access_token;
        }

        if (! $settings->google_refresh_token) {
            throw new \RuntimeException('Google Calendar must be reconnected.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $settings->google_refresh_token,
        ])->throw()->json();

        $settings->update([
            'google_access_token' => $response['access_token'],
            'google_token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 3600) - 60),
        ]);

        return $response['access_token'];
    }

    private function redirectUri(): string
    {
        return config('services.google_calendar.redirect')
            ?: route('admin.bookings.google.callback');
    }
}
