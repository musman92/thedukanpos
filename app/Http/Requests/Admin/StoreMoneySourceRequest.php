<?php

namespace App\Http\Requests\Admin;

use App\Models\MoneySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMoneySourceRequest extends FormRequest
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
            'type' => ['required', Rule::in(MoneySource::TYPES)],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'exclude_from_dashboard_profit' => ['sometimes', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $taken = MoneySource::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This money source name is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, type: string, opening_balance: float, is_active: bool, exclude_from_dashboard_profit: bool, branch_ids: list<int>|null}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'type' => strtoupper((string) $this->input('type')),
            'opening_balance' => (float) $this->input('opening_balance', 0),
            'is_active' => $this->boolean('is_active', true),
            'exclude_from_dashboard_profit' => $this->boolean('exclude_from_dashboard_profit'),
            'branch_ids' => $this->filled('branch_ids')
                ? array_map('intval', (array) $this->input('branch_ids'))
                : null,
        ];
    }
}
