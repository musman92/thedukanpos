<?php

namespace App\Support;

class Locale
{
    public static function default(): string
    {
        return (string) config('locales.default', config('app.locale', 'en'));
    }

    /**
     * @return array<string, array{label: string, native: string}>
     */
    public static function supported(): array
    {
        /** @var array<string, array{label?: string, native?: string}> $supported */
        $supported = config('locales.supported', []);

        return collect($supported)
            ->map(fn (array $meta, string $code) => [
                'label' => (string) ($meta['label'] ?? strtoupper($code)),
                'native' => (string) ($meta['native'] ?? $meta['label'] ?? strtoupper($code)),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::supported());
    }

    public static function normalize(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '' || ! array_key_exists($locale, self::supported())) {
            return self::default();
        }

        return $locale;
    }

    /**
     * Whether the UI should render right-to-left.
     * Controlled by company setting `rtl`, not by language.
     */
    public static function isRtl(): bool
    {
        return self::resolveRtl();
    }

    public static function direction(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * Resolve locale for the current request (company setting when tenancy is on).
     */
    public static function resolve(): string
    {
        if (tenancy()->initialized) {
            try {
                $stored = company_settings()['locale'] ?? null;

                return self::normalize(is_string($stored) ? $stored : null);
            } catch (\Throwable) {
                // fall through
            }
        }

        return self::normalize(config('app.locale'));
    }

    /**
     * Resolve RTL flag for the current request (company setting when tenancy is on).
     */
    public static function resolveRtl(): bool
    {
        if (tenancy()->initialized) {
            try {
                return filter_var(company_settings()['rtl'] ?? false, FILTER_VALIDATE_BOOLEAN);
            } catch (\Throwable) {
                // fall through
            }
        }

        return false;
    }

    /**
     * Options for selects / Inertia.
     *
     * @return list<array{value: string, label: string, native: string}>
     */
    public static function options(): array
    {
        return collect(self::supported())
            ->map(fn (array $meta, string $code) => [
                'value' => $code,
                'label' => $meta['label'],
                'native' => $meta['native'],
            ])
            ->values()
            ->all();
    }
}
