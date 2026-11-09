<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\Locale;
use App\Support\ReceiptSections;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settings) {}

    public function show(): JsonResponse
    {
        $this->settings->ensurePrintLogo();

        return response()->json([
            'settings' => $this->settings->all(),
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->payload());

        return response()->json([
            'message' => __('settings.saved'),
            'settings' => $this->settings->all(),
            'options' => $this->options(),
        ]);
    }

    /**
     * Same choices the website settings form uses.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'currencies' => SettingService::CURRENCIES,
            'date_formats' => SettingService::DATE_FORMATS,
            'locales' => Locale::options(),
            'receipt_section_labels' => ReceiptSections::labels(),
            'receipt_section_groups' => ReceiptSections::groups(),
            'timezones' => timezone_identifiers_list(),
        ];
    }
}
