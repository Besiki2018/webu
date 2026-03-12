<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiComponentFeatureSpecParserTest extends TestCase
{
    public function test_it_parses_ui_intent_feature_spec_into_canonical_auto_generator_contract(): void
    {
        $parser = app(CmsAiComponentFeatureSpecParser::class);

        $result = $parser->parse([
            'feature_key' => 'wishlist',
            'title' => 'Wishlist',
            'category' => 'E-commerce',
            'description' => 'Save products to wishlist.',
            'context' => 'customer',
            'endpoints' => [
                [
                    'name' => 'GetWishlist',
                    'method' => 'GET',
                    'path' => '/wishlist',
                    'auth' => 'customer',
                    'query' => [],
                    'body' => null,
                    'response_shape' => [
                        'items' => [
                            [
                                'product_id' => 'number',
                                'slug' => 'string',
                                'name' => 'string',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'AddToWishlist',
                    'method' => 'POST',
                    'path' => '/wishlist/items',
                    'auth' => 'customer',
                    'body' => ['product_id' => 'number'],
                    'response_shape' => ['ok' => 'boolean'],
                ],
                [
                    'name' => 'RemoveWishlistItem',
                    'method' => 'DELETE',
                    'path' => '/wishlist/items/{id}',
                    'auth' => 'customer',
                    'response_shape' => ['ok' => 'boolean'],
                ],
            ],
            'events' => [
                ['name' => 'wishlist.updated', 'payload' => ['count' => 'number']],
            ],
            'ui_intent' => [
                'primary_component' => 'wishlistList',
                'secondary_components' => ['wishlistButton', 'wishlistCounter'],
            ],
        ]);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame([], $result['errors']);
        $this->assertSame('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json', data_get($result, 'meta.schema'));

        $spec = $result['feature_spec'];
        $this->assertSame(1, data_get($spec, 'schema_version'));
        $this->assertSame('wishlist', data_get($spec, 'feature_key'));
        $this->assertSame('E-commerce', data_get($spec, 'category'));
        $this->assertSame('customer', data_get($spec, 'context'));
        $this->assertSame('customer', data_get($spec, 'permissions.required'));

        $this->assertSame('list', data_get($spec, 'ui_intent.primary_component'));
        $this->assertSame(['button', 'counter'], data_get($spec, 'ui_intent.secondary_components'));
        $this->assertSame('ecom.wishlist.list', data_get($spec, 'ui_intent.component_set.0.type'));
        $this->assertSame('ecom.wishlist.button', data_get($spec, 'ui_intent.component_set.1.type'));
        $this->assertSame('ecom.wishlist.counter', data_get($spec, 'ui_intent.component_set.2.type'));

        $this->assertSame('remove_wishlist_item', data_get($spec, 'endpoints.2.operation_key'));
        $this->assertSame(['id'], data_get($spec, 'endpoints.2.route_params'));
        $this->assertSame('E-commerce', data_get($spec, 'generator_hints.builder_sidebar_category'));
        $this->assertSame(
            [
                'ecom.wishlist.list',
                'ecom.wishlist.button',
                'ecom.wishlist.counter',
            ],
            data_get($spec, 'generator_hints.component_types')
        );
        $this->assertSame('ui_intent', data_get($spec, 'meta.source_variant'));
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json',
            data_get($spec, 'meta.contracts.feature_spec_schema')
        );
    }

    public function test_it_parses_legacy_ui_alias_format_and_derives_endpoint_defaults_for_generator(): void
    {
        $parser = app(CmsAiComponentFeatureSpecParser::class);

        $result = $parser->parse([
            'feature_key' => 'Compare',
            'category' => 'E-commerce',
            'context' => 'public',
            'entities' => [
                [
                    'name' => 'compareItem',
                    'fields' => [
                        'product_id' => 'number',
                    ],
                ],
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => 'compare',
                ],
            ],
            'ui' => [
                'primary' => 'table',
                'secondary' => ['button'],
            ],
        ]);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));

        $spec = $result['feature_spec'];
        $this->assertSame('compare', data_get($spec, 'feature_key'));
        $this->assertSame('Compare', data_get($spec, 'title'));
        $this->assertSame('ui', data_get($spec, 'meta.source_variant'));
        $this->assertContains('feature_key.normalized', data_get($spec, 'meta.aliases_applied', []));
        $this->assertContains('ui.primary->ui_intent.primary_component', data_get($spec, 'meta.aliases_applied', []));
        $this->assertContains('ui.secondary->ui_intent.secondary_components', data_get($spec, 'meta.aliases_applied', []));
        $this->assertContains('endpoints.name.derived', data_get($spec, 'meta.aliases_applied', []));
        $this->assertContains('endpoints.path.leading_slash', data_get($spec, 'meta.aliases_applied', []));
        $this->assertContains('endpoint.auth.defaulted', data_get($spec, 'meta.aliases_applied', []));

        $this->assertSame('/compare', data_get($spec, 'endpoints.0.path'));
        $this->assertSame('GetCompare', data_get($spec, 'endpoints.0.name'));
        $this->assertSame('get_compare', data_get($spec, 'endpoints.0.operation_key'));
        $this->assertSame('public', data_get($spec, 'endpoints.0.auth'));
        $this->assertSame([], data_get($spec, 'endpoints.0.route_params'));
        $this->assertSame([], data_get($spec, 'endpoints.0.query'));
        $this->assertNull(data_get($spec, 'endpoints.0.body'));
        $this->assertNull(data_get($spec, 'endpoints.0.response_shape'));

        $this->assertSame('table', data_get($spec, 'ui_intent.primary_component'));
        $this->assertSame(['button'], data_get($spec, 'ui_intent.secondary_components'));
        $this->assertSame('ecom.compare.table', data_get($spec, 'ui_intent.component_set.0.type'));
        $this->assertSame('ecom.compare.button', data_get($spec, 'ui_intent.component_set.1.type'));

        $this->assertSame('compare_item', data_get($spec, 'entities.0.key'));
        $this->assertSame('number', data_get($spec, 'entities.0.fields.product_id'));
    }

    public function test_it_reports_invalid_feature_specs_with_structured_errors(): void
    {
        $parser = app(CmsAiComponentFeatureSpecParser::class);

        $result = $parser->parse([
            'title' => 'Broken Spec',
            'endpoints' => [
                [
                    'method' => 'TRACE',
                    'path' => '',
                ],
            ],
            'ui' => [
                'secondary' => ['button'],
            ],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_feature_spec', $result['code']);

        $paths = collect($result['errors'])->pluck('path')->all();
        $this->assertContains('$.feature_key', $paths);
        $this->assertContains('$.ui_intent.primary_component', $paths);
        $this->assertContains('$.endpoints[0].method', $paths);
    }

    public function test_it_reports_explicit_errors_for_partial_strict_format_field_types(): void
    {
        $parser = app(CmsAiComponentFeatureSpecParser::class);

        $result = $parser->parse([
            'feature_key' => 'wishlist',
            'title' => 'Wishlist',
            'category' => 'E-commerce',
            'description' => 'Wishlist feature.',
            'context' => 'customer',
            'permissions' => 'customer',
            'entities' => [
                'wishlistItem' => [
                    'fields' => [
                        'id' => 'number',
                    ],
                ],
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/wishlist',
                    'query' => 'invalid query shape',
                    'body' => 123,
                    'response_shape' => true,
                ],
            ],
            'events' => 'wishlist.updated',
            'ui' => [
                'secondary' => ['button'],
            ],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_feature_spec', $result['code']);
        $this->assertNull($result['feature_spec']);
        $this->assertSame('CmsAiComponentFeatureSpecParser', data_get($result, 'meta.parser'));
        $this->assertSame('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json', data_get($result, 'meta.schema'));

        $errors = collect($result['errors']);
        $paths = $errors->pluck('path')->all();
        $codes = $errors->pluck('code')->all();

        $this->assertContains('$.permissions', $paths);
        $this->assertContains('$.entities', $paths);
        $this->assertContains('$.endpoints[0].query', $paths);
        $this->assertContains('$.endpoints[0].body', $paths);
        $this->assertContains('$.endpoints[0].response_shape', $paths);
        $this->assertContains('$.events', $paths);
        $this->assertContains('$.ui_intent.primary_component', $paths);

        $this->assertContains('invalid_type', $codes);
        $this->assertContains('missing_required_key', $codes);
    }

    public function test_it_reports_invalid_json_when_parsing_json_strings(): void
    {
        $parser = app(CmsAiComponentFeatureSpecParser::class);

        $result = $parser->parseJsonString('{invalid json}');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_feature_spec', $result['code']);
        $this->assertSame('invalid_json', data_get($result, 'errors.0.code'));
    }

    public function test_architecture_doc_documents_canonical_feature_spec_parser_and_alias_support(): void
    {
        $path = base_path('docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Component Feature Spec Parser v1', $doc);
        $this->assertStringContainsString('P4-E4-01', $doc);
        $this->assertStringContainsString('CmsAiComponentFeatureSpecParser', $doc);
        $this->assertStringContainsString('cms-ai-component-feature-spec.v1.schema.json', $doc);
        $this->assertStringContainsString('ui_intent', $doc);
        $this->assertStringContainsString('ui.primary', $doc);
        $this->assertStringContainsString('ecom.<feature_key>.<component>', $doc);
        $this->assertStringContainsString('wishlist', strtolower($doc));
        $this->assertStringContainsString('reviews', strtolower($doc));
        $this->assertStringContainsString('subscriptions', strtolower($doc));
        $this->assertStringContainsString('P4-E4-02', $doc);
    }
}
