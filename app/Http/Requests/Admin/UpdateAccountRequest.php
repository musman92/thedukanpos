<?php

namespace App\Http\Requests\Admin;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
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
        /** @var Account $account */
        $account = $this->route('account');

        if ($account->is_system) {
            return [
                'is_active' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Account $account */
            $account = $this->route('account');

            if ($account->is_system) {
                return;
            }

            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $taken = Account::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->where('id', '!=', $account->id)
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This account name is already taken.');
            }
        });
    }

    /**
     * @return array{name?: string, type?: string, is_active: bool}
     */
    public function payload(): array
    {
        /** @var Account $account */
        $account = $this->route('account');

        if ($account->is_system) {
            return [
                'is_active' => $this->boolean('is_active'),
            ];
        }

        return [
            'name' => trim((string) $this->input('name')),
            'type' => (string) $this->input('type'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
