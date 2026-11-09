<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $shopCode = $this->input('shop_code');
        $username = $this->input('username');

        $this->merge([
            'shop_code' => is_string($shopCode) ? strtolower(trim($shopCode)) : '',
            'username' => is_string($username) ? strtolower(trim($username)) : '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'shop_code' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_code.required' => 'Enter the shop code.',
            'username.required' => 'Enter the username.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }
}
