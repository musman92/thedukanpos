<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roles) {}

    public function index(Request $request): Response
    {
        $result = $this->roles->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Roles/Index', $result);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roles->create($request->payload());

        return back()->with('status', 'Role created.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->payload());

        return back()->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roles->delete($role);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete role.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Role deleted.');
    }
}
