<?php

namespace App\Support;

use App\Services\SettingService;

/**
 * Company letterhead data shared by every generated PDF document
 * (quotations, reports) so they all carry an identical header.
 */
class PdfBranding
{
    /**
     * @return array{name: string, address: string, phone: string, email: string, tax_id: string, logo_src: ?string}
     */
    public static function company(): array
    {
        $settings = app(SettingService::class)->all();

        return [
            'name' => $settings['shop_name'] ?: (string) (tenant('name') ?? config('app.name')),
            'address' => (string) ($settings['address'] ?? ''),
            'phone' => (string) ($settings['phone'] ?? ''),
            'email' => (string) ($settings['email'] ?? ''),
            'tax_id' => (string) ($settings['tax_id'] ?? ''),
            'logo_src' => self::logoDataUri($settings['logo'] ?? null)
                ?? self::logoDataUri($settings['logo_print'] ?? null),
        ];
    }

    /**
     * Dompdf cannot fetch the tenant disk over HTTP, so the logo is inlined.
     */
    public static function logoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $full = storage_path('app/public/'.$path);
        if (! is_file($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($full));

        return "data:{$mime};base64,{$data}";
    }
}
