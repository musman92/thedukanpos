<?php

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            // Web: login = "admin@shop1"
            // API: login OR (username + tenant_code)
            'login' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'tenant_code' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        [$username, $tenantCode] = $this->resolveCredentials();

        $tenant = Tenant::findByCode($tenantCode);

        if (! $tenant) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('Shop code not found or inactive.'),
            ]);
        }

        tenancy()->initialize($tenant);

        if (! Auth::attempt(
            ['username' => $username, 'password' => $this->input('password'), 'is_active' => true, 'can_login' => true],
            $this->boolean('remember'),
        )) {
            tenancy()->end();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($this->hasSession()) {
            $this->session()->regenerate();
            // Persist tenant before anything else reads Auth::user() on the next request.
            $this->session()->put('tenant_id', $tenant->id);
            $this->session()->put('tenant_code', $tenant->code);
        }
    }

    /**
     * @return array{0: string, 1: string} [username, tenant_code]
     */
    protected function resolveCredentials(): array
    {
        $login = trim((string) $this->input('login', ''));

        if ($login !== '') {
            if (! str_contains($login, '@')) {
                throw ValidationException::withMessages([
                    'login' => __('Use username@shopcode (e.g. admin@shop1).'),
                ]);
            }

            [$username, $tenantCode] = explode('@', strtolower($login), 2);
            $username = trim($username);
            $tenantCode = trim($tenantCode);

            if ($username === '' || $tenantCode === '') {
                throw ValidationException::withMessages([
                    'login' => __('Use username@shopcode (e.g. admin@shop1).'),
                ]);
            }

            return [$username, $tenantCode];
        }

        $username = strtolower(trim((string) $this->input('username', '')));
        $tenantCode = strtolower(trim((string) $this->input('tenant_code', '')));

        if ($username === '' || $tenantCode === '') {
            throw ValidationException::withMessages([
                'login' => __('Use username@shopcode (e.g. admin@shop1).'),
            ]);
        }

        return [$username, $tenantCode];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $identity = (string) ($this->input('login')
            ?: ($this->string('username').'@'.$this->string('tenant_code')));

        return Str::transliterate(Str::lower($identity.'|'.$this->ip()));
    }
}
