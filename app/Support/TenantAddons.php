<?php

namespace App\Support;

use App\Models\TenantAddon;

/**
 * Runtime addon flags for the active tenant (central tenant_addons table).
 */
final class TenantAddons
{
    /**
     * @return list<string>
     */
    public static function activeSlugs(): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        $tenantId = tenant('id');
        if (! $tenantId) {
            return [];
        }

        return TenantAddon::query()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantAddon::STATUS_ACTIVE)
            ->pluck('slug')
            ->map(fn (string $slug) => strtolower(trim($slug)))
            ->values()
            ->all();
    }

    public static function has(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return in_array($slug, self::activeSlugs(), true);
    }
}
