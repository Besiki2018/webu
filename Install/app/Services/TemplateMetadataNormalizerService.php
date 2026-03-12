<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TemplateMetadataNormalizerService
{
    private const MODULE_KEYS = [
        'cms_pages',
        'cms_menus',
        'cms_settings',
        'media_library',
        'domains',
        'database',
        'forms',
        'notifications',
        'portfolio',
        'real_estate',
        'restaurant',
        'hotel',
        'ecommerce',
        'booking',
        'payments',
        'shipping',
        'ecommerce_inventory',
        'ecommerce_accounting',
        'ecommerce_rs',
        'booking_team_scheduling',
        'booking_finance',
        'booking_advanced_calendar',
    ];

    /**
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function normalize(?array $manifest, string $slug, string $name, string $category, array $options = []): array
    {
        $metadata = is_array($manifest) ? $manifest : [];

        $vertical = $this->normalizeVertical(Arr::get($metadata, 'vertical', $category));
        $framework = $this->normalizeFramework(Arr::get($metadata, 'framework', 'React'));

        $defaultPages = $this->normalizeDefaultPages(Arr::get($metadata, 'default_pages', []));
        $defaultSections = $this->normalizeDefaultSections(
            Arr::get($metadata, 'default_sections', []),
            $defaultPages
        );

        $normalized = $metadata;
        $normalized['vertical'] = $vertical;
        $normalized['framework'] = $framework;
        $normalized['module_flags'] = $this->normalizeModuleFlags(
            Arr::get($metadata, 'module_flags', []),
            $category,
            $vertical
        );
        $normalized['typography_tokens'] = $this->normalizeTypographyTokens(
            Arr::get($metadata, 'typography_tokens', [])
        );
        $normalized['default_pages'] = $defaultPages;
        $normalized['default_sections'] = $defaultSections;

        if (isset($options['section_inventory']) && is_array($options['section_inventory'])) {
            $normalized['section_inventory'] = $options['section_inventory'];
        }

        if (isset($options['source_root']) && is_string($options['source_root'])) {
            $normalized['source_root'] = $options['source_root'];
        }

        $normalized['import'] = array_merge(
            is_array($normalized['import'] ?? null) ? $normalized['import'] : [],
            [
                'imported_at' => now()->toIso8601String(),
                'imported_slug' => $slug,
                'imported_name' => $name,
            ]
        );

        return $normalized;
    }

    private function normalizeVertical(mixed $value): string
    {
        $raw = Str::of((string) $value)->trim()->lower()->replace(' ', '-')->value();

        return $raw !== '' ? Str::slug($raw) : 'general';
    }

    private function normalizeFramework(mixed $value): string
    {
        $raw = Str::lower(trim((string) $value));

        return match (true) {
            str_contains($raw, 'next') => 'Next.js',
            str_contains($raw, 'nuxt') => 'Nuxt',
            str_contains($raw, 'vue') => 'Vue',
            str_contains($raw, 'svelte') => 'Svelte',
            str_contains($raw, 'astro') => 'Astro',
            str_contains($raw, 'angular') => 'Angular',
            str_contains($raw, 'html') => 'HTML',
            default => 'React',
        };
    }

    /**
     * @param  mixed  $value
     * @return array<int, array{slug: string, title: string, sections: array<int, string>}>
     */
    private function normalizeDefaultPages(mixed $value): array
    {
        $pages = [];

        if (is_array($value)) {
            foreach ($value as $index => $page) {
                if (! is_array($page)) {
                    continue;
                }

                $slug = Str::slug((string) Arr::get($page, 'slug', ''));
                if ($slug === '') {
                    $slug = 'page-'.($index + 1);
                }

                $title = trim((string) Arr::get($page, 'title', Str::headline($slug)));
                if ($title === '') {
                    $title = Str::headline($slug);
                }

                $sections = $this->normalizePageSections(Arr::get($page, 'sections', []));
                if ($sections === []) {
                    $sections = ['hero'];
                }

                $pages[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'sections' => $sections,
                ];
            }
        }

        if ($pages === []) {
            $pages[] = [
                'slug' => 'home',
                'title' => 'Home',
                'sections' => ['hero', 'services', 'contact'],
            ];
        }

        $seen = [];

        return collect($pages)
            ->filter(function (array $page) use (&$seen): bool {
                $slug = $page['slug'];
                if (isset($seen[$slug])) {
                    return false;
                }

                $seen[$slug] = true;

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $sections
     * @return array<int, string>
     */
    private function normalizePageSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        $keys = [];
        foreach ($sections as $section) {
            $resolved = null;
            if (is_string($section)) {
                $resolved = $section;
            } elseif (is_array($section)) {
                $resolved = Arr::get($section, 'key') ?? Arr::get($section, 'type');
            }

            $key = trim(Str::lower((string) $resolved));
            if ($key === '') {
                continue;
            }

            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  mixed  $sections
     * @param  array<int, array{slug: string, title: string, sections: array<int, string>}>  $defaultPages
     * @return array<string, array<int, array{key: string, enabled: bool, props: array<string, mixed>}>>
     */
    private function normalizeDefaultSections(mixed $sections, array $defaultPages): array
    {
        $normalized = [];

        if (is_array($sections)) {
            foreach ($sections as $pageSlug => $rawList) {
                $slug = Str::slug((string) $pageSlug);
                if ($slug === '') {
                    continue;
                }

                $normalized[$slug] = $this->normalizeSectionList($rawList);
            }
        }

        foreach ($defaultPages as $page) {
            $slug = $page['slug'];
            if (! isset($normalized[$slug]) || $normalized[$slug] === []) {
                $normalized[$slug] = collect($page['sections'])
                    ->map(static fn (string $key): array => [
                        'key' => $key,
                        'enabled' => true,
                        'props' => [],
                    ])
                    ->values()
                    ->all();
            }
        }

        if (! isset($normalized['home'])) {
            $normalized['home'] = [
                ['key' => 'hero', 'enabled' => true, 'props' => []],
                ['key' => 'services', 'enabled' => true, 'props' => []],
                ['key' => 'contact', 'enabled' => true, 'props' => []],
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $sections
     * @return array<int, array{key: string, enabled: bool, props: array<string, mixed>}>
     */
    private function normalizeSectionList(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($sections as $index => $section) {
            $rawKey = null;
            $enabled = true;
            $props = [];

            if (is_string($section)) {
                $rawKey = $section;
            } elseif (is_array($section)) {
                $rawKey = Arr::get($section, 'key') ?? Arr::get($section, 'type') ?? 'section-'.$index;
                $enabled = (bool) Arr::get($section, 'enabled', true);
                $candidateProps = Arr::get($section, 'props', []);
                if (is_array($candidateProps)) {
                    $props = $candidateProps;
                }
            }

            $key = trim(Str::lower((string) $rawKey));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = [
                'key' => $key,
                'enabled' => $enabled,
                'props' => $props,
            ];
        }

        return $result;
    }

    /**
     * @param  mixed  $value
     * @return array<string, bool>
     */
    private function normalizeModuleFlags(mixed $value, string $category, string $vertical): array
    {
        $input = is_array($value) ? $value : [];
        $isCommerce = str_contains(Str::lower($category), 'ecommerce')
            || str_contains(Str::lower($vertical), 'ecommerce')
            || str_contains(Str::lower($vertical), 'shop')
            || str_contains(Str::lower($vertical), 'store');
        $isPortfolio = str_contains(Str::lower($category), 'portfolio')
            || str_contains(Str::lower($vertical), 'portfolio')
            || str_contains(Str::lower($vertical), 'showcase');
        $isRealEstate = str_contains(Str::lower($category), 'real_estate')
            || str_contains(Str::lower($category), 'realestate')
            || str_contains(Str::lower($category), 'realtor')
            || str_contains(Str::lower($vertical), 'real-estate')
            || str_contains(Str::lower($vertical), 'realestate')
            || str_contains(Str::lower($vertical), 'property');
        $isRestaurant = str_contains(Str::lower($category), 'restaurant')
            || str_contains(Str::lower($vertical), 'restaurant')
            || str_contains(Str::lower($vertical), 'cafe')
            || str_contains(Str::lower($vertical), 'food');
        $isHotel = str_contains(Str::lower($category), 'hotel')
            || str_contains(Str::lower($vertical), 'hotel')
            || str_contains(Str::lower($vertical), 'hospitality')
            || str_contains(Str::lower($vertical), 'rooms');

        $defaults = [
            'cms_pages' => true,
            'cms_menus' => true,
            'cms_settings' => true,
            'media_library' => true,
            'domains' => true,
            'database' => true,
            'forms' => true,
            'notifications' => true,
            'portfolio' => $isPortfolio,
            'real_estate' => $isRealEstate,
            'restaurant' => $isRestaurant,
            'hotel' => $isHotel,
            'ecommerce' => $isCommerce,
            'booking' => false,
            'payments' => $isCommerce,
            'shipping' => $isCommerce,
            'ecommerce_inventory' => $isCommerce,
            'ecommerce_accounting' => $isCommerce,
            'ecommerce_rs' => $isCommerce,
            'booking_team_scheduling' => false,
            'booking_finance' => false,
            'booking_advanced_calendar' => false,
        ];

        $normalized = [];
        foreach (self::MODULE_KEYS as $key) {
            $normalized[$key] = array_key_exists($key, $input)
                ? (bool) $input[$key]
                : (bool) ($defaults[$key] ?? false);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return array{heading: string, body: string, button: string}
     */
    private function normalizeTypographyTokens(mixed $value): array
    {
        $input = is_array($value) ? $value : [];
        $allowed = ['heading' => true, 'body' => true, 'button' => true, 'base' => true];

        $resolved = [
            'heading' => 'heading',
            'body' => 'body',
            'button' => 'body',
        ];

        foreach (['heading', 'body', 'button'] as $token) {
            $candidate = trim(Str::lower((string) ($input[$token] ?? '')));
            if ($candidate !== '' && isset($allowed[$candidate])) {
                $resolved[$token] = $candidate;
            }
        }

        return $resolved;
    }
}
