<?php

namespace App\Services;

use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductImage;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotent e-commerce demo data seeder for a site.
 * Seeds: 6+ categories, 24+ products, 1-3 images per product, prices/discounts, stock, featured/best-seller.
 * Run via EcommerceDemoSeeder (CLI) or during provisioning when "Provision demo store" is chosen.
 */
class EcommerceDemoSeederService
{
    private const SEED_MARKER_KEY = 'ecommerce_demo_seeded_at';

    public function __construct(
        protected EcommerceInventoryServiceContract $inventory
    ) {}

    /**
     * Seed demo e-commerce data for the site. Idempotent: skips if already seeded unless $force.
     */
    public function run(Site $site, bool $force = false): void
    {
        $site = $site->fresh();
        if (! $site) {
            return;
        }

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        if (! $force && ! empty($settings[self::SEED_MARKER_KEY])) {
            return;
        }

        DB::transaction(function () use ($site): void {
            $categoryIds = $this->ensureCategories($site);
            $this->ensureProductsAndImages($site, $categoryIds);
            $this->markSeeded($site);
        }, 3);
    }

    /**
     * @return array<string, int>
     */
    private function ensureCategories(Site $site): array
    {
        $definitions = [
            ['slug' => 'featured', 'name' => 'Featured', 'sort_order' => 1],
            ['slug' => 'best-sellers', 'name' => 'Best Sellers', 'sort_order' => 2],
            ['slug' => 'new-arrivals', 'name' => 'New Arrivals', 'sort_order' => 3],
            ['slug' => 'sale', 'name' => 'Sale', 'sort_order' => 4],
            ['slug' => 'accessories', 'name' => 'Accessories', 'sort_order' => 5],
            ['slug' => 'essentials', 'name' => 'Essentials', 'sort_order' => 6],
        ];

        $ids = [];
        foreach ($definitions as $def) {
            $cat = EcommerceCategory::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'description' => 'Demo category.',
                    'status' => 'active',
                    'sort_order' => $def['sort_order'],
                    'meta_json' => ['demo' => true],
                ]
            );
            $ids[$def['slug']] = $cat->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $categoryIds
     */
    private function ensureProductsAndImages(Site $site, array $categoryIds): void
    {
        $baseProducts = $this->getDemoProductDefinitions($categoryIds);
        $productSlugs = array_column($baseProducts, 'slug');

        foreach ($baseProducts as $index => $def) {
            $product = EcommerceProduct::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $def['slug'],
                ],
                [
                    'category_id' => $def['category_id'],
                    'name' => $def['name'],
                    'sku' => $def['sku'],
                    'short_description' => $def['short_description'] ?? 'Demo product.',
                    'description' => $def['description'] ?? 'Demo product description for storefront.',
                    'price' => $def['price'],
                    'compare_at_price' => $def['compare_at_price'] ?? null,
                    'currency' => $def['currency'] ?? 'GEL',
                    'status' => 'active',
                    'stock_tracking' => true,
                    'stock_quantity' => $def['stock_quantity'] ?? 10,
                    'allow_backorder' => false,
                    'is_digital' => false,
                    'weight_grams' => 350,
                    'attributes_json' => $def['attributes_json'] ?? [],
                    'seo_title' => $def['name'].' | '.$site->name,
                    'seo_description' => 'Demo product.',
                    'published_at' => now()->subDays(rand(0, 7)),
                ]
            );

            $this->ensureProductImages($site, $product, $def, $index);
            $this->inventory->syncInventorySnapshotForProduct($site, $product, reason: 'ecommerce_demo_seed');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDemoProductDefinitions(array $categoryIds): array
    {
        $featuredId = $categoryIds['featured'] ?? null;
        $bestSellersId = $categoryIds['best-sellers'] ?? null;
        $newId = $categoryIds['new-arrivals'] ?? null;
        $saleId = $categoryIds['sale'] ?? null;
        $accId = $categoryIds['accessories'] ?? null;
        $essId = $categoryIds['essentials'] ?? null;

        $products = [];
        $names = [
            'Smart Watch Pro', 'Wireless Headphones X', 'Home Aroma Set', 'Business Backpack Lite',
            'Minimal Desk Lamp', 'Ceramic Mug Set', 'Leather Card Holder', 'Cotton Throw Blanket',
            'Stainless Bottle', 'Wooden Phone Stand', 'Scented Candle Pack', 'Linen Napkins Set',
            'Portable Speaker', 'Notebook Pack', 'Desk Organizer', 'Cozy Slippers',
            'Glass Terrarium', 'Brass Bookends', 'Wool Scarf', 'Silk Pillowcase',
            'Bamboo Cutlery Set', 'Hand Cream Trio', 'Tea Sampler Box', 'Canvas Tote',
        ];
        $prices = [249.00, 179.00, 89.00, 129.00, 59.00, 34.00, 45.00, 72.00, 38.00, 28.00, 42.00, 55.00, 95.00, 18.00, 48.00, 35.00, 64.00, 52.00, 44.00, 68.00, 32.00, 26.00, 39.00, 22.00];
        $categories = [$featuredId, $bestSellersId, $newId, $saleId, $accId, $essId];
        $withDiscount = [0, 1, 2, 3, 6, 9, 12, 15, 18, 21];

        for ($i = 0; $i < 24; $i++) {
            $name = $names[$i] ?? 'Demo Product '.($i + 1);
            $slug = Str::slug($name).'-'.($i + 1);
            $price = $prices[$i] ?? 49.99;
            $compareAt = in_array($i, $withDiscount, true) ? round($price * 1.15, 2) : null;
            $products[] = [
                'slug' => $slug,
                'name' => $name,
                'sku' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'category_id' => $categories[$i % 6],
                'price' => number_format($price, 2, '.', ''),
                'compare_at_price' => $compareAt !== null ? number_format($compareAt, 2, '.', '') : null,
                'currency' => 'GEL',
                'stock_quantity' => rand(5, 50),
                'short_description' => 'Demo product short description.',
                'description' => 'Demo product full description for storefront.',
                'attributes_json' => [],
            ];
        }

        return $products;
    }

    private function ensureProductImages(Site $site, EcommerceProduct $product, array $def, int $index): void
    {
        $pathBase = 'site-media/'.$site->id.'/demo';
        $count = min(3, 1 + ($index % 3));
        $existing = EcommerceProductImage::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id)
            ->count();
        if ($existing >= $count) {
            return;
        }
        for ($i = 0; $i < $count; $i++) {
            EcommerceProductImage::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'product_id' => $product->id,
                    'sort_order' => $i,
                ],
                [
                    'path' => $pathBase.'/product-'.$product->id.'-'.$i.'.jpg',
                    'alt_text' => $product->name.($i > 0 ? ' ('.($i + 1).')' : ''),
                    'is_primary' => $i === 0,
                    'meta_json' => ['demo' => true],
                ]
            );
        }
    }

    private function markSeeded(Site $site): void
    {
        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $settings[self::SEED_MARKER_KEY] = now()->toIso8601String();
        $site->update(['theme_settings' => $settings]);
    }

    public function isSeeded(Site $site): bool
    {
        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];

        return ! empty($settings[self::SEED_MARKER_KEY]);
    }
}
