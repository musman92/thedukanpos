<?php

namespace App\Http\Requests\Admin;

use App\Models\MoneySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMoneySourceRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'exclude_from_dashboard_profit' => ['sometimes', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var MoneySource $moneySource */
            $moneySource = $this->route('moneySource');

            if ($moneySource->is_system) {
                $validator->errors()->add('money_source', 'System money sources cannot be edited.');

                return;
            }

            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $taken = MoneySource::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->where('id', '!=', $moneySource->id)
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This money source name is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, type: string, is_active: bool, exclude_from_dashboard_profit: bool, branch_ids: list<int>}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'type' => strtoupper((string) $this->input('type')),
            'is_active' => $this->boolean('is_active'),
            'exclude_from_dashboard_profit' => $this->boolean('exclude_from_dashboard_profit'),
            'branch_ids' => array_map('intval', (array) $this->input('branch_ids', [])),
        ];
    }
}
