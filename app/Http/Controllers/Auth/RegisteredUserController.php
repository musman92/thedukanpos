<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\ShopRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RegisteredUserController extends Controller
{
    public function __construct(protected ShopRegistrationService $registration) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse|SymfonyResponse
    {
        $tenant = $this->registration->register($request->validated());

        tenancy()->initialize($tenant);

        $signedIn = Auth::attempt([
            'username' => 'admin',
            'password' => $request->validated('password'),
            'is_active' => true,
            'can_login' => true,
        ]);

        if (! $signedIn) {
            tenancy()->end();

            throw ValidationException::withMessages([
                'password' => 'The shop was created. Sign in with username admin and your shop code.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->put('tenant_code', $tenant->code);

        return Inertia::location(route('admin.dashboard'));
    }
}
