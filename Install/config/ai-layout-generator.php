<?php

/**
 * AI Layout Generator — component registry, layout presets, binding paths.
 * AI produces layout JSON only; no raw HTML. All content comes from CMS bindings.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Component registry: AI component key => section key (builder/cms)
    | Section key is used in content_json.sections[].type and in section library.
    |--------------------------------------------------------------------------
    */
    'component_registry' => [
        'hero' => 'hero',
        'hero-video' => 'hero',
        'hero-split' => 'hero',
        'product-card' => 'webu_ecom_product_grid_01',
        'product-grid' => 'webu_ecom_product_grid_01',
        'product-carousel' => 'webu_ecom_product_carousel_01',
        'category-grid' => 'webu_ecom_category_list_01',
        'category-slider' => 'webu_ecom_category_list_01',
        'banner' => 'hero',
        'cta' => 'hero',
        'newsletter' => 'webu_general_form_wrapper_01',
        'header' => 'hero',
        'footer' => 'footer',
        'cart' => 'webu_ecom_cart_page_01',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valid variants per component (for validation and AI hints)
    |--------------------------------------------------------------------------
    */
    'component_variants' => [
        'hero' => ['default', 'modern', 'minimal', 'split', 'video'],
        'product-grid' => ['default', 'premium', 'minimal', 'compact', 'classic'],
        'product-card' => ['classic', 'minimal', 'modern', 'premium', 'compact'],
        'category-grid' => ['default', 'cards', 'chips', 'slider'],
        'newsletter' => ['default', 'minimal', 'inline'],
        'header' => ['default', 'minimal', 'mega'],
        'footer' => ['default', 'minimal', 'mega'],
        'banner' => ['default', 'minimal', 'cta'],
        'cta' => ['default', 'minimal'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical CMS binding paths (dot notation). Used by AI and resolver.
    |--------------------------------------------------------------------------
    */
    'binding_paths' => [
        'site.logo',
        'site.name',
        'site.navigation',
        'site.hero.title',
        'site.hero.subtitle',
        'site.hero.image',
        'site.hero.cta_text',
        'site.hero.cta_url',
        'site.footer.links',
        'site.footer.contact',
        'site.footer.copyright',
        'products.featured',
        'products.latest',
        'categories.main',
        'categories.featured',
        'collections.sale',
        'banners.home',
        'banners.promo',
        'newsletter.form',
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout presets: named starting points for AI. AI may add/remove sections.
    | Each section: component (key), variant (optional), bindings (optional).
    |--------------------------------------------------------------------------
    */
    'presets' => [
        'ecommerce_default' => [
            'name' => 'Ecommerce Default',
            'description' => 'Hero, categories, featured products, promo banner, newsletter, footer',
            'sections' => [
                ['component' => 'hero', 'variant' => 'default', 'bindings' => ['title' => 'site.hero.title', 'subtitle' => 'site.hero.subtitle', 'image' => 'site.hero.image', 'ctaText' => 'site.hero.cta_text', 'ctaUrl' => 'site.hero.cta_url']],
                ['component' => 'category-grid', 'variant' => 'default', 'bindings' => ['categories' => 'categories.main', 'title' => 'site.categories_title']],
                ['component' => 'product-grid', 'variant' => 'default', 'bindings' => ['products' => 'products.featured', 'title' => 'site.featured_title']],
                ['component' => 'banner', 'variant' => 'cta', 'bindings' => ['title' => 'banners.promo.title', 'subtitle' => 'banners.promo.subtitle', 'ctaText' => 'banners.promo.cta_text', 'ctaUrl' => 'banners.promo.cta_url']],
                ['component' => 'newsletter', 'variant' => 'default', 'bindings' => ['title' => 'newsletter.form.title', 'subtitle' => 'newsletter.form.subtitle']],
                ['component' => 'footer', 'variant' => 'default', 'bindings' => ['logo' => 'site.logo', 'links' => 'site.footer.links', 'contact' => 'site.footer.contact', 'copyright' => 'site.footer.copyright']],
            ],
        ],
        'ecommerce_minimal' => [
            'name' => 'Ecommerce Minimal',
            'description' => 'Minimal hero, product grid, newsletter, footer',
            'sections' => [
                ['component' => 'hero', 'variant' => 'minimal', 'bindings' => ['title' => 'site.hero.title', 'subtitle' => 'site.hero.subtitle', 'ctaText' => 'site.hero.cta_text', 'ctaUrl' => 'site.hero.cta_url']],
                ['component' => 'product-grid', 'variant' => 'minimal', 'bindings' => ['products' => 'products.featured', 'title' => 'site.featured_title']],
                ['component' => 'newsletter', 'variant' => 'minimal', 'bindings' => ['title' => 'newsletter.form.title']],
                ['component' => 'footer', 'variant' => 'minimal', 'bindings' => ['logo' => 'site.logo', 'links' => 'site.footer.links', 'copyright' => 'site.footer.copyright']],
            ],
        ],
        'ecommerce_modern' => [
            'name' => 'Ecommerce Modern',
            'description' => 'Modern hero with image, category grid, featured products, CTA, newsletter, footer',
            'sections' => [
                ['component' => 'hero', 'variant' => 'modern', 'bindings' => ['title' => 'site.hero.title', 'subtitle' => 'site.hero.subtitle', 'image' => 'site.hero.image', 'ctaText' => 'site.hero.cta_text', 'ctaUrl' => 'site.hero.cta_url']],
                ['component' => 'category-grid', 'variant' => 'cards', 'bindings' => ['categories' => 'categories.main', 'title' => 'site.categories_title']],
                ['component' => 'product-grid', 'variant' => 'modern', 'bindings' => ['products' => 'products.featured', 'title' => 'site.featured_title']],
                ['component' => 'cta', 'variant' => 'default', 'bindings' => ['title' => 'banners.home.title', 'subtitle' => 'banners.home.subtitle', 'ctaText' => 'banners.home.cta_text', 'ctaUrl' => 'banners.home.cta_url']],
                ['component' => 'newsletter', 'variant' => 'default', 'bindings' => ['title' => 'newsletter.form.title', 'subtitle' => 'newsletter.form.subtitle']],
                ['component' => 'footer', 'variant' => 'default', 'bindings' => ['logo' => 'site.logo', 'links' => 'site.footer.links', 'contact' => 'site.footer.contact', 'copyright' => 'site.footer.copyright']],
            ],
        ],
        'ecommerce_premium' => [
            'name' => 'Ecommerce Premium',
            'description' => 'Premium hero, categories, featured products (premium cards), promo, newsletter, footer',
            'sections' => [
                ['component' => 'hero', 'variant' => 'modern', 'bindings' => ['title' => 'site.hero.title', 'subtitle' => 'site.hero.subtitle', 'image' => 'site.hero.image', 'ctaText' => 'site.hero.cta_text', 'ctaUrl' => 'site.hero.cta_url']],
                ['component' => 'category-grid', 'variant' => 'cards', 'bindings' => ['categories' => 'categories.featured', 'title' => 'site.categories_title']],
                ['component' => 'product-grid', 'variant' => 'premium', 'bindings' => ['products' => 'products.featured', 'title' => 'site.featured_title']],
                ['component' => 'banner', 'variant' => 'cta', 'bindings' => ['title' => 'banners.promo.title', 'subtitle' => 'banners.promo.subtitle', 'ctaText' => 'banners.promo.cta_text', 'ctaUrl' => 'banners.promo.cta_url']],
                ['component' => 'newsletter', 'variant' => 'default', 'bindings' => ['title' => 'newsletter.form.title', 'subtitle' => 'newsletter.form.subtitle']],
                ['component' => 'footer', 'variant' => 'mega', 'bindings' => ['logo' => 'site.logo', 'links' => 'site.footer.links', 'contact' => 'site.footer.contact', 'copyright' => 'site.footer.copyright']],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Design style => theme preset (for applyThemeToWorkspace / project theme_preset)
    |--------------------------------------------------------------------------
    */
    'design_style_to_theme_preset' => [
        'minimal' => 'default',
        'modern' => 'default',
        'premium' => 'luxury_minimal',
        'default' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe defaults for theme token generation (AI may override)
    |--------------------------------------------------------------------------
    */
    'theme_defaults' => [
        'primary_color' => '#111827',
        'secondary_color' => '#6b7280',
        'font_family' => 'Inter',
        'border_radius' => '12px',
    ],

    /*
    |--------------------------------------------------------------------------
    | Color scheme presets (name => primary, secondary for theme generator)
    |--------------------------------------------------------------------------
    */
    'color_schemes' => [
        'pastel' => ['primary' => '#E6B8D4', 'secondary' => '#7A7AF5'],
        'neutral' => ['primary' => '#111827', 'secondary' => '#6b7280'],
        'ocean' => ['primary' => '#0ea5e9', 'secondary' => '#06b6d4'],
        'forest' => ['primary' => '#059669', 'secondary' => '#10b981'],
        'luxury' => ['primary' => '#1e293b', 'secondary' => '#94a3b8'],
    ],
];
