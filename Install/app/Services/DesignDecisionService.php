<?php

namespace App\Services;

use Illuminate\Support\Arr;

/**
 * Maps requirement config (Part 1) to site blueprint: themePreset + pages with sections.
 * Output shape is compatible with SiteProvisioningService (theme_preset, default_pages).
 * PART 7 Business Type Mapping: template and theme come from config/business_template_map.php
 * (business_type_to_template, by_design_preference, fallback_template, fallback_theme_preset).
 */
class DesignDecisionService
{
    /**
     * designStyle from config → theme_preset key in config/theme-presets.php (fallback when not in business_template_map).
     */
    private const DESIGN_STYLE_TO_PRESET = [
        'clean_minimal' => 'luxury_minimal',
        'luxury_minimal' => 'luxury_minimal',
        'luxury_brand' => 'luxury_minimal',
        'colorful_startup' => 'bold_startup',
        'dark_modern' => 'dark_modern',
        'soft_pastel' => 'soft_pastel',
        'corporate_clean' => 'corporate_clean',
        'bold_startup' => 'bold_startup',
        'creative_portfolio' => 'creative_portfolio',
    ];

    public function __construct(
        protected ReadyTemplatesService $readyTemplates
    ) {}

    /**
     * Build blueprint (templateData) from requirement config.
     * Uses config('business_template_map') for business_type → template_slug and optional design_preference override.
     *
     * @param  array{siteType: string, businessType?: string, designStyle: string, design_preference?: string, payments?: array, shipping?: array, modules?: array, homepageSections?: array}  $config
     * @return array{name: string, theme_preset: string, default_pages: array<int, array{slug: string, title: string, sections: array<int, array{key: string, props: array}>}>}
     */
    public function configToBlueprint(array $config): array
    {
        $siteType = trim((string) Arr::get($config, 'siteType', 'ecommerce'));
        $businessType = $this->normalizeBusinessType(trim((string) Arr::get($config, 'businessType', '')));
        $designStyle = trim((string) Arr::get($config, 'designStyle', 'luxury_minimal'));
        $designPreference = trim((string) Arr::get($config, 'design_preference', $designStyle));

        if ($siteType === 'ecommerce' && $businessType !== '') {
            [$slug, $themePreset] = $this->resolveTemplateAndThemeFromMap($businessType, $designPreference);
            $templateData = $this->readyTemplates->loadBySlug($slug);
            if ($templateData !== [] && isset($templateData['theme_preset'], $templateData['default_pages'])) {
                $preset = $themePreset !== null ? $themePreset : (string) $templateData['theme_preset'];

                return [
                    'name' => (string) Arr::get($templateData, 'name', 'Store'),
                    'theme_preset' => $preset,
                    'default_pages' => $templateData['default_pages'],
                ];
            }
        }

        $themePreset = self::DESIGN_STYLE_TO_PRESET[$designStyle] ?? config('business_template_map.fallback_theme_preset', 'luxury_minimal');

        $defaultPages = $siteType === 'ecommerce'
            ? $this->buildEcommercePages($config)
            : $this->buildGenericPages($config);

        $name = $siteType === 'ecommerce' ? 'Store' : 'Website';

        return [
            'name' => $name,
            'theme_preset' => $themePreset,
            'default_pages' => $defaultPages,
        ];
    }

    /**
     * Resolve template slug and optional theme preset from config business_template_map.
     *
     * @return array{0: string, 1: string|null} [template_slug, theme_preset or null to use template default]
     */
    private function resolveTemplateAndThemeFromMap(string $businessType, string $designPreference): array
    {
        $map = config('business_template_map.business_type_to_template', []);
        $byPref = config('business_template_map.by_design_preference', []);
        $fallback = config('business_template_map.fallback_template', 'ecommerce-storefront');
        $themeFallback = config('business_template_map.fallback_theme_preset', 'luxury_minimal');

        $slug = $map[$businessType] ?? $fallback;
        $themePreset = null;

        $prefKey = $this->designPreferenceKey($designPreference);
        if (isset($byPref[$businessType]) && is_array($byPref[$businessType])) {
            $override = $byPref[$businessType][$prefKey] ?? $byPref[$businessType][$designPreference] ?? null;
            if (is_array($override)) {
                $slug = $override[0] ?? $slug;
                $themePreset = $override[1] ?? $themeFallback;
            }
        }

        return [$slug, $themePreset];
    }

    private function normalizeBusinessType(string $value): string
    {
        if ($value === '' || $value === 'general') {
            return '';
        }

        return str_replace(' ', '_', strtolower($value));
    }

