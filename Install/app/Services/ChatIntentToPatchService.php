<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Parses chat intent and produces JSON patches (theme preset or section add).
 * All changes must produce JSON patches; no raw HTML/CSS.
 *
 * @see new tasks.txt — PART 7 AI Chat Editing
 */
class ChatIntentToPatchService
{
    /**
     * Map intent keywords to theme preset.
     *
     * @var array<string, string>
     */
    protected const THEME_INTENTS = [
        'darker' => 'dark_modern',
        'dark' => 'dark_modern',
        'make it dark' => 'dark_modern',
        'გაამუქ' => 'dark_modern',
        'მუქ' => 'dark_modern',
        'ბნელ' => 'dark_modern',
        'lighter' => 'luxury_minimal',
        'light' => 'luxury_minimal',
        'make it light' => 'luxury_minimal',
        'გაანათ' => 'luxury_minimal',
        'ნათელ' => 'luxury_minimal',
        'ღია' => 'luxury_minimal',
        'modern' => 'bold_startup',
        'თანამედროვე' => 'bold_startup',
        'minimal' => 'luxury_minimal',
        'მინიმალ' => 'luxury_minimal',
        'luxury' => 'luxury_minimal',
        'ლუქს' => 'luxury_minimal',
        'playful' => 'soft_pastel',
        'soft' => 'soft_pastel',
        'რბილ' => 'soft_pastel',
        'პასტელ' => 'soft_pastel',
        'corporate' => 'corporate_clean',
        'კორპორატ' => 'corporate_clean',
        'bold' => 'bold_startup',
        'გამოკვეთილ' => 'bold_startup',
    ];

