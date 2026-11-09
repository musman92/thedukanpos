<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roles) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->asWebGuard(fn () => $this->roles->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]));
        $page = $result['roles'];

        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'permission_groups' => $result['permission_groups'],
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->asWebGuard(fn () => $this->roles->create($request->payload()));

        return response()->json([
            'data' => $this->asWebGuard(fn () => $this->roles->present($role)),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name')->loadCount('users');

        return response()->json([
            'data' => $this->asWebGuard(fn () => $this->roles->present($role)),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->asWebGuard(fn () => $this->roles->update($role, $request->payload()));

        return response()->json([
            'data' => $this->asWebGuard(fn () => $this->roles->present($role)),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->asWebGuard(fn () => $this->roles->delete($role));

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * Spatie counts users through the default auth guard. API requests use
     * Sanctum, which has no user model, so role writes run as the web guard.
     */
    private function asWebGuard(callable $callback): mixed
    {
        $guard = config('auth.defaults.guard');
        config(['auth.defaults.guard' => 'web']);
        try {
            return $callback();
        } finally {
            config(['auth.defaults.guard' => $guard]);
        }
    }
}
