<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceProduct;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommercePublicApiCachingBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_catalog_endpoints_emit_short_public_cache_headers(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Caching Baseline Product',
            'slug' => 'caching-baseline-product',
            'sku' => 'CACHE-BASE-1',
            'price' => '25.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        $listResponse = $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('products.0.slug', $product->slug);
        $this->assertCacheHeader($listResponse, 'public, max-age=60');

        $productResponse = $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => $product->slug]))
            ->assertOk()
            ->assertJsonPath('product.slug', $product->slug);
        $this->assertCacheHeader($productResponse, 'public, max-age=60');
    }

    public function test_stateful_storefront_endpoints_emit_no_store_cache_headers(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'No Store Cart Product',
            'slug' => 'no-store-cart-product',
            'sku' => 'CACHE-BASE-2',
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        $createCartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer Cache',
        ])->assertCreated();
        $this->assertCacheHeader($createCartResponse, 'no-store');

        $cartId = (string) $createCartResponse->json('cart.id');
        $this->assertNotSame('', $cartId);

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $cartResponse = $this->getJson(route('public.sites.ecommerce.carts.show', ['site' => $site->id, 'cart' => $cartId]))
            ->assertOk()
            ->assertJsonPath('cart.id', $cartId);
        $this->assertCacheHeader($cartResponse, 'no-store');

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer Cache',
        ])->assertCreated();
        $this->assertCacheHeader($checkoutResponse, 'no-store');

        $trackResponse = $this->getJson(route('public.sites.ecommerce.shipments.track', [
            'site' => $site->id,
            'order_number' => 'ORD-TEST-1',
        ]))->assertStatus(422);
        $this->assertCacheHeader($trackResponse, 'no-store');
    }

    private function assertCacheHeader(TestResponse $response, string $expected): void
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        foreach (array_map('trim', explode(',', strtolower($expected))) as $token) {
            if ($token !== '') {
                $this->assertStringContainsString($token, $cacheControl);
            }
        }
        $this->assertSame('*', (string) $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array{0: Project, 1: Site}
     */
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
