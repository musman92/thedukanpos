<?php

namespace App\Http\Requests\Admin;

use App\Models\Variation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVariationRequest extends FormRequest
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
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['nullable', 'string', 'max:255'],
            'options.*.code' => ['nullable', 'string', 'max:50'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'options.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Variation::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This variation name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $exists = Variation::query()
                    ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('code', 'This variation code is already taken.');
                }
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, sort_order: int, is_active: bool, options: list<array{id: int|null, name: string, code: string|null, sort_order: int, is_active: bool}>}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->boolean('is_active', true),
            'options' => $this->normalizedOptions(),
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, code: string|null, sort_order: int, is_active: bool}>
     */
    protected function normalizedOptions(): array
    {
        $rows = $this->input('options', []);
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'name' => $name,
                'code' => $row['code'] ?? null,
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
                'is_active' => array_key_exists('is_active', $row)
                    ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN)
                    : true,
            ];
        }

        return $out;
    }
}
