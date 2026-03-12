<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductImage;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CMS-to-storefront binding correctness: changes in CMS reflect on public API.
 * Covers: create/update product, price, discount, stock, images.
 */
class CmsToStorefrontBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_create_product_in_cms_appears_in_shop_and_product_slug(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'New CMS Product',
            'slug' => 'new-cms-product',
            'sku' => 'CMS-001',
            'price' => '49.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'new-cms-product')
            ->assertJsonPath('products.0.name', 'New CMS Product');

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'new-cms-product']))
            ->assertOk()
            ->assertJsonPath('product.slug', 'new-cms-product')
            ->assertJsonPath('product.price', '49.00');
    }

    public function test_update_price_storefront_reflects_new_price(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Price Update Product',
            'slug' => 'price-update-product',
            'sku' => 'PRICE-001',
            'price' => '100.00',
            'currency' => 'GEL',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'price-update-product']))
            ->assertJsonPath('product.price', '100.00');

        $product->update(['price' => '89.50']);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'price-update-product']))
            ->assertOk()
            ->assertJsonPath('product.price', '89.50');
    }

    public function test_add_discount_shows_compare_at_price_and_sale(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Discount Product',
            'slug' => 'discount-product',
            'sku' => 'DISC-001',
            'price' => '79.00',
            'compare_at_price' => '99.00',
            'currency' => 'GEL',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $response = $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'discount-product']))
            ->assertOk();
        $response->assertJsonPath('product.price', '79.00');
        $response->assertJsonPath('product.compare_at_price', '99.00');
    }

    public function test_update_stock_availability_reflects_on_storefront(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Stock Product',
            'slug' => 'stock-product',
            'sku' => 'STOCK-001',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'stock-product']))
            ->assertOk()
            ->assertJsonPath('product.stock_quantity', 5);

        $product->update(['stock_quantity' => 0]);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'stock-product']))
            ->assertOk()
            ->assertJsonPath('product.stock_quantity', 0);
    }

    public function test_update_images_storefront_shows_correct_image_set(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Image Product',
            'slug' => 'image-product',
            'sku' => 'IMG-001',
            'price' => '30.00',
            'currency' => 'GEL',
            'status' => 'active',
            'published_at' => now(),
        ]);

        EcommerceProductImage::query()->create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'path' => 'site-media/'.$site->id.'/products/primary.jpg',
            'alt_text' => 'Primary image',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $response = $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'image-product']))
            ->assertOk();
        $response->assertJsonStructure(['product' => ['images']]);
        $images = $response->json('product.images');
        $this->assertNotEmpty($images);
        $this->assertSame('Primary image', $images[0]['alt_text'] ?? null);
    }

    /** CMS stress: delete product → removed from storefront grid; verifies no stale cache. */
    public function test_delete_product_removed_from_storefront_grid(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'sku' => 'DEL-001',
            'price' => '25.00',
            'currency' => 'GEL',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'to-delete');

        $product->delete();

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk();
        $slugs = $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->json('products.*.slug') ?? [];
        $this->assertNotContains('to-delete', $slugs);
    }

    /** CMS stress: sequential requests return fresh data (no caching bug). */
    public function test_update_price_reflected_immediately_no_stale_cache(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Cache Test',
            'slug' => 'cache-test',
            'sku' => 'CACHE-001',
            'price' => '10.00',
            'currency' => 'GEL',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'cache-test']))
            ->assertJsonPath('product.price', '10.00');

        $product->update(['price' => '19.99']);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'cache-test']))
            ->assertOk()
            ->assertJsonPath('product.price', '19.99');
    }

    private function createPublishedProjectWithSite(User $owner, bool $enableEcommerce): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();
        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['ecommerce'] = $enableEcommerce;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }
}
