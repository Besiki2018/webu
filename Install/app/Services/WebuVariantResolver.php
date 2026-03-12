<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Resolves design-system component variant with fallback to default.
 * Used by builder, template demo, and AI to ensure only allowed variants are applied.
 */
class WebuVariantResolver
{
    /**
     * @param  array<int, array{key: string, variants?: array<int, string>, default_variant?: string}>  $components
     */
    public function __construct(
        protected array $components = []
    ) {
        if ($this->components === []) {
            $config = config('webu-builder-components.components', []);
            foreach ($config as $entry) {
                if (is_array($entry) && isset($entry['key'])) {
                    $this->components[] = $entry;
                }
            }
        }
    }

    /**
     * Resolve variant for a section key. Returns the first allowed variant if unknown.
     */
    public function resolve(string $sectionKey, ?string $variant = null): string
    {
        $entry = $this->entryForSectionKey($sectionKey);
        $allowed = $entry['variants'] ?? [];
        if ($allowed === []) {
            return $entry['default_variant'] ?? 'default';
        }
        $normalized = $variant !== null && $variant !== '' ? Str::lower(trim($variant)) : null;
        if ($normalized !== null) {
            foreach ($allowed as $v) {
                if (Str::lower($v) === $normalized) {
                    return $v;
                }
            }
        }

        return $entry['default_variant'] ?? $allowed[0];
    }

    /**
     * Allowed variants for a section key (for builder dropdown / AI).
     *
     * @return array<int, string>
     */
    public function allowedVariants(string $sectionKey): array
    {
        $entry = $this->entryForSectionKey($sectionKey);

        return $entry['variants'] ?? [];
    }

    /**
     * Default variant for a section key.
     */
    public function defaultVariant(string $sectionKey): ?string
    {
        $entry = $this->entryForSectionKey($sectionKey);

        return $entry['default_variant'] ?? null;
    }

    /**
     * @return array{key: string, variants: array<int, string>, default_variant: string}
     */
    private function entryForSectionKey(string $sectionKey): array
    {
        $needle = Str::lower(trim($sectionKey));
        foreach ($this->components as $entry) {
            $key = Str::lower(trim((string) ($entry['key'] ?? '')));
            if ($key === $needle) {
                return [
                    'key' => $entry['key'],
                    'variants' => array_values($entry['variants'] ?? []),
                    'default_variant' => $entry['default_variant'] ?? 'default',
                ];
            }
        }

        return ['key' => $sectionKey, 'variants' => [], 'default_variant' => 'default'];
    }
}
