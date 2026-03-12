<?php

namespace Tests\Feature\Project;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\SectionLibrary;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\AiTools\ComponentGeneratorService;
use App\Services\AiTools\SitePlannerService;
use App\Services\AiProjectFileEditService;
use App\Services\InternalAiService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\SiteProvisioningService;
use App\Services\TemplateDemoService;
use App\Services\WebuCodex\CodebaseScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class WebuAiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    protected function tearDown(): void
    {
        Project::query()->pluck('id')->each(function ($projectId): void {
            File::deleteDirectory(storage_path('workspaces/'.$projectId));
        });

        parent::tearDown();
    }

    public function test_workspace_structure_refreshes_after_direct_file_write(): void
    {
        [$owner, $project] = $this->createProject();
        $this->writeWorkspaceFile($project, 'src/sections/HeroSection.tsx', "export default function HeroSection() {\n  return <section className=\"section\"><div className=\"container\">Hero</div></section>;\n}\n");

        $this->actingAs($owner)
            ->getJson(route('panel.projects.workspace.structure', $project))
            ->assertOk()
            ->assertJsonFragment(['src/sections/HeroSection.tsx']);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.workspace.file.write', $project), [
                'path' => 'src/sections/PricingSection.tsx',
                'content' => "export default function PricingSection() {\n  return <section className=\"section\"><div className=\"container\">Pricing</div></section>;\n}\n",
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($owner)
            ->getJson(route('panel.projects.workspace.structure', $project))
            ->assertOk()
            ->assertJsonFragment(['src/sections/PricingSection.tsx']);
    }

    public function test_codebase_scanner_rejects_cached_index_when_workspace_manifest_changes(): void
    {
        [$owner, $project] = $this->createProject();
        $workspace = app(ProjectWorkspaceService::class);
        $scanner = app(CodebaseScanner::class);

        $workspace->initializeProjectCodebase($project);
        $scan = $scanner->scan($project);
        $scanner->writeIndex($project, $scan);

        $this->assertNotNull($scanner->getScanFromIndex($project));

        $this->writeWorkspaceFile($project, 'src/pages/home/Page.tsx', "export default function PageHome() {\n  return <main>manifest-change-token</main>;\n}\n");

        $this->assertNull($scanner->getScanFromIndex($project));
    }

    public function test_ai_project_edit_prioritizes_relevant_file_context_for_selected_element(): void
    {
        [, $project] = $this->createProject();
        $scanner = app(CodebaseScanner::class);
        $workspace = app(ProjectWorkspaceService::class);

        $workspace->initializeProjectCodebase($project);
        $this->writeWorkspaceFile($project, 'src/pages/home/Page.tsx', "import HeroSection from '../../sections/HeroSection';\n\nexport default function PageHome() {\n  return <HeroSection title=\"Welcome\" />;\n}\n");
        $this->writeWorkspaceFile($project, 'src/pages/about/Page.tsx', "import PricingSection from '../../sections/PricingSection';\n\nexport default function PageAbout() {\n  return <PricingSection />;\n}\n");
        $this->writeWorkspaceFile($project, 'src/sections/HeroSection.tsx', "export default function HeroSection(){ return <h1 data-webu-field=\"title\">Welcome</h1>; }\n");
        $this->writeWorkspaceFile($project, 'src/sections/PricingSection.tsx', "export default function PricingSection(){ return <section>Pricing</section>; }\n");
        foreach (range(1, 15) as $index) {
            $this->writeWorkspaceFile($project, "src/components/Noise{$index}.tsx", "export default function Noise{$index}(){ return <div>noise-{$index}</div>; }\n");
        }

        $scan = $scanner->scan($project);

        $selected = [
            'section_id' => 'hero-1',
            'parameter_path' => 'title',
            'element_id' => 'hero-title',
            'page_slug' => 'home',
            'component_path' => 'src/sections/HeroSection.tsx',
            'component_type' => 'HeroSection',
            'component_name' => 'HeroSection',
        ];

        $relevant = $scanner->selectRelevantFileContents($project, $scan, 'Update the hero title and button copy on the home page', $selected);

        $this->assertArrayHasKey('src/sections/HeroSection.tsx', $relevant);
        $this->assertArrayHasKey('src/pages/home/Page.tsx', $relevant);
        $this->assertLessThanOrEqual(12, count($relevant));
    }

    public function test_codebase_scanner_uses_projection_metadata_for_layout_context(): void
    {
        [, $project] = $this->createProject();
        $scanner = app(CodebaseScanner::class);
        $workspace = app(ProjectWorkspaceService::class);

        $site = $this->prepareHeaderSixSite($project, [
            'menu_items' => [
                ['label' => 'Shop', 'href' => '/shop'],
                ['label' => 'Journal', 'href' => '/journal'],
            ],
        ]);
        $this->prepareHomePageRevision($site->id);

        $workspace->initializeProjectCodebase($project);

        $scan = $scanner->scan($project);
        $selected = [
            'section_id' => 'global-header',
            'parameter_path' => 'menuItems.0.label',
            'element_id' => 'header-menu-item-0',
            'page_slug' => 'home',
            'component_path' => 'src/components/Header.tsx',
            'component_type' => 'Header',
            'component_name' => 'Header',
            'editable_fields' => ['menuItems.0.label'],
        ];

        $relevant = $scanner->selectRelevantFileContents($project, $scan, 'Update the header menu label on the home page', $selected);

        $this->assertArrayHasKey('src/components/Header.tsx', $relevant);
        $this->assertArrayHasKey('src/layouts/SiteLayout.tsx', $relevant);
        $this->assertArrayHasKey('src/pages/home/Page.tsx', $relevant);
    }

    public function test_ai_tools_search_files_matches_file_content(): void
    {
        [$owner, $project] = $this->createProject();
        $this->writeWorkspaceFile($project, 'src/pages/home/Page.tsx', "export default function PageHome() {\n  return <main>unique-acme-content-token</main>;\n}\n");

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-tools.execute', $project), [
                'tool' => 'searchFiles',
                'args' => [
                    'keyword' => 'unique-acme-content-token',
                    'max_results' => 10,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matches.0.path', 'src/pages/home/Page.tsx')
            ->assertJsonPath('data.matches.0.match_type', 'content');
    }

    public function test_component_registry_includes_workspace_sections_overlay(): void
    {
        [$owner, $project] = $this->createProject();
        $this->writeWorkspaceFile($project, 'src/sections/PricingSection.tsx', "export default function PricingSection() {\n  return <section className=\"section\"><div className=\"container\">Pricing</div></section>;\n}\n");

        $this->actingAs($owner)
            ->getJson("/panel/projects/{$project->id}/builder/component-registry")
            ->assertOk()
            ->assertJsonFragment(['key' => 'PricingSection'])
            ->assertJsonFragment(['path' => 'src/sections/PricingSection.tsx']);
    }

    public function test_cms_page_section_library_includes_workspace_sections_overlay(): void
    {
        [$owner, $project] = $this->createProject();
        $this->writeWorkspaceFile($project, 'src/sections/PricingSection.tsx', "export default function PricingSection() {\n  return <section className=\"section\"><div className=\"container\">Pricing</div></section>;\n}\n");

        $this->actingAs($owner)
            ->get(route('project.cms', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Project/Cms')
                ->has('sectionLibrary')
                ->where('sectionLibrary', function ($sectionLibrary): bool {
                    foreach ($sectionLibrary as $item) {
                        if (($item['key'] ?? null) === 'PricingSection' && ($item['category'] ?? null) === 'workspace') {
                            return true;
                        }
                    }

                    return false;
                })
            );
    }

    public function test_ai_project_edit_generates_missing_sections_and_keeps_zero_prop_sections_safe(): void
    {
        [$owner, $project] = $this->createProject();
        $this->writeWorkspaceFile($project, 'src/layouts/SiteLayout.tsx', "export default function SiteLayout({ children }: { children: React.ReactNode }) {\n  return <div>{children}</div>;\n}\n");
        $this->writeWorkspaceFile($project, 'src/sections/HeroSection.tsx', "export default function HeroSection() {\n  return <section className=\"section\"><div className=\"container\">Hero</div></section>;\n}\n");

        $this->mock(InternalAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });
        $this->mock(SitePlannerService::class, function (MockInterface $mock) use ($project): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(static fn ($passedProject, $message, $scan): bool => $passedProject instanceof Project
                    && $passedProject->is($project)
                    && $message === 'Create restaurant website'
                    && is_array($scan))
                ->andReturn([
                    'success' => true,
                    'from_fallback' => false,
                    'plan' => [
                        'siteName' => 'Restaurant Website',
                        'pages' => [
                            [
                                'name' => 'home',
                                'title' => 'Home',
                                'sections' => ['HeroSection', 'PricingSection'],
                                'section_intents' => [
                                    ['requested' => 'HeroSection', 'section' => 'HeroSection', 'exists' => true],
                                    ['requested' => 'PricingSection', 'section' => 'PricingSection', 'exists' => false],
                                ],
                            ],
                        ],
                    ],
                ]);
        });
        $this->mock(ComponentGeneratorService::class, function (MockInterface $mock) use ($project): void {
            $mock->shouldReceive('generate')
                ->andReturnUsing(static function ($passedProject, $sectionName, $prompt) use ($project): array {
                    if ($passedProject instanceof Project && (string) $passedProject->getKey() === (string) $project->getKey() && $sectionName === 'PricingSection' && $prompt === 'Create restaurant website') {
                        return [
                            'success' => true,
                            'already_exists' => false,
                            'path' => 'src/sections/PricingSection.tsx',
                            'content' => "export default function PricingSection() {\n  return <section className=\"section\"><div className=\"container\"><h2 className=\"section-title\">Pricing</h2></div></section>;\n}\n",
                        ];
                    }

                    return [
                        'success' => true,
                        'already_exists' => true,
                        'path' => 'src/sections/'.$sectionName.'.tsx',
                    ];
                });
        });

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-project-edit', $project), [
                'message' => 'Create restaurant website',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['path' => 'src/sections/PricingSection.tsx', 'op' => 'createFile'])
            ->assertJsonFragment(['path' => 'src/pages/home/Page.tsx']);

        $pageContent = $this->readWorkspaceFile($project, 'src/pages/home/Page.tsx');
        $generatedSection = $this->readWorkspaceFile($project, 'src/sections/PricingSection.tsx');

        $this->assertNotNull($pageContent);
        $this->assertNotNull($generatedSection);
        $this->assertStringContainsString("import PricingSection from '../../sections/PricingSection';", $pageContent);
        $this->assertStringContainsString('<HeroSection />', $pageContent);
        $this->assertStringContainsString('<PricingSection />', $pageContent);
        $this->assertStringNotContainsString('title="Home"', $pageContent);
    }

    public function test_ai_project_edit_bootstraps_workspace_from_cms_and_includes_shared_code_context(): void
    {
        [$owner, $project] = $this->createProject();
        $workspaceRoot = storage_path('workspaces/'.$project->id);
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page] = $this->prepareHomePageRevision($site->id);
        File::deleteDirectory($workspaceRoot);
        $capturedPrompt = null;

        $this->mock(InternalAiService::class, function (MockInterface $mock) use (&$capturedPrompt): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('completeForProjectEdit')
                ->once()
                ->andReturnUsing(static function (string $prompt) use (&$capturedPrompt): string {
                    $capturedPrompt = $prompt;

                    return '{"no_change":true,"summary":"No changes applied.","reason":"diagnostic capture"}';
                });
        });

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-project-edit', $project), [
                'message' => 'AI მთლიან პროექტში იხედებოდეს და კომპონენტებიც ნახოს',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFileExists($workspaceRoot.'/package.json');
        $this->assertFileExists($workspaceRoot.'/src/pages/home/Page.tsx');
        $this->assertFileExists($workspaceRoot.'/src/components/Header.tsx');
        $this->assertFileExists($workspaceRoot.'/src/layouts/SiteLayout.tsx');

        $diagnosticLog = $response->json('diagnostic_log');
        $this->assertIsArray($diagnosticLog);
        $this->assertContains('Workspace scaffold created automatically.', $diagnosticLog);
        $this->assertContains('Workspace pages were generated from current CMS content.', $diagnosticLog);

        $this->assertIsString($capturedPrompt);
        $this->assertStringContainsString('Current page structure:', $capturedPrompt);
        $this->assertStringContainsString('Styles and theme files:', $capturedPrompt);
        $this->assertStringContainsString('Workspace projection metadata (real editable workspace generated from CMS authority):', $capturedPrompt);
        $this->assertStringContainsString('page home -> src/pages/home/Page.tsx', $capturedPrompt);
        $this->assertStringContainsString('layout Header -> src/components/Header.tsx', $capturedPrompt);
        $this->assertMatchesRegularExpression('/--- BEGIN src\/(components|sections)\/.+ ---/', $capturedPrompt);
        $this->assertStringContainsString('--- BEGIN src/pages/home/Page.tsx ---', $capturedPrompt);
        $this->assertStringContainsString("The user's primary working language is Georgian.", $capturedPrompt);
        $this->assertSame($page->id, Page::query()->where('site_id', $site->id)->where('slug', 'home')->value('id'));
    }

    public function test_ai_site_editor_analyze_exposes_variant_specific_header_fields(): void
    {
        [$owner, $project] = $this->createProject();
        $this->prepareHeaderSixSite($project);

        $response = $this->actingAs($owner)
            ->getJson(route('panel.projects.ai-site-editor.analyze', $project))
            ->assertOk()
            ->assertJsonPath('success', true);

        $header = collect($response->json('global_components'))->firstWhere('id', 'header');

        $this->assertIsArray($header);
        $this->assertContains('top_bar_left_text', $header['editable_fields'] ?? []);
        $this->assertContains('top_strip_text', $header['editable_fields'] ?? []);
    }

    public function test_ai_site_editor_maps_generic_header_text_patch_to_visible_variant_field_and_ignores_section_delete(): void
    {
        [$owner, $project] = $this->createProject();
        $site = $this->prepareHeaderSixSite($project);
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ჰედერში Free Shipping World wide for all orders over $199. ამის ნაცვლად ჩამიწერე უფასო მიწოდება ქვეყნის მასშტაბით',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateGlobalComponent',
                            'component' => 'header',
                            'patch' => [
                                'text' => 'უფასო მიწოდება ქვეყნის მასშტაბით',
                            ],
                        ],
                        [
                            'op' => 'deleteSection',
                            'sectionId' => $localId,
                        ],
                    ],
                    'summary' => ['Header updated', 'Section removed'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $site->refresh();

        $this->assertSame('უფასო მიწოდება ქვეყნის მასშტაბით', data_get($site->theme_settings, 'layout.header_props.top_bar_left_text'));
        $this->assertNull(data_get($site->theme_settings, 'layout.header_props.text'));
        $this->assertFalse(collect($response->json('action_log'))->contains('Section removed'));
        $this->assertCount(1, $response->json('applied_changes'));
        $this->assertSame('updateGlobalComponent', $response->json('applied_changes.0.op'));

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame($localId, data_get($latestRevision->content_json, 'sections.0.localId'));
    }

    public function test_ai_site_editor_execute_regenerates_workspace_projection_for_site_level_changes(): void
    {
        [$owner, $project] = $this->createProject();
        $site = $this->prepareHeaderSixSite($project);
        [$page] = $this->prepareHomePageRevision($site->id);
        $workspace = app(ProjectWorkspaceService::class);
        $scanner = app(CodebaseScanner::class);

        $workspace->initializeProjectCodebase($project);
        $scanner->writeIndex($project, $scanner->scan($project));

        $workspaceRoot = storage_path('workspaces/'.$project->id);
        $indexPath = $workspaceRoot.'/.webu/index.json';
        $authorityPath = $workspaceRoot.'/.webu/cms-authority.json';
        $beforeAuthority = File::get($authorityPath);
        $this->assertFileExists($indexPath);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'Update the top strip text',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateGlobalComponent',
                            'component' => 'header',
                            'patch' => [
                                'top_strip_text' => 'Fresh workspace projection text',
                            ],
                        ],
                    ],
                    'summary' => ['Header updated'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $site->refresh();
        $this->assertFileExists($authorityPath);
        $this->assertNotSame($beforeAuthority, File::get($authorityPath));
        $this->assertSame('Fresh workspace projection text', data_get($site->theme_settings, 'layout.header_props.top_strip_text'));
        $this->assertFileDoesNotExist($indexPath);
    }

    public function test_ai_site_editor_execute_keeps_multibyte_action_log_json_safe(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ჰიროს სათაური გადააკეთე ქართულ გრძელ ტექსტად',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => 'headline',
                            'value' => 'გაზაფხულის და ზაფხულის ახალი კოლექცია უკვე გაყიდვაშია მთელი საქართველოს მასშტაბით',
                        ],
                    ],
                    'summary' => ['განახლდა ჰიროს სათაური'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateText');

        $actionLogEntry = (string) $response->json('action_log.0');

        $this->assertStringContainsString('Text updated (headline):', $actionLogEntry);
        $this->assertStringContainsString('…', $actionLogEntry);
        $this->assertStringNotContainsString('�', $actionLogEntry);
    }

    public function test_ai_site_editor_analyze_reads_localized_page_sections(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareLocalizedHomePageRevision($site->id);

        $response = $this->actingAs($owner)
            ->getJson(route('panel.projects.ai-site-editor.analyze', [
                'project' => $project,
                'locale' => 'ka',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $pageEntry = collect($response->json('pages'))->firstWhere('id', $page->id);

        $this->assertIsArray($pageEntry);
        $this->assertSame($localId, data_get($pageEntry, 'sections.0.id'));
        $this->assertSame('webu_general_heading_01', data_get($pageEntry, 'sections.0.type'));
    }

    public function test_ai_site_editor_execute_updates_localized_nested_parameter_paths(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareLocalizedHomePageRevision($site->id);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'locale' => 'ka',
                'instruction' => 'ღილაკის ტექსტი შეცვალე',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => ['cta', 'label'],
                            'value' => 'შეიძინე ახლავე',
                        ],
                    ],
                    'summary' => ['განახლდა CTA ტექსტი'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateText')
            ->assertJsonPath('applied_changes.0.old_value', 'ძველი ღილაკი')
            ->assertJsonPath('applied_changes.0.new_value', 'შეიძინე ახლავე');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(
            'შეიძინე ახლავე',
            data_get($latestRevision->content_json, 'locales.ka.sections.0.props.cta.label')
        );
        $this->assertStringContainsString(
            'Text updated (cta.label):',
            (string) $response->json('action_log.0')
        );
    }

    public function test_ai_site_editor_execute_applies_top_level_update_button_fields_to_existing_cta_paths(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail()
            ->update([
                'content_json' => [
                    'sections' => [
                        [
                            'localId' => $localId,
                            'type' => 'webu_general_heading_01',
                            'props' => [
                                'primary_cta' => [
                                    'label' => 'SHOP NOW',
                                    'url' => '#',
                                ],
                                'secondary_cta' => [
                                    'label' => 'Learn more',
                                    'url' => '/about',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'SHOP NOW ის ნაცვლად დეტალები დაწერე და მაღაზიის გვერდზე გადადიოდეს',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateButton',
                            'sectionId' => $localId,
                            'label' => 'დეტალები',
                            'href' => '/shop',
                        ],
                    ],
                    'summary' => ['განახლდა CTA ღილაკი'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateButton')
            ->assertJsonPath('applied_changes.0.summary.0', 'primary_cta')
            ->assertJsonPath('diagnostic_log.0', 'Requested operations: updateButton ×1');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(
            'დეტალები',
            data_get($latestRevision->content_json, 'sections.0.props.primary_cta.label')
        );
        $this->assertSame(
            '/shop',
            data_get($latestRevision->content_json, 'sections.0.props.primary_cta.url')
        );
        $this->assertSame(
            'Learn more',
            data_get($latestRevision->content_json, 'sections.0.props.secondary_cta.label')
        );
        $this->assertStringContainsString('primary_cta', (string) $response->json('action_log.0'));
    }

    public function test_ai_site_editor_execute_returns_no_effect_when_change_set_does_not_modify_content_or_theme(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ეს ტექსტი არ შეცვლილა',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => 'headline',
                            'value' => 'Old headline',
                        ],
                    ],
                    'summary' => ['უცვლელი ტექსტი თავიდან ჩაწერა'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'no_effect')
            ->assertJsonPath('diagnostic_log.0', 'Requested operations: updateText ×1');

        $diagnosticLog = $response->json('diagnostic_log');
        $this->assertIsArray($diagnosticLog);
        $this->assertContains('Site settings changed: no', $diagnosticLog);
        $this->assertContains(
            "Verification failed for section op #1 (updateText): Value at headline already matched 'Old headline' before execution",
            $diagnosticLog
        );
    }

    public function test_ai_site_editor_execute_updates_sections_without_persisted_local_ids_using_analyze_fallback_ids(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $page = $this->prepareHomePageRevisionWithoutLocalId($site->id);

        $analyzeResponse = $this->actingAs($owner)
            ->getJson(route('panel.projects.ai-site-editor.analyze', $project))
            ->assertOk()
            ->assertJsonPath('success', true);

        $pageEntry = collect($analyzeResponse->json('pages'))->firstWhere('id', $page->id);
        $sectionId = data_get($pageEntry, 'sections.0.id');

        $this->assertSame('section-0', $sectionId);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ჰიროს სათაური შეცვალე ტექსტზე ახალი სათაური',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $sectionId,
                            'path' => 'headline',
                            'value' => 'ახალი სათაური',
                        ],
                    ],
                    'summary' => ['განახლდა ჰიროს სათაური'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateText')
            ->assertJsonPath('action_log.0', 'Text updated (headline): ახალი სათაური');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('section-0', data_get($latestRevision->content_json, 'sections.0.localId'));
        $this->assertSame('ახალი სათაური', data_get($latestRevision->content_json, 'sections.0.props.headline'));
    }

    public function test_ai_site_editor_execute_returns_no_effect_when_missing_local_id_section_already_matches_requested_text(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $page = $this->prepareHomePageRevisionWithoutLocalId($site->id);

        $analyzeResponse = $this->actingAs($owner)
            ->getJson(route('panel.projects.ai-site-editor.analyze', $project))
            ->assertOk()
            ->assertJsonPath('success', true);

        $pageEntry = collect($analyzeResponse->json('pages'))->firstWhere('id', $page->id);
        $sectionId = data_get($pageEntry, 'sections.0.id');

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ეს სათაური უკვე სწორია',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $sectionId,
                            'path' => 'headline',
                            'value' => 'Legacy headline',
                        ],
                    ],
                    'summary' => ['სათაური უცვლელი დარჩა'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'no_effect');

        $diagnosticLog = $response->json('diagnostic_log', []);

        $this->assertContains(
            "Verification failed for section op #1 (updateText): Value at headline already matched 'Legacy headline' before execution",
            $diagnosticLog
        );
    }

    public function test_ai_site_editor_execute_normalizes_insert_section_aliases_to_registered_builder_components(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'დაამატე features სექცია ჰიროს ქვემოთ',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'insertSection',
                            'sectionType' => 'features',
                            'afterSectionId' => $localId,
                        ],
                    ],
                    'summary' => ['დაემატა features სექცია'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'insertSection')
            ->assertJsonPath('action_log.0', 'Section added: webu_general_features_01');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('webu_general_features_01', data_get($latestRevision->content_json, 'sections.1.type'));
    }

    public function test_ai_site_editor_execute_rejects_changes_outside_selected_element_scope(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'Change this heading text',
                'selected_target' => [
                    'section_id' => $localId,
                    'section_key' => 'webu_general_heading_01',
                    'component_type' => 'webu_general_heading_01',
                    'component_name' => 'Heading',
                    'component_path' => 'headline',
                    'element_id' => 'HeadingSection.headline',
                    'editable_fields' => ['headline', 'background_color', 'advanced.padding_top'],
                    'allowed_updates' => [
                        'scope' => 'element',
                        'operationTypes' => ['updateText'],
                        'fieldPaths' => ['headline'],
                        'sectionOperationTypes' => ['updateText', 'updateSection'],
                        'sectionFieldPaths' => ['headline', 'background_color', 'advanced.padding_top'],
                    ],
                ],
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => 'background_color',
                            'value' => '#111111',
                        ],
                    ],
                    'summary' => ['Changed heading'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'selected_target_scope_violation');
    }

    public function test_ai_site_editor_execute_remaps_unsupported_title_text_updates_to_visible_heading_field(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail()
            ->update([
                'content_json' => [
                    'sections' => [
                        [
                            'localId' => $localId,
                            'type' => 'webu_general_heading_01',
                            'props' => [
                                'title' => 'Exclusive Finance Apps',
                                'headline' => 'ბრენდები',
                            ],
                        ],
                    ],
                ],
            ]);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'locale' => 'ka',
                'instruction' => 'ბრენდები აქ დამიწერ ონლაინ მაღაზია',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => 'title',
                            'value' => 'ონლაინ მაღაზია',
                        ],
                    ],
                    'summary' => ['განახლდა heading ტექსტი'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateText')
            ->assertJsonPath('action_log.0', 'Text updated (headline): ონლაინ მაღაზია');

        $this->assertContains(
            'Section op #1: updateText -> '.$localId.' [title => headline]',
            $response->json('diagnostic_log', [])
        );

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(
            'ონლაინ მაღაზია',
            data_get($latestRevision->content_json, 'sections.0.props.headline')
        );
        $this->assertSame(
            'Exclusive Finance Apps',
            data_get($latestRevision->content_json, 'sections.0.props.title')
        );
    }

    public function test_ai_site_editor_execute_canonicalizes_legacy_alias_sections_before_validation(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));

        SectionLibrary::query()->create([
            'key' => 'hero_split_image',
            'category' => 'marketing',
            'schema_json' => [
                'properties' => [
                    'headline' => ['type' => 'string'],
                ],
            ],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'services_grid_cards',
            'category' => 'business',
            'schema_json' => [
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
            ],
            'enabled' => true,
        ]);

        $page = Page::query()->where('site_id', $site->id)->where('slug', 'home')->first();
        if (! $page) {
            $page = Page::create([
                'site_id' => $site->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        $localId = 'section-0-legacy-hero';
        $content = [
            'sections' => [
                [
                    'localId' => $localId,
                    'key' => 'hero',
                    'type' => 'hero',
                    'binding' => [
                        'source' => 'sections_library',
                        'section_key' => 'hero_split_image',
                    ],
                    'props' => [
                        'headline' => 'ძველი სათაური',
                    ],
                ],
                [
                    'localId' => 'section-1-legacy-services',
                    'key' => 'services',
                    'type' => 'services',
                    'binding' => [
                        'source' => 'sections_library',
                        'section_key' => 'services_grid_cards',
                    ],
                    'props' => [
                        'title' => 'სერვისები',
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        if (! $revision) {
            PageRevision::create([
                'site_id' => $site->id,
                'page_id' => $page->id,
                'version' => 1,
                'content_json' => $content,
            ]);
        } else {
            $revision->update(['content_json' => $content]);
        }

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'ჰიროს სათაური შეცვალე',
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateText',
                            'sectionId' => $localId,
                            'path' => 'headline',
                            'value' => 'ახალი სათაური',
                        ],
                    ],
                    'summary' => ['განახლდა legacy hero სათაური'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateText');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('hero_split_image', data_get($latestRevision->content_json, 'sections.0.key'));
        $this->assertSame('hero_split_image', data_get($latestRevision->content_json, 'sections.0.type'));
        $this->assertSame('services_grid_cards', data_get($latestRevision->content_json, 'sections.1.key'));
        $this->assertSame('services_grid_cards', data_get($latestRevision->content_json, 'sections.1.type'));
        $this->assertSame('ახალი სათაური', data_get($latestRevision->content_json, 'sections.0.props.headline'));
    }

    public function test_ai_site_editor_execute_allows_explicit_same_section_broader_change_for_selected_target(): void
    {
        [$owner, $project] = $this->createProject();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        [$page, $localId] = $this->prepareHomePageRevision($site->id);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-site-editor.execute', $project), [
                'page_id' => $page->id,
                'page_slug' => 'home',
                'instruction' => 'Increase padding for this section',
                'selected_target' => [
                    'section_id' => $localId,
                    'section_key' => 'webu_general_heading_01',
                    'component_type' => 'webu_general_heading_01',
                    'component_name' => 'Heading',
                    'component_path' => 'headline',
                    'element_id' => 'HeadingSection.headline',
                    'editable_fields' => ['headline', 'background_color', 'advanced.padding_top'],
                    'allowed_updates' => [
                        'scope' => 'element',
                        'operationTypes' => ['updateText'],
                        'fieldPaths' => ['headline'],
                        'sectionOperationTypes' => ['updateText', 'updateSection'],
                        'sectionFieldPaths' => ['headline', 'background_color', 'advanced.padding_top'],
                    ],
                ],
                'change_set' => [
                    'operations' => [
                        [
                            'op' => 'updateSection',
                            'sectionId' => $localId,
                            'patch' => [
                                'advanced' => [
                                    'padding_top' => '80px',
                                ],
                            ],
                        ],
                    ],
                    'summary' => ['Increased section padding'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('applied_changes.0.op', 'updateSection');

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('80px', data_get($latestRevision->content_json, 'sections.0.props.advanced.padding_top'));
    }

    public function test_template_demo_uses_legacy_generic_header_text_as_visible_header_copy(): void
    {
        [$owner, $project] = $this->createProject();
        $site = $this->prepareHeaderSixSite($project, [
            'text' => 'უფასო მიწოდება ქვეყნის მასშტაბით',
        ]);

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Ecommerce',
            'version' => '1.0.0',
            'metadata' => [],
            'is_system' => true,
        ]);

        $payload = app(TemplateDemoService::class)->buildPayload(
            $template,
            'home',
            $site,
            $site->locale,
            true
        );

        $this->assertSame('უფასო მიწოდება ქვეყნის მასშტაბით', data_get($payload, 'layout_header.data.top_bar_left_text'));
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function createProject(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        return [$owner, $project];
    }

    /**
     * @param  array<string, mixed>  $headerPropOverrides
     */
    private function prepareHeaderSixSite(Project $project, array $headerPropOverrides = []): \App\Models\Site
    {
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $layout = is_array($themeSettings['layout'] ?? null) ? $themeSettings['layout'] : [];
        $layout['header_section_key'] = 'webu_header_01';
        $layout['header_props'] = array_merge([
            'layout_variant' => 'header-6',
            'top_strip_text' => 'Autumn Collection. A New Season. A New Perspective. Buy Now!',
            'brand_text' => 'ORIMA.',
        ], $headerPropOverrides);
        $themeSettings['layout'] = $layout;
        $site->update(['theme_settings' => $themeSettings]);

        return $site->fresh();
    }

    /**
     * @return array{0: Page, 1: string}
     */
    private function prepareHomePageRevision(string $siteId): array
    {
        $page = Page::query()->where('site_id', $siteId)->where('slug', 'home')->first();
        if (! $page) {
            $page = Page::create([
                'site_id' => $siteId,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        $localId = 'hero-section-1';
        $content = [
            'sections' => [
                [
                    'localId' => $localId,
                    'type' => 'webu_general_heading_01',
                    'props' => [
                        'headline' => 'Old headline',
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $siteId)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        if (! $revision) {
            PageRevision::create([
                'site_id' => $siteId,
                'page_id' => $page->id,
                'version' => 1,
                'content_json' => $content,
            ]);
        } else {
            $revision->update(['content_json' => $content]);
        }

        return [$page->fresh(), $localId];
    }

    private function prepareHomePageRevisionWithoutLocalId(string $siteId): Page
    {
        SectionLibrary::query()->firstOrCreate(
            ['key' => 'hero_split_image'],
            [
                'category' => 'marketing',
                'schema_json' => [
                    'properties' => [
                        'headline' => ['type' => 'string'],
                        'subtitle' => ['type' => 'string'],
                    ],
                ],
                'enabled' => true,
            ]
        );

        $page = Page::query()->where('site_id', $siteId)->where('slug', 'home')->first();
        if (! $page) {
            $page = Page::create([
                'site_id' => $siteId,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        $content = [
            'sections' => [
                [
                    'key' => 'hero_split_image',
                    'type' => 'hero_split_image',
                    'binding' => [
                        'source' => 'sections_library',
                        'section_key' => 'hero_split_image',
                    ],
                    'props' => [
                        'headline' => 'Legacy headline',
                        'subtitle' => 'Legacy subtitle',
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $siteId)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        if (! $revision) {
            PageRevision::create([
                'site_id' => $siteId,
                'page_id' => $page->id,
                'version' => 1,
                'content_json' => $content,
            ]);
        } else {
            $revision->update(['content_json' => $content]);
        }

        return $page->fresh();
    }

    /**
     * @return array{0: Page, 1: string}
     */
    private function prepareLocalizedHomePageRevision(string $siteId): array
    {
        $page = Page::query()->where('site_id', $siteId)->where('slug', 'home')->first();
        if (! $page) {
            $page = Page::create([
                'site_id' => $siteId,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        $localId = 'hero-section-localized';
        $content = [
            'locales' => [
                'ka' => [
                    'sections' => [
                        [
                            'localId' => $localId,
                            'type' => 'webu_general_heading_01',
                            'props' => [
                                'headline' => 'ძველი სათაური',
                                'cta' => [
                                    'label' => 'ძველი ღილაკი',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $revision = PageRevision::query()
            ->where('site_id', $siteId)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        if (! $revision) {
            PageRevision::create([
                'site_id' => $siteId,
                'page_id' => $page->id,
                'version' => 1,
                'content_json' => $content,
            ]);
        } else {
            $revision->update(['content_json' => $content]);
        }

        return [$page->fresh(), $localId];
    }

    public function test_workspace_list_files_returns_only_real_workspace_paths_no_derived_preview(): void
    {
        [$owner, $project] = $this->createProject();
        $workspace = app(ProjectWorkspaceService::class);
        $workspace->initializeProjectCodebase($project);

        $files = $workspace->listFiles($project);

        $paths = array_column($files, 'path');
        foreach ($paths as $path) {
            $this->assertStringNotContainsString('derived-preview', $path, 'Workspace must not include derived-preview paths');
            $this->assertStringNotContainsString('__generated_pages__', $path, 'Workspace must not include legacy __generated_pages__ paths');
        }
        $this->assertNotEmpty($paths, 'Workspace should have files after initialization');
        $this->assertContains('src/App.tsx', $paths);
        $this->assertContains('src/main.tsx', $paths);
        $firstFile = collect($files)->firstWhere('is_dir', false);
        $this->assertNotNull($firstFile);
        $this->assertArrayHasKey('is_editable', $firstFile);
        $this->assertArrayHasKey('is_generated_projection', $firstFile);
        $this->assertArrayHasKey('projection_source', $firstFile);
    }

    private function writeWorkspaceFile(Project $project, string $relativePath, string $content): void
    {
        $path = storage_path('workspaces/'.$project->id.'/'.ltrim($relativePath, '/'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    private function readWorkspaceFile(Project $project, string $relativePath): ?string
    {
        $path = storage_path('workspaces/'.$project->id.'/'.ltrim($relativePath, '/'));

        return is_file($path) ? File::get($path) : null;
    }
}
