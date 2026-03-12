<?php

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class CmsRuntimeTemplateAliasAdoptionAuditService
{
    private const ALIAS_MAP_PATH = 'docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json';

    /**
     * @return array<string, mixed>
     */
    public function audit(?array $roots = null, ?array $canonicalKeysOverride = null, int $topLimit = 10): array
    {
        $topLimit = max(1, min(100, $topLimit));
        $normalizedRoots = $this->normalizeRoots($roots);
        $canonicalSet = $this->normalizeCanonicalSet($canonicalKeysOverride);

        if ($canonicalSet === []) {
            $canonicalSet = $this->loadCanonicalBuilderKeySetFromAliasMap();
        }

        $legacyFixedKeys = array_fill_keys([
            'webu_header_01',
            'webu_footer_01',
            'webu_hero_01',
            'webu_cta_banner_01',
            'webu_newsletter_01',
            'webu_category_list_01',
            'webu_product_grid_01',
            'webu_product_card_01',
        ], true);

        $markerCounts = [];
        $categoryCounts = [
            'canonical_alias_map_keys' => 0,
            'legacy_fixed_semantic_keys' => 0,
            'legacy_page_section_keys' => 0,
            'legacy_named_componentish_keys' => 0,
            'other' => 0,
        ];
        $categoryMarkerCounts = [
            'canonical_alias_map_keys' => [],
            'legacy_fixed_semantic_keys' => [],
            'legacy_page_section_keys' => [],
            'legacy_named_componentish_keys' => [],
            'other' => [],
        ];

        $scannedFiles = 0;
        foreach ($normalizedRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (strtolower((string) $file->getExtension()) !== 'html') {
                    continue;
                }

                $scannedFiles++;
                $content = @file_get_contents($file->getPathname());
                if (! is_string($content) || $content === '') {
                    continue;
                }

                if (! preg_match_all('/data-webu-section="([^"]+)"/i', $content, $matches)) {
                    continue;
                }

                foreach (($matches[1] ?? []) as $rawKey) {
                    if (! is_string($rawKey)) {
                        continue;
                    }

                    $marker = strtolower(trim($rawKey));
                    if ($marker === '') {
                        continue;
                    }

                    $markerCounts[$marker] = ($markerCounts[$marker] ?? 0) + 1;
                    $category = $this->classifyMarker($marker, $canonicalSet, $legacyFixedKeys);
                    $categoryCounts[$category]++;
                    $categoryMarkerCounts[$category][$marker] = ($categoryMarkerCounts[$category][$marker] ?? 0) + 1;
                }
            }
        }

        $totalMarkers = array_sum($markerCounts);

        $categorySummary = [];
        foreach ($categoryCounts as $category => $count) {
            $categorySummary[$category] = [
                'count' => $count,
                'percent' => $totalMarkers > 0 ? round(($count / $totalMarkers) * 100, 2) : 0.0,
            ];
        }

        $topMarkersByCategory = [];
        foreach ($categoryMarkerCounts as $category => $counts) {
            arsort($counts);
            $rows = [];
            $i = 0;
            foreach ($counts as $marker => $count) {
                $rows[] = [
                    'marker' => $marker,
                    'count' => $count,
                ];
                if (++$i >= $topLimit) {
                    break;
                }
            }
            $topMarkersByCategory[$category] = $rows;
        }

        arsort($markerCounts);
        $topMarkers = [];
        $i = 0;
        foreach ($markerCounts as $marker => $count) {
            $topMarkers[] = ['marker' => $marker, 'count' => $count];
            if (++$i >= $topLimit) {
                break;
            }
        }

        return [
            'ok' => true,
            'schema_version' => 'cms.runtime-template-alias-adoption-audit.v1',
            'scanned_at' => now()->toISOString(),
            'roots' => $normalizedRoots,
            'scanned_html_files' => $scannedFiles,
            'canonical_alias_map_key_count' => count($canonicalSet),
            'totals' => [
                'markers' => $totalMarkers,
                'unique_markers' => count($markerCounts),
            ],
            'categories' => $categorySummary,
            'top_markers' => $topMarkers,
            'top_markers_by_category' => $topMarkersByCategory,
        ];
    }

    /**
     * @param  array<string, bool>  $canonicalSet
     * @param  array<string, bool>  $legacyFixedKeys
     */
    private function classifyMarker(string $marker, array $canonicalSet, array $legacyFixedKeys): string
    {
        if (isset($canonicalSet[$marker])) {
            return 'canonical_alias_map_keys';
        }

        if (isset($legacyFixedKeys[$marker])) {
            return 'legacy_fixed_semantic_keys';
        }

        if (preg_match('/^webu_[a-z0-9-]+_section_\d+$/', $marker) === 1) {
            return 'legacy_page_section_keys';
        }

        if (preg_match('/^webu_[a-z0-9_]+_\d+$/', $marker) === 1) {
            return 'legacy_named_componentish_keys';
        }

        return 'other';
    }

    /**
     * @param  array<int, string>|null  $roots
     * @return array<int, string>
     */
    private function normalizeRoots(?array $roots): array
    {
        $defaults = [
            storage_path('app/private/published'),
            storage_path('app/private/previews'),
        ];

        $input = is_array($roots) && $roots !== [] ? $roots : $defaults;
        $normalized = [];

        foreach ($input as $root) {
            if (! is_string($root)) {
                continue;
            }

            $value = trim($root);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>|null  $canonicalKeysOverride
     * @return array<string, bool>
     */
    private function normalizeCanonicalSet(?array $canonicalKeysOverride): array
    {
        $set = [];
        if (! is_array($canonicalKeysOverride)) {
            return $set;
        }

        foreach ($canonicalKeysOverride as $key) {
            if (! is_string($key)) {
                continue;
            }
            $normalized = strtolower(trim($key));
            if ($normalized === '') {
                continue;
            }
            $set[$normalized] = true;
        }

        return $set;
    }

    /**
     * @return array<string, bool>
     */
    private function loadCanonicalBuilderKeySetFromAliasMap(): array
    {
        $path = base_path(self::ALIAS_MAP_PATH);
        if (! is_file($path)) {
            throw new RuntimeException('Alias map JSON not found: '.self::ALIAS_MAP_PATH);
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ! is_array($json['mappings'] ?? null)) {
            throw new RuntimeException('Alias map JSON is invalid or missing mappings[]');
        }

        $set = [];
        foreach ($json['mappings'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $canonicalKeys = $row['canonical_builder_keys'] ?? null;
            if (! is_array($canonicalKeys)) {
                continue;
            }

            foreach ($canonicalKeys as $key) {
                if (! is_string($key)) {
                    continue;
                }
                $normalized = strtolower(trim($key));
                if ($normalized === '') {
                    continue;
                }
                $set[$normalized] = true;
            }
        }

        return $set;
    }
}
