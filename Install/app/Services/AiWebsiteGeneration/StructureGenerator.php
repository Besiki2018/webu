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
        $sectionSlugMap = [
            'home' => ['hero', 'features', 'cta'],
            'about' => ['heading', 'content', 'cta'],
            'services' => ['heading', 'features', 'cta'],
            'contact' => ['heading', 'contact'],
            'shop' => ['hero', 'features', 'cta'],
            'work' => ['heading', 'gallery', 'cta'],
        ];
        $types = $sectionSlugMap[$slug] ?? ['heading', 'content', 'cta'];
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
            'hero' => [
                'type' => 'hero',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'cta_text', 'label' => 'Button text', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'Button link', 'type' => 'link'],
                    ['key' => 'image', 'label' => 'Hero image', 'type' => 'image'],
                ],
                'default_content' => ['title' => '', 'subtitle' => '', 'cta_text' => '', 'cta_link' => '/contact', 'image' => ''],
                'default_style' => ['alignment' => 'center', 'spacing' => 'medium'],
            ],
            'features' => [
                'type' => 'features',
                'cms_fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'Features', 'type' => 'repeater'],
                ],
                'default_content' => ['heading' => '', 'items' => []],
                'default_style' => ['columns' => 3],
            ],
            'cta' => [
                'type' => 'cta',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'button_text', 'label' => 'Button text', 'type' => 'text'],
                    ['key' => 'button_link', 'label' => 'Button link', 'type' => 'link'],
                ],
                'default_content' => ['title' => '', 'button_text' => '', 'button_link' => ''],
                'default_style' => ['alignment' => 'center'],
            ],
            'heading' => [
                'type' => 'heading',
                'cms_fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                ],
                'default_content' => ['title' => '', 'subtitle' => ''],
                'default_style' => [],
            ],
            'content' => [
                'type' => 'content',
                'cms_fields' => [
                    ['key' => 'body', 'label' => 'Content', 'type' => 'richtext'],
                ],
                'default_content' => ['body' => ''],
                'default_style' => [],
            ],
            'contact' => [
                'type' => 'contact',
                'cms_fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                    ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ],
                'default_content' => ['heading' => 'Contact', 'email' => '', 'phone' => ''],
                'default_style' => [],
            ],
            'gallery' => [
                'type' => 'gallery',
                'cms_fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'images', 'label' => 'Images', 'type' => 'images'],
                ],
                'default_content' => ['heading' => '', 'images' => []],
                'default_style' => ['columns' => 3],
            ],
        ];
    }
}
