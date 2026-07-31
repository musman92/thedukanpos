<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\Locale;
use App\Support\ReceiptSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settings) {}

    public function edit(Request $request): Response
    {
        $this->settings->ensurePrintLogo();

        $section = $request->input('section', 'general');

        return Inertia::render('Admin/Settings/Edit', [
            'section' => $section,
            'settings' => $this->settings->all(),
            'options' => [
                'currencies' => SettingService::CURRENCIES,
                'date_formats' => SettingService::DATE_FORMATS,
                'locales' => Locale::options(),
                'receipt_section_keys' => SettingService::RECEIPT_SECTION_KEYS,
                'receipt_section_labels' => ReceiptSections::labels(),
                'receipt_section_groups' => ReceiptSections::groups(),
                'timezones' => timezone_identifiers_list(),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->payload());

        return back()->with('status', __('settings.saved'));
    }
}
