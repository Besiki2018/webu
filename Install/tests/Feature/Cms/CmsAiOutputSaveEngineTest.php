<?php

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiOutputSaveEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        config()->set('theme-presets', [
            'default' => ['name' => 'Default'],
            'arctic' => ['name' => 'Arctic'],
            'ocean' => ['name' => 'Ocean'],
            'summer' => ['name' => 'Summer'],
            'slate' => ['name' => 'Slate'],
            'midnight' => ['name' => 'Midnight'],
            'forest' => ['name' => 'Forest'],
        ]);
    }

    public function test_it_persists_ai_output_into_current_site_page_and_revision_models_without_parallel_storage(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        $pageEngine = app(CmsAiPageGenerationEngine::class);
        $themeEngine = app(CmsAiThemeGenerationEngine::class);
        $placementEngine = app(CmsAiComponentPlacementStylingEngine::class);
        $saveEngine = app(CmsAiOutputSaveEngine::class);

        $input = $this->validAiInputForSite($project->id, $site->id);
        $input['request']['mode'] = 'generate_site';
        $input['request']['prompt'] = 'Generate an ecommerce storefront with product pages, cart, checkout and arctic style #0ea5e9.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
        ];
        $input['request']['user_context'] = [
            'business_name' => 'AI Demo Shop',
            'brand_tone' => 'professional',
        ];
        $input['platform_context']['template_blueprint']['default_pages'] = [
            ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero_split_image', 'ecommerce_product_grid']],
        ];
        $input['platform_context']['template_blueprint']['default_sections'] = [
            'home' => [
                ['key' => 'hero_split_image', 'enabled' => true, 'props' => ['headline' => 'Welcome']],
                ['key' => 'ecommerce_product_grid', 'enabled' => true],
            ],
        ];
        $input['platform_context']['section_library'] = [
            ['key' => 'hero_split_image', 'category' => 'marketing', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_product_grid', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_product_detail', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_cart', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_checkout', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_orders', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_order_detail', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'auth_login_register', 'category' => 'auth', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_account', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'contact_split_form', 'category' => 'contact', 'schema_json' => [], 'enabled' => true],
            ['key' => 'faq', 'category' => 'content', 'schema_json' => [], 'enabled' => true],
        ];
        $input['platform_context']['module_registry']['modules'] = [
            ['key' => 'ecommerce', 'enabled' => true, 'available' => true],
        ];
        $input['platform_context']['module_entitlements']['modules'] = [
            'ecommerce' => true,
        ];

        $pageResult = $pageEngine->generateFromAiInput($input);
        $themeResult = $themeEngine->generateFromAiInput($input);

        $this->assertTrue($pageResult['ok']);
        $this->assertTrue($themeResult['ok']);

        $placementResult = $placementEngine->applyToPagesOutput(
            $input,
            $pageResult['pages_output'],
            is_array($themeResult['theme_output'] ?? null) ? $themeResult['theme_output'] : []
        );

        $this->assertTrue($placementResult['ok']);

        $pagesOutput = $placementResult['pages_output'];
        $homePageOutputIndex = array_search('home', array_column($pagesOutput, 'slug'), true);
        $this->assertNotFalse($homePageOutputIndex);
        $pagesOutput[$homePageOutputIndex]['status'] = 'published';
        $pagesOutput[] = [
            'slug' => 'legacy-section-key-smoke',
            'title' => 'Legacy Section Key Smoke',
            'status' => 'draft',
            'builder_nodes' => [
                [
                    'type' => 'webu_hero_01',
                    'props' => [
                        'content' => ['headline' => 'Legacy Hero'],
                        'data' => [],
                        'style' => [],
                        'advanced' => [],
                        'responsive' => [],
                        'states' => [],
                    ],
                    'bindings' => [],
                    'meta' => ['schema_version' => 1, 'source' => 'test'],
                ],
            ],
            'meta' => [
                'source' => 'generated',
            ],
        ];

        $output = $this->outputEnvelope(
            theme: array_merge(
                is_array($themeResult['theme_output'] ?? null) ? $themeResult['theme_output'] : ['theme_settings_patch' => []],
                [
                    'global_settings_patch' => [
                        'contact_json' => [
                            'email' => 'hello@example.com',
                        ],
                    ],
                ]
            ),
            pages: $pagesOutput,
            header: [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [
                    'headline' => 'AI Header',
                ],
                'bindings' => [
                    'login_url' => '/account/login',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ],
            footer: [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [
                    'copyright' => 'AI Footer',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ]
        );

        $home = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();
        $beforeHomeRevisionCount = PageRevision::query()->where('site_id', $site->id)->where('page_id', $home->id)->count();

        $result = $saveEngine->persistOutputForSite($site, $output, $owner->id);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame(0, data_get($result, 'validation.output.error_count'));
        $this->assertTrue((bool) data_get($result, 'saved.no_parallel_storage'));
        $this->assertSame(
            ['sites.theme_settings', 'global_settings', 'pages', 'page_revisions.content_json'],
            data_get($result, 'saved.storage_channels')
        );
        $this->assertGreaterThan(0, (int) data_get($result, 'saved.pages.created_revisions'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'saved.pages.published_revisions'));

        $site->refresh();
        $this->assertSame('published', $site->status);
        $this->assertSame(data_get($themeResult, 'theme_output.theme_settings_patch.preset'), data_get($site->theme_settings, 'preset'));
        $this->assertSame('webu_header_01', data_get($site->theme_settings, 'layout.header_section_key'));
        $this->assertSame('webu_footer_01', data_get($site->theme_settings, 'layout.footer_section_key'));
        $this->assertSame('AI Header', data_get($site->theme_settings, 'layout.header_props.headline'));
        $this->assertSame('/account/login', data_get($site->theme_settings, 'layout.header_meta.bindings.login_url'));

        $this->assertDatabaseHas('global_settings', [
            'site_id' => $site->id,
        ]);
        $global = $site->globalSettings()->firstOrFail();
        $this->assertSame('hello@example.com', data_get($global->contact_json, 'email'));

        $home->refresh();
        $this->assertSame('published', $home->status);
        $this->assertGreaterThanOrEqual(
            $beforeHomeRevisionCount + 1,
            PageRevision::query()->where('site_id', $site->id)->where('page_id', $home->id)->count()
        );

        $latestHomeRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $home->id)
            ->latest('version')
            ->firstOrFail();

        $this->assertNotNull($latestHomeRevision->published_at);
        $this->assertIsArray($latestHomeRevision->content_json);
        $this->assertIsArray(data_get($latestHomeRevision->content_json, 'sections'));
        $this->assertNotEmpty(data_get($latestHomeRevision->content_json, 'sections'));
        $this->assertSame('CmsAiOutputSaveEngine', data_get($latestHomeRevision->content_json, 'ai_generation.saved_via'));
        $this->assertIsArray(data_get($latestHomeRevision->content_json, 'ai_generation.builder_nodes'));
        $this->assertStringContainsString('webu-ai-placement:v1', (string) data_get($latestHomeRevision->content_json, 'ai_generation.page_css'));
        $this->assertSame('/', data_get($latestHomeRevision->content_json, 'ai_generation.route.path'));

        $productPage = Page::query()->where('site_id', $site->id)->where('slug', 'product')->first();
        $this->assertNotNull($productPage);
        $productLatest = PageRevision::query()->where('site_id', $site->id)->where('page_id', $productPage->id)->latest('version')->firstOrFail();
        $this->assertSame('/product/{slug}', data_get($productLatest->content_json, 'ai_generation.route.route_pattern'));
        $this->assertSame('{{route.params.slug}}', data_get($productLatest->content_json, 'ai_generation.builder_nodes.0.bindings.product_slug'));

        $legacyPage = Page::query()->where('site_id', $site->id)->where('slug', 'legacy-section-key-smoke')->first();
        $this->assertNotNull($legacyPage);
        $legacyLatest = PageRevision::query()->where('site_id', $site->id)->where('page_id', $legacyPage->id)->latest('version')->firstOrFail();
        $this->assertSame('webu_hero_01', data_get($legacyLatest->content_json, 'sections.0.type'));
        $this->assertSame('webu_hero_01', data_get($legacyLatest->content_json, 'ai_generation.builder_nodes.0.type'));
    }

    public function test_it_rejects_invalid_ai_output_and_makes_no_persistence_changes(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        $saveEngine = app(CmsAiOutputSaveEngine::class);

        $pagesCountBefore = Page::query()->where('site_id', $site->id)->count();
        $revisionsCountBefore = PageRevision::query()->where('site_id', $site->id)->count();
        $themeBefore = $site->theme_settings;

        $result = $saveEngine->persistOutputForSite($site, [
            'schema_version' => 1,
            'theme' => ['theme_settings_patch' => []],
            'pages' => [],
            'header' => ['enabled' => true, 'section_type' => null, 'props' => []],
            // footer missing
            'meta' => [],
        ], $owner->id);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_ai_output', $result['code']);
        $this->assertNotEmpty($result['errors']);
        $this->assertNull($result['saved']);

        $site->refresh();
        $this->assertSame($themeBefore, $site->theme_settings);
        $this->assertSame($pagesCountBefore, Page::query()->where('site_id', $site->id)->count());
        $this->assertSame($revisionsCountBefore, PageRevision::query()->where('site_id', $site->id)->count());
    }

    public function test_architecture_doc_documents_current_model_persistence_mapping_and_no_parallel_storage_rule(): void
    {
        $path = base_path('docs/architecture/CMS_AI_OUTPUT_SAVE_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Output Save Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-04', $doc);
        $this->assertStringContainsString('current page revision/content model', $doc);
        $this->assertStringContainsString('no parallel page storage', $doc);
        $this->assertStringContainsString('site.theme_settings', $doc);
        $this->assertStringContainsString('global_settings', $doc);
        $this->assertStringContainsString('pages', $doc);
        $this->assertStringContainsString('page_revisions.content_json', $doc);
        $this->assertStringContainsString('builder_nodes', $doc);
        $this->assertStringContainsString('sections', $doc);
        $this->assertStringContainsString('page_css', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function outputEnvelope(array $theme, array $pages, array $header, array $footer): array
    {
        return [
            'schema_version' => 1,
            'theme' => $theme,
            'pages' => $pages,
            'header' => $header,
            'footer' => $footer,
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => '2026-02-24T12:00:00Z',
                'contracts' => [
                    'ai_input_schema' => 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    'canonical_page_node_schema' => 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    'canonical_component_registry_schema' => 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                ],
                'validation_expectations' => [
                    'strict_top_level' => true,
                    'no_parallel_storage' => true,
                    'builder_native_pages' => true,
                    'component_availability_check_required' => true,
                    'binding_validation_required' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiInputForSite(string $projectId, string $siteId): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_pages',
                'prompt' => 'Generate pages',
                'locale' => 'en',
                'target' => [
                    'route_scope' => 'site',
                ],
            ],
            'platform_context' => [
                'project' => [
                    'id' => $projectId,
                    'name' => 'AI Project',
                ],
                'site' => [
                    'id' => $siteId,
                    'name' => 'AI Site',
                    'status' => 'draft',
                    'locale' => 'en',
                    'theme_settings' => [],
                ],
                'template_blueprint' => [
                    'template_id' => null,
                    'template_slug' => 'webu-shop-01',
                    'default_pages' => [],
                    'default_sections' => [],
                ],
                'site_settings_snapshot' => [
                    'site' => [
                        'id' => $siteId,
                        'project_id' => $projectId,
                        'name' => 'AI Site',
                        'status' => 'draft',
                        'locale' => 'en',
                        'theme_settings' => [],
                    ],
                    'typography' => [],
                    'global_settings' => [
                        'logo_media_id' => null,
                        'logo_asset_url' => null,
                        'contact_json' => [],
                        'social_links_json' => [],
                        'analytics_ids_json' => [],
                    ],
                ],
                'section_library' => [],
                'module_registry' => [
                    'site_id' => $siteId,
                    'project_id' => $projectId,
                    'modules' => [],
                    'summary' => ['total' => 0, 'available' => 0, 'disabled' => 0, 'not_entitled' => 0],
                ],
                'module_entitlements' => [
                    'site_id' => $siteId,
                    'project_id' => $projectId,
                    'features' => [],
                    'modules' => [],
                    'reasons' => [],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req-save-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }
}
