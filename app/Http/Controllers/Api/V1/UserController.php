<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(protected UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->users->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        $page = $result['users'];

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'filters' => $result['filters'],
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()->orderBy('name')->pluck('name')->values(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->payload());

        return response()->json([
            'data' => $this->users->present($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name', 'branch:id,name', 'employeeProfile.branch:id,name']);

        return response()->json([
            'data' => $this->users->present($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($user, $request->payload());

        return response()->json([
            'data' => $this->users->present($user),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->users->delete($user);

        return response()->json(['message' => 'User deleted.']);
    }
}
