<?php

namespace Tests\Feature\Templates;

use App\Models\EcommerceProduct;
use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** @group docs-sync */
class TemplateStorefrontE2eFlowMatrixSmokeTest extends TestCase
{
    use RefreshDatabase;

    private string $themeSlug = 'webu-shop-01';

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.demo_content.enabled' => true,
            'cms.demo_content.seed_in_testing' => true,
        ]);

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domains');
        SystemSetting::set('domain_base_domain', 'platform.example.com', 'string', 'domains');

        $this->sourceRoot = base_path('../themeplate/webu-shop');

        File::deleteDirectory(public_path("themes/{$this->themeSlug}"));
        File::deleteDirectory(base_path("templates/{$this->themeSlug}"));
        File::delete(storage_path("app/templates/{$this->themeSlug}-template.zip"));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path("themes/{$this->themeSlug}"));
        File::deleteDirectory(base_path("templates/{$this->themeSlug}"));
        File::delete(storage_path("app/templates/{$this->themeSlug}-template.zip"));

        parent::tearDown();
    }

    public function test_imported_template_supports_published_storefront_route_and_api_flow_matrix(): void
    {
        $this->assertDirectoryExists($this->sourceRoot);

        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => $this->themeSlug,
            '--name' => 'Webu Shop 01',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', $this->themeSlug)->firstOrFail();
        $owner = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->published('webu-e2e-matrix')
            ->create([
                'name' => 'Webu E2E Matrix',
                'template_id' => $template->id,
                'published_visibility' => 'public',
            ]);

        /** @var Site $site */
        $site = $project->site()->firstOrFail()->fresh();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $modules = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $modules['ecommerce'] = true;
        $settings['modules'] = $modules;
        $site->update(['theme_settings' => $settings]);
        $site = $site->fresh();

        $previewRoot = Storage::disk('local')->path("previews/{$project->id}");
        File::deleteDirectory($previewRoot);
        File::ensureDirectoryExists(dirname($previewRoot));
        $this->assertTrue(
            File::copyDirectory(public_path("themes/{$this->themeSlug}"), $previewRoot),
            'Failed to copy imported theme export into project preview directory.'
        );
        $this->assertFileExists($previewRoot.'/index.html');

        $this->ensurePublishedPage($site, $owner, 'shop', 'Shop', 'Shop Listing SEO');
        $this->ensurePublishedPage($site, $owner, 'product', 'Product', 'Product Detail SEO');
        $this->ensurePublishedPage($site, $owner, 'cart', 'Cart', 'Cart SEO');
        $this->ensurePublishedPage($site, $owner, 'checkout', 'Checkout', 'Checkout SEO');
        $this->ensurePublishedPage($site, $owner, 'login', 'Login', 'Login SEO');
        $this->ensurePublishedPage($site, $owner, 'account', 'Account', 'Account SEO');
        $this->ensurePublishedPage($site, $owner, 'orders', 'Orders', 'Orders SEO');
        $this->ensurePublishedPage($site, $owner, 'order', 'Order Detail', 'Order Detail SEO');

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Premium Dog Snack',
            'slug' => 'premium-dog-snack',
            'sku' => 'DOG-SNACK-E2E',
            'price' => '29.90',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 50,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('products.0.slug', $product->slug);

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => $product->slug]))
            ->assertOk()
            ->assertJsonPath('product.slug', $product->slug)
            ->assertJsonPath('product.price', '29.90');

        $createCart = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer Matrix',
        ])->assertCreated();

        $cartId = (string) $createCart->json('cart.id');
        $this->assertNotSame('', $cartId);

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '59.80')
            ->assertJsonPath('cart.grand_total', '59.80');

        $this->getJson(route('public.sites.ecommerce.carts.show', ['site' => $site->id, 'cart' => $cartId]))
            ->assertOk()
            ->assertJsonPath('cart.id', $cartId)
            ->assertJsonPath('cart.items.0.product_slug', $product->slug)
            ->assertJsonPath('cart.items.0.quantity', 2);

        $checkout = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer Matrix',
            'shipping_address_json' => [
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 1',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.grand_total', '59.80')
            ->assertJsonPath('order.outstanding_total', '59.80');

        $orderId = (int) $checkout->json('order.id');
        $this->assertGreaterThan(0, $orderId);

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $orderId,
            'site_id' => $site->id,
            'customer_email' => 'buyer@example.com',
            'grand_total' => '59.80',
        ]);

        $host = 'webu-e2e-matrix.platform.example.com';

        $this->assertPublishedRouteHtml($host, '/shop', 'Shop Listing SEO');
        $this->assertPublishedRouteHtml($host, '/product/'.$product->slug, 'Product Detail SEO');
        $this->assertPublishedRouteHtml($host, '/cart', 'Cart SEO');
        $this->assertPublishedRouteHtml($host, '/checkout', 'Checkout SEO');
        $this->assertPublishedRouteHtml($host, '/account/login', 'Login SEO');
        $this->assertPublishedRouteHtml($host, '/account', 'Account SEO');
        $this->assertPublishedRouteHtml($host, '/account/orders', 'Orders SEO');
        $this->assertPublishedRouteHtml($host, '/account/orders/'.$orderId, 'Order Detail SEO');
    }

    private function ensurePublishedPage(Site $site, User $owner, string $slug, string $title, string $seoTitle): void
    {
        /** @var Page $page */
        $page = $site->pages()->firstOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'status' => 'published',
            ]
        );

        $page->forceFill([
            'title' => $title,
            'status' => 'published',
            'seo_title' => $seoTitle,
            'seo_description' => "{$title} route smoke page for storefront E2E flow.",
        ])->save();

        if (! $page->revisions()->whereNotNull('published_at')->exists()) {
            $page->revisions()->create([
                'site_id' => $site->id,
                'version' => max(1, ((int) $page->revisions()->max('version')) + 1),
                'content_json' => ['sections' => []],
                'created_by' => $owner->id,
                'published_at' => now(),
            ]);
        }
    }

    private function assertPublishedRouteHtml(string $host, string $path, string $expectedTitle): void
    {
        $response = $this->get("http://{$host}{$path}");

        $response->assertOk();
        $this->assertStringContainsString(
            'text/html',
            strtolower((string) $response->headers->get('Content-Type'))
        );

        $html = (string) $response->getContent();
        $this->assertStringContainsString("<title>{$expectedTitle}</title>", $html, "Expected SEO title missing for route {$path}");
        $this->assertStringContainsString('<link rel="canonical" href="http://'.$host.$path.'">', $html);
        $this->assertStringNotContainsString('{{', $html, "Found unresolved placeholder in route {$path}");
        $this->assertStringNotContainsString('noindex, nofollow', $html, "Unexpected noindex fallback on route {$path}");
    }
}
