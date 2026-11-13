<?php

namespace Addons\Bookings\Mail;

use Addons\Bookings\Models\Booking;
use Illuminate\Support\Facades\Storage;

final class BookingMailData
{
    public function __construct(
        public string $shop,
        public string $number,
        public string $service,
        public string $when,
        public string $customer,
        public string $phone,
        public ?string $branch,
        public ?string $address,
        public ?string $notes,
        public ?string $shopLogoPath,
        public string $posLogoPath,
        public ?string $shopPhone,
        public ?string $shopEmail,
    ) {}

    public static function from(Booking $booking): self
    {
        $booking->loadMissing(['branch']);
        $settings = company_settings();
        $timezone = (string) ($settings['timezone'] ?? tenant('timezone') ?? config('app.timezone'));
        $when = $booking->scheduled_at
            ? $booking->scheduled_at->copy()->timezone($timezone)->format('D, j M Y, g:i A')
            : '';

        return new self(
            shop: (string) ($settings['shop_name'] ?: tenant('name') ?: 'Shop'),
            number: (string) $booking->number,
            service: (string) ($booking->service_name ?: 'Service'),
            when: $when,
            customer: (string) $booking->customer_name,
            phone: (string) $booking->customer_phone,
            branch: filled($booking->branch?->name) ? (string) $booking->branch->name : null,
            address: filled($booking->address) ? (string) $booking->address : null,
            notes: filled($booking->notes) ? (string) $booking->notes : null,
            shopLogoPath: self::shopLogoPath(),
            posLogoPath: public_path('brand/logo_horizontal.png'),
            shopPhone: filled($settings['phone'] ?? null) ? trim((string) $settings['phone']) : null,
            shopEmail: filled($settings['email'] ?? null) ? trim((string) $settings['email']) : null,
        );
    }

    private static function shopLogoPath(): ?string
    {
        $path = company_settings()['logo'] ?? null;
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }
}
