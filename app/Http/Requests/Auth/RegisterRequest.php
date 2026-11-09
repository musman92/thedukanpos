<?php

namespace App\Http\Requests\Auth;

use App\Services\ShopRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');
        $code = $this->input('shop_code');

        $this->merge([
            'name' => is_string($name) ? trim($name) : '',
            'email' => is_string($email) ? trim($email) : '',
            'shop_code' => is_string($code) ? strtolower(trim($code)) : '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'shop_code' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:'.ShopRegistrationService::SHOP_CODE_PATTERN,
                Rule::unique('tenants', 'code'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_code.unique' => 'This shop code is already taken.',
            'shop_code.regex' => 'Use lowercase letters, numbers, and hyphens only.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }
}
