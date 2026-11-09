<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ShopPasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopPasswordResetService
{
    /**
     * @return array{message: string}
     */
    public function sendLink(string $shopCode, string $username): array
    {
        try {
            $user = $this->openAccount($shopCode, $username);

            if (Password::broker()->getRepository()->recentlyCreatedToken($user)) {
                throw ValidationException::withMessages([
                    'username' => 'Please wait a minute before requesting another link.',
                ]);
            }

            $token = Password::broker()->createToken($user);
            $user->notify(new ShopPasswordResetNotification($token, strtolower(trim($shopCode))));

            return [
                'message' => 'We sent a reset link to '.$this->maskEmail((string) $user->email).'.',
            ];
        } finally {
            $this->endTenancy();
        }
    }

    public function reset(string $shopCode, string $username, string $token, string $password): void
    {
        try {
            $user = $this->openAccount($shopCode, $username);

            if (! Password::broker()->tokenExists($user, $token)) {
                throw ValidationException::withMessages([
                    'password' => 'This reset link is invalid or has expired.',
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            Password::broker()->deleteToken($user);

            event(new PasswordReset($user));
        } finally {
            $this->endTenancy();
        }
    }

    private function openAccount(string $shopCode, string $username): User
    {
        $shopCode = strtolower(trim($shopCode));
        $username = strtolower(trim($username));

        $tenant = Tenant::findByCode($shopCode);

        if (! $tenant) {
            throw ValidationException::withMessages([
                'shop_code' => 'Shop code not found or inactive.',
            ]);
        }

        tenancy()->initialize($tenant);

        $user = User::query()->where('username', $username)->first();

        if (! $user || ! $user->is_active || ! $user->can_login) {
            throw ValidationException::withMessages([
                'username' => 'No account uses that username in this shop.',
            ]);
        }

        $email = trim((string) $user->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'username' => 'This account has no email. Ask a manager to add one.',
            ]);
        }

        return $user;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);
        $visible = mb_substr($name, 0, 1);

        return $visible.'***@'.$domain;
    }

    private function endTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
