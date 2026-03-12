<?php

namespace App\Services;

/**
 * Enforces required section order and presence for ecommerce pages.
 * Director uses this to validate/auto-fix blueprint structure.
 *
 * @see new tasks.txt — AI Design Director PART 4
 */
class SectionCompositionRules
{
    public const REQUIRED_HOME_ORDER = [
        'hero',
        'categories_or_collections',
        'featured_products',
        'promo',
        'best_sellers',
        'testimonials',
        'newsletter',
    ];

    public const REQUIRED_SHOP = ['filters_search', 'product_grid', 'pagination'];

    public const REQUIRED_PRODUCT = ['gallery', 'price_cta', 'details', 'shipping_info', 'related_products'];

    public const REQUIRED_CART = ['items', 'totals', 'checkout_cta'];

    public const REQUIRED_CHECKOUT = ['address', 'shipping', 'payment', 'confirmation'];

    public const REQUIRED_CONTACT = ['form', 'company_info', 'socials'];

    /**
     * Check if page has required section types (by inferred type from component key).
     *
     * @param  array<int, array{type?: string, key?: string}>  $sections
     * @param  array<int, string>  $required  e.g. ['hero','featured_products']
     * @return array{valid: bool, missing: array<int, string>}
     */
    public function validatePageSections(array $sections, array $required): array
    {
        $types = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = $this->inferSectionType($section);
            if ($type !== '') {
                $types[] = $type;
            }
        }
        $missing = [];
        foreach ($required as $need) {
            if (! $this->typesContain($types, $need)) {
                $missing[] = $need;
            }
        }
        return [
            'valid' => $missing === [],
            'missing' => $missing,
        ];
    }

    /**
     * Required section keys for home (conceptual).
     *
     * @return array<int, string>
     */
    public function requiredHomeConceptual(): array
    {
        return self::REQUIRED_HOME_ORDER;
    }

    public function requiredShopConceptual(): array
    {
        return self::REQUIRED_SHOP;
    }

    public function requiredProductConceptual(): array
    {
        return self::REQUIRED_PRODUCT;
    }

    public function requiredCartConceptual(): array
    {
        return self::REQUIRED_CART;
    }

    public function requiredCheckoutConceptual(): array
    {
        return self::REQUIRED_CHECKOUT;
    }

    public function requiredContactConceptual(): array
    {
        return self::REQUIRED_CONTACT;
    }

    private function inferSectionType(array $section): string
    {
        $key = (string) ($section['type'] ?? $section['key'] ?? '');
        $key = strtolower($key);
        if (str_contains($key, 'heading') || str_contains($key, 'hero')) {
            return 'hero';
        }
        if (str_contains($key, 'product_grid') || str_contains($key, 'product_carousel')) {
            return 'featured_products';
        }
        if (str_contains($key, 'categor')) {
            return 'categories_or_collections';
        }
        if (str_contains($key, 'testimonial')) {
            return 'testimonials';
        }
        if (str_contains($key, 'newsletter')) {
            return 'newsletter';
        }
        if (str_contains($key, 'cart')) {
            return 'items';
        }
        if (str_contains($key, 'checkout')) {
            return 'address';
        }
        if (str_contains($key, 'search') || str_contains($key, 'filter')) {
            return 'filters_search';
        }
        if (str_contains($key, 'contact') && str_contains($key, 'form')) {
            return 'form';
        }
        return $key;
    }

    private function typesContain(array $types, string $need): bool
    {
        $aliases = [
            'hero' => ['hero', 'heading'],
            'categories_or_collections' => ['categories_or_collections', 'categor', 'collection'],
            'featured_products' => ['featured_products', 'product_grid', 'product_carousel'],
            'promo' => ['promo', 'banner', 'cta'],
            'best_sellers' => ['best_sellers', 'product_grid'],
            'testimonials' => ['testimonials'],
            'newsletter' => ['newsletter'],
            'filters_search' => ['filters_search', 'search', 'filter'],
            'product_grid' => ['product_grid', 'product_carousel'],
            'pagination' => ['pagination', 'product_grid'],
            'items' => ['items', 'cart'],
            'totals' => ['totals', 'cart', 'summary'],
            'checkout_cta' => ['checkout_cta', 'cart'],
            'address' => ['address', 'checkout'],
            'form' => ['form', 'contact'],
        ];
        $check = $aliases[$need] ?? [$need];
        foreach ($types as $t) {
            foreach ($check as $c) {
                if (str_contains($t, $c) || str_contains($c, $t)) {
                    return true;
                }
            }
        }
        return false;
    }
}
