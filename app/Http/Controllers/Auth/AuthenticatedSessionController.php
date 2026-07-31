<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        // Roles live in the tenant DB; tenancy is already initialized by authenticate().
        $user = Auth::user();

        // Auth, Admin, and POS use separate Vite entrypoints. A normal Inertia
        // redirect stays inside the auth bundle and cannot resolve Admin/POS
        // pages — force a full document visit so the correct JS loads.
        if ($user?->hasRole('Cashier') && ! $user->hasAnyRole(['Administrator', 'Manager'])) {
            return Inertia::location(route('pos.index'));
        }

        return Inertia::location(route('admin.dashboard'));
    }

    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }
}
