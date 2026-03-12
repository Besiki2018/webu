<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiComponentRegistryIntegrationWorkflowServiceTest extends TestCase
{
    public function test_it_prepares_ready_for_activation_bundles_only_after_preflight_validators_pass(): void
    {
        $workflow = app(CmsAiComponentRegistryIntegrationWorkflowService::class);

        $result = $workflow->prepareActivationFromRawFeatureSpec($this->wishlistFeatureSpec());

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame(2, data_get($result, 'summary.component_count'));
        $this->assertSame(2, data_get($result, 'summary.ready_component_count'));
        $this->assertSame(0, data_get($result, 'summary.blocked_component_count'));
        $this->assertSame('preflight_only', data_get($result, 'activation_plan.meta.activation_mode'));
        $this->assertSame('wishlist', data_get($result, 'activation_plan.feature_key'));

        $first = data_get($result, 'activation_plan.components.0');
        $this->assertSame('ready_for_activation', data_get($first, 'status'));
        $this->assertSame(true, data_get($first, 'checks.registry_entry_present'));
        $this->assertSame(true, data_get($first, 'checks.node_scaffold_present'));
        $this->assertSame(true, data_get($first, 'checks.renderer_template_present'));
        $this->assertSame(true, data_get($first, 'checks.renderer_template_validation_ok'));
        $this->assertStringContainsString(
            'data-webu-ai-template-generated="1"',
            (string) data_get($first, 'renderer_template.html')
        );
        $this->assertSame(
            data_get($first, 'registry_entry.renderer.html_template_ref'),
            data_get($first, 'renderer_template.template_ref')
        );
    }

    public function test_it_blocks_activation_when_renderer_template_validation_fails(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);
        $rendererTemplates = app(CmsAiRendererTemplateGenerationService::class);
        $workflow = app(CmsAiComponentRegistryIntegrationWorkflowService::class);

        $factoryResult = $factory->generateFromRawSpec($this->wishlistFeatureSpec());
        $this->assertTrue($factoryResult['ok']);

        $rendererResult = $rendererTemplates->generateFromComponentFactoryResult($factoryResult);
        $this->assertTrue($rendererResult['ok']);

        $rendererScaffold = data_get($factoryResult, 'generated.renderer_scaffolds.0');
        $originalTemplate = data_get($rendererResult, 'generated.templates.0');
        $this->assertIsArray($rendererScaffold);
        $this->assertIsArray($originalTemplate);

        $tamperedHtml = str_replace(
            (string) data_get($rendererScaffold, 'template.markers.root'),
            'data-webu-ai-feature-component="tampered"',
            (string) data_get($originalTemplate, 'html')
        );
        $tamperedValidation = $rendererTemplates->validateGeneratedTemplate($rendererScaffold, $tamperedHtml);

        data_set($rendererResult, 'generated.templates.0.html', $tamperedHtml);
        data_set($rendererResult, 'generated.templates.0.validation', $tamperedValidation);
        data_set($rendererResult, 'ok', true);

        $result = $workflow->prepareActivationFromGeneratedArtifacts($factoryResult, $rendererResult);

        $this->assertFalse($result['ok']);
        $this->assertSame('generated_component_validation_failed', $result['code']);
        $this->assertSame(2, data_get($result, 'summary.component_count'));
        $this->assertSame(1, data_get($result, 'summary.ready_component_count'));
        $this->assertSame(1, data_get($result, 'summary.blocked_component_count'));

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('renderer_template_validation_failed', $errorCodes);

        $blocked = collect(data_get($result, 'activation_plan.components', []))
            ->firstWhere('status', 'blocked');
        $this->assertIsArray($blocked);
        $this->assertSame(false, data_get($blocked, 'checks.renderer_template_validation_ok'));
        $this->assertContains('renderer_template_validation_failed', collect(data_get($blocked, 'errors', []))->pluck('code')->all());
    }

    public function test_it_blocks_activation_when_security_validation_fails_even_if_renderer_markers_validate(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);
        $rendererTemplates = app(CmsAiRendererTemplateGenerationService::class);
        $workflow = app(CmsAiComponentRegistryIntegrationWorkflowService::class);

        $factoryResult = $factory->generateFromRawSpec($this->wishlistFeatureSpec());
        $this->assertTrue($factoryResult['ok']);

        $rendererResult = $rendererTemplates->generateFromComponentFactoryResult($factoryResult);
        $this->assertTrue($rendererResult['ok']);

        $templateHtml = (string) data_get($rendererResult, 'generated.templates.0.html');
        $tamperedHtml = $templateHtml."\n<script>window.evil=true</script>";
        $rendererScaffold = data_get($factoryResult, 'generated.renderer_scaffolds.0');
        $this->assertIsArray($rendererScaffold);

        $tamperedValidation = $rendererTemplates->validateGeneratedTemplate($rendererScaffold, $tamperedHtml);
        $this->assertTrue($tamperedValidation['ok'], 'Renderer marker validation should still pass so security gate is independently tested.');

        data_set($rendererResult, 'generated.templates.0.html', $tamperedHtml);
        data_set($rendererResult, 'generated.templates.0.validation', $tamperedValidation);

        $result = $workflow->prepareActivationFromGeneratedArtifacts($factoryResult, $rendererResult);

        $this->assertFalse($result['ok']);
        $this->assertSame('generated_component_validation_failed', $result['code']);
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'summary.blocked_component_count'));

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('unsafe_renderer_html_script_tag', $errorCodes);

        $blocked = collect(data_get($result, 'activation_plan.components', []))
            ->firstWhere('status', 'blocked');
        $this->assertIsArray($blocked);
        $this->assertSame(false, data_get($blocked, 'checks.security_validation_ok'));
        $this->assertSame(false, data_get($blocked, 'security_validation.ok'));
        $this->assertContains('unsafe_renderer_html_script_tag', collect(data_get($blocked, 'security_validation.errors', []))->pluck('code')->all());
    }

    public function test_architecture_doc_documents_pre_activation_validator_gate_for_p4_e4_04(): void
    {
        $path = base_path('docs/architecture/CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Component Registry Integration Workflow v1', $doc);
        $this->assertStringContainsString('P4-E4-04', $doc);
        $this->assertStringContainsString('CmsAiComponentRegistryIntegrationWorkflowService', $doc);
        $this->assertStringContainsString('ready_for_activation', $doc);
        $this->assertStringContainsString('blocked', $doc);
        $this->assertStringContainsString('preflight_only', $doc);
        $this->assertStringContainsString('CmsAiGeneratedComponentSecurityValidationService', $doc);
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
