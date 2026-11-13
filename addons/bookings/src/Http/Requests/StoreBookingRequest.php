<?php

namespace Addons\Bookings\Http\Requests;

use Addons\Bookings\Models\BookingProductSetting;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['required', 'date'],
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

            if ($this->routeIs('bookings.public.store')
                && Branch::query()->where('is_active', true)->count() > 1
                && ! $this->filled('branch_id')) {
                $validator->errors()->add('branch_id', 'Choose a branch.');
            }

            if ($this->filled('scheduled_at') && ! $validator->errors()->has('scheduled_at')) {
                $timezone = (string) (company_settings()['timezone'] ?? tenant('timezone') ?? config('app.timezone'));
                $start = Carbon::parse($this->input('scheduled_at'), $timezone);
                if ($start->lte(Carbon::now($timezone))) {
                    $validator->errors()->add('scheduled_at', 'Choose a time in the future.');
                }
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
            unset($data['customer_id'], $data['assigned_user_id']);
        }

        return [
            ...$data,
            'source' => $source,
            'status' => $source === 'public' ? 'pending' : $this->input('status', 'pending'),
        ];
    }
}
