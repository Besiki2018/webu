<?php

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiOutputValidationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_validates_schema_component_availability_and_bindings_for_ai_output_v1(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        SectionLibrary::query()->create([
            'key' => 'hero_split_image',
            'category' => 'marketing',
            'schema_json' => [],
            'enabled' => true,
        ]);
        SectionLibrary::query()->create([
            'key' => 'ecommerce_product_detail',
            'category' => 'ecommerce',
            'schema_json' => [],
            'enabled' => true,
        ]);
        SectionLibrary::query()->create([
            'key' => 'webu_header_01',
            'category' => 'layout',
            'schema_json' => [],
            'enabled' => true,
        ]);
        SectionLibrary::query()->create([
            'key' => 'webu_footer_01',
            'category' => 'layout',
            'schema_json' => [],
            'enabled' => true,
        ]);

        $engine = app(CmsAiOutputValidationEngine::class);
        $result = $engine->validateOutputForSite($site, $this->validAiOutput());

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertTrue((bool) data_get($result, 'validation.schema.valid'));
        $this->assertTrue((bool) data_get($result, 'validation.component_availability.valid'));
        $this->assertTrue((bool) data_get($result, 'validation.bindings.valid'));
        $this->assertGreaterThanOrEqual(4, (int) data_get($result, 'validation.component_availability.checked_components'));
        $this->assertGreaterThanOrEqual(4, (int) data_get($result, 'validation.bindings.checked_candidates'));
        $this->assertSame([], data_get($result, 'errors'));
    }

    public function test_it_reports_component_availability_and_binding_validation_failures_for_invalid_output(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        SectionLibrary::query()->create([
            'key' => 'ecommerce_product_detail',
            'category' => 'ecommerce',
            'schema_json' => [],
            'enabled' => false,
        ]);
        SectionLibrary::query()->create([
            'key' => 'webu_footer_01',
            'category' => 'layout',
            'schema_json' => [],
            'enabled' => true,
        ]);

        $output = $this->validAiOutput();
        $output['pages'][1]['builder_nodes'][0]['bindings']['product_slug'] = '{{route.params.id}}';
        $output['pages'][1]['builder_nodes'][0]['bindings']['bad'] = '{{ route.params.slug + 1 }}';
        $output['pages'][1]['builder_nodes'][0]['props']['content']['title'] = '{{unknown.path}}';
        $output['pages'][0]['builder_nodes'][] = $this->node('mystery-widget');
        $output['header']['section_type'] = null;

        $engine = app(CmsAiOutputValidationEngine::class);
        $result = $engine->validateOutputForSite($site, $output);

        $this->assertFalse($result['ok']);
        $this->assertTrue((bool) data_get($result, 'validation.schema.valid'));
        $this->assertFalse((bool) data_get($result, 'validation.component_availability.valid'));
        $this->assertFalse((bool) data_get($result, 'validation.bindings.valid'));

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('missing_enabled_fixed_section_type', $errorCodes);
        $this->assertContains('disabled_component', $errorCodes);
        $this->assertContains('unavailable_component', $errorCodes);
        $this->assertContains('invalid_syntax', $errorCodes);
        $this->assertContains('unsupported_namespace', $errorCodes);
        $this->assertContains('invalid_required_route_binding', $errorCodes);
    }

    public function test_it_enforces_foundation_layout_nesting_rules_for_nested_builder_nodes(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        foreach ([
            'hero_split_image',
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_spacer_01',
            'webu_general_divider_01',
            'webu_header_01',
            'webu_footer_01',
        ] as $key) {
            SectionLibrary::query()->create([
                'key' => $key,
                'category' => str_contains($key, 'general') ? 'general' : 'layout',
                'schema_json' => [],
                'enabled' => true,
            ]);
        }

        $engine = app(CmsAiOutputValidationEngine::class);

        $validOutput = $this->outputEnvelope(
            pages: [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'path' => '/',
                    'status' => 'draft',
                    'builder_nodes' => [
                        $this->node('webu_general_section_01', [], [], [
                            $this->node('webu_general_container_01', [], [], [
                                $this->node('hero_split_image'),
                            ]),
                            $this->node('webu_general_grid_01', [], [], [
                                $this->node('hero_split_image'),
                            ]),
                            $this->node('webu_general_columns_01', [], [], [
                                $this->node('hero_split_image'),
                            ]),
                        ]),
                    ],
                    'meta' => [
                        'source' => 'generated',
                    ],
                ],
            ],
            header: [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [],
                'bindings' => [],
                'meta' => ['source' => 'generated'],
            ],
            footer: [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [],
                'bindings' => [],
                'meta' => ['source' => 'generated'],
            ]
        );

        $validResult = $engine->validateOutputForSite($site, $validOutput);
        $this->assertTrue($validResult['ok'], json_encode($validResult, JSON_PRETTY_PRINT));
        $this->assertNotContains(
            'invalid_layout_nesting_child',
            collect($validResult['errors'])->pluck('code')->all()
        );

        $invalidOutput = $this->outputEnvelope(
            pages: [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'path' => '/',
                    'status' => 'draft',
                    'builder_nodes' => [
                        $this->node('webu_general_section_01', [], [], [
                            $this->node('webu_general_divider_01'),
                        ]),
                        $this->node('webu_general_spacer_01', [], [], [
                            $this->node('hero_split_image'),
                        ]),
                    ],
                    'meta' => [
                        'source' => 'generated',
                    ],
                ],
            ],
            header: [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [],
                'bindings' => [],
                'meta' => ['source' => 'generated'],
            ],
            footer: [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [],
                'bindings' => [],
                'meta' => ['source' => 'generated'],
            ]
        );

        $invalidResult = $engine->validateOutputForSite($site, $invalidOutput);
        $this->assertFalse($invalidResult['ok']);

        $layoutNestingErrors = collect($invalidResult['errors'])
            ->where('code', 'invalid_layout_nesting_child')
            ->values();

        $this->assertGreaterThanOrEqual(2, $layoutNestingErrors->count());
        $this->assertContains('section', $layoutNestingErrors->pluck('parent_layout_role')->all());
        $this->assertContains('spacer', $layoutNestingErrors->pluck('parent_layout_role')->all());
    }

    public function test_architecture_doc_documents_schema_component_and_binding_validation_gates(): void
    {
        $path = base_path('docs/architecture/CMS_AI_OUTPUT_VALIDATION_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Output Validation Engine v1', $doc);
        $this->assertStringContainsString('P4-E3-01', $doc);
        $this->assertStringContainsString('CmsAiOutputValidationEngine', $doc);
        $this->assertStringContainsString('CmsAiSchemaValidationService', $doc);
        $this->assertStringContainsString('CmsCanonicalBindingResolver', $doc);
        $this->assertStringContainsString('component availability', $doc);
        $this->assertStringContainsString('binding validation', $doc);
        $this->assertStringContainsString('SectionLibrary', $doc);
        $this->assertStringContainsString('builder_nodes[]', $doc);
        $this->assertStringContainsString('header/footer', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiOutput(): array
    {
        return $this->outputEnvelope(
            pages: [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'path' => '/',
                    'status' => 'draft',
                    'builder_nodes' => [
                        $this->node('hero-split-image', [
                            'headline' => 'Welcome',
                            'subtitle' => '{{site.name}}',
                        ]),
                    ],
                    'meta' => [
                        'source' => 'generated',
                    ],
                ],
                [
                    'slug' => 'product',
                    'title' => 'Product',
                    'path' => '/product/{slug}',
                    'route_pattern' => '/product/{slug}',
                    'status' => 'draft',
                    'builder_nodes' => [
                        $this->node('ecommerce-product-detail', [
                            'title' => 'Product',
                            'product_slug' => '{{route.params.slug}}',
                        ], [
                            'product_slug' => '{{route.params.slug}}',
                            'title' => '{{ecommerce.product.title}}',
                        ]),
                    ],
                    'meta' => [
                        'source' => 'generated',
                    ],
                ],
            ],
            header: [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [
                    'headline' => 'Header',
                    'login_url' => '{{route.login}}',
                ],
                'bindings' => [
                    'menu_key' => 'menu.header',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ],
            footer: [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [
                    'copyright' => '2026',
                ],
                'bindings' => [
                    'menu_key' => 'menu.footer',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $bindings
     * @return array<string, mixed>
     */
    private function node(string $type, array $content = [], array $bindings = [], array $children = []): array
    {
        $node = [
            'type' => $type,
            'props' => [
                'content' => $content,
                'data' => [
                    'query' => [
                        'resource' => 'ecommerce.product',
                        'binding' => '{{route.params.slug}}',
                    ],
                ],
                'style' => [],
                'advanced' => [],
                'responsive' => [],
                'states' => [],
            ],
            'bindings' => $bindings,
            'meta' => [
                'schema_version' => 1,
                'source' => 'generated',
            ],
        ];

        if ($children !== []) {
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $footer
     * @return array<string, mixed>
     */
    private function outputEnvelope(array $pages, array $header, array $footer): array
    {
        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => [],
            ],
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
}
