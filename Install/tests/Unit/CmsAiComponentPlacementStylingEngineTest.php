<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiComponentPlacementStylingEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_it_applies_deterministic_component_placement_and_styling_rules_to_generated_pages(): void
    {
        $pageEngine = app(CmsAiPageGenerationEngine::class);
        $themeEngine = app(CmsAiThemeGenerationEngine::class);
        $placementEngine = app(CmsAiComponentPlacementStylingEngine::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'generate_site';
        $input['request']['prompt'] = 'Use arctic preset for a modern electronics ecommerce storefront with product pages, cart, checkout, and professional style #0ea5e9.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
        ];
        $input['request']['user_context'] = [
            'brand_tone' => 'professional minimal',
        ];
        $input['platform_context']['template_blueprint']['default_pages'] = [
            ['slug' => 'home', 'title' => 'Home', 'sections' => ['faq', 'ecommerce_product_grid', 'hero_split_image']],
        ];
        $input['platform_context']['template_blueprint']['default_sections'] = [
            'home' => [
                ['key' => 'faq', 'enabled' => true],
                ['key' => 'ecommerce_product_grid', 'enabled' => true],
                ['key' => 'hero_split_image', 'enabled' => true, 'props' => ['headline' => 'Hero First After Placement']],
            ],
        ];
        $input['platform_context']['section_library'] = [
            ['key' => 'hero_split_image', 'category' => 'marketing', 'schema_json' => ['bindings' => ['headline' => 'content.headline']], 'enabled' => true],
            ['key' => 'ecommerce_product_grid', 'category' => 'ecommerce', 'schema_json' => ['bindings' => ['title' => 'content.title']], 'enabled' => true],
            ['key' => 'ecommerce_product_detail', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_cart', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_checkout', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
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
        $this->assertTrue((bool) data_get($placementResult, 'validation.output_pages.valid'));
        $this->assertTrue((bool) data_get($placementResult, 'validation.canonical_nodes.valid'));
        $this->assertSame(1, data_get($placementResult, 'decisions.ruleset_version'));
        $this->assertSame('arctic', data_get($placementResult, 'decisions.theme_context.preset'));

        $home = collect($placementResult['pages_output'])->firstWhere('slug', 'home');
        $this->assertNotNull($home);
        $homeNodeTypes = collect(data_get($home, 'builder_nodes', []))
            ->map(fn (array $node): string => (string) ($node['type'] ?? ''))
            ->all();
        $this->assertSame('hero-split-image', $homeNodeTypes[0] ?? null);
        $this->assertSame('ecommerce-product-grid', $homeNodeTypes[1] ?? null);
        $this->assertSame('faq', $homeNodeTypes[2] ?? null);
        $this->assertSame('hero_or_primary', data_get($home, 'builder_nodes.0.meta.ai_placement.slot'));
        $this->assertSame('catalog_or_main', data_get($home, 'builder_nodes.1.meta.ai_placement.slot'));
        $this->assertSame('#0ea5e9', data_get($home, 'builder_nodes.0.props.style.accent_color'));
        $this->assertSame('0.375rem', data_get($home, 'builder_nodes.0.props.style.border_radius_base'));
        $this->assertSame('arctic', data_get($home, 'builder_nodes.1.props.style.theme_preset'));
        $this->assertSame(4, data_get($home, 'builder_nodes.1.props.style.grid_columns_desktop'));
        $this->assertStringContainsString('webu-ai-placement:v1', (string) data_get($home, 'page_css'));

        $product = collect($placementResult['pages_output'])->firstWhere('slug', 'product');
        $this->assertNotNull($product);
        $this->assertSame('/product/{slug}', data_get($product, 'route_pattern'));
        $this->assertSame('two-column', data_get($product, 'builder_nodes.0.props.style.layout_mode'));
        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.bindings.product_slug'));

        $checkout = collect($placementResult['pages_output'])->firstWhere('slug', 'checkout');
        $this->assertSame('conversion', data_get($checkout, 'builder_nodes.0.props.advanced.ai_priority'));
        $this->assertSame('conversion', data_get($checkout, 'builder_nodes.0.props.advanced.attributes.data-webu-ai-priority'));
    }

    public function test_it_reports_invalid_pages_output_shape_before_rule_application(): void
    {
        $placementEngine = app(CmsAiComponentPlacementStylingEngine::class);

        $result = $placementEngine->applyToPagesOutput(
            $this->validAiInput(),
            ['slug' => 'home']
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_pages_output', $result['code']);
        $this->assertSame('$.pages_output', data_get($result, 'errors.0.path'));
    }

    public function test_it_reports_invalid_ai_input_payloads_using_phase_e1_validator_contract(): void
    {
        $placementEngine = app(CmsAiComponentPlacementStylingEngine::class);

        $result = $placementEngine->applyToPagesOutput([
            'schema_version' => 1,
            'request' => ['mode' => 'generate_pages'],
        ], []);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_ai_input', $result['code']);
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            data_get($result, 'validation.input.schema')
        );
    }

    public function test_architecture_doc_documents_non_duplicate_component_placement_and_styling_pipeline(): void
    {
        $path = base_path('docs/architecture/CMS_AI_COMPONENT_PLACEMENT_STYLING_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Component Placement & Styling Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-03', $doc);
        $this->assertStringContainsString('component placement and styling rules engine', $doc);
        $this->assertStringContainsString('CmsAiPageGenerationEngine', $doc);
        $this->assertStringContainsString('CmsAiThemeGenerationEngine', $doc);
        $this->assertStringContainsString('builder_nodes[]', $doc);
        $this->assertStringContainsString('page_revisions.content_json', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1', $doc);
        $this->assertStringContainsString('no parallel AI page storage', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiInput(): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_pages',
                'prompt' => 'Generate pages',
                'locale' => 'en',
                'target' => [
                    'route_scope' => 'pages',
                ],
            ],
            'platform_context' => [
                'project' => [
                    'id' => '1',
                    'name' => 'Demo Project',
                ],
                'site' => [
                    'id' => '1',
                    'name' => 'Demo Site',
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
                        'id' => '1',
                        'project_id' => '1',
                        'name' => 'Demo Site',
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
                    'site_id' => '1',
                    'project_id' => '1',
                    'modules' => [],
                    'summary' => ['total' => 0, 'available' => 0, 'disabled' => 0, 'not_entitled' => 0],
                ],
                'module_entitlements' => [
                    'site_id' => '1',
                    'project_id' => '1',
                    'features' => [],
                    'modules' => [],
                    'reasons' => [],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req-placement-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }
}
