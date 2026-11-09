<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\ShopPasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function __construct(private ShopPasswordResetService $resets) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => (string) $request->route('token'),
            'shop_code' => strtolower(trim((string) $request->query('shop_code', ''))),
            'username' => strtolower(trim((string) $request->query('username', ''))),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $this->resets->reset(
            $request->validated('shop_code'),
            $request->validated('username'),
            $request->validated('token'),
            $request->validated('password'),
        );

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Sign in with the new one.');
    }
}
