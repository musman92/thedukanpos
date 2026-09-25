<?php

namespace Addons\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_concurrent' => ['required', 'integer', 'min:0', 'max:100'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'public_enabled' => ['sometimes', 'boolean'],
            'allow_staff_overbook' => ['sometimes', 'boolean'],
            'sales_ui' => ['required', 'in:orders,pos,both'],
            'business_hours' => ['required', 'array'],
            'business_hours.*.enabled' => ['required', 'boolean'],
            'business_hours.*.open' => ['required', 'date_format:H:i'],
            'business_hours.*.close' => ['required', 'date_format:H:i', 'after:business_hours.*.open'],
            'google_calendar_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function payload(): array
    {
        return [
            ...$this->validated(),
            'public_enabled' => $this->boolean('public_enabled'),
            'allow_staff_overbook' => $this->boolean('allow_staff_overbook'),
        ];
    }
}
