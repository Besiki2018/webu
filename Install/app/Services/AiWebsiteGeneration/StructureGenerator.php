<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Builds the initial CMS page composition using canonical reusable Webu components.
 * The goal is to keep generation component-library-first instead of falling back to
 * generic heading/text/banner-only scaffolds.
 */
class StructureGenerator
{
    /** @var array<string, array{type: string, cms_fields: array<int, array{key: string, label: string, type: string}>, default_content: array<string, mixed>, default_style: array<string, mixed>}> */
    private array $sectionTemplates = [];

    public function __construct()
    {
        $this->sectionTemplates = $this->defaultSectionTemplates();
    }

    /**
     * @param  array{websiteType: string, mustHavePages: array<int, string>, style: string, businessType?: string|null}  $brief
     * @return array{pages: array<int, array{slug: string, title: string, sections: array<int, array{section_type: string, cms_fields: array, content_json: array, style_json: array}>}>}
     */
    public function generate(array $brief): array
    {
        $pages = [];
        $pageSlugs = is_array($brief['mustHavePages'] ?? null) && $brief['mustHavePages'] !== []
            ? array_values(array_map('strval', $brief['mustHavePages']))
            : ['home', 'about', 'services', 'contact'];
        $pageTitles = $this->pageTitlesForSlugs($pageSlugs);

        foreach ($pageSlugs as $slug) {
            $pages[] = [
                'slug' => $slug,
                'title' => $pageTitles[$slug] ?? ucfirst($slug),
                'sections' => $this->sectionsForPage($slug, $brief),
            ];
        }

        return ['pages' => $pages];
    }

    /** @return array<int, array{section_type: string, cms_fields: array, content_json: array, style_json: array}> */
    private function sectionsForPage(string $slug, array $brief): array
    {
        $websiteType = (string) ($brief['websiteType'] ?? 'business');
        $types = $this->sectionTypesForPage($websiteType, $slug);

        return array_map(function (string $type): array {
            $template = $this->sectionTemplates[$type] ?? $this->sectionTemplates['webu_general_text_01'];

            return [
                'section_type' => $template['type'],
                'cms_fields' => $template['cms_fields'],
                'content_json' => $template['default_content'],
                'style_json' => $template['default_style'],
            ];
        }, $types);
    }

