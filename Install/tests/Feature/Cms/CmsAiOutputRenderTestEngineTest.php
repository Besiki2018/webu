<?php

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiOutputRenderTestEngineTest extends TestCase
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

    public function test_it_runs_preview_smoke_checks_against_saved_generated_output_using_app_preview_and_bootstrap_bridge(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published('ai-render-smoke')
            ->create([
                'published_visibility' => 'public',
            ]);
        $site = $project->site()->firstOrFail();

        $this->seedSectionLibrary();

        $pageEngine = app(CmsAiPageGenerationEngine::class);
        $themeEngine = app(CmsAiThemeGenerationEngine::class);
        $placementEngine = app(CmsAiComponentPlacementStylingEngine::class);
        $validationEngine = app(CmsAiOutputValidationEngine::class);
        $saveEngine = app(CmsAiOutputSaveEngine::class);
        $renderEngine = app(CmsAiOutputRenderTestEngine::class);

        $input = $this->validAiInputForSite((string) $project->id, (string) $site->id);
        $input['request']['mode'] = 'generate_site';
        $input['request']['prompt'] = 'Generate an ecommerce storefront with product pages, cart, checkout, account, and orders.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
        ];
        $input['request']['user_context'] = [
            'business_name' => 'Render Smoke Shop',
            'brand_tone' => 'modern professional',
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
        $this->assertTrue($pageResult['ok'], json_encode($pageResult, JSON_PRETTY_PRINT));
        $this->assertTrue($themeResult['ok'], json_encode($themeResult, JSON_PRETTY_PRINT));

        $placementResult = $placementEngine->applyToPagesOutput(
            $input,
            $pageResult['pages_output'],
            is_array($themeResult['theme_output'] ?? null) ? $themeResult['theme_output'] : []
        );
        $this->assertTrue($placementResult['ok'], json_encode($placementResult, JSON_PRETTY_PRINT));

        $output = $this->outputEnvelope(
            theme: is_array($themeResult['theme_output'] ?? null) ? $themeResult['theme_output'] : ['theme_settings_patch' => []],
            pages: $placementResult['pages_output'],
            header: [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => ['headline' => 'AI Header'],
                'bindings' => ['login_url' => '/account/login'],
                'meta' => ['source' => 'generated'],
            ],
            footer: [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => ['copyright' => '2026'],
                'bindings' => ['menu_key' => 'menu.footer'],
                'meta' => ['source' => 'generated'],
            ],
        );

        $validation = $validationEngine->validateOutputForSite($site, $output);
        $this->assertTrue($validation['ok'], json_encode($validation, JSON_PRETTY_PRINT));

        $save = $saveEngine->persistOutputForSite($site, $output, $owner->id);
        $this->assertTrue($save['ok'], json_encode($save, JSON_PRETTY_PRINT));

        Storage::disk('local')->put(
            "previews/{$project->id}/index.html",
            <<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>AI Preview Smoke</title></head>
<body><main id="app">AI Preview Render Smoke</main></body>
</html>
HTML
        );

        $render = $renderEngine->runPreviewSmokeForProject($project->fresh(), [
            'ai_output' => $output,
            'resolved_domain' => 'ai-render-smoke.local',
            'require_preview_assets' => true,
            'check_html_preview' => true,
            'max_pages' => 8,
        ]);

        $this->assertTrue($render['ok'], json_encode($render, JSON_PRETTY_PRINT));
        $this->assertTrue((bool) data_get($render, 'validation.preview_assets.valid'));
        $this->assertTrue((bool) data_get($render, 'validation.bootstrap_smoke.valid'));
        $this->assertGreaterThanOrEqual(3, (int) data_get($render, 'validation.bootstrap_smoke.checked_pages'));

        $smokedSlugs = collect(data_get($render, 'validation.bootstrap_smoke.pages', []))
            ->pluck('slug')
            ->all();
        $this->assertContains('home', $smokedSlugs);
        $this->assertContains('product', $smokedSlugs);
        $this->assertContains('checkout', $smokedSlugs);

        $productSmoke = collect(data_get($render, 'validation.bootstrap_smoke.pages', []))->firstWhere('slug', 'product');
        $this->assertTrue((bool) data_get($productSmoke, 'ok'));
        $this->assertSame('ai-smoke-product', data_get($productSmoke, 'route_params.product_slug'));

        Storage::disk('local')->put(
            "previews/{$project->id}/index.html",
            <<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>AI Preview Smoke (Builder Preview)</title></head>
<body>
  <header data-webu-section="webu_header_01" data-webu-menu="header"></header>
  <main id="app">AI Preview Render Smoke</main>
  <footer data-webu-section="webu_footer_01"></footer>
</body>
</html>
HTML
        );

        $previewRender = $renderEngine->runPreviewSmoke($project->fresh(), $owner, [
            'slugs' => ['home', 'product'],
            'route_params_by_slug' => [
                'product' => [
                    'product_slug' => 'premium-dog-snack',
                ],
            ],
        ]);

        $this->assertTrue($previewRender['ok'], json_encode($previewRender, JSON_PRETTY_PRINT));
        $this->assertTrue((bool) data_get($previewRender, 'checks.preview_index.exists'));
        $this->assertSame(200, data_get($previewRender, 'checks.preview_html.status'));
        $this->assertTrue((bool) data_get($previewRender, 'checks.preview_html.markers.id="preview-inspector"'));
        $previewProduct = collect(data_get($previewRender, 'checks.bootstrap', []))->firstWhere('slug', 'product');
        $this->assertTrue((bool) data_get($previewProduct, 'ok'));
        $this->assertSame('premium-dog-snack', data_get($previewProduct, 'route_params.product_slug'));
        $this->assertSame('premium-dog-snack', data_get($previewProduct, 'route_params.slug'));
    }

    public function test_it_fails_safely_when_preview_assets_are_required_but_missing_and_bootstrap_has_no_sections(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published('ai-render-smoke-missing')
            ->create([
                'published_visibility' => 'public',
            ]);

        Storage::disk('local')->delete("previews/{$project->id}/index.html");

        $engine = app(CmsAiOutputRenderTestEngine::class);
        $result = $engine->runPreviewSmokeForProject($project, [
            'require_preview_assets' => true,
            'check_html_preview' => true,
            'max_pages' => 2,
        ]);

        $this->assertFalse($result['ok']);
        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('preview_asset_missing', $errorCodes);
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'validation.bootstrap_smoke.checked_pages'));
    }

    public function test_architecture_doc_documents_preview_smoke_render_engine_and_pipeline_position(): void
    {
        $path = base_path('docs/architecture/CMS_AI_OUTPUT_RENDER_TEST_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Output Render Test Engine v1', $doc);
        $this->assertStringContainsString('P4-E3-02', $doc);
        $this->assertStringContainsString('CmsAiOutputRenderTestEngine', $doc);
        $this->assertStringContainsString('app.serve', $doc);
        $this->assertStringContainsString('preview.serve', $doc);
        $this->assertStringContainsString('__cms/bootstrap', $doc);
        $this->assertStringContainsString('runPreviewSmoke', $doc);
        $this->assertStringContainsString('id="preview-inspector"', $doc);
        $this->assertStringContainsString('CmsAiOutputValidationEngine', $doc);
        $this->assertStringContainsString('CmsAiOutputSaveEngine', $doc);
        $this->assertStringContainsString('preview smoke checks', $doc);
    }

    private function seedSectionLibrary(): void
    {
        foreach ([
            ['key' => 'hero_split_image', 'category' => 'marketing', 'enabled' => true],
            ['key' => 'ecommerce_product_grid', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'ecommerce_product_detail', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'ecommerce_cart', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'ecommerce_checkout', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'ecommerce_orders', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'ecommerce_order_detail', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'auth_login_register', 'category' => 'auth', 'enabled' => true],
            ['key' => 'ecommerce_account', 'category' => 'ecommerce', 'enabled' => true],
            ['key' => 'contact_split_form', 'category' => 'contact', 'enabled' => true],
            ['key' => 'faq', 'category' => 'content', 'enabled' => true],
            ['key' => 'webu_header_01', 'category' => 'layout', 'enabled' => true],
            ['key' => 'webu_footer_01', 'category' => 'layout', 'enabled' => true],
        ] as $row) {
            SectionLibrary::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'category' => $row['category'],
                    'schema_json' => [],
                    'enabled' => $row['enabled'],
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $theme
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $footer
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
                'request_id' => 'req-render-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }
}
