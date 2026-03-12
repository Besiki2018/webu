<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiGeneratedComponentSecurityValidationServiceTest extends TestCase
{
    public function test_it_accepts_safe_generated_component_bundle(): void
    {
        [$componentArtifact, $rendererTemplate] = $this->buildFirstGeneratedBundle();
        $validator = app(CmsAiGeneratedComponentSecurityValidationService::class);

        $result = $validator->validateComponentBundle($componentArtifact, $rendererTemplate);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame(true, data_get($result, 'checks.registry_type_safe'));
        $this->assertSame(true, data_get($result, 'checks.template_refs_safe'));
        $this->assertSame(true, data_get($result, 'checks.renderer_html_safe'));
        $this->assertSame(true, data_get($result, 'checks.bindings_safe'));
        $this->assertSame(true, data_get($result, 'checks.custom_css_safe'));
    }

    public function test_it_rejects_unsafe_renderer_html_template_refs_and_custom_css(): void
    {
        [$componentArtifact, $rendererTemplate] = $this->buildFirstGeneratedBundle();
        $validator = app(CmsAiGeneratedComponentSecurityValidationService::class);

        data_set($componentArtifact, 'registry_entry.renderer.html_template_ref', '../evil.php');
        data_set($componentArtifact, 'renderer_scaffold.template.ref', 'generated\\evil.html');
        data_set($componentArtifact, 'node_scaffold.bindings.product_slug', 'javascript:alert(1)');
        data_set($componentArtifact, 'node_scaffold.props.advanced.custom_css', '@import "https://evil.test/x.css"; .x{background:url(javascript:alert(1))}');
        data_set($rendererTemplate, 'template_ref', 'external/tampered.html');
        data_set($rendererTemplate, 'html', (string) data_get($rendererTemplate, 'html')."\n<script>alert(1)</script>\n<div onclick=\"evil()\"></div>");

        $result = $validator->validateComponentBundle($componentArtifact, $rendererTemplate);

        $this->assertFalse($result['ok']);
        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('unsafe_template_ref_path_traversal', $errorCodes);
        $this->assertContains('unsafe_template_ref_prefix', $errorCodes);
        $this->assertContains('unsafe_template_ref_suffix', $errorCodes);
        $this->assertContains('unsafe_binding_value', $errorCodes);
        $this->assertContains('unsafe_custom_css_import', $errorCodes);
        $this->assertContains('unsafe_custom_css_javascript_url', $errorCodes);
        $this->assertContains('unsafe_renderer_html_script_tag', $errorCodes);
        $this->assertContains('unsafe_renderer_html_inline_event_handler', $errorCodes);
    }

    public function test_architecture_doc_documents_security_constraints_and_pre_activation_gate(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generated Component Security Constraints v1', $doc);
        $this->assertStringContainsString('CmsAiGeneratedComponentSecurityValidationService', $doc);
        $this->assertStringContainsString('P4-E4-04', $doc);
        $this->assertStringContainsString('javascript:', $doc);
        $this->assertStringContainsString('<script', $doc);
        $this->assertStringContainsString('pre-activation gate', $doc);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function buildFirstGeneratedBundle(): array
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);
        $rendererTemplates = app(CmsAiRendererTemplateGenerationService::class);

        $factoryResult = $factory->generateFromRawSpec($this->wishlistFeatureSpec());
        $this->assertTrue($factoryResult['ok'], json_encode($factoryResult, JSON_PRETTY_PRINT));

        $rendererResult = $rendererTemplates->generateFromComponentFactoryResult($factoryResult);
        $this->assertTrue($rendererResult['ok'], json_encode($rendererResult, JSON_PRETTY_PRINT));

        $componentArtifact = data_get($factoryResult, 'generated.components.0');
        $rendererTemplate = data_get($rendererResult, 'generated.templates.0');

        $this->assertIsArray($componentArtifact);
        $this->assertIsArray($rendererTemplate);

        return [$componentArtifact, $rendererTemplate];
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

