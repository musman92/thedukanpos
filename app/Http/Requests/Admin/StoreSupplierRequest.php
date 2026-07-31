<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSupplierRequest extends FormRequest
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
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Supplier::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This supplier name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code === '') {
                return;
            }

            $exists = Supplier::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', 'This supplier code is already taken.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'contact_person' => $this->input('contact_person'),
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
            'address' => $this->input('address'),
            'notes' => $this->input('notes'),
            'opening_balance' => (float) $this->input('opening_balance', 0),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
