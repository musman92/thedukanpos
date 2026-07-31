<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Support\ReceiptSections;
use Illuminate\Http\UploadedFile;

class SettingService
{
    /** @var list<string> */
    public const CURRENCIES = [
        'PKR', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'INR', 'AUD', 'CAD', 'CNY',
    ];

    /** @var array<string, string> */
    public const CURRENCY_SYMBOLS = [
        'PKR' => 'Rs',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'AED',
        'SAR' => 'SAR',
        'INR' => '₹',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CNY' => '¥',
    ];

    /** @var list<string> */
    public const DATE_FORMATS = ['Y-m-d', 'd-m-Y', 'm-d-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d'];

    /** @var list<string> Kept in sync with ReceiptSections::defaults() */
    public const RECEIPT_SECTION_KEYS = [
        'logo',
        'branch_name',
        'address',
        'phone',
        'tax_id',
        'invoice_title',
        'sale_number',
        'date_cashier',
        'customer_block',
        'items_header',
        'item_variants',
        'item_unit_price',
        'subtotal',
        'discount',
        'tax',
        'payment_info',
        'thank_you',
    ];

    public function __construct(
        protected ImageUploadService $images,
        protected CompanyReceiptLogoService $printLogos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'shop_name' => tenancy()->initialized ? (string) (tenant('name') ?? '') : '',
            'email' => '',
            'address' => '',
            'phone' => '',
            'tax_id' => '',
            'logo' => null,
            'logo_print' => null,
            'currency' => 'PKR',
            'currency_symbol' => 'Rs',
            'currency_position' => 'left',
            'decimal_points' => 2,
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'Y-m-d',
            'time_format' => '12',
            'week_starts_on' => 'monday',
            'list_page_limit' => 15,
            'activity_logging_enabled' => true,
            'pos_allow_credit' => true,
            'pos_show_stock' => true,
            'pos_show_product_image' => true,
            'pos_catalog_mode' => 'flat',
            'receipt_footer' => 'Thank you for shopping with us',
            'receipt_paper_width' => '80',
            'receipt_font_size' => 14,
            'receipt_show_address' => true,
            'receipt_sections' => $this->defaultReceiptSections(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function defaultReceiptSections(): array
    {
        return ReceiptSections::defaults();
    }

    /**
     * Resolved settings for the settings form / consumers.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $raw = CompanySetting::allGrouped();
        $defaults = $this->defaults();
        $out = [];

        foreach ($defaults as $key => $default) {
            if ($key === 'receipt_sections') {
                $out[$key] = $this->normalizeReceiptSections($raw['receipt_sections'] ?? null);
                continue;
            }

            if ($key === 'logo') {
                $path = $raw['logo'] ?? null;
                $out['logo'] = $path ?: null;
                $out['logo_url'] = $path ? $this->images->url($path) : null;
                continue;
            }

            if ($key === 'logo_print') {
                $printPath = $raw['logo_print'] ?? null;
                $out['logo_print'] = $printPath ?: null;
                $out['logo_print_url'] = $printPath ? $this->images->url($printPath) : null;
                continue;
            }

            if (is_bool($default)) {
                $out[$key] = array_key_exists($key, $raw) && $raw[$key] !== null
                    ? filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN)
                    : $default;
                continue;
            }

            if (is_int($default)) {
                $out[$key] = array_key_exists($key, $raw) && $raw[$key] !== null && $raw[$key] !== ''
                    ? (int) $raw[$key]
                    : $default;
                continue;
            }

            $value = $raw[$key] ?? null;
            $out[$key] = ($value !== null && $value !== '') ? $value : $default;
        }

        // Derive symbol if blank.
        if (trim((string) ($out['currency_symbol'] ?? '')) === '') {
            $out['currency_symbol'] = self::CURRENCY_SYMBOLS[$out['currency']] ?? $out['currency'];
        }

        return $out;
    }

    /**
     * Shared Inertia / POS public config.
     *
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        $all = $this->all();

        return [
            'shop_name' => $all['shop_name'],
            'list_page_limit' => (int) $all['list_page_limit'],
            'currency' => $all['currency'],
            'currency_symbol' => $all['currency_symbol'],
            'currency_position' => $all['currency_position'],
            'decimal_points' => (int) $all['decimal_points'],
            'timezone' => $all['timezone'],
            'date_format' => $all['date_format'],
            'time_format' => $all['time_format'],
            'week_starts_on' => $all['week_starts_on'],
            'pos_allow_credit' => (bool) $all['pos_allow_credit'],
            'pos_show_stock' => (bool) $all['pos_show_stock'],
            'pos_show_product_image' => (bool) $all['pos_show_product_image'],
            'pos_catalog_mode' => in_array($all['pos_catalog_mode'] ?? 'flat', ['flat', 'grouped'], true)
                ? $all['pos_catalog_mode']
                : 'flat',
            'activity_logging_enabled' => (bool) $all['activity_logging_enabled'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptBranding(?string $branchName = null): array
    {
        $all = $this->all();
        $sections = $all['receipt_sections'];

        // Legacy receipt_show_address overrides section if explicitly false in old data.
        if (! $all['receipt_show_address']) {
            $sections['address'] = false;
        }

        return [
            'shop_name' => $all['shop_name'],
            'email' => $all['email'],
            'address' => $all['address'],
            'phone' => $all['phone'],
            'tax_id' => $all['tax_id'],
            // Thermal printers need the B&W print logo; fall back to color if missing.
            'logo_url' => $all['logo_print_url'] ?? $all['logo_url'] ?? null,
            'logo_print_url' => $all['logo_print_url'] ?? null,
            'branch_name' => $branchName,
            'currency' => $all['currency'],
            'currency_symbol' => $all['currency_symbol'],
            'currency_position' => $all['currency_position'],
            'decimal_points' => (int) $all['decimal_points'],
            'receipt_footer' => $all['receipt_footer'],
            'receipt_paper_width' => (string) $all['receipt_paper_width'],
            'receipt_font_size' => (int) $all['receipt_font_size'],
            'receipt_sections' => $sections,
            'date_format' => $all['date_format'],
            'time_format' => $all['time_format'],
            'timezone' => $all['timezone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        $defaults = $this->defaults();

        if (! empty($data['remove_logo'])) {
            $this->deleteLogoFiles(
                CompanySetting::getValue('logo'),
                CompanySetting::getValue('logo_print'),
            );
            CompanySetting::setValue('logo', null);
            CompanySetting::setValue('logo_print', null);
        }

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $this->deleteLogoFiles(
                CompanySetting::getValue('logo'),
                CompanySetting::getValue('logo_print'),
            );

            $logoPath = $this->images->storeCompressed($data['logo'], 'settings');
            CompanySetting::setValue('logo', $logoPath);

            $printPath = $this->printLogos->generateFromStoredPath($logoPath);
            CompanySetting::setValue('logo_print', $printPath);
        }

        $scalarKeys = array_keys($defaults);
        foreach ($scalarKeys as $key) {
            if (in_array($key, ['logo', 'logo_print', 'receipt_sections'], true)) {
                continue;
            }
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_array($value)) {
                continue;
            }

            CompanySetting::setValue($key, $value === null ? null : (string) $value);
        }

        if (array_key_exists('receipt_sections', $data)) {
            $sections = $this->normalizeReceiptSections($data['receipt_sections']);
            CompanySetting::setValue('receipt_sections', json_encode($sections));
            // Keep legacy flag in sync.
            CompanySetting::setValue('receipt_show_address', $sections['address'] ? '1' : '0');
        }
    }

    public function seedDefaults(): void
    {
        $defaults = $this->defaults();
        $existing = CompanySetting::allGrouped();

        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $existing) && $existing[$key] !== null && $existing[$key] !== '') {
                continue;
            }

            if ($key === 'receipt_sections') {
                CompanySetting::setValue($key, json_encode($value));
                continue;
            }

            if ($key === 'logo' || $key === 'logo_print') {
                continue;
            }

            if (is_bool($value)) {
                CompanySetting::setValue($key, $value ? '1' : '0');
                continue;
            }

            CompanySetting::setValue($key, (string) $value);
        }
    }

    /**
     * Ensure a B&W print logo exists when a color logo is stored (e.g. logos uploaded before this feature).
     */
    public function ensurePrintLogo(): void
    {
        $logo = CompanySetting::getValue('logo');
        $print = CompanySetting::getValue('logo_print');

        if (! is_string($logo) || $logo === '') {
            return;
        }

        if (is_string($print) && $print !== '') {
            return;
        }

        $generated = $this->printLogos->generateFromStoredPath($logo);
        if ($generated) {
            CompanySetting::setValue('logo_print', $generated);
        }
    }

    protected function deleteLogoFiles(mixed $logo, mixed $logoPrint): void
    {
        $this->images->delete(is_string($logo) ? $logo : null);
        $this->printLogos->deletePrintLogo(is_string($logoPrint) ? $logoPrint : null);
    }

    public function activityLoggingEnabled(): bool
    {
        return (bool) $this->all()['activity_logging_enabled'];
    }

    public function allowPosCredit(): bool
    {
        return (bool) $this->all()['pos_allow_credit'];
    }

    /**
     * @return array{symbol:string,position:string,decimals:int,currency:string}
     */
    public function moneyConfig(): array
    {
        $all = $this->all();

        return [
            'currency' => (string) $all['currency'],
            'symbol' => (string) $all['currency_symbol'],
            'position' => ($all['currency_position'] ?? 'left') === 'right' ? 'right' : 'left',
            'decimals' => max(0, min(4, (int) $all['decimal_points'])),
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<string, bool>
     */
    public function normalizeReceiptSections(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        return ReceiptSections::normalize(is_array($raw) ? $raw : null);
    }
}