    /**
     * Map intent to predefined section to add (key + default props).
     *
     * @var array<string, array{key: string, props: array}>
     */
    protected const SECTION_INTENTS = [
        'best_selling' => ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 8, 'show_filters' => false, 'show_sort' => true]],
        'best_sellers' => ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 8]],
        'best seller' => ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 8]],
        'ბესტსელერ' => ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 8]],
        'საუკეთესო გაყიდვ' => ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Best Sellers', 'products_per_page' => 8]],
        'testimonials' => ['key' => 'webu_general_testimonials_01', 'props' => ['title' => 'What Our Customers Say', 'layout_variant' => 'carousel', 'style_variant' => 'card']],
        'testimonial' => ['key' => 'webu_general_testimonials_01', 'props' => ['title' => 'What Our Customers Say', 'layout_variant' => 'carousel', 'style_variant' => 'card']],
        'მომხმარებელთა შეფას' => ['key' => 'webu_general_testimonials_01', 'props' => ['title' => 'What Our Customers Say', 'layout_variant' => 'carousel', 'style_variant' => 'card']],
        'რევიუ' => ['key' => 'webu_general_testimonials_01', 'props' => ['title' => 'What Our Customers Say', 'layout_variant' => 'carousel', 'style_variant' => 'card']],
        'categories' => ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Shop by Category', 'subtitle' => 'Browse our collections', 'layout_variant' => 'centered']],
        'category' => ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Shop by Category', 'subtitle' => 'Browse our collections', 'layout_variant' => 'centered']],
        'კატეგორი' => ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Shop by Category', 'subtitle' => 'Browse our collections', 'layout_variant' => 'centered']],
        'newsletter' => ['key' => 'webu_general_newsletter_01', 'props' => ['title' => 'Subscribe to our newsletter']],
        'subscribe' => ['key' => 'webu_general_newsletter_01', 'props' => ['title' => 'Subscribe to our newsletter']],
        'გამოწერ' => ['key' => 'webu_general_newsletter_01', 'props' => ['title' => 'Subscribe to our newsletter']],
        'ნიუსლეთ' => ['key' => 'webu_general_newsletter_01', 'props' => ['title' => 'Subscribe to our newsletter']],
        'promo_banner' => ['key' => 'webu_general_cta_banner_01', 'props' => ['headline' => 'Special offer', 'subtitle' => 'Free shipping on orders over 100']],
        'promo banner' => ['key' => 'webu_general_cta_banner_01', 'props' => ['headline' => 'Special offer', 'subtitle' => 'Free shipping on orders over 100']],
        'პრომო ბანერ' => ['key' => 'webu_general_cta_banner_01', 'props' => ['headline' => 'Special offer', 'subtitle' => 'Free shipping on orders over 100']],
        'აქციის ბანერ' => ['key' => 'webu_general_cta_banner_01', 'props' => ['headline' => 'Special offer', 'subtitle' => 'Free shipping on orders over 100']],
    ];

    /**
     * Parse user message and return applicable patch.
     *
     * @return array{type: 'theme_preset'|'add_section'|'none', patch: array<string, mixed>}
     */
    public function parse(string $message): array
    {
        $msg = Str::lower(trim($message));
        if ($msg === '') {
            return ['type' => 'none', 'patch' => []];
        }

        $themePatch = $this->parseThemeIntent($msg);
        if ($themePatch !== null) {
            return [
                'type' => 'theme_preset',
                'patch' => ['theme_preset' => $themePatch],
            ];
        }

        $sectionPatch = $this->parseSectionIntent($msg);
        if ($sectionPatch !== null) {
            return [
                'type' => 'add_section',
                'patch' => [
                    'page_slug' => $sectionPatch['page_slug'],
                    'section' => $sectionPatch['section'],
                    'index' => $sectionPatch['index'],
                ],
            ];
        }

        return ['type' => 'none', 'patch' => []];
    }

    private function parseThemeIntent(string $msg): ?string
    {
        foreach (self::THEME_INTENTS as $keyword => $preset) {
            if (Str::contains($msg, $keyword)) {
                return $preset;
            }
        }
        if (Str::contains($msg, 'make it') && (Str::contains($msg, 'dark') || Str::contains($msg, 'darker'))) {
            return 'dark_modern';
        }
        if ((Str::contains($msg, 'გაამუქ') || Str::contains($msg, 'გააკეთე')) && (Str::contains($msg, 'მუქ') || Str::contains($msg, 'ბნელ'))) {
            return 'dark_modern';
        }
        if (Str::contains($msg, 'change color') || Str::contains($msg, 'change colours')) {
            if (Str::contains($msg, 'dark')) {
                return 'dark_modern';
            }
            return 'luxury_minimal';
        }
        if (Str::contains($msg, 'ფერი') || Str::contains($msg, 'ფერები')) {
            if (Str::contains($msg, 'მუქ') || Str::contains($msg, 'ბნელ')) {
                return 'dark_modern';
            }
            if (Str::contains($msg, 'ღია') || Str::contains($msg, 'ნათელ')) {
                return 'luxury_minimal';
            }
        }
        return null;
    }

    /**
     * @return array{page_slug: string, section: array{key: string, props: array}, index: int}|null
     */
    private function parseSectionIntent(string $msg): ?array
    {
        $pageSlug = 'home';
        if (Str::contains($msg, 'homepage') || Str::contains($msg, 'home page')) {
            $pageSlug = 'home';
        }

        foreach (self::SECTION_INTENTS as $keyword => $def) {
            if (Str::contains($msg, $keyword)) {
                return [
                    'page_slug' => $pageSlug,
                    'section' => ['key' => $def['key'], 'props' => $def['props']],
                    'index' => -1,
                ];
            }
        }
        if (Str::contains($msg, 'add') && Str::contains($msg, 'best sell')) {
            return [
                'page_slug' => 'home',
                'section' => self::SECTION_INTENTS['best_sellers'],
                'index' => -1,
            ];
        }
        if (
            (Str::contains($msg, 'დაამატ') || Str::contains($msg, 'მატ')) &&
            (Str::contains($msg, 'ბესტსელერ') || Str::contains($msg, 'საუკეთესო გაყიდვ'))
        ) {
            return [
                'page_slug' => 'home',
                'section' => self::SECTION_INTENTS['best_sellers'],
                'index' => -1,
            ];
        }
        if (Str::contains($msg, 'show categor') || Str::contains($msg, 'categories on home')) {
            return [
                'page_slug' => 'home',
                'section' => self::SECTION_INTENTS['categories'],
                'index' => -1,
            ];
        }
        if (
            (Str::contains($msg, 'კატეგორი') || Str::contains($msg, 'კატეგორიები')) &&
            (Str::contains($msg, 'მთავარ') || Str::contains($msg, 'ჰოუმ'))
        ) {
            return [
                'page_slug' => 'home',
                'section' => self::SECTION_INTENTS['categories'],
                'index' => -1,
            ];
        }
        return null;
    }
}
