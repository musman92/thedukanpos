<?php

namespace App\Http\Requests\Admin;

use App\Models\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaxRequest extends FormRequest
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
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $taken = Tax::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('name', 'This tax name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $taken = Tax::query()
                    ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('code', 'This tax code is already taken.');
                }
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, rate: float, is_inclusive: bool, is_active: bool}
     */
    public function payload(): array
    {
        $code = trim((string) $this->input('code', ''));

        return [
            'name' => trim((string) $this->input('name')),
            'code' => $code !== '' ? $code : null,
            'rate' => (float) $this->input('rate'),
            'is_inclusive' => $this->boolean('is_inclusive', false),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
