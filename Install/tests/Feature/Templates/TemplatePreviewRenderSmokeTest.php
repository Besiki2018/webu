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
class TemplatePreviewRenderSmokeTest extends TestCase
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

    public function test_builder_preview_route_serves_imported_template_and_cms_bootstrap_payload_with_inspector(): void
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
            ->published('webu-preview-smoke')
            ->create([
                'name' => 'Webu Preview Smoke',
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

        $previewUrl = route('preview.serve', ['project' => $project->id]);

        $htmlResponse = $this->actingAs($owner)->get($previewUrl);

        $htmlResponse->assertOk();
        $this->assertStringContainsString(
            'text/html',
            strtolower((string) $htmlResponse->headers->get('Content-Type'))
        );

        $html = (string) $htmlResponse->getContent();
        $this->assertStringContainsString('data-webu-menu="header"', $html);
        $this->assertStringContainsString('data-webu-section="webu_header_01"', $html);
        $this->assertStringContainsString('data-webu-section="webu_footer_01"', $html);
        $this->assertStringContainsString('id="preview-inspector"', $html);

        $bootstrapUrl = route('preview.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]).'?slug=home';

        $bootstrapResponse = $this->actingAs($owner)->getJson($bootstrapUrl);

        $bootstrapResponse
            ->assertOk()
            ->assertJsonPath('project_id', $project->id)
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('slug', 'home')
            ->assertJsonPath('menus.header.key', 'header')
            ->assertJsonPath('site.theme_settings.demo_content.seeded', true)
            ->assertJsonPath('meta.source', 'cms-runtime-bridge');

        $payload = $bootstrapResponse->json();
        $this->assertIsArray(data_get($payload, 'revision.content_json.sections'));
        $this->assertNotEmpty(data_get($payload, 'revision.content_json.sections'));
        $this->assertStringContainsString(
            "/public/sites/{$site->id}/ecommerce/products",
            (string) data_get($payload, 'meta.endpoints.ecommerce_products')
        );
    }
}
