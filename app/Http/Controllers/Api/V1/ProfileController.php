<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateOwnPasswordRequest;
use App\Http\Requests\Profile\UpdateOwnProfileRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(protected UserService $users) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->users->profilePayload($request->user()),
        ]);
    }

    public function update(UpdateOwnProfileRequest $request): JsonResponse
    {
        $user = $this->users->updateOwnProfile($request->user(), $request->validated());

        return response()->json([
            'data' => $this->users->profilePayload($user),
        ]);
    }

    public function updatePassword(UpdateOwnPasswordRequest $request): JsonResponse
    {
        $this->users->changeOwnPassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
