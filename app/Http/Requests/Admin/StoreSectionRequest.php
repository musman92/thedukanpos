<?php

namespace App\Http\Requests\Admin;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSectionRequest extends FormRequest
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
            'racks' => ['nullable', 'array'],
            'racks.*.id' => ['nullable', 'integer'],
            'racks.*.name' => ['nullable', 'string', 'max:255'],
            'racks.*.code' => ['nullable', 'string', 'max:50'],
            'racks.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Section::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This section name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $exists = Section::query()
                    ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('code', 'This section code is already taken.');
                }
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, is_active: bool, racks: list<array{id: int|null, name: string, code: string|null, is_active: bool}>}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'is_active' => $this->boolean('is_active', true),
            'racks' => $this->normalizedRacks(),
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, code: string|null, is_active: bool}>
     */
    protected function normalizedRacks(): array
    {
        $rows = $this->input('racks', []);
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach (array_values($rows) as $row) {
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
                'is_active' => array_key_exists('is_active', $row)
                    ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN)
                    : true,
            ];
        }

        return $out;
    }
}
