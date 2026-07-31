<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
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
        $canLogin = $this->boolean('can_login', true);
        $isEmployee = $this->boolean('is_employee', false);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'can_login' => ['sometimes', 'boolean'],
            'username' => [$canLogin ? 'required' : 'nullable', 'string', 'max:100'],
            'password' => [$canLogin ? 'required' : 'nullable', 'string', 'min:6'],
            'role' => [$canLogin ? 'required' : 'nullable', 'string', Rule::exists('roles', 'name')],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_employee' => ['sometimes', 'boolean'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'in:active,suspended,resigned,terminated'],
            'pay_frequency' => ['nullable', 'in:daily,weekly,fortnight,monthly'],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'employee_branch_id' => ['nullable', 'exists:branches,id'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'employee_phone' => ['nullable', 'string', 'max:50'],
        ] + ($isEmployee ? [] : []);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('can_login', true) && ! $this->boolean('is_employee', false)) {
                $validator->errors()->add(
                    'is_employee',
                    'User without login must be marked as an employee (HR record).',
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'is_active' => $this->boolean('is_active', true),
            'can_login' => $this->boolean('can_login', true),
            'username' => $this->input('username'),
            'password' => $this->input('password'),
            'role' => $this->input('role'),
            'branch_id' => $this->input('branch_id') ?: null,
            'is_employee' => $this->boolean('is_employee', false),
            'employee_number' => $this->input('employee_number'),
            'designation' => $this->input('designation'),
            'department' => $this->input('department'),
            'hire_date' => $this->input('hire_date'),
            'employment_status' => $this->input('employment_status', 'active'),
            'pay_frequency' => $this->input('pay_frequency', 'monthly'),
            'pay_rate' => $this->input('pay_rate'),
            'employee_branch_id' => $this->input('employee_branch_id') ?: null,
            'address' => $this->input('address'),
            'notes' => $this->input('notes'),
            'employee_phone' => $this->input('employee_phone'),
        ];
    }
}
