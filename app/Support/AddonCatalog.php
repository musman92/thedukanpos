<?php

namespace App\Support;

/**
 * Discovers in-repo addons from addons/{slug}/addon.json.
 * Skips _template and folders without a valid manifest.
 */
final class AddonCatalog
{
    /**
     * @return list<array{
     *   slug: string,
     *   name: string,
     *   version: string,
     *   description: string,
     *   provider: string|null,
     *   permissions: list<string>,
     *   nav: list<array<string, mixed>>,
     *   path: string
     * }>
     */
    public static function all(): array
    {
        $root = base_path('addons');
        if (! is_dir($root)) {
            return [];
        }

        $out = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '_')) {
                continue;
            }

            $dir = $root.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($dir)) {
                continue;
            }

            $manifestPath = $dir.DIRECTORY_SEPARATOR.'addon.json';
            if (! is_file($manifestPath)) {
                continue;
            }

            $raw = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($raw)) {
                continue;
            }

            $slug = strtolower(trim((string) ($raw['slug'] ?? $entry)));
            // Folder name must match slug.
            if ($slug === '' || $slug !== strtolower($entry)) {
                continue;
            }

            $out[] = [
                'slug' => $slug,
                'name' => (string) ($raw['name'] ?? $slug),
                'version' => (string) ($raw['version'] ?? '0.0.0'),
                'description' => (string) ($raw['description'] ?? ''),
                'provider' => isset($raw['provider']) ? (string) $raw['provider'] : null,
                'permissions' => array_values(array_filter(
                    array_map('strval', $raw['permissions'] ?? []),
                )),
                'nav' => is_array($raw['nav'] ?? null) ? array_values($raw['nav']) : [],
                'path' => $dir,
            ];
        }

        usort($out, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));

        foreach (self::all() as $addon) {
            if ($addon['slug'] === $slug) {
                return $addon;
            }
        }

        return null;
    }
}
