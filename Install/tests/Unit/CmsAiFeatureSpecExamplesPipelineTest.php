<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiFeatureSpecExamplesPipelineTest extends TestCase
{
    public function test_product_reviews_feature_spec_passes_component_auto_generator_pipeline_preflight(): void
    {
        $result = $this->runFeatureSpecThroughPreflight([
            'feature' => 'product-reviews',
            'title' => 'Product Reviews',
            'module' => 'shop',
            'widgets' => [
                [
                    'name' => 'reviews-summary',
                    'kind' => 'summary',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.reviews.summary', 'binding' => 'route.params.slug'],
                    ],
                    'props' => [
                        'heading' => 'Customer Reviews',
                        'labels' => [
                            'empty' => 'No reviews yet',
                        ],
                    ],
                    'controls' => ['content', 'style', 'data'],
                    'variants' => ['default', 'compact'],
                ],
                [
                    'key' => 'reviews-list',
                    'role' => 'list',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'data_queries' => [
                        ['resource' => 'ecommerce.reviews.list', 'binding' => 'route.params.slug'],
                    ],
                    'events' => ['paginate', 'sort'],
                    'props' => [
                        'empty_state' => [
                            'title' => 'Be the first to review this product',
                        ],
                    ],
                ],
                [
                    'key' => 'review-form',
                    'role' => 'form',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.reviews.permissions', 'binding' => 'route.params.slug'],
                    ],
                    'actions' => ['reviews.submit'],
                    'props' => [
                        'labels' => [
                            'submit' => 'Submit Review',
                        ],
                    ],
                    'controls' => ['content', 'data', 'style', 'advanced'],
                ],
            ],
            'ui_states' => ['ready', 'loading', 'empty', 'error'],
            'user_events' => ['submit_review', 'paginate', 'sort'],
            'api_endpoints' => [
                'GET /public/sites/{site}/products/{slug}/reviews',
                'POST /public/sites/{site}/products/{slug}/reviews',
            ],
        ]);

        $this->assertExamplePipelineResult(
            result: $result,
            expectedFeatureKey: 'product-reviews',
            expectedComponentCount: 3,
            expectedRegistryTypes: [
                'feature-product-reviews-reviews-summary',
                'feature-product-reviews-reviews-list',
                'feature-product-reviews-review-form',
            ],
            requiredHtmlMarkers: [
                'data-bind-binding="product_slug"',
                'data-bind-query-resource="ecommerce.reviews.list"',
                'data-webu-ai-template-generated="1"',
            ]
        );
    }

    public function test_subscriptions_feature_spec_passes_component_auto_generator_pipeline_preflight(): void
    {
        $result = $this->runFeatureSpecThroughPreflight([
            'feature' => 'subscriptions',
            'title' => 'Product Subscriptions',
            'module' => 'shop',
            'widgets' => [
                [
                    'name' => 'subscription-plan-selector',
                    'kind' => 'selector',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.subscriptions.plans', 'binding' => 'route.params.slug'],
                    ],
                    'props' => [
                        'heading' => 'Choose a plan',
                    ],
                    'controls' => ['content', 'data', 'style'],
                ],
                [
                    'key' => 'subscription-summary-card',
                    'role' => 'card',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'data_queries' => [
                        ['resource' => 'ecommerce.subscriptions.active', 'binding' => 'customer.id'],
                    ],
                    'events' => ['manage_subscription'],
                    'props' => [
                        'empty_state' => [
                            'title' => 'No active subscriptions',
                        ],
                    ],
                ],
                [
                    'key' => 'subscription-action-form',
                    'role' => 'form',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.subscriptions.permissions', 'binding' => 'route.params.slug'],
                    ],
                    'actions' => ['subscriptions.start'],
                    'props' => [
                        'labels' => [
                            'submit' => 'Start Subscription',
                        ],
                    ],
                    'controls' => ['content', 'data', 'style', 'advanced'],
                ],
            ],
            'ui_states' => ['ready', 'loading', 'empty', 'error'],
            'user_events' => ['start_subscription', 'manage_subscription'],
            'api_endpoints' => [
                'GET /public/sites/{site}/products/{slug}/subscriptions',
                'POST /public/sites/{site}/subscriptions',
            ],
        ]);

        $this->assertExamplePipelineResult(
            result: $result,
            expectedFeatureKey: 'subscriptions',
            expectedComponentCount: 3,
            expectedRegistryTypes: [
                'feature-subscriptions-subscription-plan-selector',
                'feature-subscriptions-subscription-summary-card',
                'feature-subscriptions-subscription-action-form',
            ],
            requiredHtmlMarkers: [
                'data-bind-query-resource="ecommerce.subscriptions.plans"',
                'data-bind-query-resource="ecommerce.subscriptions.active"',
                'data-bind-binding="customer_id"',
            ]
        );
    }

    public function test_loyalty_points_feature_spec_passes_component_auto_generator_pipeline_preflight(): void
    {
        $result = $this->runFeatureSpecThroughPreflight([
            'feature' => 'loyalty-points',
            'title' => 'Loyalty Points',
            'module' => 'shop',
            'widgets' => [
                [
                    'name' => 'points-balance-card',
                    'kind' => 'summary',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.loyalty.balance', 'binding' => 'customer.id'],
                    ],
                    'props' => [
                        'heading' => 'Your Points',
                    ],
                ],
                [
                    'key' => 'points-history-list',
                    'role' => 'list',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'data_queries' => [
                        ['resource' => 'ecommerce.loyalty.history', 'binding' => 'customer.id'],
                    ],
                    'events' => ['paginate'],
                ],
                [
                    'key' => 'rewards-redeem-panel',
                    'role' => 'panel',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.loyalty.rewards', 'binding' => 'customer.id'],
                    ],
                    'actions' => ['loyalty.redeem'],
                    'props' => [
                        'labels' => [
                            'cta' => 'Redeem Reward',
                        ],
                    ],
                ],
            ],
            'ui_states' => ['ready', 'loading', 'empty', 'error'],
            'user_events' => ['redeem_reward', 'paginate'],
            'api_endpoints' => [
                'GET /public/sites/{site}/loyalty/balance',
                'GET /public/sites/{site}/loyalty/history',
                'POST /public/sites/{site}/loyalty/redeem',
            ],
        ]);

        $this->assertExamplePipelineResult(
            result: $result,
            expectedFeatureKey: 'loyalty-points',
            expectedComponentCount: 3,
            expectedRegistryTypes: [
                'feature-loyalty-points-points-balance-card',
                'feature-loyalty-points-points-history-list',
                'feature-loyalty-points-rewards-redeem-panel',
            ],
            requiredHtmlMarkers: [
                'data-bind-query-resource="ecommerce.loyalty.balance"',
                'data-bind-query-resource="ecommerce.loyalty.history"',
                'data-bind-query-resource="ecommerce.loyalty.rewards"',
            ]
        );
    }

    public function test_product_compare_feature_spec_passes_component_auto_generator_pipeline_preflight(): void
    {
        $result = $this->runFeatureSpecThroughPreflight([
            'feature' => 'product-compare',
            'title' => 'Product Compare',
            'module' => 'shop',
            'widgets' => [
                [
                    'name' => 'compare-toggle-button',
                    'kind' => 'toggle',
                    'bindings' => [
                        'product_slug' => 'route.params.slug',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.compare.state', 'binding' => 'route.params.slug'],
                    ],
                    'actions' => ['compare.toggle'],
                    'props' => [
                        'labels' => [
                            'active' => 'In Compare',
                            'inactive' => 'Compare',
                        ],
                    ],
                ],
                [
                    'key' => 'compare-table',
                    'role' => 'table',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'data_queries' => [
                        ['resource' => 'ecommerce.compare.items', 'binding' => 'customer.id'],
                    ],
                    'events' => ['remove_item', 'sort'],
                ],
                [
                    'key' => 'compare-actions-bar',
                    'role' => 'toolbar',
                    'bindings' => [
                        'customer_id' => 'customer.id',
                    ],
                    'queries' => [
                        ['resource' => 'ecommerce.compare.permissions', 'binding' => 'customer.id'],
                    ],
                    'actions' => ['compare.clear_all'],
                    'props' => [
                        'labels' => [
                            'clear' => 'Clear Compare',
                        ],
                    ],
                ],
            ],
            'ui_states' => ['ready', 'loading', 'empty', 'error'],
            'user_events' => ['toggle_compare', 'remove_item', 'clear_compare'],
            'api_endpoints' => [
                'GET /public/sites/{site}/compare',
                'POST /public/sites/{site}/compare/toggle',
            ],
        ]);

        $this->assertExamplePipelineResult(
            result: $result,
            expectedFeatureKey: 'product-compare',
            expectedComponentCount: 3,
            expectedRegistryTypes: [
                'feature-product-compare-compare-toggle-button',
                'feature-product-compare-compare-table',
                'feature-product-compare-compare-actions-bar',
            ],
            requiredHtmlMarkers: [
                'data-bind-query-resource="ecommerce.compare.state"',
                'data-bind-query-resource="ecommerce.compare.items"',
                'data-bind-query-resource="ecommerce.compare.permissions"',
            ]
        );
    }

    public function test_architecture_docs_reference_feature_spec_driven_examples_and_security_constraints(): void
    {
        $featureSpecDoc = File::get(base_path('docs/architecture/CMS_AI_FEATURE_SPEC_PARSER_V1.md'));
        $securityDoc = File::get(base_path('docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md'));

        $this->assertStringContainsString('wishlist', $featureSpecDoc);
        $this->assertStringContainsString('reviews', $featureSpecDoc);
        $this->assertStringContainsString('subscriptions', $featureSpecDoc);
        $this->assertStringContainsString('loyalty', $featureSpecDoc);
        $this->assertStringContainsString('compare', $featureSpecDoc);
        $this->assertStringContainsString('Security constraints in generated code/components', $securityDoc);
    }

    /**
     * @param  array<string, mixed>  $rawFeatureSpec
     * @return array<string, mixed>
     */
    private function runFeatureSpecThroughPreflight(array $rawFeatureSpec): array
    {
        $workflow = app(CmsAiComponentRegistryIntegrationWorkflowService::class);

        return $workflow->prepareActivationFromRawFeatureSpec($rawFeatureSpec);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $expectedRegistryTypes
     * @param  array<int, string>  $requiredHtmlMarkers
     */
    private function assertExamplePipelineResult(
        array $result,
        string $expectedFeatureKey,
        int $expectedComponentCount,
        array $expectedRegistryTypes,
        array $requiredHtmlMarkers
    ): void {
        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame($expectedFeatureKey, data_get($result, 'activation_plan.feature_key'));
        $this->assertSame($expectedComponentCount, data_get($result, 'summary.component_count'));
        $this->assertSame($expectedComponentCount, data_get($result, 'summary.ready_component_count'));
        $this->assertSame(0, data_get($result, 'summary.blocked_component_count'));

        $components = data_get($result, 'activation_plan.components', []);
        $this->assertIsArray($components);

        $statuses = collect($components)->pluck('status')->unique()->all();
        $this->assertSame(['ready_for_activation'], $statuses);

        $registryTypes = collect($components)->pluck('registry_type')->all();
        foreach ($expectedRegistryTypes as $expectedRegistryType) {
            $this->assertContains($expectedRegistryType, $registryTypes);
        }

        $htmlBlob = collect($components)
            ->map(fn (array $component): string => (string) data_get($component, 'renderer_template.html'))
            ->implode("\n");

        $this->assertStringContainsString('data-webu-ai-template-generated="1"', $htmlBlob);
        foreach ($requiredHtmlMarkers as $marker) {
            $this->assertStringContainsString($marker, $htmlBlob);
        }
    }
}
