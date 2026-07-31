<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'default_tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Category::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This category name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $exists = Category::query()
                    ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('code', 'This category code is already taken.');
                }
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, parent_id: int|null, default_tax_id: int|null, is_active: bool}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'default_tax_id' => $this->filled('default_tax_id') ? (int) $this->input('default_tax_id') : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
