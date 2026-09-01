<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Support\AddonCatalog;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function index(): Response
    {
        $counts = TenantAddon::query()
            ->where('status', TenantAddon::STATUS_ACTIVE)
            ->selectRaw('slug, COUNT(*) as total')
            ->groupBy('slug')
            ->pluck('total', 'slug');

        $addons = collect(AddonCatalog::all())
            ->map(fn (array $addon) => $this->listItem($addon, (int) ($counts[$addon['slug']] ?? 0)))
            ->values()
            ->all();

        return Inertia::render('Platform/Addons/Index', [
            'addons' => $addons,
            'total' => count($addons),
        ]);
    }

    public function show(string $addon): Response
    {
        $manifest = AddonCatalog::find($addon);
        if ($manifest === null) {
            abort(404);
        }

        $activeCount = (int) TenantAddon::query()
            ->where('slug', $manifest['slug'])
            ->where('status', TenantAddon::STATUS_ACTIVE)
            ->count();

        $tenants = Tenant::query()
            ->whereIn('id', TenantAddon::query()
                ->where('slug', $manifest['slug'])
                ->where('status', TenantAddon::STATUS_ACTIVE)
                ->pluck('tenant_id'))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'url' => route('platform.tenants.show', $tenant),
            ])
            ->values()
            ->all();

        return Inertia::render('Platform/Addons/Show', [
            'addon' => $this->detailItem($manifest, $activeCount),
            'tenants' => $tenants,
        ]);
    }

    /**
     * @param  array<string, mixed>  $addon
     * @return array<string, mixed>
     */
    private function listItem(array $addon, int $activeTenantCount): array
    {
        return [
            'slug' => $addon['slug'],
            'name' => $addon['name'],
            'version' => $addon['version'],
            'description' => $addon['description'],
            'highlights' => array_slice($addon['highlights'], 0, 3),
            'screen_count' => count($addon['nav']),
            'active_tenant_count' => $activeTenantCount,
            'url' => route('platform.addons.show', $addon['slug']),
        ];
    }

    /**
     * @param  array<string, mixed>  $addon
     * @return array<string, mixed>
     */
    private function detailItem(array $addon, int $activeTenantCount): array
    {
        $nav = collect($addon['nav'])
            ->map(fn (array $item) => [
                'label' => (string) ($item['label'] ?? ''),
                'group' => (string) ($item['group'] ?? ''),
                'route' => (string) ($item['route'] ?? ''),
                'permission' => (string) ($item['permission'] ?? ''),
            ])
            ->filter(fn (array $item) => $item['label'] !== '')
            ->values()
            ->all();

        $groups = collect($nav)
            ->pluck('group')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'slug' => $addon['slug'],
            'name' => $addon['name'],
            'version' => $addon['version'],
            'description' => $addon['description'],
            'requires_core' => $addon['requires_core'],
            'highlights' => $addon['highlights'],
            'permissions' => $addon['permissions'],
            'nav' => $nav,
            'nav_groups' => $groups,
            'has_provider' => $addon['provider'] !== null,
            'readme_excerpt' => AddonCatalog::readmeExcerpt($addon['path']),
            'active_tenant_count' => $activeTenantCount,
        ];
    }
}
