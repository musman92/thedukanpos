<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\ShopPasswordResetService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private ShopPasswordResetService $resets) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $result = $this->resets->sendLink(
            $request->validated('shop_code'),
            $request->validated('username'),
        );

        return back()->with('status', $result['message']);
    }
}
