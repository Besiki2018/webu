<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiComponentFactoryGeneratorTest extends TestCase
{
    public function test_it_generates_registry_entries_node_scaffolds_and_renderer_scaffolds_from_raw_feature_spec(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);

        $result = $factory->generateFromRawSpec([
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
        ]);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame('wishlist', data_get($result, 'summary.feature_key'));
        $this->assertSame('ecommerce', data_get($result, 'summary.domain'));
        $this->assertSame(2, data_get($result, 'summary.component_count'));
        $this->assertSame(2, data_get($result, 'summary.registry_entry_count'));
        $this->assertSame(2, data_get($result, 'summary.node_scaffold_count'));
        $this->assertSame(2, data_get($result, 'summary.renderer_scaffold_count'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'summary.dynamic_binding_components'));

        $generated = data_get($result, 'generated');
        $this->assertIsArray($generated);
        $this->assertSame(1, data_get($generated, 'schema_version'));
        $this->assertIsArray(data_get($generated, 'feature_spec'));

        $first = data_get($generated, 'components.0');
        $this->assertSame('wishlist-toggle-button', data_get($first, 'component_key'));
        $this->assertStringStartsWith('feature-wishlist-', (string) data_get($first, 'registry_type'));

        $registryEntry = data_get($first, 'registry_entry');
        $this->assertIsArray($registryEntry);
        $this->assertSame((string) data_get($first, 'registry_type'), data_get($registryEntry, 'type'));
        $this->assertSame('ecommerce', data_get($registryEntry, 'category'));
        $this->assertSame('adapter', data_get($registryEntry, 'renderer.kind'));
        $this->assertStringContainsString('generated/wishlist/wishlist-toggle-button.html', (string) data_get($registryEntry, 'renderer.html_template_ref'));
        $this->assertIsArray(data_get($registryEntry, 'props_schema.properties.content.properties.labels.properties.active'));
        $this->assertIsArray(data_get($registryEntry, 'default_props.content.labels'));
        $this->assertSame('Saved', data_get($registryEntry, 'default_props.content.labels.active'));
        $this->assertSame('{{route.params.slug}}', data_get($registryEntry, 'default_props.data.query.binding'));
        $this->assertSame('ecommerce.wishlist.state', data_get($registryEntry, 'default_props.data.query.resource'));
        $this->assertSame('primary', data_get($registryEntry, 'default_props.style.variant'));
        $this->assertSame('wishlist', data_get($registryEntry, 'default_props.advanced.attributes.data-webu-ai-feature'));
        $this->assertSame(true, data_get($registryEntry, 'meta.supports_dynamic_bindings'));
        $this->assertSame(true, data_get($registryEntry, 'meta.supports_responsive'));
        $this->assertSame(true, data_get($registryEntry, 'meta.supports_states'));

        $controlGroupIds = collect(data_get($registryEntry, 'controls_config.groups', []))->pluck('id')->all();
        $this->assertContains('content', $controlGroupIds);
        $this->assertContains('data', $controlGroupIds);
        $this->assertContains('style', $controlGroupIds);
        $this->assertContains('advanced', $controlGroupIds);
        $this->assertContains('states', $controlGroupIds);
        $this->assertContains('interactions', $controlGroupIds);

        $nodeScaffold = data_get($first, 'node_scaffold');
        $this->assertIsArray($nodeScaffold);
        $this->assertSame((string) data_get($first, 'registry_type'), data_get($nodeScaffold, 'type'));
        $this->assertSame('{{route.params.slug}}', data_get($nodeScaffold, 'bindings.product_slug'));
        $this->assertSame('Saved', data_get($nodeScaffold, 'props.content.labels.active'));
        $this->assertIsArray(data_get($nodeScaffold, 'props.responsive.desktop'));
        $this->assertIsArray(data_get($nodeScaffold, 'props.states.loading'));
        $this->assertSame(1, data_get($nodeScaffold, 'meta.schema_version'));

        $rendererScaffold = data_get($first, 'renderer_scaffold');
        $this->assertSame((string) data_get($first, 'registry_type'), data_get($rendererScaffold, 'registry_type'));
        $this->assertStringContainsString('data-webu-ai-feature-component', (string) data_get($rendererScaffold, 'template.markers.root'));
        $this->assertContains('product_slug', data_get($rendererScaffold, 'adapter_contract.expects_bindings', []));
        $this->assertContains('ecommerce.wishlist.state', data_get($rendererScaffold, 'adapter_contract.queries', []));

        $registryEntries = data_get($generated, 'registry_entries', []);
        $nodeScaffolds = data_get($generated, 'node_scaffolds', []);
        $this->assertCount(2, $registryEntries);
        $this->assertCount(2, $nodeScaffolds);
        $this->assertSame(data_get($registryEntries, '0.type'), data_get($nodeScaffolds, '0.type'));
    }

    public function test_it_rejects_invalid_canonical_feature_spec_before_generation(): void
    {
        $factory = app(CmsAiComponentFactoryGenerator::class);

        $result = $factory->generateFromCanonicalSpec([
            'schema_version' => 999,
            'feature_key' => 'wishlist',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_feature_spec', $result['code']);
        $this->assertNull($result['generated']);

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('unsupported_schema_version', $errorCodes);
        $this->assertContains('missing_required_key', $errorCodes);
        $this->assertContains('invalid_components', $errorCodes);
    }

    public function test_architecture_doc_documents_component_factory_scaffolds_and_handoffs(): void
    {
        $path = base_path('docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Component Factory Generator v1', $doc);
        $this->assertStringContainsString('P4-E4-02', $doc);
        $this->assertStringContainsString('CmsAiComponentFactoryGenerator', $doc);
        $this->assertStringContainsString('registry_entry', $doc);
        $this->assertStringContainsString('node_scaffold', $doc);
        $this->assertStringContainsString('renderer_scaffold', $doc);
        $this->assertStringContainsString('cms-canonical-component-registry-entry.v1', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1', $doc);
        $this->assertStringContainsString('P4-E4-03', $doc);
        $this->assertStringContainsString('P4-E4-04', $doc);
    }
}
