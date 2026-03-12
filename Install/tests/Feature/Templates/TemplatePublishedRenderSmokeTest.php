<?php

namespace Tests\Feature\Templates;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** @group docs-sync */
class TemplatePublishedRenderSmokeTest extends TestCase
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

    public function test_published_subdomain_serves_imported_template_html_and_cms_bootstrap_bridge(): void
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
            ->published('webu-published-smoke')
            ->create([
                'name' => 'Webu Published Smoke',
                'template_id' => $template->id,
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail()->fresh();

        $this->assertTrue((bool) data_get($site->theme_settings, 'demo_content.seeded'));

        $previewRoot = Storage::disk('local')->path("previews/{$project->id}");
        File::deleteDirectory($previewRoot);
        File::ensureDirectoryExists(dirname($previewRoot));
        $copied = File::copyDirectory(public_path("themes/{$this->themeSlug}"), $previewRoot);
        $this->assertTrue($copied, 'Failed to copy imported theme export into project preview directory.');
        $this->assertFileExists($previewRoot.'/index.html');

        $host = 'webu-published-smoke.platform.example.com';

        $htmlResponse = $this->get("http://{$host}/");

        $htmlResponse->assertOk();
        $this->assertStringContainsString(
            'text/html',
            strtolower((string) $htmlResponse->headers->get('Content-Type'))
        );
        $this->assertStringContainsString(
            'public',
            strtolower((string) $htmlResponse->headers->get('Cache-Control'))
        );
        $this->assertStringContainsString(
            'max-age=3600',
            strtolower((string) $htmlResponse->headers->get('Cache-Control'))
        );
        $servedHtmlPath = $htmlResponse->baseResponse->getFile()->getRealPath();
        $this->assertIsString($servedHtmlPath);
        $this->assertNotFalse($servedHtmlPath);
        $normalizedServedHtmlPath = str_replace('\\', '/', $servedHtmlPath);
        $this->assertStringContainsString("/published/{$project->id}/index.html", $normalizedServedHtmlPath);
        $servedHtml = File::get($servedHtmlPath);
        $this->assertStringContainsString('data-webu-menu="header"', $servedHtml);
        $this->assertStringContainsString('data-webu-section="webu_header_01"', $servedHtml);
        $this->assertStringContainsString('data-webu-section="webu_footer_01"', $servedHtml);

        $bootstrapResponse = $this->getJson("http://{$host}/__cms/bootstrap?slug=home");

        $bootstrapResponse
            ->assertOk()
            ->assertJsonPath('resolved_domain', $host)
            ->assertJsonPath('slug', 'home')
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('menus.header.key', 'header')
            ->assertJsonPath('site.theme_settings.demo_content.seeded', true)
            ->assertJsonPath('meta.source', 'cms-runtime-bridge');
        $bootstrapCacheControl = strtolower((string) $bootstrapResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $bootstrapCacheControl);
        $this->assertStringContainsString('no-store', $bootstrapCacheControl);
        $this->assertStringContainsString('must-revalidate', $bootstrapCacheControl);

        $payload = $bootstrapResponse->json();
        $this->assertIsArray(data_get($payload, 'revision.content_json.sections'));
        $this->assertNotEmpty(data_get($payload, 'revision.content_json.sections'));
        $this->assertStringContainsString(
            "/public/sites/{$site->id}/ecommerce/products",
            (string) data_get($payload, 'meta.endpoints.ecommerce_products')
        );

        $productPage = $site->pages()->firstOrCreate(
            ['slug' => 'product'],
            [
                'title' => 'Product Detail',
                'status' => 'published',
            ]
        );
        $productPage->forceFill([
            'title' => 'Product Detail',
            'status' => 'published',
            'seo_title' => 'Premium Dog Snack SEO',
            'seo_description' => 'Server-rendered SEO description for product detail page.',
        ])->save();

        if (! $productPage->revisions()->whereNotNull('published_at')->exists()) {
            $nextVersion = max(1, ((int) $productPage->revisions()->max('version')) + 1);

            $productPage->revisions()->create([
                'site_id' => $site->id,
                'version' => $nextVersion,
                'content_json' => ['sections' => []],
                'created_by' => $owner->id,
                'published_at' => now(),
            ]);
        }

        $productDetailUrl = "http://{$host}/product/premium-dog-snack";
        $productDetailResponse = $this->get($productDetailUrl);
        $productDetailResponse->assertOk();
        $this->assertStringContainsString(
            'text/html',
            strtolower((string) $productDetailResponse->headers->get('Content-Type'))
        );
        $this->assertStringContainsString(
            'public',
            strtolower((string) $productDetailResponse->headers->get('Cache-Control'))
        );
        $this->assertStringContainsString(
            'max-age=3600',
            strtolower((string) $productDetailResponse->headers->get('Cache-Control'))
        );
        $productDetailHtml = (string) $productDetailResponse->getContent();
        $this->assertStringContainsString('<title>Premium Dog Snack SEO</title>', $productDetailHtml);
        $this->assertStringContainsString(
            '<meta name="description" content="Server-rendered SEO description for product detail page.">',
            $productDetailHtml
        );
        $this->assertStringContainsString(
            '<meta property="og:url" content="'.$productDetailUrl.'">',
            $productDetailHtml
        );
        $this->assertStringContainsString(
            '<link rel="canonical" href="'.$productDetailUrl.'">',
            $productDetailHtml
        );
        $this->assertStringNotContainsString('noindex, nofollow', $productDetailHtml);

        $missingUrl = "http://{$host}/this/path/does-not-exist";
        $missingResponse = $this->get($missingUrl);
        $missingResponse->assertOk();
        $this->assertStringContainsString(
            'public',
            strtolower((string) $missingResponse->headers->get('Cache-Control'))
        );
        $this->assertStringContainsString(
            'max-age=3600',
            strtolower((string) $missingResponse->headers->get('Cache-Control'))
        );
        $missingHtml = (string) $missingResponse->getContent();
        $this->assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            $missingHtml
        );
        $this->assertStringContainsString(
            '<link rel="canonical" href="'.$missingUrl.'">',
            $missingHtml
        );
    }
}
