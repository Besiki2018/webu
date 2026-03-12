<?php

namespace App\Services;

use App\Models\SectionLibrary;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TemplateSectionInventoryService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function extract(array $metadata): array
    {
        $defaultSections = Arr::get($metadata, 'default_sections', []);
        $defaultPages = Arr::get($metadata, 'default_pages', []);

        $index = [];

        if (is_array($defaultSections)) {
            foreach ($defaultSections as $pageSlug => $sections) {
                $resolvedSlug = $this->normalizePageSlug($pageSlug);
                foreach ($this->normalizeSections($sections) as $section) {
                    $key = $section['key'];
                    if (! isset($index[$key])) {
                        $index[$key] = [
                            'key' => $key,
                            'pages' => [],
                            'occurrences' => 0,
                        ];
                    }

                    $index[$key]['occurrences']++;
                    if ($resolvedSlug !== '' && ! in_array($resolvedSlug, $index[$key]['pages'], true)) {
                        $index[$key]['pages'][] = $resolvedSlug;
                    }
                }
            }
        }

        if (is_array($defaultPages)) {
            foreach ($defaultPages as $page) {
                if (! is_array($page)) {
                    continue;
                }

                $pageSlug = $this->normalizePageSlug($page['slug'] ?? 'home');
                foreach ($this->normalizeSections($page['sections'] ?? []) as $section) {
                    $key = $section['key'];
                    if (! isset($index[$key])) {
                        $index[$key] = [
                            'key' => $key,
                            'pages' => [],
                            'occurrences' => 0,
                        ];
                    }

                    $index[$key]['occurrences']++;
                    if ($pageSlug !== '' && ! in_array($pageSlug, $index[$key]['pages'], true)) {
                        $index[$key]['pages'][] = $pageSlug;
                    }
                }
            }
        }

        $library = SectionLibrary::query()
            ->get(['key', 'category'])
            ->mapWithKeys(static fn (SectionLibrary $section): array => [
                Str::lower(trim((string) $section->key)) => [
                    'key' => (string) $section->key,
                    'category' => (string) $section->category,
                ],
            ])
            ->all();

        $mappedCount = 0;
        $unmappedKeys = [];
        $items = [];

        foreach ($index as $item) {
            $key = (string) ($item['key'] ?? '');
            $mapping = $this->mapToLibrary($key, $library);

            if ($mapping['mapped_key'] !== null) {
                $mappedCount++;
            } else {
                $unmappedKeys[] = $key;
            }

            $items[] = [
                'key' => $key,
                'pages' => array_values($item['pages']),
                'occurrences' => (int) $item['occurrences'],
                'mapped_key' => $mapping['mapped_key'],
                'mapped_category' => $mapping['mapped_category'],
                'mapping_confidence' => $mapping['confidence'],
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? ''));
        });

        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'mapped' => $mappedCount,
                'unmapped' => count($items) - $mappedCount,
            ],
            'unmapped_keys' => array_values(array_unique($unmappedKeys)),
        ];
    }

    private function normalizePageSlug(mixed $value): string
    {
        $slug = Str::slug((string) $value);

        return $slug !== '' ? $slug : 'home';
    }

    /**
     * @param  mixed  $sections
     * @return array<int, array{key: string}>
     */
    private function normalizeSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        $normalized = [];
        foreach ($sections as $index => $section) {
            $key = null;

            if (is_string($section)) {
                $key = $section;
            } elseif (is_array($section)) {
                $key = Arr::get($section, 'key') ?? Arr::get($section, 'type');
            }

            $resolved = trim(Str::lower((string) $key));
            if ($resolved === '') {
                $resolved = 'section-'.$index;
            }

            $normalized[] = ['key' => $resolved];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{key: string, category: string}>  $library
     * @return array{mapped_key: string|null, mapped_category: string|null, confidence: string}
     */
    private function mapToLibrary(string $key, array $library): array
    {
        $normalized = Str::lower(trim($key));
        if ($normalized === '') {
            return [
                'mapped_key' => null,
                'mapped_category' => null,
                'confidence' => 'none',
            ];
        }

        if (isset($library[$normalized])) {
            return [
                'mapped_key' => $library[$normalized]['key'],
                'mapped_category' => $library[$normalized]['category'],
                'confidence' => 'exact',
            ];
        }

        $compact = str_replace(['-', '_', ' '], '', $normalized);
        foreach ($library as $libNormalized => $mapped) {
            $libCompact = str_replace(['-', '_', ' '], '', $libNormalized);
            if ($compact === $libCompact) {
                return [
                    'mapped_key' => $mapped['key'],
                    'mapped_category' => $mapped['category'],
                    'confidence' => 'alias',
                ];
            }
        }

        foreach ($library as $libNormalized => $mapped) {
            if (str_contains($libNormalized, $normalized) || str_contains($normalized, $libNormalized)) {
                return [
                    'mapped_key' => $mapped['key'],
                    'mapped_category' => $mapped['category'],
                    'confidence' => 'fuzzy',
                ];
            }
        }

        return [
            'mapped_key' => null,
            'mapped_category' => null,
            'confidence' => 'none',
        ];
    }
}
