<?php

namespace App\Services\AiWebsiteGeneration;

use Illuminate\Support\Str;

/**
 * Optional: for websiteType=ecommerce, generate demo categories and products.
 * Store in website_categories, website_products (or existing ecommerce tables).
 *
 * This stub returns empty data; extend when website_categories/website_products
 * (or equivalent) exist and Shop/Product pages are wired to CMS product data.
 *
 * Target: 6–10 categories, 12–24 products, short descriptions + bullet features.
 */
class EcommerceDataGenerator
{
    /**
     * @param  array{websiteType: string, businessType?: string|null, brandName: string}  $brief
     * @return array{categories: array<int, array{name: string, slug: string}>, products: array<int, array{name: string, slug: string, description: string, features: array<int, string>, category_slug: string}>}
     */
    public function generate(array $brief): array
    {
        if (($brief['websiteType'] ?? '') !== 'ecommerce') {
            return ['categories' => [], 'products' => []];
        }

        $brand = $brief['brandName'] ?? 'Shop';
        $categories = $this->defaultCategories($brief['businessType'] ?? null);
        $products = [];
        foreach ($categories as $cat) {
            for ($i = 0; $i < 2; $i++) {
                $products[] = [
                    'name' => "{$cat['name']} product " . ($i + 1),
                    'slug' => Str::slug($cat['slug'] . '-product-' . ($i + 1)),
                    'description' => 'Short description. Edit in CMS.',
                    'features' => ['Feature one', 'Feature two'],
                    'category_slug' => $cat['slug'],
                ];
            }
        }

        return ['categories' => $categories, 'products' => $products];
    }

    /** @return array<int, array{name: string, slug: string}> */
    private function defaultCategories(?string $businessType): array
    {
        $map = [
            'electronics' => [['name' => 'Phones', 'slug' => 'phones'], ['name' => 'Laptops', 'slug' => 'laptops']],
            'fashion' => [['name' => 'Men', 'slug' => 'men'], ['name' => 'Women', 'slug' => 'women']],
        ];
        $cats = $map[$businessType] ?? [['name' => 'Category 1', 'slug' => 'category-1'], ['name' => 'Category 2', 'slug' => 'category-2']];
        return array_slice($cats, 0, 10);
    }
}
