<?php

namespace App\Services;

use App\Support\AppPermissions;
use App\Support\TenantDefaultRoles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(protected RoleBootstrapService $bootstrap) {}

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{
     *   roles: LengthAwarePaginator,
     *   filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string},
     *   permission_groups: list<array{title: string, permissions: list<array{name: string, action: string, label: string, module_key: string}>}>,
     *   protected_role_names: list<string>
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->bootstrap->ensureDefaultRoles();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Role $role) => $this->present($role));

        return [
            'roles' => $roles,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'permission_groups' => AppPermissions::groupsForInertia(),
            'protected_role_names' => TenantDefaultRoles::names(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name'];
        $sort = strtolower(trim((string) ($sort ?? 'name')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'name';
        }

        $direction = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{name:string, permissions?:list<string>}  $data
     */
    public function create(array $data): Role
    {
        $this->bootstrap->syncPermissions();

        $name = trim((string) $data['name']);
        $this->assertNameAvailable($name);
        $this->assertNotReservedName($name);

        $permissions = $this->normalizePermissions($data['permissions'] ?? []);

        $role = Role::create([
            'name' => $name,
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        $role->syncPermissions($permissions);

        return $role->fresh(['permissions']);
    }

    /**
     * @param  array{name:string, permissions?:list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $this->bootstrap->syncPermissions();

        $name = trim((string) $data['name']);
        $protected = TenantDefaultRoles::isProtected($role->name);

        if ($protected && $name !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'System roles cannot be renamed.',
            ]);
        }

        if (! $protected) {
            $this->assertNotReservedName($name);
            $this->assertNameAvailable($name, $role->id);
            $role->name = $name;
            $role->save();
        }

        $permissions = $this->normalizePermissions($data['permissions'] ?? []);
        $role->syncPermissions($permissions);

        return $role->fresh(['permissions']);
    }

    public function delete(Role $role): void
    {
        if (TenantDefaultRoles::isProtected($role->name)) {
            throw ValidationException::withMessages([
                'role' => "The role \"{$role->name}\" is a system role and cannot be deleted.",
            ]);
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            throw ValidationException::withMessages([
                'role' => "Cannot delete this role because it is assigned to {$userCount} user(s).",
            ]);
        }

        $role->syncPermissions([]);
        $role->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'is_protected' => TenantDefaultRoles::isProtected($role->name),
            'permissions' => $role->permissions->pluck('name')->values()->all(),
            'permissions_count' => $role->permissions->count(),
            'users_count' => (int) ($role->users_count ?? $role->users()->count()),
        ];
    }

    /**
     * @param  list<mixed>  $permissions
     * @return list<string>
     */
    protected function normalizePermissions(array $permissions): array
    {
        $allowed = array_flip(AppPermissions::all());
        $names = [];

        foreach ($permissions as $permission) {
            $name = trim((string) $permission);
            if ($name === '' || ! isset($allowed[$name])) {
                continue;
            }
            Permission::findOrCreate($name, config('auth.defaults.guard', 'web'));
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Role::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This role name is already taken.',
            ]);
        }
    }

    protected function assertNotReservedName(string $name): void
    {
        if (TenantDefaultRoles::isProtected($name)) {
            throw ValidationException::withMessages([
                'name' => "\"{$name}\" is a reserved system role name.",
            ]);
        }
    }
}