    private function designPreferenceKey(string $designPreference): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', $designPreference));
        $aliases = [
            'luxury_minimal' => 'luxury_minimal',
            'minimal' => 'luxury_minimal',
            'clean_minimal' => 'luxury_minimal',
            'dark' => 'dark_modern',
            'dark_modern' => 'dark_modern',
            'bold' => 'bold_startup',
            'bold_startup' => 'bold_startup',
            'soft' => 'soft_pastel',
            'soft_pastel' => 'soft_pastel',
            'colorful' => 'soft_pastel',
            'modern' => 'corporate_clean',
            'corporate_clean' => 'corporate_clean',
            'luxury' => 'luxury_minimal',
            'playful' => 'bold_startup',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{slug: string, title: string, sections: array<int, array{key: string, props: array}>}>
     */
    private function buildEcommercePages(array $config): array
    {
        $homeSections = $this->buildHomeSections($config);
        $hasBlog = in_array('blog', (array) Arr::get($config, 'modules', []), true);

        $pages = [
            ['slug' => 'home', 'title' => 'Home', 'sections' => $homeSections],
            [
                'slug' => 'shop',
                'title' => 'Shop',
                'sections' => [
                    ['key' => 'webu_ecom_product_search_01', 'props' => ['title' => 'Search', 'variant' => 'inline']],
                    ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'All Products', 'show_filters' => true, 'show_sort' => true, 'pagination_mode' => 'pagination']],
                ],
            ],
            [
                'slug' => 'product',
                'title' => 'Product',
                'sections' => [
                    ['key' => 'webu_ecom_product_gallery_01', 'props' => ['product_slug' => '{{route.params.slug}}']],
                    ['key' => 'webu_ecom_product_detail_01', 'props' => ['product_slug' => '{{route.params.slug}}']],
                    ['key' => 'webu_ecom_product_tabs_01', 'props' => ['product_slug' => '{{route.params.slug}}']],
                ],
            ],
            [
                'slug' => 'cart',
                'title' => 'Cart',
                'sections' => [
                    ['key' => 'webu_ecom_cart_page_01', 'props' => ['title' => 'Your Cart', 'show_coupon_slot' => true, 'show_order_summary' => true]],
                ],
            ],
            [
                'slug' => 'checkout',
                'title' => 'Checkout',
                'sections' => [
                    ['key' => 'webu_ecom_checkout_form_01', 'props' => ['title' => 'Checkout', 'variant' => 'combined']],
                ],
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'sections' => [
                    ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Contact Us']],
                    ['key' => 'webu_general_text_01', 'props' => ['title' => 'Get in touch', 'body' => 'Email or call us for support.']],
                ],
            ],
        ];

        if ($hasBlog) {
            $pages[] = [
                'slug' => 'blog',
                'title' => 'Blog',
                'sections' => [
                    ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Blog']],
                    ['key' => 'webu_general_blog_list_01', 'props' => ['title' => 'Latest posts']],
                ],
            ];
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{key: string, props: array}>
     */
    private function buildHomeSections(array $config): array
    {
        $sections = [];
        $homepageSections = (array) Arr::get($config, 'homepageSections', ['hero', 'categories', 'featured_products', 'testimonials']);

        if (in_array('hero', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Welcome to Our Store', 'subtitle' => 'Discover quality products.', 'layout_variant' => 'centered', 'style_variant' => 'minimal']];
        }
        if (in_array('categories', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Shop by Category']];
        }
        if (in_array('featured_products', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Featured Products', 'products_per_page' => 8, 'show_filters' => false, 'show_sort' => false, 'layout_variant' => 'standard', 'style_variant' => 'softshadow']];
        }
        if (in_array('best_sellers', $homepageSections, true) || in_array('featured_products', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 4]];
        }
        if (in_array('testimonials', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_general_testimonials_01', 'props' => ['title' => 'What Our Customers Say', 'layout_variant' => 'carousel', 'style_variant' => 'card']];
        }
        if (in_array('promo_banner', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_general_cta_banner_01', 'props' => ['headline' => 'Special offer', 'subtitle' => 'Free shipping on orders over 100 GEL']];
        }
        if (in_array('newsletter', $homepageSections, true)) {
            $sections[] = ['key' => 'webu_general_newsletter_01', 'props' => ['title' => 'Subscribe to our newsletter']];
        }

        if ($sections === []) {
            $sections = [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Welcome to Our Store', 'subtitle' => 'Discover quality products.']],
                ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Featured Products', 'products_per_page' => 8]],
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{slug: string, title: string, sections: array<int, array{key: string, props: array}>}>
     */
    private function buildGenericPages(array $config): array
    {
        return [
            ['slug' => 'home', 'title' => 'Home', 'sections' => $this->buildHomeSections($config)],
            ['slug' => 'contact', 'title' => 'Contact', 'sections' => [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Contact Us']],
                ['key' => 'webu_general_text_01', 'props' => ['title' => 'Get in touch', 'body' => 'Email or call us.']],
            ]],
        ];
    }
}
