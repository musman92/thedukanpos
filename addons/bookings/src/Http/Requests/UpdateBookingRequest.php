<?php

namespace Addons\Bookings\Http\Requests;

use Addons\Bookings\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(Booking::STATUSES)],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
