<?php

namespace App\Support;

use App\Models\TenantAddon;

/**
 * Runtime addon flags for the active tenant (central tenant_addons table).
 */
final class TenantAddons
{
    public const SHIFTS = 'shifts';

    /**
     * @return list<string>
     */
    public static function activeSlugs(): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        return once(function () {
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
        });
    }

    public static function has(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return in_array($slug, self::activeSlugs(), true);
    }

    /**
     * Shared Inertia shape: { shifts: bool, ... }
     *
     * @return array<string, bool>
     */
    public static function flags(): array
    {
        $active = array_fill_keys(self::activeSlugs(), true);

        return [
            self::SHIFTS => isset($active[self::SHIFTS]),
        ];
    }
}
