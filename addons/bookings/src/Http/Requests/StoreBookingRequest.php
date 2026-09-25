<?php

namespace Addons\Bookings\Http\Requests;

use Addons\Bookings\Models\BookingProductSetting;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'status' => ['nullable', 'in:pending,confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Choose a service.',
            'customer_name.required' => 'Enter your name.',
            'customer_phone.required' => 'Enter your phone number.',
            'customer_phone.regex' => 'Enter a valid phone number.',
            'scheduled_at.required' => 'Choose a date and time.',
            'scheduled_at.after' => 'Choose a time in the future.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $requiresAddress = BookingProductSetting::query()
                ->where('product_id', $this->input('product_id'))
                ->value('requires_address');

            if ($requiresAddress && blank($this->input('address'))) {
                $validator->errors()->add('address', 'Enter the service address.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $source): array
    {
        $data = $this->validated();
        if ($source === 'public') {
            unset($data['customer_id']);
        }

        return [
            ...$data,
            'source' => $source,
            'status' => $source === 'public' ? 'pending' : $this->input('status', 'pending'),
        ];
    }
}
