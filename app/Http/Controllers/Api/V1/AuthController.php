<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\PlanService;
use App\Services\ShopPasswordResetService;
use App\Services\ShopRegistrationService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected ShopRegistrationService $registration,
        protected ShopPasswordResetService $passwordResets,
    ) {}

    public function shopCode(Request $request): JsonResponse
    {
        $code = strtolower(trim((string) $request->query('shop_code', '')));

        return response()->json($this->registration->inspectShopCode($code));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return response()->json($this->passwordResets->sendLink(
            $request->validated('shop_code'),
            $request->validated('username'),
        ));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $tenant = $this->registration->register($request->validated());

        tenancy()->initialize($tenant);

        /** @var User $user */
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json($this->sessionPayload($token, $user));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json($this->sessionPayload($token, $user));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $branch = BranchContext::ensure();
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->values();

        $plan = tenancy()->initialized ? $this->planPayload() : [];

        return response()->json([
            'tenant' => tenancy()->initialized ? [
                ...$plan,
                'id' => tenant('id'),
                'code' => tenant('code'),
                'name' => tenant('name'),
            ] : null,
            ...$plan,
            'user' => $this->userPayload($user),
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'branches' => $branches,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(string $token, User $user): array
    {
        $plan = $this->planPayload();

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            ...$plan,
            'tenant' => [
                ...$plan,
                'id' => tenant('id'),
                'code' => tenant('code'),
                'name' => tenant('name'),
            ],
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        $usage = app(PlanService::class)->usage();

        return [
            'plan' => $usage['code'],
            'plan_name' => $usage['name'],
            'product_limit' => $usage['product_limit'],
            'user_limit' => $usage['user_limit'],
            'branch_limit' => $usage['branch_limit'],
            'ads_enabled' => $usage['ads_enabled'],
            'product_count' => $usage['product_count'],
            'user_count' => $usage['user_count'],
            'branch_count' => $usage['branch_count'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
