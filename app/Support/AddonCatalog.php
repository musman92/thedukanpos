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
     *   requires_core: string|null,
     *   highlights: list<string>,
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

            $parsed = self::parseManifest($raw, $dir, $entry);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
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

    /**
     * Plain-text excerpt from addons/{slug}/README.md for sales / support pages.
     */
    public static function readmeExcerpt(string $path, int $maxChars = 4000): ?string
    {
        $readme = $path.DIRECTORY_SEPARATOR.'README.md';
        if (! is_file($readme)) {
            return null;
        }

        $text = (string) file_get_contents($readme);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $parts = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($parts !== [] && end($parts) !== '') {
                    $parts[] = '';
                }

                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, '```') || str_starts_with($trimmed, '|')) {
                continue;
            }

            $parts[] = preg_replace('/`([^`]+)`/', '$1', $trimmed) ?? $trimmed;
        }

        $body = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $parts)) ?? '');
        if ($body === '') {
            return null;
        }

        if (mb_strlen($body) <= $maxChars) {
            return $body;
        }

        return rtrim(mb_substr($body, 0, $maxChars - 1)).'…';
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private static function parseManifest(array $raw, string $dir, string $entry): ?array
    {
        $slug = strtolower(trim((string) ($raw['slug'] ?? $entry)));
        if ($slug === '' || $slug !== strtolower($entry)) {
            return null;
        }

        $highlights = [];
        if (is_array($raw['highlights'] ?? null)) {
            $highlights = array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $raw['highlights'],
            )));
        }

        return [
            'slug' => $slug,
            'name' => (string) ($raw['name'] ?? $slug),
            'version' => (string) ($raw['version'] ?? '0.0.0'),
            'description' => (string) ($raw['description'] ?? ''),
            'requires_core' => isset($raw['requires_core']) ? (string) $raw['requires_core'] : null,
            'highlights' => $highlights,
            'provider' => isset($raw['provider']) ? (string) $raw['provider'] : null,
            'permissions' => array_values(array_filter(
                array_map('strval', $raw['permissions'] ?? []),
            )),
            'nav' => is_array($raw['nav'] ?? null) ? array_values($raw['nav']) : [],
            'path' => $dir,
        ];
    }
}
