<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Validates AI output: reject raw HTML/CSS, unknown component types, unknown bindings.
 *
 * @see new tasks.txt — Quality & Safety Guards 5.1
 */
class AiOutputValidator
{
    /**
     * Known section/component keys (Webu components only).
     *
     * @var array<int, string>
     */
    protected const KNOWN_SECTION_KEYS = [
        'webu_header_01',
        'webu_footer_01',
        'webu_general_hero_01',
        'webu_general_features_01',
        'webu_general_cards_01',
        'webu_general_grid_01',
        'webu_general_cta_01',
        'webu_general_heading_01',
        'webu_general_text_01',
        'webu_general_banner_01',
        'webu_general_form_wrapper_01',
        'webu_general_testimonials_01',
        'webu_general_newsletter_01',
        'webu_general_cta_banner_01',
        'webu_general_blog_list_01',
        'webu_ecom_product_grid_01',
        'webu_ecom_product_carousel_01',
        'webu_ecom_product_search_01',
        'webu_ecom_product_gallery_01',
        'webu_ecom_product_detail_01',
        'webu_ecom_product_tabs_01',
        'webu_ecom_cart_page_01',
        'webu_ecom_cart_icon_01',
        'webu_ecom_checkout_form_01',
        'webu_ecom_coupon_01',
        'section',
        'container',
        'grid',
    ];

    /**
     * Allowed binding sources (CMS).
     *
     * @var array<int, string>
     */
    protected const ALLOWED_BINDING_SOURCES = [
        'products',
        'product_by_slug',
        'categories',
        'category_by_slug',
        'cart',
        'checkout',
        'orders',
        'pages',
        'site_settings',
        'tenant_payment_providers',
        'shipping_methods',
    ];

    /**
     * @param  array<string, mixed>  $output  AI generation output (pages, theme, etc.)
     * @return array{valid: bool, errors: array<int, array{code: string, path: string, message: string}>}
     */
    public function validate(array $output): array
    {
        $errors = [];

        $this->rejectRawHtmlCss($output, $errors);
        $this->validatePages($output, $errors);
        $this->validateBindings($output, $errors);
        $this->validateRequiredPages($output, $errors);

        return [
            'valid' => $errors === [],
            'errors' => array_values($errors),
        ];
    }

    /**
     * @param  array<int, array{code: string, path: string, message: string}>  $errors
     */
    private function rejectRawHtmlCss(array $output, array &$errors): void
    {
        $haystack = json_encode($output);
        if (Str::contains($haystack, '<html') || Str::contains($haystack, '<div ') || Str::contains($haystack, '<section')) {
            $errors[] = [
                'code' => 'raw_html_forbidden',
                'path' => '$',
                'message' => 'AI output must not contain raw HTML.',
            ];
        }
        if (preg_match('/\b(?:margin|padding|font-size)\s*:\s*[^;]+;/', $haystack) && Str::contains($haystack, 'style=')) {
            $errors[] = [
                'code' => 'raw_css_forbidden',
                'path' => '$',
                'message' => 'AI output must not inject raw CSS; use theme tokens only.',
            ];
        }
    }

    /**
     * @param  array<int, array{code: string, path: string, message: string}>  $errors
     */
    private function validatePages(array $output, array &$errors): void
    {
        $pages = $output['pages'] ?? [];
        if (! is_array($pages)) {
            return;
        }
        foreach ($pages as $i => $page) {
            if (! is_array($page)) {
                continue;
            }
            $sections = $page['builder_nodes'] ?? $page['sections'] ?? [];
            foreach ($sections as $j => $section) {
                if (! is_array($section)) {
                    continue;
                }
                $type = (string) ($section['type'] ?? $section['key'] ?? '');
                if ($type === '') {
                    $errors[] = [
                        'code' => 'missing_section_type',
                        'path' => "\$.pages[{$i}].sections[{$j}]",
                        'message' => 'Section must have type or key.',
                    ];
                    continue;
                }
                if (! $this->isKnownSectionKey($type)) {
                    $errors[] = [
                        'code' => 'unknown_component_type',
                        'path' => "\$.pages[{$i}].sections[{$j}]",
                        'message' => "Unknown component type: {$type}. Only Webu component keys are allowed.",
                    ];
                }
            }
        }
    }

    /**
     * @param  array<int, array{code: string, path: string, message: string}>  $errors
     */
    private function validateBindings(array $output, array &$errors): void
    {
        $pages = $output['pages'] ?? [];
        if (! is_array($pages)) {
            return;
        }
        foreach ($pages as $i => $page) {
            $sections = $page['builder_nodes'] ?? $page['sections'] ?? [];
            foreach ($sections as $j => $section) {
                if (! is_array($section)) {
                    continue;
                }
                $binding = $section['binding'] ?? $section['bindings'] ?? [];
                if (! is_array($binding)) {
                    continue;
                }
                $source = $binding['source'] ?? $binding['data'] ?? null;
                if ($source !== null && is_string($source) && ! in_array($source, self::ALLOWED_BINDING_SOURCES, true)) {
                    $errors[] = [
                        'code' => 'unknown_binding_source',
                        'path' => "\$.pages[{$i}].sections[{$j}].binding",
                        'message' => "Unknown binding source: {$source}. Allowed: " . implode(', ', self::ALLOWED_BINDING_SOURCES),
                    ];
                }
            }
        }
    }

    /**
     * @param  array<int, array{code: string, path: string, message: string}>  $errors
     */
    private function validateRequiredPages(array $output, array &$errors): void
    {
        $pages = $output['pages'] ?? [];
        if (! is_array($pages)) {
            $errors[] = ['code' => 'missing_pages', 'path' => '$.pages', 'message' => 'Output must include pages array.'];
            return;
        }
        $slugs = [];
        foreach ($pages as $page) {
            if (is_array($page) && isset($page['slug'])) {
                $slugs[] = (string) $page['slug'];
            }
        }
        $required = ['home', 'shop', 'product', 'cart', 'checkout', 'contact'];
        foreach ($required as $slug) {
            if (! in_array($slug, $slugs, true)) {
                $errors[] = [
                    'code' => 'required_page_missing',
                    'path' => '$.pages',
                    'message' => "Required ecommerce page missing: {$slug}.",
                ];
            }
        }
    }

    private function isKnownSectionKey(string $key): bool
    {
        $key = Str::lower(trim($key));
        foreach (self::KNOWN_SECTION_KEYS as $known) {
            if ($key === Str::lower($known)) {
                return true;
            }
        }
        if (Str::startsWith($key, 'webu_')) {
            return true;
        }
        return in_array($key, ['section', 'container', 'grid'], true);
    }
}
