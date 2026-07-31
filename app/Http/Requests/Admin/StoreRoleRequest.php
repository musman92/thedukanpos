<?php

namespace App\Http\Requests\Admin;

use App\Support\AppPermissions;
use App\Support\TenantDefaultRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::notIn(TenantDefaultRoles::names()),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AppPermissions::all())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $taken = Role::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This role name is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'permissions' => array_values(array_filter(
                (array) $this->input('permissions', []),
                fn ($p) => is_string($p) && $p !== ''
            )),
        ];
    }
}
