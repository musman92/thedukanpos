<?php

namespace Tests\Feature;

use App\Services\ReportPdfService;
use App\Services\SettingService;
use Illuminate\Support\Once;
use Tests\TestCase;

class ReportPdfServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // format_amount()/company_settings() memoise per request via once().
        Once::flush();
    }

    protected function fakeSettings(int $decimals): void
    {
        $settings = [
            'shop_name' => 'Dukan Traders',
            'address' => '12 Mall Road, Lahore',
            'phone' => '0300-1234567',
            'email' => 'hello@dukan.test',
            'tax_id' => '1234567-8',
            'logo' => null,
            'logo_print' => null,
            'currency' => 'PKR',
            'currency_symbol' => 'Rs',
            'currency_position' => 'left',
            'decimal_points' => $decimals,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd-m-Y',
            'time_format' => '12',
        ];

        $this->mock(SettingService::class, function ($mock) use ($settings, $decimals) {
            $mock->shouldReceive('all')->andReturn($settings);
            $mock->shouldReceive('moneyConfig')->andReturn([
                'currency' => 'PKR',
                'symbol' => 'Rs',
                'position' => 'left',
                'decimals' => $decimals,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function document(): array
    {
        return [
            'key' => 'daily-sales',
            'title' => 'Daily Sales',
            'meta' => [
                ['label' => 'Branch', 'value' => 'Main'],
                ['label' => 'Period', 'value' => '01-08-2026 — 15-08-2026'],
            ],
            'columns' => [
                ['key' => 'day', 'label' => 'Date'],
                ['key' => 'count', 'label' => 'Sales', 'format' => 'int'],
                ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty'],
                ['key' => 'total', 'label' => 'Total', 'format' => 'money'],
            ],
            'rows' => [
                ['day' => '01-08-2026', 'count' => 12, 'qty' => 3.5, 'total' => 1234.5678],
                ['day' => '02-08-2026', 'count' => 7, 'qty' => 10, 'total' => 890.1],
            ],
            'summary' => [
                ['label' => 'Total', 'value' => 2124.6678, 'format' => 'money'],
            ],
            'totals' => ['total' => 2124.6678],
        ];
    }

    public function test_it_renders_a_downloadable_pdf(): void
    {
        $this->fakeSettings(2);

        $response = app(ReportPdfService::class)->download($this->document());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('daily-sales', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_money_columns_follow_the_configured_decimal_points(): void
    {
        $this->fakeSettings(3);

        $service = app(ReportPdfService::class);

        $this->assertSame('1,234.568', $service->formatValue(1234.5678, ReportPdfService::FORMAT_MONEY));
    }

    public function test_zero_decimal_setting_is_respected(): void
    {
        $this->fakeSettings(0);

        $service = app(ReportPdfService::class);

        $this->assertSame('1,235', $service->formatValue(1234.5678, ReportPdfService::FORMAT_MONEY));
    }

    public function test_quantities_drop_trailing_zeros_and_ignore_money_decimals(): void
    {
        $this->fakeSettings(0);

        $service = app(ReportPdfService::class);

        $this->assertSame('10', $service->formatValue(10.0, ReportPdfService::FORMAT_QTY));
        $this->assertSame('3.5', $service->formatValue(3.5, ReportPdfService::FORMAT_QTY));
        $this->assertSame('1,200', $service->formatValue(1200, ReportPdfService::FORMAT_INT));
    }
}
