<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Customer::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This customer name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code === '') {
                return;
            }

            $exists = Customer::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', 'This customer code is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, phone: string|null, email: string|null, address: string|null, opening_balance: float, is_active: bool}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
            'address' => $this->input('address'),
            'opening_balance' => (float) $this->input('opening_balance', 0),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
