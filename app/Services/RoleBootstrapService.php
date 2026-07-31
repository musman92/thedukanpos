<?php

namespace App\Services;

use App\Support\AppPermissions;
use App\Support\TenantDefaultRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleBootstrapService
{
    public function syncPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (AppPermissions::all() as $name) {
            Permission::findOrCreate($name, $guard);
        }
    }

    /**
     * Ensure default roles exist. Administrator always gets the full catalog.
     * Manager / Cashier are seeded only when they have no permissions yet.
     */
    public function syncDefaultRoles(): void
    {
        $this->syncPermissions();

        $guard = config('auth.defaults.guard', 'web');

        $administrator = Role::findOrCreate(TenantDefaultRoles::ADMINISTRATOR, $guard);
        $administrator->syncPermissions(AppPermissions::all());

        $manager = Role::findOrCreate(TenantDefaultRoles::MANAGER, $guard);
        if ($manager->permissions()->count() === 0) {
            $manager->syncPermissions($this->managerPermissions());
        }

        $cashier = Role::findOrCreate(TenantDefaultRoles::CASHIER, $guard);
        if ($cashier->permissions()->count() === 0) {
            $cashier->syncPermissions($this->cashierPermissions());
        }
    }

    /**
     * Create missing default roles without resetting customized permissions.
     */
    public function ensureDefaultRoles(): void
    {
        $this->syncPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (TenantDefaultRoles::names() as $name) {
            $role = Role::findOrCreate($name, $guard);

            if ($role->permissions()->count() > 0) {
                continue;
            }

            if ($name === TenantDefaultRoles::ADMINISTRATOR) {
                $role->syncPermissions(AppPermissions::all());
            } elseif ($name === TenantDefaultRoles::MANAGER) {
                $role->syncPermissions($this->managerPermissions());
            } elseif ($name === TenantDefaultRoles::CASHIER) {
                $role->syncPermissions($this->cashierPermissions());
            }
        }
    }

    /**
     * @return list<string>
     */
    public function managerPermissions(): array
    {
        $excludeModules = ['roles', 'settings', 'users'];

        return array_values(array_filter(
            AppPermissions::all(),
            function (string $permission) use ($excludeModules) {
                $module = AppPermissions::parse($permission)['module'] ?? '';

                if (in_array($module, $excludeModules, true)) {
                    // Managers can view users / settings but not manage roles.
                    return in_array($permission, [
                        'users.index',
                        'settings.index',
                    ], true);
                }

                return true;
            }
        ));
    }

    /**
     * @return list<string>
     */
    public function cashierPermissions(): array
    {
        return [
            'pos.index',
            'pos.checkout',
            'pos.receipt',
            'shifts.index',
            'shifts.store',
            'shifts.update',
            'customers.index',
            'customers.store',
            'customers.receive-payment',
            'products.index',
            'sales-returns.index',
            'sales-returns.store',
        ];
    }
}
