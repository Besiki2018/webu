<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Assets\ImageImportService;
use App\Services\Assets\ImageSearchService;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
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
        $this->assertFileExists($workspaceRoot.'/src/sections/HeadingSection.tsx');
        $this->assertFileExists($workspaceRoot.'/.webu/index.json');
        $this->assertFileExists($workspaceRoot.'/.webu/component-parameters.json');

        $index = json_decode(File::get($workspaceRoot.'/.webu/index.json'), true);

        $this->assertIsArray($index);
        $this->assertArrayHasKey('component_parameters', $index);
        $this->assertArrayHasKey('sections', $index['component_parameters']);
        $this->assertArrayHasKey('HeadingSection', $index['component_parameters']['sections']);
        $this->assertArrayHasKey('fields', $index['component_parameters']['sections']['HeadingSection']);

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
                    'path' => sprintf('projects/%s/assets/images/generated-banner.webp', $project->id),
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

        $bannerSection = collect($homeRevision->content_json['sections'] ?? [])
            ->first(static fn ($section): bool => data_get($section, 'type') === 'banner');

        $this->assertIsArray($bannerSection);
        $this->assertSame(
            route('public.sites.assets', [
                'site' => $result['site']->id,
                'path' => sprintf('projects/%s/assets/images/generated-banner.webp', $result['project']->id),
            ]),
            data_get($bannerSection, 'props.backgroundImage')
        );
        $this->assertContains('backgroundImage', data_get($bannerSection, 'binding.webu_v2.content_fields', []));
        $this->assertSame('cms', data_get($bannerSection, 'binding.webu_v2.content_owner'));
    }
}
