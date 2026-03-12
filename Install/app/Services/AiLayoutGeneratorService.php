<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * AI Layout Generator Service.
 * Transforms user prompt → structured input; builds layout JSON from preset + modifiers;
 * generates theme tokens. No raw HTML; output is layout JSON only.
 */
class AiLayoutGeneratorService
{
    public function __construct(
        protected AiLayoutPromptParser $promptParser
    ) {}

    /**
     * Convert free-text user prompt into structured AI input.
     *
     * @return array{business_type: string, industry: string, design_style: string, color_scheme: string, sections_required: array<int, string>}
     */
    public function promptToStructuredInput(string $prompt): array
    {
        return $this->promptParser->parse($prompt);
    }

    /**
     * Generate layout JSON from structured input (preset + sections_required).
     *
     * @param  array{business_type?: string, industry?: string, design_style?: string, color_scheme?: string, sections_required?: array<int, string>}  $input
     * @return array{page: string, sections: array<int, array{component: string, variant: string, bindings: array<string, string>}>}
     */
    public function generateLayoutFromStructuredInput(array $input): array
    {
        $presetKey = $this->resolvePresetKey($input);
        $presets = config('ai-layout-generator.presets', []);
        $preset = $presets[$presetKey] ?? $presets['ecommerce_default'];
        $sections = $preset['sections'] ?? [];

        $requested = array_map('strtolower', array_map('trim', $input['sections_required'] ?? []));
        if ($requested !== []) {
            $registry = config('ai-layout-generator.component_registry', []);
            $allowed = array_keys($registry);
            $sections = $this->filterAndOrderSections($sections, $requested, $allowed);
        }

        $designStyle = Str::lower(trim((string) Arr::get($input, 'design_style', 'default')));
        $sections = $this->applyVariantFromDesignStyle($sections, $designStyle);

        return [
            'page' => 'home',
            'sections' => $sections,
        ];
    }

    /**
     * Generate theme tokens (for theme.tokens.json / runtime CSS).
     *
     * @param  array{primary_color?: string, secondary_color?: string, font_family?: string, border_radius?: string, color_scheme?: string}  $input
     * @return array<string, mixed>
     */
    public function generateThemeTokens(array $input): array
    {
        $defaults = config('ai-layout-generator.theme_defaults', []);
        $schemes = config('ai-layout-generator.color_schemes', []);

        $colorScheme = Str::lower(trim((string) Arr::get($input, 'color_scheme', 'neutral')));
        $schemeColors = $schemes[$colorScheme] ?? $schemes['neutral'] ?? [];

        return [
            'primary_color' => Arr::get($input, 'primary_color') ?? $schemeColors['primary'] ?? $defaults['primary_color'],
            'secondary_color' => Arr::get($input, 'secondary_color') ?? $schemeColors['secondary'] ?? $defaults['secondary_color'],
            'font_family' => Arr::get($input, 'font_family') ?? $defaults['font_family'],
            'border_radius' => Arr::get($input, 'border_radius') ?? $defaults['border_radius'],
        ];
    }

    /**
     * Resolve builder section key for an AI component key.
     */
    public function componentToSectionKey(string $componentKey): string
    {
        $registry = config('ai-layout-generator.component_registry', []);
        $key = Str::lower(trim($componentKey));

        return $registry[$key] ?? $key;
    }

    /**
     * Convert AI layout schema to template default_pages format (key, enabled, props).
     * Used by project generator to provision site.
     *
     * @param  array{page?: string, sections: array<int, array{component: string, variant?: string, bindings?: array<string, string>}>}  $layout
     * @return array<int, array{slug: string, title: string, sections: array<int, array{key: string, enabled: bool, props: array<string, mixed>}>}>
     */
    public function layoutToTemplateDefaultPages(array $layout): array
    {
        $pageSlug = Arr::get($layout, 'page', 'home');
        $sections = $layout['sections'] ?? [];
        $rows = [];

        foreach ($sections as $section) {
            $component = Str::lower(trim((string) Arr::get($section, 'component', '')));
            if ($component === '') {
                continue;
            }
            $sectionKey = $this->componentToSectionKey($component);
            $variant = Arr::get($section, 'variant', 'default');
            $bindings = is_array($section['bindings'] ?? null) ? $section['bindings'] : [];
            $props = array_merge($bindings, ['layout_variant' => $variant, 'variant' => $variant]);
            $rows[] = [
                'key' => $sectionKey,
                'enabled' => true,
                'props' => $props,
            ];
        }

        return [
            [
                'slug' => $pageSlug,
                'title' => $pageSlug === 'home' ? 'Home' : Str::headline($pageSlug),
                'sections' => $rows,
            ],
        ];
    }

