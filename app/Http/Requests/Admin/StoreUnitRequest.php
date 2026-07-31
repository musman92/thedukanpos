<?php

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUnitRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Unit::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This unit name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $exists = Unit::query()
                    ->whereRaw('LOWER(code) = ?', [strtolower($code)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('code', 'This unit code is already taken.');
                }
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, is_active: bool}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
