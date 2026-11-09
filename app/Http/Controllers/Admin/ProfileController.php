<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateOwnPasswordRequest;
use App\Http\Requests\Profile\UpdateOwnProfileRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(protected UserService $users) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Admin/Profile/Edit', [
            'profile' => $this->users->profilePayload($request->user()),
        ]);
    }

    public function update(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $this->users->updateOwnProfile($request->user(), $request->validated());

        return back()->with('status', __('profile.saved'));
    }

    public function updatePassword(UpdateOwnPasswordRequest $request): RedirectResponse
    {
        $this->users->changeOwnPassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return back()->with('status', __('profile.password_saved'));
    }
}
