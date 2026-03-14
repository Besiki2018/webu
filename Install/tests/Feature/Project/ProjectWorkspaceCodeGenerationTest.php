<?php

namespace Tests\Feature\Project;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectWorkspaceCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_workspace_generation_preserves_section_props_and_upgrades_section_scaffolds(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $contentJson = [
            'sections' => [
                [
                    'type' => 'hero',
                    'localId' => 'hero-1',
                    'props' => [
                        'headline' => 'Best Restaurant in Town',
                        'subtitle' => 'Fresh local ingredients and same-day reservations.',
                        'ctaText' => 'Book a table',
                        'ctaLink' => '/contact',
                    ],
                ],
                [
                    'type' => 'features',
                    'localId' => 'features-1',
                    'props' => [
                        'heading' => 'Why guests return',
                        'items' => [
                            ['title' => 'Seasonal menu', 'description' => 'Changes every week'],
                            ['title' => 'Open kitchen', 'description' => 'Watch the team work live'],
                            ['title' => 'Late hours', 'description' => 'Dinner service until midnight'],
                        ],
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->first();

        if ($revision) {
            $revision->update(['content_json' => $contentJson]);
        } else {
            PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
                'content_json' => $contentJson,
            ]);
        }

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        $pageFile = $workspaceRoot.'/src/pages/home/Page.tsx';
        $heroFile = $workspaceRoot.'/src/sections/HeroSection.tsx';

        $this->assertFileExists($pageFile);
        $this->assertFileExists($heroFile);

        $pageContent = File::get($pageFile);
        $heroContent = File::get($heroFile);

        $this->assertStringContainsString('"sectionId":"hero-1"', $pageContent);
        $this->assertStringContainsString('"title":"Best Restaurant in Town"', $pageContent);
        $this->assertStringContainsString('"buttonText":"Book a table"', $pageContent);
        $this->assertStringContainsString('"primaryCta":{"label":"Book a table","link":"/contact"}', $pageContent);
        $this->assertStringContainsString('"items":[{"title":"Seasonal menu","description":"Changes every week"}', $pageContent);
        $this->assertStringContainsString('"cardOneTitle":"Seasonal menu"', $pageContent);
        $this->assertStringContainsString('"cardTwoDescription":"Watch the team work live"', $pageContent);

        $this->assertStringContainsString('data-webu-field="title"', $heroContent);
        $this->assertStringContainsString('data-webu-field="primaryCta.label"', $heroContent);
        $this->assertStringContainsString('data-webu-field-url="primaryCta.link"', $heroContent);
        $this->assertStringContainsString('data-webu-section-local-id={sectionId}', $heroContent);
    }

    public function test_workspace_generation_reads_localized_page_content(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $contentJson = [
            'locales' => [
                'ka' => [
                    'sections' => [
                        [
                            'type' => 'hero',
                            'localId' => 'hero-localized-1',
                            'props' => [
                                'headline' => 'ლოკალიზებული სათაური',
                                'subtitle' => 'ლოკალიზებული აღწერა',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->first();

        if ($revision) {
            $revision->update(['content_json' => $contentJson]);
        } else {
            PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
                'content_json' => $contentJson,
            ]);
        }

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        $pageContent = File::get($workspaceRoot.'/src/pages/home/Page.tsx');

        $this->assertStringContainsString('"sectionId":"hero-localized-1"', $pageContent);
        $this->assertStringContainsString('"title":"ლოკალიზებული სათაური"', $pageContent);
        $this->assertStringContainsString('"subtitle":"ლოკალიზებული აღწერა"', $pageContent);
        $this->assertFileExists($workspaceRoot.'/package.json');
        $this->assertFileExists($workspaceRoot.'/src/main.tsx');
    }

    public function test_workspace_generation_preserves_cms_authority_metadata_in_projection_and_manifest(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $contentJson = [
            'webu_cms_binding' => [
                'authorities' => [
                    'content' => 'cms',
                    'layout' => 'cms_revision',
                    'code' => 'workspace',
                    'preview' => 'derived',
                ],
                'page' => [
                    'content_owner' => 'mixed',
                    'sync_direction' => 'cms_to_workspace',
                    'conflict_status' => 'clean',
                ],
                'sections' => [
                    [
                        'local_id' => 'hero-1',
                        'content_fields' => ['headline', 'subtitle'],
                        'visual_fields' => ['variant'],
                        'code_owned_fields' => [],
                    ],
                ],
            ],
            'sections' => [
                [
                    'type' => 'hero',
                    'localId' => 'hero-1',
                    'props' => [
                        'headline' => 'Authority headline',
                        'subtitle' => 'Authority subtitle',
                        'variant' => 'split',
                    ],
                    'binding' => [
                        'webu_v2' => [
                            'cms_backed' => true,
                            'content_owner' => 'mixed',
                            'content_fields' => ['headline', 'subtitle'],
                            'visual_fields' => ['variant'],
                            'code_owned_fields' => [],
                            'sync_direction' => 'bidirectional',
                            'conflict_status' => 'clean',
                        ],
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->first();

        if ($revision) {
            $revision->update(['content_json' => $contentJson]);
        } else {
            PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
                'content_json' => $contentJson,
            ]);
        }

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        $projection = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-projection.json'), true);
        $manifest = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-manifest.json'), true);

        $this->assertTrue((bool) data_get($projection, 'pages.0.cms_backed'));
        $this->assertSame('mixed', data_get($projection, 'pages.0.content_owner'));
        $this->assertSame(['headline', 'subtitle'], data_get($projection, 'pages.0.sections.0.content_field_paths'));
        $this->assertSame(['variant'], data_get($projection, 'pages.0.sections.0.visual_field_paths'));
        $homePageManifest = collect(data_get($manifest, 'fileOwnership', []))
            ->firstWhere('path', 'src/pages/home/Page.tsx');
        $this->assertTrue((bool) data_get($homePageManifest, 'cmsBacked'));
        $this->assertNotEmpty(data_get($homePageManifest, 'cmsFieldPaths'));
    }

    public function test_workspace_generation_normalizes_fixed_components_and_nested_editable_paths(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $site->update([
            'theme_settings' => [
                'layout' => [
                    'header_props' => [
                        'logo_text' => 'Acme Store',
                        'menu_items' => json_encode([
                            ['label' => 'Shop', 'href' => '/shop'],
                            ['label' => 'About', 'href' => '/about'],
                        ], JSON_UNESCAPED_UNICODE),
                        'cta_text' => 'View cart',
                        'cta_link' => '/cart',
                    ],
                    'footer_props' => [
                        'links' => [
                            ['label' => 'Privacy', 'href' => '/privacy'],
                        ],
                        'social_links' => [
                            ['label' => 'Instagram', 'href' => 'https://instagram.com/acme'],
                        ],
                    ],
                ],
            ],
        ]);

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        $pageContent = File::get($workspaceRoot.'/src/pages/home/Page.tsx');
        $headerContent = File::get($workspaceRoot.'/src/components/Header.tsx');
        $footerContent = File::get($workspaceRoot.'/src/components/Footer.tsx');

        $this->assertStringContainsString('"menuItems":[{"label":"Shop","href":"/shop"},{"label":"About","href":"/about"}]', $pageContent);
        $this->assertStringContainsString('"buttons":[{"label":"View cart","href":"/cart","variant":"primary"}]', $pageContent);
        $this->assertStringContainsString('"links":[{"label":"Privacy","href":"/privacy"}]', $pageContent);
        $this->assertStringContainsString('"socialLinks":[{"label":"Instagram","href":"https://instagram.com/acme"}]', $pageContent);
        $this->assertStringContainsString('data-webu-field={`menuItems.${index}.label`}', $headerContent);
        $this->assertStringContainsString('data-webu-field-url={`menuItems.${index}.href`}', $headerContent);
        $this->assertStringContainsString('data-webu-field={`links.${index}.label`}', $footerContent);
        $this->assertStringContainsString('data-webu-field={`socialLinks.${index}.label`}', $footerContent);
    }

    public function test_initialize_project_codebase_writes_manifest_baseline_for_initial_generation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->first();

        $revision?->update([
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'localId' => 'hero-initial-1',
                        'props' => [
                            'headline' => 'Initial hero',
                        ],
                    ],
                ],
            ],
        ]);

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project, [
            'active_generation_run_id' => 'run-initial-123',
            'phase' => ProjectGenerationRun::STATUS_WRITING_FILES,
        ]);

        $manifest = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-manifest.json'), true);

        $this->assertIsArray($manifest);
        $this->assertSame((string) $project->id, data_get($manifest, 'projectId'));
        $this->assertSame('run-initial-123', data_get($manifest, 'activeGenerationRunId'));
        $this->assertSame(ProjectGenerationRun::STATUS_WRITING_FILES, data_get($manifest, 'preview.phase'));
        $this->assertFalse((bool) data_get($manifest, 'preview.ready'));
        $this->assertSame('home', data_get($manifest, 'generatedPages.0.slug'));
        $this->assertSame('/', data_get($manifest, 'generatedPages.0.routePath'));
        $this->assertNotEmpty(data_get($manifest, 'fileOwnership'));

        $pageEntry = collect(data_get($manifest, 'fileOwnership', []))
            ->firstWhere('path', 'src/pages/home/Page.tsx');

        $this->assertNotNull($pageEntry);
        $this->assertSame('ai-generated', data_get($pageEntry, 'editState'));
        $this->assertSame('home', data_get($pageEntry, 'originatingPageSlug'));
    }

    public function test_workspace_file_writes_update_manifest_and_operation_log(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $workspace = app(ProjectWorkspaceService::class);
        $workspace->writeFile($project, 'src/utils/format.ts', <<<'TS'
export function formatCurrency(value: number): string {
    return `$${value.toFixed(2)}`;
}
TS, [
            'actor' => 'user',
            'source' => 'code_editor',
            'reason' => 'manual_rollout_test',
        ]);

        $manifest = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-manifest.json'), true);
        $operationLog = json_decode((string) File::get($workspaceRoot.'/.webu/workspace-operation-log.json'), true);

        $this->assertIsArray($manifest);
        $this->assertIsArray($operationLog);
        $this->assertSame('building_preview', data_get($manifest, 'preview.phase'));
        $this->assertFalse((bool) data_get($manifest, 'preview.ready'));

        $ownershipEntry = collect(data_get($manifest, 'fileOwnership', []))
            ->firstWhere('path', 'src/utils/format.ts');

        $this->assertNotNull($ownershipEntry);
        $this->assertSame('user-edited', data_get($ownershipEntry, 'editState'));
        $this->assertSame('user', data_get($ownershipEntry, 'lastEditor'));
        $this->assertTrue((bool) data_get($ownershipEntry, 'dirty'));
        $this->assertSame('create_file', data_get($ownershipEntry, 'lastOperationKind'));

        $latestOperation = data_get($operationLog, 'entries.0');
        $this->assertIsArray($latestOperation);
        $this->assertSame('src/utils/format.ts', data_get($latestOperation, 'path'));
        $this->assertSame('create_file', data_get($latestOperation, 'operation_kind'));
        $this->assertSame('manual_rollout_test', data_get($latestOperation, 'reason'));
        $this->assertSame('user', data_get($latestOperation, 'actor'));
    }

    public function test_workspace_generation_upgrades_bare_placeholder_components_and_sections(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        app(ProjectWorkspaceService::class)->seedTemplate($project, false);

        File::put($workspaceRoot.'/src/components/Header.tsx', <<<'TSX'
export default function Header() {
  return (
    <header className="site-header">
      <nav>Header</nav>
    </header>
  );
}
TSX);

        File::put($workspaceRoot.'/src/sections/HeroSection.tsx', <<<'TSX'
export default function HeroSection() {
  return (
    <section className="section-hero" data-section="hero">
      <div className="container">HeroSection</div>
    </section>
  );
}
TSX);

        File::put($workspaceRoot.'/src/sections/ContactSection.tsx', <<<'TSX'
type ContactSectionProps = {
  sectionId?: string;
  title?: string;
  subtitle?: string;
};

export default function ContactSection({
  sectionId = 'contact-section',
  title = 'Contact us',
  subtitle = 'Replace this starter copy with project-specific content.',
}: ContactSectionProps) {
  return (
    <section className="section section-contact" data-webu-section="ContactSection" data-webu-section-local-id={sectionId}>
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title" data-webu-field="title">{title}</h2>
          <p className="section-description" data-webu-field="subtitle">{subtitle}</p>
        </div>
      </div>
    </section>
  );
}
TSX);

        app(ProjectWorkspaceService::class)->generateFromCms($project);

        $headerContent = File::get($workspaceRoot.'/src/components/Header.tsx');
        $heroContent = File::get($workspaceRoot.'/src/sections/HeroSection.tsx');
        $contactContent = File::get($workspaceRoot.'/src/sections/ContactSection.tsx');

        $this->assertStringContainsString('data-webu-field="logoText"', $headerContent);
        $this->assertStringContainsString('data-webu-field={`menuItems.${index}.label`}', $headerContent);
        $this->assertStringContainsString('data-webu-section-local-id={sectionId}', $headerContent);
        $this->assertStringContainsString('data-webu-field="title"', $heroContent);
        $this->assertStringContainsString('data-webu-field="primaryCta.label"', $heroContent);
        $this->assertStringContainsString('data-webu-field="primaryCta.label"', $contactContent);
        $this->assertStringNotContainsString('<nav>Header</nav>', $headerContent);
        $this->assertStringNotContainsString('<div className="container">HeroSection</div>', $heroContent);
        $this->assertStringNotContainsString('Replace this starter copy with project-specific content.', $contactContent);
    }

    public function test_ensure_project_codebase_ready_regenerates_projection_managed_pages_when_cms_is_authoritative(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();
        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $homePage->id,
            'version' => 99,
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'localId' => 'hero-authority',
                        'props' => ['headline' => 'CMS authoritative headline'],
                    ],
                ],
            ],
        ]);
        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $homePage->id,
            'version' => 100,
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'localId' => 'hero-authority-updated',
                        'props' => ['headline' => 'Fresh CMS projection headline'],
                    ],
                ],
            ],
        ]);

        $status = app(ProjectWorkspaceService::class)->ensureProjectCodebaseReady($project);

        $this->assertTrue($status['generated_from_cms']);
        $this->assertTrue($status['has_page_files']);
        $this->assertTrue($status['ready']);
        $this->assertStringContainsString('Fresh CMS projection headline', File::get($workspaceRoot.'/src/pages/home/Page.tsx'));
    }

    public function test_generate_from_cms_preserves_detached_custom_page_overrides_and_marks_them_as_workspace_overrides(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

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
                            'type' => 'hero',
                            'localId' => 'hero-original',
                            'props' => ['headline' => 'Original CMS headline'],
                        ],
                    ],
                ],
            ]
        );

        $workspace = app(ProjectWorkspaceService::class);
        $workspace->initializeProjectCodebase($project);
        $workspace->writeFile($project, 'src/pages/home/Page.tsx', <<<'TSX'
export default function CustomHomePage() {
  return <main>custom-workspace-marker</main>;
}
TSX);

        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $homePage->id,
            'version' => 2,
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'localId' => 'hero-updated',
                        'props' => ['headline' => 'Updated CMS headline'],
                    ],
                ],
            ],
        ]);

        $workspace->generateFromCms($project);

        $pageContent = File::get($workspaceRoot.'/src/pages/home/Page.tsx');
        $projection = json_decode(File::get($workspaceRoot.'/.webu/workspace-projection.json'), true);
        $homeProjection = collect($projection['pages'] ?? [])->firstWhere('slug', 'home');
        $workspaceFile = collect($workspace->listFiles($project))->firstWhere('path', 'src/pages/home/Page.tsx');

        $this->assertStringContainsString('custom-workspace-marker', $pageContent);
        $this->assertStringNotContainsString('Updated CMS headline', $pageContent);
        $this->assertIsArray($homeProjection);
        $this->assertSame('src/pages/home/Page.tsx', $homeProjection['path'] ?? null);
        $this->assertSame('Updated CMS headline', data_get($homeProjection, 'sections.0.sample_props.headline'));
        $this->assertIsArray($workspaceFile);
        $this->assertSame('detached-projection', $workspaceFile['projection_source'] ?? null);
        $this->assertFalse((bool) ($workspaceFile['is_generated_projection'] ?? true));
    }

    public function test_workspace_projection_snapshot_tracks_real_pages_layouts_and_used_components_only(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        File::deleteDirectory($workspaceRoot);

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $site->update([
            'theme_settings' => [
                'layout' => [
                    'header_props' => [
                        'logo_text' => 'Projection Bistro',
                        'menu_items' => [
                            ['label' => 'Home', 'href' => '/'],
                            ['label' => 'About', 'href' => '/about'],
                        ],
                    ],
                    'footer_props' => [
                        'links' => [
                            ['label' => 'Privacy', 'href' => '/privacy'],
                        ],
                    ],
                ],
            ],
        ]);

        $homePage = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();
        PageRevision::query()->updateOrCreate(
            ['site_id' => $site->id, 'page_id' => $homePage->id, 'version' => 1],
            ['content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'localId' => 'hero-home',
                        'props' => [
                            'headline' => 'Projection Home',
                            'subtitle' => 'Faithful workspace projection',
                            'ctaText' => 'Book now',
                            'ctaLink' => '/contact',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'localId' => 'features-home',
                        'props' => [
                            'heading' => 'Why choose us',
                            'items' => [
                                ['title' => 'Fresh', 'description' => 'Every day'],
                            ],
                        ],
                    ],
                ],
            ]]
        );

        $aboutPage = Page::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'slug' => 'about',
            ],
            [
                'title' => 'About',
                'status' => 'draft',
            ],
        );
        PageRevision::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'page_id' => $aboutPage->id,
                'version' => 1,
            ],
            [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'cta',
                            'localId' => 'cta-about',
                            'props' => [
                                'headline' => 'Visit us',
                                'ctaText' => 'Get directions',
                                'ctaLink' => '/visit',
                            ],
                        ],
                    ],
                ],
            ],
        );

        app(ProjectWorkspaceService::class)->initializeProjectCodebase($project);

        $this->assertFileExists($workspaceRoot.'/src/pages/home/Page.tsx');
        $this->assertFileExists($workspaceRoot.'/src/pages/about/Page.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/HeroSection.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/FeaturesSection.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/CTASection.tsx');
        $this->assertFileDoesNotExist($workspaceRoot.'/src/sections/NewsletterSection.tsx');

        $projection = json_decode(File::get($workspaceRoot.'/.webu/workspace-projection.json'), true);

        $this->assertIsArray($projection);
        $pageSlugs = collect($projection['pages'] ?? [])->pluck('slug')->filter()->values()->all();
        $this->assertContains('home', $pageSlugs);
        $this->assertContains('about', $pageSlugs);
        $this->assertCount(3, $projection['layouts'] ?? []);

        $homeProjection = collect($projection['pages'] ?? [])->firstWhere('slug', 'home');
        $heroProjection = collect($projection['components'] ?? [])->firstWhere('component_name', 'HeroSection');
        $headerProjection = collect($projection['layouts'] ?? [])->firstWhere('component_name', 'Header');

        $this->assertIsArray($homeProjection);
        $this->assertContains('src/layouts/SiteLayout.tsx', $homeProjection['layout_files'] ?? []);
        $this->assertContains('src/sections/HeroSection.tsx', $homeProjection['section_files'] ?? []);
        $this->assertIsArray($heroProjection);
        $this->assertContains('home', $heroProjection['pages'] ?? []);
        $this->assertContains('primaryCta.label', $heroProjection['prop_paths'] ?? []);
        $this->assertContains('src/pages/home/Page.tsx', $heroProjection['page_paths'] ?? []);
        $this->assertIsArray($headerProjection);
        $this->assertContains('menuItems.0.label', $headerProjection['prop_paths'] ?? []);
        $this->assertContains('src/components/Header.tsx', array_keys($projection['files'] ?? []));
    }
}
