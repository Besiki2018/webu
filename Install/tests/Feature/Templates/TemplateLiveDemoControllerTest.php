<?php

namespace Tests\Feature\Templates;

use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductImage;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Template;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class TemplateLiveDemoControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $demoDir;
    private string $themeDir;
    private string $runtimeRootDir;
    private string $runtimeDir;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        $this->demoDir = public_path('template-demos/test-demo');
        $this->themeDir = public_path('themes/test-demo');
        $this->runtimeRootDir = base_path('templates/test-demo');
        $this->runtimeDir = $this->runtimeRootDir.'/runtime';
        File::deleteDirectory($this->demoDir);
        File::deleteDirectory($this->themeDir);
        File::deleteDirectory($this->runtimeRootDir);
        File::makeDirectory($this->demoDir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->demoDir);
        File::deleteDirectory($this->themeDir);
        File::deleteDirectory($this->runtimeRootDir);
        parent::tearDown();
    }

    public function test_it_serves_index_file_for_template_root(): void
    {
        File::put($this->demoDir.'/index.html', '<html><body>root-demo</body></html>');

        $response = $this->get('/template-demos/test-demo')->assertOk();

        $this->assertSame(
            realpath($this->demoDir.'/index.html'),
            $response->baseResponse->getFile()->getRealPath()
        );
    }

    public function test_it_serves_html_file_when_path_has_no_extension(): void
    {
        File::ensureDirectoryExists($this->demoDir.'/pages');
        File::put($this->demoDir.'/index.html', '<html><body>fallback-root</body></html>');
        File::put($this->demoDir.'/pages/about.html', '<html><body>about-demo</body></html>');

        $response = $this->get('/template-demos/test-demo/pages/about')->assertOk();

        $this->assertSame(
            realpath($this->demoDir.'/pages/about.html'),
            $response->baseResponse->getFile()->getRealPath()
        );
    }

    public function test_it_falls_back_to_index_for_unknown_client_side_route(): void
    {
        File::put($this->demoDir.'/index.html', '<html><body>spa-fallback</body></html>');

        $response = $this->get('/template-demos/test-demo/some/non-existing/route')->assertOk();

        $this->assertSame(
            realpath($this->demoDir.'/index.html'),
            $response->baseResponse->getFile()->getRealPath()
        );
    }

    public function test_it_serves_from_public_theme_export_when_template_demo_folder_is_missing(): void
    {
        File::deleteDirectory($this->demoDir);
        File::ensureDirectoryExists($this->themeDir);
        File::put($this->themeDir.'/index.html', '<html><body>theme-export</body></html>');

        $response = $this->get('/template-demos/test-demo')->assertOk();

        $this->assertSame(
            realpath($this->themeDir.'/index.html'),
            $response->baseResponse->getFile()->getRealPath()
        );
    }

    public function test_it_serves_from_template_runtime_when_public_exports_are_missing(): void
    {
        File::deleteDirectory($this->demoDir);
        File::deleteDirectory($this->themeDir);
        File::ensureDirectoryExists($this->runtimeDir.'/assets');
        File::put($this->runtimeDir.'/index.html', '<html><body>runtime-export</body></html>');
        File::put($this->runtimeDir.'/assets/site.css', 'body{color:#111;}');

        $indexResponse = $this->get('/template-demos/test-demo')->assertOk();
        $assetResponse = $this->get('/template-demos/test-demo/assets/site.css')->assertOk();

        $this->assertSame(
            realpath($this->runtimeDir.'/index.html'),
            $indexResponse->baseResponse->getFile()->getRealPath()
        );
        $this->assertSame(
            realpath($this->runtimeDir.'/assets/site.css'),
            $assetResponse->baseResponse->getFile()->getRealPath()
        );
    }

    public function test_it_renders_generated_full_demo_when_static_export_is_missing(): void
    {
        File::deleteDirectory($this->demoDir);

        Template::factory()->create([
            'slug' => 'test-demo',
            'name' => 'Test Demo Template',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero_split_image', 'ecommerce_product_grid']],
                    ['slug' => 'about', 'title' => 'About', 'sections' => ['team_grid', 'contact_split_form']],
                ],
            ],
        ]);

        $this->get('/template-demos/test-demo')
            ->assertOk()
            ->assertSee('Test Demo Template')
            ->assertSee('Template Demo')
            ->assertSee('Home')
            ->assertSee('About');
    }

    public function test_it_normalizes_slug_query_when_rendering_generated_demo(): void
    {
        File::deleteDirectory($this->demoDir);

        Template::factory()->create([
            'slug' => 'test-demo',
            'name' => 'Test Demo Template',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero_split_image', 'ecommerce_product_grid']],
                    ['slug' => 'about', 'title' => 'About', 'sections' => ['team_grid', 'contact_split_form']],
                ],
            ],
        ]);

        $this->get('/template-demos/test-demo?slug=HOME.html')
            ->assertOk()
            ->assertSee('Test Demo Template')
            ->assertSee('Template Demo')
            ->assertSee('Home')
            ->assertSee('About');
    }

    public function test_owned_ecommerce_template_prefers_generated_demo_even_when_static_export_exists(): void
    {
        $ecommerceDir = public_path('template-demos/ecommerce');
        File::deleteDirectory($ecommerceDir);
        File::ensureDirectoryExists($ecommerceDir);
        File::put($ecommerceDir.'/index.html', '<html><body>stale-static-ecommerce</body></html>');

        Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $this->get('/template-demos/ecommerce')
            ->assertOk()
            ->assertSee('Live demo')
            ->assertDontSee('stale-static-ecommerce');

        File::deleteDirectory($ecommerceDir);
    }

    public function test_generated_ecommerce_demo_uses_real_site_catalog_data_when_site_is_provided(): void
    {
        File::deleteDirectory($this->demoDir);

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $project->refresh();
        $site = $project->site()->firstOrFail();

        $category = EcommerceCategory::query()->create([
            'site_id' => $site->id,
            'name' => 'ქართული კატეგორია',
            'slug' => 'qartuli-kategoria',
            'status' => 'active',
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $category->id,
            'name' => 'ქართული ტესტ პროდუქტი',
            'slug' => 'qartuli-test-produkti',
            'sku' => 'TEST-001',
            'price' => '129.00',
            'compare_at_price' => '149.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        EcommerceProductImage::query()->create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'path' => 'site-media/'.$site->id.'/products/test-product.svg',
            'alt_text' => 'ქართული ტესტ პროდუქტის ფოტო',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this->get('/template-demos/ecommerce?site='.$site->id.'&slug=home')
            ->assertOk()
            ->assertSee('ქართული ტესტ პროდუქტი')
            ->assertSee('129.00 GEL')
            ->assertSee('id="webby-ecommerce-runtime"', false)
            ->assertSee('data-webby-ecommerce-products', false)
            ->assertDontSee('Urban Jacket');
    }

    public function test_site_scoped_default_shell_uses_site_page_content_even_when_project_template_is_different(): void
    {
        File::deleteDirectory(public_path('template-demos/default'));

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $site = $project->site()->firstOrFail();
        $homePage = Page::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'slug' => 'home',
            ],
            [
                'title' => 'Home',
                'status' => 'draft',
            ]
        );

        PageRevision::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
            ],
            [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'hero_split_image',
                            'props' => [
                                'headline' => 'Studio landing',
                                'subheading' => 'Generated from site revision',
                            ],
                        ],
                        [
                            'type' => 'features',
                            'props' => [
                                'heading' => 'Why choose us',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->get('/template-demos/default?site='.$site->id.'&slug=home&draft=1')
            ->assertOk()
            ->assertSee('Studio landing')
            ->assertSee('data-webu-section-local-id="section-0"', false)
            ->assertSee('data-webu-section-local-id="section-1"', false);
    }

    public function test_generated_ecommerce_demo_exposes_payment_methods_page_even_when_metadata_has_only_home(): void
    {
        File::deleteDirectory($this->demoDir);

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $project->refresh();
        $site = $project->site()->firstOrFail();

        $this->get('/template-demos/ecommerce?site='.$site->id.'&slug=payments')
            ->assertOk()
            ->assertSee('Payment Methods')
            ->assertSee('data-webby-ecommerce-payment-selector', false)
            ->assertSee('id="webby-ecommerce-runtime"', false);
    }

    public function test_generated_ecommerce_demo_exposes_full_required_navigation_even_when_metadata_is_minimal(): void
    {
        File::deleteDirectory($this->demoDir);

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $project->refresh();
        $site = $project->site()->firstOrFail();

        $response = $this->get('/template-demos/ecommerce?site='.$site->id.'&slug=home')
            ->assertOk();

        foreach ([
            'Home',
            'Shop',
            'Contact',
            'Delivery & Returns',
        ] as $pageTitle) {
            $response->assertSee($pageTitle);
        }
    }

    public function test_generated_ecommerce_home_renders_fashion_header_and_split_hero_from_component_data(): void
    {
        File::deleteDirectory($this->demoDir);

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_general_heading_01', 'webu_ecom_product_grid_01']],
                ],
            ],
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $project->refresh();
        $site = $project->site()->firstOrFail();

        $response = $this->get('/template-demos/ecommerce?site='.$site->id.'&slug=home');
        $response->assertOk()
            ->assertSee('fashion-header');
        $body = $response->getContent();
        $this->assertTrue(str_contains($body, 'New arrivals') || str_contains($body, 'Autumn Collection'), 'Strip text should appear');
        $this->assertTrue(str_contains($body, 'E-commerce Store') || str_contains($body, 'ORIMA.'), 'Brand should appear');
        $this->assertStringContainsString('fashion-hero', $body);
        $this->assertStringContainsString('images.unsplash.com/photo-1521572163474-6864f9cf17ab', $body);
    }

    public function test_generated_ecommerce_home_applies_component_prop_overrides_for_header_and_hero(): void
    {
        File::deleteDirectory($this->demoDir);

        Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'E-commerce Store',
            'category' => 'ecommerce',
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['webu_general_heading_01', 'webu_ecom_product_grid_01']],
                ],
                'default_sections' => [
                    'home' => [
                        [
                            'key' => 'webu_general_heading_01',
                            'enabled' => true,
                            'props' => [
                                'headline' => 'CUSTOM LOOK',
                                'subheading' => 'Custom subtitle from CMS.',
                                'top_strip_text' => 'Custom top strip from CMS.',
                                'contact_phone' => '+995 599 00 00 00',
                                'contact_email' => 'hello@custom.shop',
                                'brand_text' => 'MYBRAND.',
                                'left_image_url' => 'https://example.com/left-fashion.jpg',
                                'right_image_url' => 'https://example.com/right-fashion.jpg',
                                'hero_cta_label' => 'BUY NOW',
                                'hero_cta_url' => '/shop',
                            ],
                        ],
                        [
                            'key' => 'webu_ecom_product_grid_01',
                            'enabled' => true,
                            'props' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get('/template-demos/ecommerce?slug=home')
            ->assertOk()
            ->assertSee('CUSTOM LOOK')
            ->assertSee('Custom subtitle from CMS.')
            ->assertSee('Custom top strip from CMS.')
            ->assertSee('MYBRAND.')
            ->assertSee('+995 599 00 00 00')
            ->assertSee('hello@custom.shop')
            ->assertSee('https://example.com/left-fashion.jpg')
            ->assertSee('https://example.com/right-fashion.jpg')
            ->assertSee('BUY NOW');
    }
}
