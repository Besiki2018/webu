<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiFeatureSpecParserTest extends TestCase
{
    public function test_it_parses_alias_heavy_wishlist_feature_spec_into_canonical_v1_shape(): void
    {
        $parser = app(CmsAiFeatureSpecParser::class);

        $input = [
            'feature' => 'wishlist',
            'title' => 'Wishlist & Favorites',
            'module' => 'shop',
            'description' => 'Allow customers to save products and revisit later.',
            'models' => [
                [
                    'name' => 'wishlist_item',
                    'fields' => [
                        'id',
                        'customer_id',
                        ['name' => 'product_slug', 'type' => 'string', 'required' => true],
                        ['key' => 'created_at', 'type' => 'datetime'],
                    ],
                ],
            ],
            'widgets' => [
                [
                    'name' => 'wishlist-toggle-button',
                    'kind' => 'toggle',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                        'broken_expr' => '{{ route.params.slug + 1 }}',
                    ],
                    'queries' => [
                        'ecommerce.wishlist.state',
                    ],
                    'actions' => [
                        'wishlist.toggle',
                    ],
                    'props' => [
                        'labels' => [
                            'active' => 'Saved',
                            'inactive' => 'Save',
                        ],
                    ],
                    'controls' => ['content', 'style'],
                ],
                [
                    'key' => 'wishlist-list',
                    'role' => 'list',
                    'data_queries' => [
                        ['resource' => 'ecommerce.wishlist.items', 'binding' => 'customer.id'],
                    ],
                    'events' => ['move_to_cart', 'remove_item'],
                    'summary' => 'List of saved wishlist items',
                ],
            ],
            'ui_states' => ['loading', 'empty', 'error', 'ready', 'loading'],
            'user_events' => ['toggle', 'move_to_cart'],
            'api_endpoints' => [
                'GET /public/sites/{site}/wishlist',
                'POST /public/sites/{site}/wishlist/items',
            ],
            'acceptance_criteria' => [
                'Toggle works on product detail page',
                'Wishlist list renders saved items',
            ],
            'example_prompts' => [
                'Add wishlist feature for ecommerce storefront.',
            ],
            'meta' => [
                'source' => 'internal',
            ],
        ];

        $result = $parser->parse($input);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame('wishlist', data_get($result, 'spec.feature_key'));
        $this->assertSame('Wishlist & Favorites', data_get($result, 'spec.display_name'));
        $this->assertSame('ecommerce', data_get($result, 'spec.domain'));
        $this->assertSame('internal', data_get($result, 'spec.meta.source'));
        $this->assertSame(1, data_get($result, 'spec.meta.parser_version'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'summary.warning_count'));

        $aliasTrace = data_get($result, 'spec.meta.normalized_aliases', []);
        $this->assertContains('feature->feature_key', $aliasTrace);
        $this->assertContains('title->display_name', $aliasTrace);
        $this->assertContains('module->domain', $aliasTrace);
        $this->assertContains('widgets->components', $aliasTrace);

        $this->assertCount(1, data_get($result, 'spec.entities'));
        $this->assertSame('wishlist-item', data_get($result, 'spec.entities.0.key'));
        $this->assertSame('id', data_get($result, 'spec.entities.0.fields.0.type'));
        $this->assertSame('id', data_get($result, 'spec.entities.0.fields.1.type'));
        $this->assertSame('date', data_get($result, 'spec.entities.0.fields.3.type'));

        $this->assertCount(2, data_get($result, 'spec.components'));
        $this->assertSame('trigger', data_get($result, 'spec.components.0.role'));
        $this->assertSame('ecommerce', data_get($result, 'spec.components.0.category'));
        $this->assertSame('{{route.params.slug}}', data_get($result, 'spec.components.0.data_contract.bindings.product-slug'));
        $this->assertSame('{{ route.params.slug + 1 }}', data_get($result, 'spec.components.0.data_contract.bindings.broken-expr'));
        $this->assertSame('ecommerce.wishlist.state', data_get($result, 'spec.components.0.data_contract.queries.0.resource'));
        $this->assertSame('{{customer.id}}', data_get($result, 'spec.components.1.data_contract.queries.0.binding'));

        $stateKeys = collect(data_get($result, 'spec.states', []))->pluck('key')->all();
        $this->assertSame(['loading', 'empty', 'error', 'ready'], $stateKeys);
        $eventKeys = collect(data_get($result, 'spec.events', []))->pluck('key')->all();
        $this->assertSame(['toggle', 'move-to-cart'], $eventKeys);

        $this->assertSame('GET', data_get($result, 'spec.api_contract.endpoints.0.method'));
        $this->assertSame('/public/sites/{site}/wishlist', data_get($result, 'spec.api_contract.endpoints.0.path'));
        $this->assertSame('POST', data_get($result, 'spec.api_contract.endpoints.1.method'));

        $this->assertContains('ecommerce', data_get($result, 'spec.builder_contract.target_registry_categories', []));
        $this->assertContains('data', data_get($result, 'spec.builder_contract.control_groups', []));
        $this->assertContains('states', data_get($result, 'spec.builder_contract.control_groups', []));
        $this->assertContains('interactions', data_get($result, 'spec.builder_contract.control_groups', []));
        $this->assertCount(2, data_get($result, 'spec.builder_contract.renderer_hints', []));

        $warningCodes = collect($result['warnings'])->pluck('code')->all();
        $this->assertContains('binding_expression_invalid', $warningCodes);
    }

    public function test_it_returns_fail_safe_errors_for_missing_feature_identity_and_components(): void
    {
        $parser = app(CmsAiFeatureSpecParser::class);

        $result = $parser->parse([
            'description' => 'No feature key and no components',
            'ui_states' => ['loading'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['spec']);
        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertContains('missing_feature_key', $errorCodes);
        $this->assertContains('missing_display_name', $errorCodes);
        $this->assertContains('missing_components', $errorCodes);
    }

    public function test_it_returns_explicit_missing_components_for_source_strict_format_ui_only_payload(): void
    {
        $parser = app(CmsAiFeatureSpecParser::class);

        $result = $parser->parse([
            'feature_key' => 'wishlist',
            'title' => 'Wishlist',
            'category' => 'E-commerce',
            'description' => 'Strict source-spec shaped payload with ui primary/secondary block.',
            'context' => 'customer',
            'endpoints' => [
                [
                    'name' => 'GetWishlist',
                    'method' => 'GET',
                    'path' => '/wishlist',
                    'auth' => 'customer',
                    'body' => [],
                    'query' => [],
                    'response_shape' => [],
                ],
            ],
            'entities' => [
                [
                    'name' => 'wishlistItem',
                    'fields' => ['id', 'product_id'],
                ],
            ],
            'ui' => [
                'primary' => 'list',
                'secondary' => ['button', 'counter'],
            ],
            'permissions' => [
                'required' => 'customer',
            ],
            'events' => [
                [
                    'name' => 'wishlist.updated',
                    'payload' => ['count' => 'number'],
                ],
            ],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['spec']);

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertSame(['missing_components'], $errorCodes);
        $this->assertSame('$.components', data_get($result, 'errors.0.path'));
        $this->assertNotContains('missing_feature_key', $errorCodes);
        $this->assertNotContains('missing_display_name', $errorCodes);
    }

    public function test_schema_and_architecture_doc_lock_p4_e4_01_feature_spec_parser_contract(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-ai-feature-spec.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertSame(
            ['schema_version', 'feature_key', 'display_name', 'domain', 'components', 'states', 'api_contract', 'builder_contract', 'meta'],
            $schema['required'] ?? null
        );
        $this->assertSame(1, data_get($schema, 'properties.schema_version.const'));
        $this->assertSame(
            ['ecommerce', 'booking', 'blog', 'services', 'software', 'universal'],
            data_get($schema, 'properties.domain.enum')
        );
        $this->assertSame(
            ['key', 'label', 'role', 'category', 'data_contract', 'props_contract'],
            data_get($schema, '$defs.component.required')
        );

        $docPath = base_path('docs/architecture/CMS_AI_FEATURE_SPEC_PARSER_V1.md');
        $this->assertFileExists($docPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('# CMS AI Feature Spec Parser v1', $doc);
        $this->assertStringContainsString('P4-E4-01', $doc);
        $this->assertStringContainsString('CmsAiFeatureSpecParser', $doc);
        $this->assertStringContainsString('wishlist', $doc);
        $this->assertStringContainsString('reviews', $doc);
        $this->assertStringContainsString('subscriptions', $doc);
        $this->assertStringContainsString('loyalty', $doc);
        $this->assertStringContainsString('compare', $doc);
        $this->assertStringContainsString('P4-E4-02', $doc);
        $this->assertStringContainsString('cms-ai-feature-spec.v1.schema.json', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $decoded = json_decode((string) File::get($path), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
