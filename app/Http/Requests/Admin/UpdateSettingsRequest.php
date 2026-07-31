<?php

namespace App\Http\Requests\Admin;

use App\Services\ImageUploadService;
use App\Services\SettingService;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:'.ImageUploadService::MAX_UPLOAD_KB,
            ],
            'remove_logo' => ['sometimes', 'boolean'],
            'currency' => ['nullable', 'string', Rule::in(SettingService::CURRENCIES)],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'currency_position' => ['nullable', 'in:left,right'],
            'decimal_points' => ['nullable', 'integer', 'min:0', 'max:4'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', Rule::in(Locale::keys())],
            'rtl' => ['sometimes', 'boolean'],
            'date_format' => ['nullable', 'string', Rule::in(SettingService::DATE_FORMATS)],
            'time_format' => ['nullable', 'in:12,24'],
            'week_starts_on' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'list_page_limit' => ['nullable', 'integer', 'in:10,15,25,50'],
            'activity_logging_enabled' => ['sometimes', 'boolean'],
            'pos_allow_credit' => ['sometimes', 'boolean'],
            'pos_show_stock' => ['sometimes', 'boolean'],
            'pos_show_product_image' => ['sometimes', 'boolean'],
            'pos_catalog_mode' => ['sometimes', 'string', Rule::in(['flat', 'grouped'])],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'receipt_paper_width' => ['nullable', 'in:58,80'],
            'receipt_font_size' => ['nullable', 'integer', 'min:10', 'max:20'],
            'receipt_show_address' => ['sometimes', 'boolean'],
            'receipt_sections' => ['nullable', 'array'],
            ...collect(SettingService::RECEIPT_SECTION_KEYS)
                ->mapWithKeys(fn (string $key) => ["receipt_sections.{$key}" => ['sometimes', 'boolean']])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [
            'shop_name' => $this->input('shop_name'),
            'email' => $this->input('email'),
            'address' => $this->input('address'),
            'phone' => $this->input('phone'),
            'tax_id' => $this->input('tax_id'),
            'logo' => $this->file('logo'),
            'remove_logo' => $this->boolean('remove_logo'),
            'currency' => $this->input('currency'),
            'currency_symbol' => $this->input('currency_symbol'),
            'currency_position' => $this->input('currency_position'),
            'decimal_points' => $this->input('decimal_points'),
            'timezone' => $this->input('timezone'),
            'locale' => $this->input('locale'),
            'rtl' => $this->boolean('rtl'),
            'date_format' => $this->input('date_format'),
            'time_format' => $this->input('time_format'),
            'week_starts_on' => $this->input('week_starts_on'),
            'list_page_limit' => $this->input('list_page_limit'),
            'activity_logging_enabled' => $this->boolean('activity_logging_enabled'),
            'pos_allow_credit' => $this->boolean('pos_allow_credit'),
            'pos_show_stock' => $this->boolean('pos_show_stock'),
            'pos_show_product_image' => $this->boolean('pos_show_product_image'),
            'pos_catalog_mode' => $this->input('pos_catalog_mode', 'flat'),
            'receipt_footer' => $this->input('receipt_footer'),
            'receipt_paper_width' => $this->input('receipt_paper_width'),
            'receipt_font_size' => $this->input('receipt_font_size'),
            'receipt_show_address' => $this->boolean('receipt_show_address'),
        ];

        if ($this->has('receipt_sections')) {
            $normalized = [];
            foreach (SettingService::RECEIPT_SECTION_KEYS as $key) {
                $normalized[$key] = $this->boolean("receipt_sections.{$key}");
            }
            $payload['receipt_sections'] = $normalized;
        }

        return $payload;
    }
}
