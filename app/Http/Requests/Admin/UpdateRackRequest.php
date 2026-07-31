<?php

namespace App\Http\Requests\Admin;

use App\Models\Rack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRackRequest extends FormRequest
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
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Rack $rack */
            $rack = $this->route('rack');
            $sectionId = (int) $this->input('section_id', $rack->section_id);
            if (! $sectionId) {
                return;
            }

            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Rack::query()
                    ->where('section_id', $sectionId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->where('id', '!=', $rack->id)
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This rack name is already taken in this section.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code !== '') {
                $exists = Rack::query()
                    ->where('section_id', $sectionId)
                    ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                    ->where('id', '!=', $rack->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('code', 'This rack code is already taken in this section.');
                }
            }
        });
    }

    /**
     * @return array{section_id: int, name: string, code: string|null, is_active: bool}
     */
    public function payload(): array
    {
        return [
            'section_id' => (int) $this->input('section_id'),
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
