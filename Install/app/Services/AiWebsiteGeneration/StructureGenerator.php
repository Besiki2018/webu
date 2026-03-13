<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Builds SitePlan: pages and sections with cms_fields, content_json, style_json.
 * Uses allowed components only; each section has required CMS field mapping.
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
     * @param  array{websiteType: string, mustHavePages: array<int, string>, style: string}  $brief
     * @return array{pages: array<int, array{slug: string, title: string, sections: array<int, array{section_type: string, cms_fields: array, content_json: array, style_json: array}>}>}
     */
    public function generate(array $brief): array
    {
        $pages = [];
        $pageTitles = $this->pageTitlesForSlugs($brief['mustHavePages'] ?? ['home', 'about', 'contact']);
        foreach ($brief['mustHavePages'] as $index => $slug) {
            $title = $pageTitles[$slug] ?? ucfirst($slug);
            $sections = $this->sectionsForPage($slug, $brief);
            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'sections' => $sections,
            ];
        }
        return ['pages' => $pages];
    }

    /** @return array<int, array{section_type: string, cms_fields: array, content_json: array, style_json: array}> */
    private function sectionsForPage(string $slug, array $brief): array
    {
        $websiteType = (string) ($brief['websiteType'] ?? 'business');

        $types = match ($websiteType) {
            'ecommerce' => match ($slug) {
                'home' => ['webu_general_heading_01', 'webu_ecom_product_grid_01', 'webu_general_heading_01', 'webu_ecom_product_grid_01', 'banner'],
                'shop', 'product', 'cart', 'checkout' => ['webu_general_heading_01', 'webu_ecom_product_grid_01'],
                'contact' => ['webu_general_heading_01', 'webu_general_text_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            'portfolio' => match ($slug) {
                'home' => ['webu_general_heading_01', 'webu_general_text_01', 'banner'],
                'work', 'about', 'contact' => ['webu_general_heading_01', 'webu_general_text_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            'booking' => match ($slug) {
                'home' => ['webu_general_heading_01', 'webu_general_text_01', 'banner'],
                'services', 'book', 'contact' => ['webu_general_heading_01', 'webu_general_text_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
            default => match ($slug) {
                'home' => ['webu_general_heading_01', 'webu_general_text_01', 'banner'],
                'about', 'services', 'contact' => ['webu_general_heading_01', 'webu_general_text_01'],
                default => ['webu_general_heading_01', 'webu_general_text_01'],
            },
        };

        $sections = [];
        foreach ($types as $order => $type) {
            $template = $this->sectionTemplates[$type] ?? $this->sectionTemplates['content'];
            $sections[] = [
                'section_type' => $type,
                'cms_fields' => $template['cms_fields'],
                'content_json' => $template['default_content'],
                'style_json' => $template['default_style'],
            ];
        }
        return $sections;
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
        foreach ($slugs as $s) {
            $out[$s] = $map[$s] ?? ucfirst($s);
        }
        return $out;
    }

    /** @return array<string, array{type: string, cms_fields: array, default_content: array, default_style: array}> */
    private function defaultSectionTemplates(): array
    {
        return [
            'webu_general_heading_01' => [
                'type' => 'webu_general_heading_01',
                'cms_fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                ],
                'default_content' => ['headline' => '', 'title' => '', 'subtitle' => '', 'eyebrow' => '', 'layout_variant' => 'centered', 'style_variant' => 'minimal'],
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
            'banner' => [
                'type' => 'banner',
                'cms_fields' => [
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'cta_label', 'label' => 'Button text', 'type' => 'text'],
                    ['key' => 'cta_url', 'label' => 'Button link', 'type' => 'link'],
                ],
                'default_content' => ['headline' => '', 'title' => '', 'subtitle' => '', 'cta_label' => '', 'cta_url' => '/contact'],
                'default_style' => ['alignment' => 'center'],
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
            'content' => [
                'type' => 'webu_general_text_01',
                'cms_fields' => [
                    ['key' => 'body', 'label' => 'Content', 'type' => 'richtext'],
                ],
                'default_content' => ['title' => '', 'body' => ''],
                'default_style' => [],
            ],
        ];
    }
}
