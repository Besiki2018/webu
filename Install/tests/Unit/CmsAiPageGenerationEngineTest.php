<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiPageGenerationEngineTest extends TestCase
{
    public function test_it_generates_builder_native_pages_with_route_metadata_and_canonical_nodes(): void
    {
        $engine = app(CmsAiPageGenerationEngine::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'generate_site';
        $input['request']['prompt'] = 'Generate an ecommerce storefront with product pages, cart, and checkout.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
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
            ['key' => 'hero_split_image', 'category' => 'marketing', 'schema_json' => ['bindings' => ['headline' => 'content.headline']], 'enabled' => true],
            ['key' => 'ecommerce_product_grid', 'category' => 'ecommerce', 'schema_json' => ['bindings' => ['title' => 'content.title']], 'enabled' => true],
            ['key' => 'ecommerce_product_detail', 'category' => 'ecommerce', 'schema_json' => ['bindings' => ['product_slug' => 'route.params.slug']], 'enabled' => true],
            ['key' => 'ecommerce_cart', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'ecommerce_checkout', 'category' => 'ecommerce', 'schema_json' => [], 'enabled' => true],
            ['key' => 'faq', 'category' => 'content', 'schema_json' => [], 'enabled' => true],
        ];
        $input['platform_context']['module_registry']['modules'] = [
            ['key' => 'ecommerce', 'enabled' => true, 'available' => true],
        ];

        $result = $engine->generateFromAiInput($input);

        $this->assertTrue($result['ok']);
        $this->assertSame('template_default_pages', data_get($result, 'decisions.page_strategy'));
        $this->assertTrue((bool) data_get($result, 'decisions.ecommerce_signal'));
        $this->assertTrue((bool) data_get($result, 'validation.output_pages.valid'));
        $this->assertTrue((bool) data_get($result, 'validation.canonical_nodes.valid'));

        $slugs = collect($result['pages_output'])->pluck('slug')->all();
        $this->assertContains('home', $slugs);
        $this->assertContains('shop', $slugs);
        $this->assertContains('product', $slugs);
        $this->assertContains('cart', $slugs);
        $this->assertContains('checkout', $slugs);

        $home = collect($result['pages_output'])->firstWhere('slug', 'home');
        $this->assertSame('/', data_get($home, 'path'));
        $this->assertSame('draft', data_get($home, 'status'));
        $this->assertSame('template_derived', data_get($home, 'meta.source'));
        $this->assertNotEmpty(data_get($home, 'builder_nodes'));
        $this->assertSame(1, data_get($home, 'builder_nodes.0.meta.schema_version'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.content'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.data'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.style'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.advanced'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.responsive'));
        $this->assertIsArray(data_get($home, 'builder_nodes.0.props.states'));

        $product = collect($result['pages_output'])->firstWhere('slug', 'product');
        $this->assertSame('/product/{slug}', data_get($product, 'path'));
        $this->assertSame('/product/{slug}', data_get($product, 'route_pattern'));
        $this->assertTrue((bool) data_get($product, 'meta.required_page'));
        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.bindings.product_slug'));
    }

    public function test_it_respects_target_page_slugs_in_edit_page_mode_and_marks_keep_existing(): void
    {
        $engine = app(CmsAiPageGenerationEngine::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'edit_page';
        $input['request']['target'] = [
            'page_slugs' => ['contact'],
            'route_scope' => 'pages',
        ];
        $input['request']['constraints'] = [
            'preserve_existing_pages' => true,
        ];
        $input['platform_context']['pages_snapshot'] = [
            [
                'id' => 21,
                'title' => 'Contact',
                'slug' => 'contact',
                'status' => 'published',
                'seo_title' => 'Contact',
                'seo_description' => null,
            ],
        ];
        $input['platform_context']['section_library'] = [
            ['key' => 'contact_split_form', 'category' => 'contact', 'schema_json' => [], 'enabled' => true],
        ];

        $result = $engine->generateFromAiInput($input);

        $this->assertTrue($result['ok']);
        $this->assertSame('target_page_slugs', data_get($result, 'decisions.page_strategy'));
        $this->assertSame(['contact'], data_get($result, 'decisions.resolved_page_slugs'));
        $this->assertCount(1, $result['pages_output']);
        $this->assertSame('contact', data_get($result, 'pages_output.0.slug'));
        $this->assertSame('/contact', data_get($result, 'pages_output.0.path'));
        $this->assertSame('keep_existing', data_get($result, 'pages_output.0.meta.source'));
        $this->assertTrue((bool) data_get($result, 'validation.output_pages.valid'));
    }

    public function test_it_reports_invalid_ai_input_payloads_using_phase_e1_validator_contract(): void
    {
        $engine = app(CmsAiPageGenerationEngine::class);

        $result = $engine->generateFromAiInput([
            'schema_version' => 1,
            'request' => ['mode' => 'generate_pages'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_ai_input', $result['code']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            data_get($result, 'validation.input.schema')
        );
    }

    public function test_architecture_doc_documents_builder_native_page_output_and_current_revision_model_mapping(): void
    {
        $path = base_path('docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Page Generation Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-02', $doc);
        $this->assertStringContainsString('builder-native page output fragments', $doc);
        $this->assertStringContainsString('builder_nodes[]', $doc);
        $this->assertStringContainsString('route/page metadata', $doc);
        $this->assertStringContainsString('page_revisions.content_json', $doc);
        $this->assertStringContainsString('CmsAiSchemaValidationService', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1.schema.json', $doc);
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
                'request_id' => 'req-page-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }
}
