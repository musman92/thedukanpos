<?php

namespace App\Services;

use App\Contracts\AddonLifecycle;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Support\AddonCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Platform-only: install / remove addons for a tenant.
 * Tenants never call this from their admin UI.
 */
class AddonProvisionService
{
    private const EXCLUSIVE_CAPABILITIES = ['checkout.surface'];

    /**
     * Catalog + install state for the platform tenant Addons tab.
     *
     * @return list<array{
     *   slug: string,
     *   name: string,
     *   version: string,
     *   description: string,
     *   installed: bool,
     *   status: string|null,
     *   installed_at: string|null,
     *   activated_at: string|null
     * }>
     */
    public function statusForTenant(Tenant $tenant): array
    {
        $rows = TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('slug');

        return collect(AddonCatalog::all())
            ->map(function (array $addon) use ($rows) {
                /** @var TenantAddon|null $row */
                $row = $rows->get($addon['slug']);

                return [
                    'slug' => $addon['slug'],
                    'name' => $addon['name'],
                    'version' => $addon['version'],
                    'description' => $addon['description'],
                    'installed' => $row !== null,
                    'status' => $row?->status,
                    'installed_at' => optional($row?->installed_at)->toDateTimeString(),
                    'activated_at' => optional($row?->activated_at)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    public function install(Tenant $tenant, string $slug): TenantAddon
    {
        $manifest = AddonCatalog::find($slug);
        if (! $manifest) {
            throw ValidationException::withMessages([
                'addon' => 'Unknown addon.',
            ]);
        }

        $this->assertCompatible($tenant, $manifest);
        try {
            $this->migrate($tenant, $manifest);
            $this->lifecycle($tenant, $manifest, 'afterInstall');
        } catch (\Throwable $e) {
            $this->rollback($tenant, $manifest);
            throw $e;
        }

        $existing = TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $manifest['slug'])
            ->first();

        if ($existing) {
            $existing->fill([
                'status' => TenantAddon::STATUS_ACTIVE,
                'version' => $manifest['version'],
                'activated_at' => $existing->activated_at ?? Carbon::now(),
            ]);
            $existing->save();
            $this->syncRoles($tenant);

            return $existing->refresh();
        }

        $installed = TenantAddon::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => $manifest['slug'],
            'status' => TenantAddon::STATUS_ACTIVE,
            'version' => $manifest['version'],
            'installed_at' => Carbon::now(),
            'activated_at' => Carbon::now(),
        ]);
        $this->syncRoles($tenant);

        return $installed;
    }

    public function remove(Tenant $tenant, string $slug): void
    {
        $slug = strtolower(trim($slug));
        $manifest = AddonCatalog::find($slug);
        if ($manifest === null && ! TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->exists()) {
            throw ValidationException::withMessages([
                'addon' => 'Unknown addon.',
            ]);
        }

        $dependent = collect(AddonCatalog::all())->first(function (array $candidate) use ($tenant, $slug) {
            return in_array($slug, $candidate['requires'], true)
                && $this->isActiveForTenant($tenant, $candidate['slug']);
        });
        if ($dependent) {
            throw ValidationException::withMessages([
                'addon' => "Remove {$dependent['name']} before removing this addon.",
            ]);
        }

        if ($manifest) {
            $this->lifecycle($tenant, $manifest, 'beforeRemove');
            $this->rollback($tenant, $manifest);
            $this->removePermissions($tenant, $manifest['permissions']);
        }

        TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->delete();
        $this->syncRoles($tenant);
    }

    public function isActiveForTenant(Tenant|string $tenant, string $slug): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return TenantAddon::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', strtolower(trim($slug)))
            ->where('status', TenantAddon::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertCompatible(Tenant $tenant, array $manifest): void
    {
        $active = TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantAddon::STATUS_ACTIVE)
            ->pluck('slug')
            ->map(fn (string $slug) => strtolower($slug))
            ->all();

        $missing = array_values(array_diff($manifest['requires'], $active));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'addon' => 'Required addons are not active: '.implode(', ', $missing).'.',
            ]);
        }

        foreach (AddonCatalog::all() as $candidate) {
            if (! in_array($candidate['slug'], $active, true)) {
                continue;
            }

            if (
                in_array($candidate['slug'], $manifest['conflicts'], true)
                || in_array($manifest['slug'], $candidate['conflicts'], true)
            ) {
                throw ValidationException::withMessages([
                    'addon' => "{$manifest['name']} conflicts with {$candidate['name']}.",
                ]);
            }

            foreach (self::EXCLUSIVE_CAPABILITIES as $key) {
                $requested = $manifest['capabilities'][$key] ?? null;
                $claimed = $candidate['capabilities'][$key] ?? null;
                if ($requested !== null && $claimed !== null && $requested !== $claimed) {
                    throw ValidationException::withMessages([
                        'addon' => "{$manifest['name']} and {$candidate['name']} require different {$key} modes.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function migrate(Tenant $tenant, array $manifest): void
    {
        $path = $manifest['path'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path) || glob($path.DIRECTORY_SEPARATOR.'*.php') === []) {
            return;
        }

        $tenant->run(function () use ($path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function rollback(Tenant $tenant, array $manifest): void
    {
        $path = $manifest['path'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $files = is_dir($path) ? glob($path.DIRECTORY_SEPARATOR.'*.php') : [];
        if ($files === false || $files === []) {
            return;
        }

        rsort($files);
        $tenant->run(function () use ($files) {
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $ran = DB::table('migrations')->where('migration', $name)->exists();
                if (! $ran) {
                    continue;
                }

                $migration = require $file;
                $migration->down();
                DB::table('migrations')->where('migration', $name)->delete();
            }
        });
    }

    private function syncRoles(Tenant $tenant): void
    {
        $tenant->run(fn () => app(RoleBootstrapService::class)->ensureDefaultRoles());
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function lifecycle(Tenant $tenant, array $manifest, string $method): void
    {
        $class = $manifest['lifecycle'] ?? null;
        if (! $class || ! class_exists($class)) {
            return;
        }

        $tenant->run(function () use ($class, $method) {
            $lifecycle = app($class);
            if (! $lifecycle instanceof AddonLifecycle) {
                throw new \RuntimeException("Addon lifecycle {$class} must implement ".AddonLifecycle::class.'.');
            }

            $lifecycle->{$method}();
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    private function removePermissions(Tenant $tenant, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        $tenant->run(function () use ($permissions) {
            foreach (Role::query()->get() as $role) {
                $role->permissions()->detach(
                    $role->permissions()->whereIn('name', $permissions)->pluck('id'),
                );
            }

            Permission::query()->whereIn('name', $permissions)->delete();
        });
    }
}