    /**
     * @return array<int, string>
     */
    private function sectionTypesForPage(string $websiteType, string $slug): array
    {
        return match ($websiteType) {
            'ecommerce' => match ($slug) {
                'home' => [
                    'webu_general_hero_01',
                    'webu_general_cards_01',
                    'webu_ecom_product_grid_01',
                    'webu_general_cta_01',
                ],
                'shop' => ['webu_general_heading_01', 'webu_ecom_product_grid_01'],
                'product' => ['webu_general_heading_01', 'webu_ecom_product_grid_01', 'webu_general_cta_01'],
                'cart', 'checkout' => ['webu_general_heading_01', 'webu_general_text_01'],
                'contact' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cta_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            'portfolio' => match ($slug) {
                'home' => [
                    'webu_general_hero_01',
                    'webu_general_grid_01',
                    'webu_general_testimonials_01',
                    'webu_general_cta_01',
                ],
                'work' => ['webu_general_grid_01', 'webu_general_testimonials_01'],
                'about' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cards_01'],
                'contact' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cta_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            'booking' => match ($slug) {
                'home' => [
                    'webu_general_hero_01',
                    'webu_general_cards_01',
                    'webu_general_testimonials_01',
                    'webu_general_cta_01',
                ],
                'services' => ['webu_general_cards_01', 'webu_general_testimonials_01'],
                'book' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cta_01'],
                'contact' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cta_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            default => match ($slug) {
                'home' => [
                    'webu_general_hero_01',
                    'webu_general_cards_01',
                    'webu_general_testimonials_01',
                    'webu_general_cta_01',
                ],
                'about' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_grid_01'],
                'services' => ['webu_general_cards_01', 'webu_general_cta_01'],
                'contact' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_general_cta_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
        };
    }

    /** @return array<string, string> */
    private function pageTitlesForSlugs(array $slugs): array
    {
        $map = [
            'home' => 'Home',
            'about' => 'About',
            'contact' => 'Contact',
            'services' => 'Services',
            'shop' => 'Shop',
            'product' => 'Product',
            'cart' => 'Cart',
            'checkout' => 'Checkout',
            'work' => 'Work',
            'book' => 'Book',
        ];

        $out = [];
        foreach ($slugs as $slug) {
            $out[$slug] = $map[$slug] ?? ucfirst($slug);
        }

        return $out;
    }

    /** @return array<string, array{type: string, cms_fields: array, default_content: array, default_style: array}> */
    private function defaultSectionTemplates(): array
    {
        return [
            'webu_general_hero_01' => [
                'type' => 'webu_general_hero_01',
                'cms_fields' => [
                    ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'richtext'],
                    ['key' => 'buttonText', 'label' => 'Primary button text', 'type' => 'text'],
                    ['key' => 'buttonLink', 'label' => 'Primary button link', 'type' => 'link'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['key' => 'imageAlt', 'label' => 'Image alt', 'type' => 'text'],
                ],
                'default_content' => [
                    'eyebrow' => '',
                    'title' => '',
                    'subtitle' => '',
                    'description' => '',
                    'buttonText' => '',
                    'buttonLink' => '/contact',
                    'secondaryButtonText' => '',
                    'secondaryButtonLink' => '',
                    'image' => '',
                    'imageAlt' => '',
                    'overlayImageUrl' => '',
                    'overlayImageAlt' => '',
                    'backgroundImage' => '',
                    'variant' => 'hero-1',
                    'alignment' => 'left',
                ],
                'default_style' => [
                    'layout_variant' => 'split',
                    'style_variant' => 'default',
                ],
            ],
            'webu_general_heading_01' => [
                'type' => 'webu_general_heading_01',
                'cms_fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                ],
                'default_content' => [
                    'headline' => '',
                    'title' => '',
                    'subtitle' => '',
                    'eyebrow' => '',
                    'layout_variant' => 'centered',
                    'style_variant' => 'minimal',
                ],
                'default_style' => ['alignment' => 'center', 'spacing' => 'medium'],
            ],
            'webu_general_text_01' => [
                'type' => 'webu_general_text_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'richtext'],
                ],
                'default_content' => ['title' => '', 'body' => ''],
                'default_style' => [],
            ],
            'webu_general_cards_01' => [
                'type' => 'webu_general_cards_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Section title', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'Cards', 'type' => 'collection'],
                ],
                'default_content' => [
                    'title' => '',
                    'items' => [
                        ['title' => '', 'description' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                        ['title' => '', 'description' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                        ['title' => '', 'description' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                    ],
                    'variant' => 'cards-1',
                ],
                'default_style' => ['backgroundColor' => '', 'textColor' => ''],
            ],
            'webu_general_grid_01' => [
                'type' => 'webu_general_grid_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Section title', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'Grid items', 'type' => 'collection'],
                ],
                'default_content' => [
                    'title' => '',
                    'items' => [
                        ['title' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                        ['title' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                        ['title' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                        ['title' => '', 'image' => '', 'imageAlt' => '', 'link' => '#'],
                    ],
                    'columns' => 3,
                    'variant' => 'grid-1',
                ],
                'default_style' => ['backgroundColor' => '', 'textColor' => ''],
            ],
            'webu_general_cta_01' => [
                'type' => 'webu_general_cta_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'buttonText', 'label' => 'Button text', 'type' => 'text'],
                    ['key' => 'buttonLink', 'label' => 'Button link', 'type' => 'link'],
                    ['key' => 'backgroundImage', 'label' => 'Background image', 'type' => 'image'],
                ],
                'default_content' => [
                    'title' => '',
                    'subtitle' => '',
                    'buttonText' => '',
                    'buttonLink' => '/contact',
                    'backgroundImage' => '',
                    'variant' => 'cta-1',
                ],
                'default_style' => ['backgroundColor' => '', 'textColor' => ''],
            ],
            'webu_general_testimonials_01' => [
                'type' => 'webu_general_testimonials_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Section title', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'Testimonials', 'type' => 'collection'],
                ],
                'default_content' => [
                    'title' => '',
                    'items' => [
                        ['author' => '', 'role' => '', 'quote' => '', 'avatar' => ''],
                        ['author' => '', 'role' => '', 'quote' => '', 'avatar' => ''],
                        ['author' => '', 'role' => '', 'quote' => '', 'avatar' => ''],
                    ],
                    'variant' => 'testimonials-1',
                ],
                'default_style' => [],
            ],
            'webu_general_banner_01' => [
                'type' => 'webu_general_banner_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'cta_label', 'label' => 'Button text', 'type' => 'text'],
                    ['key' => 'cta_url', 'label' => 'Button link', 'type' => 'link'],
                ],
                'default_content' => [
                    'title' => '',
                    'subtitle' => '',
                    'cta_label' => '',
                    'cta_url' => '/contact',
                    'variant' => 'banner-1',
                ],
                'default_style' => [],
            ],
            'webu_ecom_product_grid_01' => [
                'type' => 'webu_ecom_product_grid_01',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'products_per_page', 'label' => 'Products per page', 'type' => 'number'],
                ],
                'default_content' => [
                    'title' => '',
                    'subtitle' => '',
                    'add_to_cart_label' => 'Add to cart',
                    'products_per_page' => 8,
                    'show_filters' => false,
                    'show_sort' => false,
                    'pagination_mode' => 'pagination',
                ],
                'default_style' => ['layout_style' => 'grid'],
            ],
        ];
    }
}
