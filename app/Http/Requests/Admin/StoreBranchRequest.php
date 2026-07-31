<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBranchRequest extends FormRequest
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
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Branch::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This branch name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code === '') {
                return;
            }

            $exists = Branch::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', 'This branch code is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, phone: string|null, address: string|null, is_active: bool}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'phone' => $this->input('phone'),
            'address' => $this->input('address'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
