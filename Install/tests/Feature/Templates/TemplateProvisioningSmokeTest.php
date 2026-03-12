<?php

namespace Tests\Feature\Templates;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class TemplateProvisioningSmokeTest extends TestCase
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

    public function test_imported_template_provisions_site_pages_menus_and_published_home_revision(): void
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
            ->published('webu-provision-smoke')
            ->create([
                'name' => 'Webu Provision Smoke',
                'template_id' => $template->id,
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail()->fresh();

        $this->assertSame('published', $site->status);
        $this->assertSame('webu-provision-smoke', $site->subdomain);
        $this->assertTrue((bool) data_get($site->theme_settings, 'demo_content.seeded'));

        $global = $site->globalSettings()->first();
        $this->assertNotNull($global, 'Global settings should be provisioned.');

        $headerMenu = $site->menus()->where('key', 'header')->first();
        $footerMenu = $site->menus()->where('key', 'footer')->first();
        $this->assertNotNull($headerMenu, 'Header menu should be provisioned.');
        $this->assertNotNull($footerMenu, 'Footer menu should be provisioned.');
        $this->assertIsArray($headerMenu?->items_json);
        $this->assertNotEmpty($headerMenu?->items_json ?? []);

        $pages = $site->pages()->orderBy('slug')->get();
        $this->assertGreaterThan(0, $pages->count(), 'Template provisioning should create default pages.');
        $this->assertTrue($pages->contains(fn ($page): bool => $page->slug === 'home'));

        $homePage = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->assertSame('published', $homePage->status);

        $publishedHomeRevision = $homePage->revisions()
            ->whereNotNull('published_at')
            ->latest('version')
            ->first();

        $this->assertNotNull($publishedHomeRevision, 'Home page should have a published revision after provisioning.');
        $this->assertIsArray(data_get($publishedHomeRevision?->content_json, 'sections'));
        $this->assertNotEmpty(data_get($publishedHomeRevision?->content_json, 'sections', []));

        $pageCountBefore = $site->pages()->count();
        $menuCountBefore = $site->menus()->count();
        $homeRevisionCountBefore = $homePage->revisions()->count();

        app(SiteProvisioningService::class)->provisionForProject($project->fresh());

        $site = $site->fresh();
        $homePage = $site->pages()->where('slug', 'home')->firstOrFail();

        $this->assertSame($pageCountBefore, $site->pages()->count(), 'Re-provision should not duplicate pages.');
        $this->assertSame($menuCountBefore, $site->menus()->count(), 'Re-provision should not duplicate menus.');
        $this->assertSame($homeRevisionCountBefore, $homePage->revisions()->count(), 'Re-provision should not create extra page revisions.');
    }
}
