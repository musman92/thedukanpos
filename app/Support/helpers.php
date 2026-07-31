<?php

use App\Services\SettingService;
use App\Support\PageLimit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('company_page_limit')) {
    function company_page_limit(): int
    {
        return PageLimit::companyDefault();
    }
}

if (! function_exists('resolve_page_limit')) {
    function resolve_page_limit(mixed $requested = null, ?int $fallback = null): int
    {
        return PageLimit::resolve($requested, $fallback);
    }
}

if (! function_exists('company_settings')) {
    /**
     * @return array<string, mixed>
     */
    function company_settings(): array
    {
        return once(fn () => app(SettingService::class)->all());
    }
}

if (! function_exists('format_money')) {
    function format_money(float|int|string|null $amount, ?int $decimals = null): string
    {
        $config = once(fn () => app(SettingService::class)->moneyConfig());
        $value = (float) ($amount ?? 0);
        $dp = $decimals ?? $config['decimals'];
        $formatted = number_format($value, $dp, '.', ',');
        $symbol = trim((string) $config['symbol']) !== ''
            ? (string) $config['symbol']
            : (string) $config['currency'];

        if ($config['position'] === 'right') {
            return $formatted.' '.$symbol;
        }

        return $symbol.' '.$formatted;
    }
}

if (! function_exists('format_company_date')) {
    function format_company_date(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $settings = company_settings();
        $tz = (string) ($settings['timezone'] ?? config('app.timezone', 'UTC'));
        $format = (string) ($settings['date_format'] ?? 'Y-m-d');
        $carbon = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $carbon->timezone($tz)->format($format);
    }
}

if (! function_exists('format_company_datetime')) {
    function format_company_datetime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $settings = company_settings();
        $tz = (string) ($settings['timezone'] ?? config('app.timezone', 'UTC'));
        $dateFormat = (string) ($settings['date_format'] ?? 'Y-m-d');
        $timeFormat = (($settings['time_format'] ?? '12') === '24') ? 'H:i' : 'g:i A';
        $carbon = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $carbon->timezone($tz)->format("{$dateFormat} {$timeFormat}");
    }
}
