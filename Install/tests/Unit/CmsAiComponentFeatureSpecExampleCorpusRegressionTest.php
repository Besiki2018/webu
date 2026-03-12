<?php

namespace Tests\Unit;

use App\Services\CmsAiComponentFeatureSpecParser;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CmsAiComponentFeatureSpecExampleCorpusRegressionTest extends TestCase
{
    #[DataProvider('exampleFixtureProvider')]
    public function test_source_example_fixtures_are_parser_compatible_and_machine_checkable(
        string $fixtureFile,
        array $expected
    ): void {
        $parser = app(CmsAiComponentFeatureSpecParser::class);
        $path = base_path('tests/Fixtures/CmsAiComponentFeatureSpecExamples/'.$fixtureFile);

        $this->assertFileExists($path);

        $json = File::get($path);
        $result = $parser->parseJsonString($json);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame([], $result['errors']);
        $this->assertSame('CmsAiComponentFeatureSpecParser', data_get($result, 'meta.parser'));
        $this->assertSame('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json', data_get($result, 'meta.schema'));

        $spec = $result['feature_spec'];
        $this->assertIsArray($spec);
        $this->assertSame(1, data_get($spec, 'schema_version'));
        $this->assertSame($expected['feature_key'], data_get($spec, 'feature_key'));
        $this->assertSame($expected['title'], data_get($spec, 'title'));
        $this->assertSame($expected['category'], data_get($spec, 'category'));
        $this->assertSame($expected['context'], data_get($spec, 'context'));
        $this->assertSame($expected['context'], data_get($spec, 'permissions.required'));
        $this->assertSame($expected['primary_component'], data_get($spec, 'ui_intent.primary_component'));
        $this->assertSame($expected['secondary_components'], data_get($spec, 'ui_intent.secondary_components'));
        $this->assertSame($expected['component_types'], data_get($spec, 'generator_hints.component_types'));
        $this->assertSame($expected['builder_sidebar_category'], data_get($spec, 'generator_hints.builder_sidebar_category'));
        $this->assertSame($expected['source_variant'], data_get($spec, 'meta.source_variant'));
        $this->assertSame(count($expected['endpoint_methods']), count(data_get($spec, 'endpoints', [])));

        foreach ($expected['endpoint_methods'] as $index => $method) {
            $this->assertSame($method, data_get($spec, 'endpoints.'.$index.'.method'));
            $this->assertSame($expected['endpoint_paths'][$index], data_get($spec, 'endpoints.'.$index.'.path'));
            $this->assertSame($expected['endpoint_auth'][$index], data_get($spec, 'endpoints.'.$index.'.auth'));
        }

        if (array_key_exists('endpoint_name_0', $expected)) {
            $this->assertSame($expected['endpoint_name_0'], data_get($spec, 'endpoints.0.name'));
        }
        if (array_key_exists('endpoint_operation_key_0', $expected)) {
            $this->assertSame($expected['endpoint_operation_key_0'], data_get($spec, 'endpoints.0.operation_key'));
        }
        if (array_key_exists('route_params_0', $expected)) {
            $this->assertSame($expected['route_params_0'], data_get($spec, 'endpoints.0.route_params'));
        }
    }

    public function test_corpus_fixtures_cover_all_roadmap_examples_wishlist_reviews_subscriptions_loyalty_and_compare(): void
    {
        $dir = base_path('tests/Fixtures/CmsAiComponentFeatureSpecExamples');
        $this->assertDirectoryExists($dir);

        $files = collect(File::files($dir))
            ->map(fn ($file): string => $file->getFilename())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['compare.json', 'loyalty.json', 'reviews.json', 'subscriptions.json', 'wishlist.json'],
            $files
        );
    }

    /**
     * @return array<string, array{0:string,1:array<string,mixed>}>
     */
    public static function exampleFixtureProvider(): array
    {
        return [
            'wishlist' => [
                'wishlist.json',
                [
                    'feature_key' => 'wishlist',
                    'title' => 'Wishlist',
                    'category' => 'E-commerce',
                    'context' => 'customer',
                    'primary_component' => 'list',
                    'secondary_components' => ['button', 'counter'],
                    'component_types' => ['ecom.wishlist.list', 'ecom.wishlist.button', 'ecom.wishlist.counter'],
                    'builder_sidebar_category' => 'E-commerce',
                    'source_variant' => 'ui',
                    'endpoint_methods' => ['GET', 'POST', 'DELETE'],
                    'endpoint_paths' => ['/wishlist', '/wishlist/items', '/wishlist/items/{id}'],
                    'endpoint_auth' => ['customer', 'customer', 'customer'],
                    'route_params_0' => [],
                ],
            ],
            'reviews' => [
                'reviews.json',
                [
                    'feature_key' => 'reviews',
                    'title' => 'Product Reviews',
                    'category' => 'E-commerce',
                    'context' => 'public',
                    'primary_component' => 'list',
                    'secondary_components' => ['form'],
                    'component_types' => ['ecom.reviews.list', 'ecom.reviews.form'],
                    'builder_sidebar_category' => 'E-commerce',
                    'source_variant' => 'ui',
                    'endpoint_methods' => ['GET', 'POST'],
                    'endpoint_paths' => ['/products/{id}/reviews', '/reviews'],
                    'endpoint_auth' => ['public', 'public'],
                    'route_params_0' => ['id'],
                ],
            ],
            'subscriptions' => [
                'subscriptions.json',
                [
                    'feature_key' => 'subscriptions',
                    'title' => 'Subscriptions',
                    'category' => 'E-commerce',
                    'context' => 'customer',
                    'primary_component' => 'list',
                    'secondary_components' => [],
                    'component_types' => ['ecom.subscriptions.list'],
                    'builder_sidebar_category' => 'E-commerce',
                    'source_variant' => 'ui',
                    'endpoint_methods' => ['GET'],
                    'endpoint_paths' => ['/subscriptions'],
                    'endpoint_auth' => ['customer'],
                    'route_params_0' => [],
                ],
            ],
            'loyalty' => [
                'loyalty.json',
                [
                    'feature_key' => 'loyalty',
                    'title' => 'Loyalty',
                    'category' => 'Account',
                    'context' => 'customer',
                    'primary_component' => 'balance',
                    'secondary_components' => ['history'],
                    'component_types' => ['ecom.loyalty.balance', 'ecom.loyalty.history'],
                    'builder_sidebar_category' => 'Account',
                    'source_variant' => 'ui',
                    'endpoint_methods' => ['GET'],
                    'endpoint_paths' => ['/loyalty'],
                    'endpoint_auth' => ['customer'],
                    'route_params_0' => [],
                ],
            ],
            'compare' => [
                'compare.json',
                [
                    'feature_key' => 'compare',
                    'title' => 'Compare',
                    'category' => 'E-commerce',
                    'context' => 'public',
                    'primary_component' => 'table',
                    'secondary_components' => ['button'],
                    'component_types' => ['ecom.compare.table', 'ecom.compare.button'],
                    'builder_sidebar_category' => 'E-commerce',
                    'source_variant' => 'ui',
                    'endpoint_methods' => ['GET'],
                    'endpoint_paths' => ['/compare'],
                    'endpoint_auth' => ['public'],
                    'endpoint_name_0' => 'GetCompare',
                    'endpoint_operation_key_0' => 'get_compare',
                    'route_params_0' => [],
                ],
            ],
        ];
    }
}
