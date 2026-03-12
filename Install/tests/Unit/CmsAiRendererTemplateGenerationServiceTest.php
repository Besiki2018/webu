<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiRendererTemplateGenerationServiceTest extends TestCase
{
    public function test_it_generates_renderer_templates_and_validation_reports_from_component_factory_result(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);
        $rendererTemplates = app(CmsAiRendererTemplateGenerationService::class);

        $factoryResult = $factory->generateFromRawSpec($this->wishlistFeatureSpec());
        $this->assertTrue($factoryResult['ok'], json_encode($factoryResult, JSON_PRETTY_PRINT));

        $result = $rendererTemplates->generateFromComponentFactoryResult($factoryResult);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame(2, data_get($result, 'summary.template_count'));
        $this->assertSame(2, data_get($result, 'summary.valid_template_count'));
        $this->assertSame(0, data_get($result, 'summary.invalid_template_count'));
        $this->assertSame(0, data_get($result, 'summary.validation_error_count'));
        $this->assertSame('wishlist', data_get($result, 'generated.manifest.feature_key'));
        $this->assertContains(
            'generated/wishlist/wishlist-toggle-button.html',
            data_get($result, 'generated.manifest.template_refs', [])
        );

        $first = data_get($result, 'generated.templates.0');
        $this->assertSame('wishlist-toggle-button', data_get($first, 'component_key'));
        $this->assertStringContainsString(
            'data-webu-ai-feature-component="feature-wishlist-wishlist-toggle-button"',
            (string) data_get($first, 'html')
        );
        $this->assertStringContainsString('data-bind-content="labels.active"', (string) data_get($first, 'html'));
        $this->assertStringContainsString('data-bind-binding="product_slug"', (string) data_get($first, 'html'));
        $this->assertStringContainsString('data-bind-query-resource="ecommerce.wishlist.state"', (string) data_get($first, 'html'));
        $this->assertSame(true, data_get($first, 'validation.ok'));
        $this->assertSame(true, data_get($first, 'validation.checks.root_marker_present'));
        $this->assertSame(2, data_get($first, 'validation.checks.content_marker_count'));
        $this->assertSame(1, data_get($first, 'validation.checks.binding_marker_count'));
        $this->assertSame(1, data_get($first, 'validation.checks.query_marker_count'));
    }

    public function test_it_fails_validation_for_tampered_renderer_template_html(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);
        $rendererTemplates = app(CmsAiRendererTemplateGenerationService::class);

        $factoryResult = $factory->generateFromRawSpec($this->wishlistFeatureSpec());
        $this->assertTrue($factoryResult['ok']);

        $rendererScaffold = data_get($factoryResult, 'generated.renderer_scaffolds.0');
        $this->assertIsArray($rendererScaffold);

        $built = $rendererTemplates->generateTemplateFromRendererScaffold($rendererScaffold, [
            'feature_key' => 'wishlist',
            'domain' => 'ecommerce',
        ]);
        $this->assertTrue($built['ok'], json_encode($built, JSON_PRETTY_PRINT));

        $html = (string) data_get($built, 'template.html');
        $tampered = str_replace(
            'data-bind-binding="product_slug"',
            'data-bind-binding="missing_binding"',
            str_replace(
                (string) data_get($rendererScaffold, 'template.markers.root'),
                'data-webu-ai-feature-component="tampered"',
                $html
            )
        );

        $validation = $rendererTemplates->validateGeneratedTemplate($rendererScaffold, $tampered);

        $this->assertFalse($validation['ok']);
        $errorCodes = collect($validation['errors'])->pluck('code')->all();
        $this->assertContains('missing_root_marker', $errorCodes);
        $this->assertContains('missing_binding_marker', $errorCodes);
    }

    public function test_architecture_doc_documents_p4_e4_03_renderer_template_generation_and_validation(): void
    {
        $path = base_path('docs/architecture/CMS_AI_RENDERER_TEMPLATE_GENERATION_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Renderer Template Generation v1', $doc);
        $this->assertStringContainsString('P4-E4-03', $doc);
        $this->assertStringContainsString('CmsAiRendererTemplateGenerationService', $doc);
        $this->assertStringContainsString('renderer_scaffold', $doc);
        $this->assertStringContainsString('data-webu-ai-template-generated="1"', $doc);
        $this->assertStringContainsString('P4-E4-04', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function wishlistFeatureSpec(): array
    {
        return [
            'feature' => 'wishlist',
            'title' => 'Wishlist & Favorites',
            'module' => 'shop',
            'widgets' => [
                [
                    'name' => 'wishlist-toggle-button',
                    'kind' => 'toggle',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.wishlist.state', 'binding' => 'route.params.slug'],
                    ],
                    'actions' => ['wishlist.toggle'],
                    'props' => [
                        'labels' => [
                            'active' => 'Saved',
                            'inactive' => 'Save',
                        ],
                    ],
                    'controls' => ['content', 'style'],
                    'variants' => ['primary', 'ghost'],
                ],
                [
                    'key' => 'wishlist-list',
                    'role' => 'list',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'data_queries' => [
                        ['resource' => 'ecommerce.wishlist.items', 'binding' => 'customer.id'],
                    ],
                    'events' => ['move_to_cart', 'remove_item'],
                    'props' => [
                        'empty_state' => [
                            'title' => 'No saved items',
                        ],
                    ],
                ],
            ],
            'ui_states' => ['ready', 'loading', 'empty', 'error'],
            'user_events' => ['toggle', 'move_to_cart'],
            'api_endpoints' => [
                'GET /public/sites/{site}/wishlist',
                'POST /public/sites/{site}/wishlist/items',
            ],
        ];
    }
}