    private function resolvePresetKey(array $input): string
    {
        $style = Str::lower(trim((string) Arr::get($input, 'design_style', 'default')));
        $presetMap = [
            'minimal' => 'ecommerce_minimal',
            'modern' => 'ecommerce_modern',
            'premium' => 'ecommerce_premium',
        ];

        return $presetMap[$style] ?? 'ecommerce_default';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, string>  $requested  e.g. ['hero', 'product-grid', 'newsletter']
     * @param  array<int, string>  $allowed
     * @return array<int, array<string, mixed>>
     */
    private function filterAndOrderSections(array $sections, array $requested, array $allowed): array
    {
        $normalize = static function (string $c): string {
            return str_replace(['_', ' '], '-', strtolower(trim($c)));
        };
        $requestedSet = array_flip(array_map($normalize, $requested));
        $out = [];
        foreach ($sections as $sec) {
            $comp = $normalize((string) Arr::get($sec, 'component', ''));
            if ($comp === '' || ! in_array($comp, $allowed, true)) {
                continue;
            }
            if ($requestedSet !== [] && ! isset($requestedSet[$comp])) {
                continue;
            }
            $out[] = $sec;
        }
        if ($out === [] && $requested !== []) {
            $defaultBindings = $this->defaultBindingsForComponent();
            foreach ($requested as $r) {
                $r = $normalize($r);
                if (in_array($r, $allowed, true)) {
                    $out[] = [
                        'component' => $r,
                        'variant' => 'default',
                        'bindings' => $defaultBindings[$r] ?? [],
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array<string, array<string, string>> */
    private function defaultBindingsForComponent(): array
    {
        return [
            'hero' => ['title' => 'site.hero.title', 'subtitle' => 'site.hero.subtitle', 'image' => 'site.hero.image', 'ctaText' => 'site.hero.cta_text', 'ctaUrl' => 'site.hero.cta_url'],
            'product-grid' => ['products' => 'products.featured', 'title' => 'site.featured_title'],
            'category-grid' => ['categories' => 'categories.main', 'title' => 'site.categories_title'],
            'banner' => ['title' => 'banners.promo.title', 'subtitle' => 'banners.promo.subtitle', 'ctaText' => 'banners.promo.cta_text', 'ctaUrl' => 'banners.promo.cta_url'],
            'cta' => ['title' => 'banners.home.title', 'subtitle' => 'banners.home.subtitle', 'ctaText' => 'banners.home.cta_text', 'ctaUrl' => 'banners.home.cta_url'],
            'newsletter' => ['title' => 'newsletter.form.title', 'subtitle' => 'newsletter.form.subtitle'],
            'footer' => ['logo' => 'site.logo', 'links' => 'site.footer.links', 'contact' => 'site.footer.contact', 'copyright' => 'site.footer.copyright'],
            'cart' => ['title' => 'site.cart_title', 'emptyMessage' => 'site.cart_empty_message'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function applyVariantFromDesignStyle(array $sections, string $designStyle): array
    {
        if ($designStyle === 'default') {
            return $sections;
        }
        $out = [];
        foreach ($sections as $sec) {
            $s = $sec;
            if ($designStyle === 'minimal' && isset($s['variant'])) {
                $s['variant'] = 'minimal';
            }
            if ($designStyle === 'premium' && ($s['component'] ?? '') === 'product-grid') {
                $s['variant'] = 'premium';
            }
            $out[] = $s;
        }

        return $out;
    }
}
