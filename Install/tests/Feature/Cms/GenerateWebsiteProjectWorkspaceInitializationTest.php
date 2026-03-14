<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\ProjectGenerationRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiWebsiteGeneration\BuilderBlueprintGenerationService;
use App\Services\Assets\ImageImportService;
use App\Services\Assets\ImageSearchService;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use App\Services\ProjectGenerationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class GenerateWebsiteProjectWorkspaceInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_generated_websites_create_real_workspace_files_and_component_index(): void
    {
        $user = User::factory()->create();

        $search = Mockery::mock(ImageSearchService::class);
        $search->shouldReceive('search')->andThrow(new \RuntimeException('skip stock search for file scaffolding test'));
        $this->app->instance(ImageSearchService::class, $search);

        $import = Mockery::mock(ImageImportService::class);
        $import->shouldReceive('import')->never();
        $this->app->instance(ImageImportService::class, $import);

        $result = app(GenerateWebsiteProjectService::class)->generate([
            'userPrompt' => 'Create a website for restaurant',
            'user_id' => $user->id,
            'ultra_cheap_mode' => true,
        ]);

        $project = $result['project']->fresh();
        $workspaceRoot = storage_path('workspaces/'.$project->id);

        $this->assertFileExists($workspaceRoot.'/package.json');
        $this->assertFileExists($workspaceRoot.'/index.html');
        $this->assertFileExists($workspaceRoot.'/src/main.tsx');
        $this->assertFileExists($workspaceRoot.'/src/pages/home/Page.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/HeroSection.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/CardsSection.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/CTASection.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/HeadingSection.tsx');
        $this->assertFileExists($workspaceRoot.'/.webu/index.json');
        $this->assertFileExists($workspaceRoot.'/.webu/component-parameters.json');

        $index = json_decode(File::get($workspaceRoot.'/.webu/index.json'), true);

        $this->assertIsArray($index);
        $this->assertArrayHasKey('component_parameters', $index);
        $this->assertArrayHasKey('sections', $index['component_parameters']);
        $this->assertArrayHasKey('HeroSection', $index['component_parameters']['sections']);
        $this->assertArrayHasKey('fields', $index['component_parameters']['sections']['HeroSection']);

        $homePage = Page::query()
            ->where('site_id', $result['site']->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $homeRevision = PageRevision::query()
            ->where('site_id', $result['site']->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->firstOrFail();

        $sections = $homeRevision->content_json['sections'] ?? [];
        $this->assertNotEmpty($sections);
        foreach (array_values($sections) as $index => $section) {
            $this->assertSame('section-'.$index, $section['localId'] ?? null);
            $this->assertTrue((bool) data_get($section, 'binding.webu_v2.cms_backed'));
            $this->assertSame('cms', data_get($section, 'binding.webu_v2.content_owner'));
        }
        $this->assertSame('cms', data_get($homeRevision->content_json, 'webu_cms_binding.authorities.content'));
        $this->assertSame('workspace', data_get($homeRevision->content_json, 'webu_cms_binding.authorities.code'));

        $manifest = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-manifest.json'), true);
        $this->assertTrue((bool) data_get($manifest, 'fileOwnership.0.cmsBacked'));
        $this->assertNotNull(data_get($manifest, 'cmsBinding'));
    }

    public function test_generated_websites_can_bind_ai_imported_stock_images_into_cms_revisions(): void
    {
        $user = User::factory()->create();

        $search = Mockery::mock(ImageSearchService::class);
        $search->shouldReceive('search')
            ->atLeast()
            ->once()
            ->withArgs(static function (string $query, int $limit, array $options): bool {
                return $limit === 5
                    && trim($query) !== ''
                    && in_array(($options['orientation'] ?? null), ['landscape', 'portrait', 'square'], true);
            })
            ->andReturn([
                [
                    'provider' => 'unsplash',
                    'id' => 'hero-stock-1',
                    'title' => 'Modern veterinary clinic exterior',
                    'preview_url' => 'https://images.unsplash.com/preview-1',
                    'full_url' => 'https://images.unsplash.com/full-1',
                    'download_url' => 'https://images.unsplash.com/full-1',
                    'width' => 2400,
                    'height' => 1600,
                    'author' => 'Unsplash Author',
                    'license' => 'Unsplash License',
                ],
            ]);
        $this->app->instance(ImageSearchService::class, $search);

        $import = Mockery::mock(ImageImportService::class);
        $import->shouldReceive('import')
            ->atLeast()
            ->once()
            ->andReturnUsing(static function ($project, $actor, array $payload): Media {
                $media = new Media([
                    'site_id' => $project->site_id,
                    'path' => sprintf('projects/%s/assets/images/generated-stock-image.webp', $project->id),
                    'mime' => 'image/webp',
                    'size' => 2048,
                    'meta_json' => [
                        'stock_provider' => $payload['provider'] ?? null,
                        'stock_image_id' => $payload['image_id'] ?? null,
                        'imported_by' => $payload['imported_by'] ?? null,
                    ],
                ]);
                $media->id = 98765;

                return $media;
            });
        $this->app->instance(ImageImportService::class, $import);

        $result = app(GenerateWebsiteProjectService::class)->generate([
            'userPrompt' => 'Create a website for a modern veterinary clinic',
            'user_id' => $user->id,
            'ultra_cheap_mode' => true,
        ]);

        $homePage = Page::query()
            ->where('site_id', $result['site']->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $homeRevision = PageRevision::query()
            ->where('site_id', $result['site']->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->firstOrFail();

        $heroSection = collect($homeRevision->content_json['sections'] ?? [])
            ->first(static fn ($section): bool => data_get($section, 'type') === 'webu_general_hero_01');

        $this->assertIsArray($heroSection);
        $this->assertSame(
            route('public.sites.assets', [
                'site' => $result['site']->id,
                'path' => sprintf('projects/%s/assets/images/generated-stock-image.webp', $result['project']->id),
            ]),
            data_get($heroSection, 'props.image')
        );
        $this->assertContains('image', data_get($heroSection, 'binding.webu_v2.content_fields', []));
        $this->assertSame('cms', data_get($heroSection, 'binding.webu_v2.content_owner'));
        $this->assertSame('unsplash', data_get($heroSection, 'binding.webu_v2.media_fields.image.provider'));
        $this->assertSame('hero-stock-1', data_get($heroSection, 'binding.webu_v2.media_fields.image.provider_image_id'));
        $this->assertSame('ai', data_get($heroSection, 'binding.webu_v2.media_fields.image.imported_by'));
    }

    public function test_generated_websites_fall_back_to_placeholder_images_when_stock_search_fails(): void
    {
        $user = User::factory()->create();

        $search = Mockery::mock(ImageSearchService::class);
        $search->shouldReceive('search')
            ->atLeast()
            ->once()
            ->andThrow(new \RuntimeException('stock search unavailable'));
        $this->app->instance(ImageSearchService::class, $search);

        $import = Mockery::mock(ImageImportService::class);
        $import->shouldReceive('import')->never();
        $this->app->instance(ImageImportService::class, $import);

        $result = app(GenerateWebsiteProjectService::class)->generate([
            'userPrompt' => 'Create a website for a modern veterinary clinic',
            'user_id' => $user->id,
            'ultra_cheap_mode' => true,
        ]);

        $homePage = Page::query()
            ->where('site_id', $result['site']->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $homeRevision = PageRevision::query()
            ->where('site_id', $result['site']->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->firstOrFail();

        $heroSection = collect($homeRevision->content_json['sections'] ?? [])
            ->first(static fn ($section): bool => data_get($section, 'type') === 'webu_general_hero_01');

        $this->assertIsArray($heroSection);
        $this->assertStringContainsString('/demo/hero/hero-', (string) data_get($heroSection, 'props.image'));
        $this->assertSame('placeholder', data_get($heroSection, 'binding.webu_v2.media_fields.image.source'));
        $this->assertSame('ai', data_get($heroSection, 'binding.webu_v2.media_fields.image.imported_by'));
    }

    public function test_prompt_generation_uses_builder_blueprint_pipeline_for_home_page_sections(): void
    {
        $user = User::factory()->create();

        $search = Mockery::mock(ImageSearchService::class);
        $search->shouldReceive('search')->andThrow(new \RuntimeException('skip stock search for template blueprint test'));
        $this->app->instance(ImageSearchService::class, $search);

        $import = Mockery::mock(ImageImportService::class);
        $import->shouldReceive('import')->never();
        $this->app->instance(ImageImportService::class, $import);

        $result = app(GenerateWebsiteProjectService::class)->generate([
            'userPrompt' => 'Create a modern fashion store website',
            'user_id' => $user->id,
            'ultra_cheap_mode' => true,
        ]);

        $project = $result['project']->fresh();
        $site = $result['site']->fresh();

        $this->assertNotNull($project->theme_preset);
        $this->assertSame($project->theme_preset, data_get($site->theme_settings, 'preset'));
        $this->assertSame('ecommerce', data_get($result, 'builder_generation.project_type'));
        $this->assertContains('productGrid', data_get($result, 'builder_generation.diagnostics.selectedSections', []));

        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $homeRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->firstOrFail();

        $sectionTypes = array_values(array_map(
            static fn ($section): string => (string) data_get($section, 'type', ''),
            is_array($homeRevision->content_json['sections'] ?? null) ? $homeRevision->content_json['sections'] : []
        ));

        $this->assertContains('webu_header_01', $sectionTypes);
        $this->assertContains('webu_ecom_product_grid_01', $sectionTypes);
        $this->assertContains('webu_footer_01', $sectionTypes);
        $this->assertNotContains('webu_general_cards_01', $sectionTypes);
    }

    public function test_project_generation_runner_marks_run_failed_when_builder_blueprint_generation_fails(): void
    {
        $user = User::factory()->create();

        $bridge = Mockery::mock(BuilderBlueprintGenerationService::class);
        $bridge->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('builder blueprint failed'));
        $this->app->instance(BuilderBlueprintGenerationService::class, $bridge);

        $project = app(GenerateWebsiteProjectService::class)->createProjectShell($user->id, 'Create a website for restaurant');
        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_QUEUED,
            'requested_prompt' => 'Create a website for restaurant',
            'requested_input' => [
                'prompt' => 'Create a website for restaurant',
                'ultra_cheap_mode' => true,
            ],
            'progress_message' => 'Preparing generation.',
        ]);

        app(ProjectGenerationRunner::class)->run($run);

        $run->refresh();
        $this->assertSame(ProjectGenerationRun::STATUS_FAILED, $run->status);
        $this->assertSame('builder blueprint failed', $run->error_message);

        $manifest = json_decode((string) File::get(storage_path('workspaces/'.$project->id.'/.webu/workspace-manifest.json')), true);
        $this->assertSame(ProjectGenerationRun::STATUS_FAILED, data_get($manifest, 'preview.phase'));
        $this->assertSame('builder blueprint failed', data_get($manifest, 'preview.errorMessage'));
    }

    public function test_project_generation_runner_keeps_workspace_manifest_ready_after_site_provisioning(): void
    {
        $user = User::factory()->create();

        $search = Mockery::mock(ImageSearchService::class);
        $search->shouldReceive('search')->andThrow(new \RuntimeException('skip stock search for runner test'));
        $this->app->instance(ImageSearchService::class, $search);

        $import = Mockery::mock(ImageImportService::class);
        $import->shouldReceive('import')->never();
        $this->app->instance(ImageImportService::class, $import);

        $project = app(GenerateWebsiteProjectService::class)->createProjectShell($user->id, 'Create a website for restaurant');
        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_QUEUED,
            'requested_prompt' => 'Create a website for restaurant',
            'requested_input' => [
                'prompt' => 'Create a website for restaurant',
                'ultra_cheap_mode' => true,
            ],
            'progress_message' => 'Preparing generation.',
        ]);

        app(ProjectGenerationRunner::class)->run($run);

        $run->refresh();
        $this->assertSame(ProjectGenerationRun::STATUS_READY, $run->status);

        $manifest = json_decode((string) File::get(storage_path('workspaces/'.$project->id.'/.webu/workspace-manifest.json')), true);

        $this->assertSame(ProjectGenerationRun::STATUS_READY, data_get($manifest, 'preview.phase'));
        $this->assertTrue((bool) data_get($manifest, 'preview.ready'));
        $this->assertNull(data_get($manifest, 'activeGenerationRunId'));
    }
}
