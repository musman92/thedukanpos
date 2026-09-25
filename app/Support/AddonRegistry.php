<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Read-only runtime view of the active tenant's addon manifests.
 *
 * Additive extension points (nav and slots) are merged. Capabilities use
 * deterministic manifest order; exclusive conflicts are rejected at install.
 */
final class AddonRegistry
{
    /** @var list<array{slug:string,key:string,resolver:\Closure}> */
    private array $capabilityResolvers = [];

    public function registerCapability(string $slug, string $key, \Closure $resolver): void
    {
        $this->capabilityResolvers[] = compact('slug', 'key', 'resolver');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeManifests(): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        $active = array_fill_keys(TenantAddons::activeSlugs(), true);

        return array_values(array_filter(
            AddonCatalog::all(),
            fn (array $manifest) => isset($active[$manifest['slug']]),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nav(): array
    {
        $items = [];

        foreach ($this->activeManifests() as $manifest) {
            foreach ($manifest['nav'] as $item) {
                if (! is_array($item) || empty($item['route']) || empty($item['label'])) {
                    continue;
                }

                $items[] = [
                    ...$item,
                    'addon' => $manifest['slug'],
                    'group' => $item['group'] ?? 'addons',
                    'order' => (int) ($item['order'] ?? 500),
                ];
            }
        }

        usort($items, fn (array $a, array $b) => [
            $a['order'],
            $a['label'],
        ] <=> [
            $b['order'],
            $b['label'],
        ]);

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach ($this->activeManifests() as $manifest) {
            foreach ($manifest['capabilities'] as $key => $value) {
                if (is_bool($value)) {
                    Arr::set(
                        $capabilities,
                        $key,
                        (bool) Arr::get($capabilities, $key, false) || $value,
                    );

                    continue;
                }

                if (! Arr::has($capabilities, $key)) {
                    Arr::set($capabilities, $key, $value);
                }
            }
        }

        $active = array_fill_keys(TenantAddons::activeSlugs(), true);
        foreach ($this->capabilityResolvers as $registration) {
            if (isset($active[$registration['slug']])) {
                Arr::set($capabilities, $registration['key'], ($registration['resolver'])());
            }
        }

        return $capabilities;
    }

    public function capability(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->capabilities(), $key, $default);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function slots(): array
    {
        $slots = [];

        foreach ($this->activeManifests() as $manifest) {
            foreach ($manifest['slots'] as $name => $registrations) {
                if (! is_array($registrations)) {
                    continue;
                }

                foreach ($registrations as $registration) {
                    if (! is_array($registration) || empty($registration['component'])) {
                        continue;
                    }

                    $slots[$name][] = [
                        ...$registration,
                        'addon' => $manifest['slug'],
                        'order' => (int) ($registration['order'] ?? 500),
                    ];
                }
            }
        }

        foreach ($slots as &$registrations) {
            usort($registrations, fn (array $a, array $b) => $a['order'] <=> $b['order']);
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return [
            'active_slugs' => TenantAddons::activeSlugs(),
            'nav' => $this->nav(),
            'capabilities' => $this->capabilities(),
            'slots' => $this->slots(),
        ];
    }
}
