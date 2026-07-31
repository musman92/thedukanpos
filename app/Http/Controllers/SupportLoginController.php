<?php

namespace App\Http\Controllers;

use App\Models\SupportLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SupportLoginController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $record = SupportLoginToken::query()->where('token', $token)->first();

        if (! $record || ! $record->isValid()) {
            return redirect()->route('login')->withErrors([
                'login' => 'Support login link is invalid or expired.',
            ]);
        }

        $tenant = $record->tenant;
        if (! $tenant || ! $tenant->is_active) {
            return redirect()->route('login')->withErrors([
                'login' => 'Tenant is unavailable.',
            ]);
        }

        tenancy()->initialize($tenant);

        $admin = User::query()->where('username', 'admin')->where('is_active', true)->first()
            ?? User::query()->where('is_active', true)->orderBy('id')->first();

        if (! $admin) {
            tenancy()->end();

            return redirect()->route('login')->withErrors([
                'login' => 'No active user found in tenant.',
            ]);
        }

        Auth::guard('web')->login($admin);
        $record->update(['used_at' => now()]);

        request()->session()->regenerate();
        request()->session()->put('tenant_id', $tenant->id);
        request()->session()->put('tenant_code', $tenant->code);
        request()->session()->put('support_login', true);

        return redirect()->route('admin.dashboard')
            ->with('status', "Support session as {$admin->username}@{$tenant->code}");
    }
}
