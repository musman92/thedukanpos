<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Support\AddonCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Platform-only: install / remove addons for a tenant.
 * Tenants never call this from their admin UI.
 */
class AddonProvisionService
{
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

            return $existing->refresh();
        }

        // Tenant DB migrations / hooks for this addon will run here later.
        return TenantAddon::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => $manifest['slug'],
            'status' => TenantAddon::STATUS_ACTIVE,
            'version' => $manifest['version'],
            'installed_at' => Carbon::now(),
            'activated_at' => Carbon::now(),
        ]);
    }

    public function remove(Tenant $tenant, string $slug): void
    {
        $slug = strtolower(trim($slug));
        if (AddonCatalog::find($slug) === null && ! TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->exists()) {
            throw ValidationException::withMessages([
                'addon' => 'Unknown addon.',
            ]);
        }

        // Uninstall hooks (drop addon tables) will run here later.
        TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->delete();
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
}
