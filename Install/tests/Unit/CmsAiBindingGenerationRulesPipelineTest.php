<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiBindingGenerationRulesPipelineTest extends TestCase
{
    public function test_page_generation_engine_normalizes_section_library_bindings_and_injects_product_route_binding(): void
    {
        $engine = app(CmsAiPageGenerationEngine::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'generate_site';
        $input['request']['prompt'] = 'Generate ecommerce pages with product detail.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
        ];
        $input['platform_context']['template_blueprint']['default_pages'] = [
            ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero_split_image']],
            ['slug' => 'product', 'title' => 'Product', 'sections' => ['ecommerce_product_detail']],
        ];
        $input['platform_context']['template_blueprint']['default_sections'] = [
            'home' => [
                ['key' => 'hero_split_image', 'enabled' => true],
            ],
            'product' => [
                ['key' => 'ecommerce_product_detail', 'enabled' => true],
            ],
        ];
        $input['platform_context']['section_library'] = [
            [
                'key' => 'hero_split_image',
                'category' => 'marketing',
                'schema_json' => [
                    'bindings' => [
                        'headline' => 'content.headline',
                    ],
                ],
                'enabled' => true,
            ],
            [
                'key' => 'ecommerce_product_detail',
                'category' => 'ecommerce',
                'schema_json' => [
                    'bindings' => [
                        'title' => 'ecommerce.product.title',
                    ],
                ],
                'enabled' => true,
            ],
        ];
        $input['platform_context']['module_registry']['modules'] = [
            ['key' => 'ecommerce', 'enabled' => true, 'available' => true],
        ];

        $result = $engine->generateFromAiInput($input);

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $home = collect($result['pages_output'])->firstWhere('slug', 'home');
        $product = collect($result['pages_output'])->firstWhere('slug', 'product');

        $this->assertSame('{{content.headline}}', data_get($home, 'builder_nodes.0.bindings.headline'));
        $this->assertSame('{{ecommerce.product.title}}', data_get($product, 'builder_nodes.0.bindings.title'));
        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.bindings.product_slug'));
        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.props.content.product_slug'));
    }

    public function test_component_placement_styling_engine_applies_page_type_binding_and_query_rules(): void
    {
        $engine = app(CmsAiComponentPlacementStylingEngine::class);

        $pagesOutput = [
            $this->pageOutput('product', '/product/{slug}', [
                $this->node('ecommerce-product-detail'),
            ]),
            $this->pageOutput('cart', '/cart', [
                $this->node('ecommerce-cart'),
            ]),
            $this->pageOutput('checkout', '/checkout', [
                $this->node('ecommerce-checkout'),
            ]),
            $this->pageOutput('account', '/account', [
                $this->node('ecommerce-account'),
            ]),
            $this->pageOutput('login', '/account/login', [
                $this->node('auth-login-register'),
            ]),
            $this->pageOutput('orders', '/account/orders', [
                $this->node('ecommerce-orders'),
            ]),
            $this->pageOutput('order-detail', '/account/orders/{id}', [
                $this->node('ecommerce-order-detail'),
            ]),
        ];

        $result = $engine->applyToPagesOutput(
            $this->validAiInput(),
            $pagesOutput,
            [
                'theme_settings_patch' => [
                    'preset' => 'arctic',
                    'theme_tokens' => [
                        'radii' => ['base' => '0.375rem'],
                        'colors' => ['primary' => '#0ea5e9'],
                    ],
                ],
                'meta' => ['source' => 'generated'],
            ]
        );

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));

        $product = collect($result['pages_output'])->firstWhere('slug', 'product');
        $cart = collect($result['pages_output'])->firstWhere('slug', 'cart');
        $checkout = collect($result['pages_output'])->firstWhere('slug', 'checkout');
        $account = collect($result['pages_output'])->firstWhere('slug', 'account');
        $login = collect($result['pages_output'])->firstWhere('slug', 'login');
        $orders = collect($result['pages_output'])->firstWhere('slug', 'orders');
        $orderDetail = collect($result['pages_output'])->firstWhere('slug', 'order-detail');

        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.bindings.product_slug'));
        $this->assertSame('ecommerce.product', data_get($product, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('{{route.params.slug}}', data_get($product, 'builder_nodes.0.props.data.query.binding'));

        $this->assertSame('ecommerce.cart', data_get($cart, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('ecommerce.checkout', data_get($checkout, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('conversion', data_get($checkout, 'builder_nodes.0.props.advanced.ai_priority'));

        $this->assertSame('ecommerce.account', data_get($account, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('auth.session', data_get($login, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('ecommerce.orders', data_get($orders, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('ecommerce.order', data_get($orderDetail, 'builder_nodes.0.props.data.query.resource'));
        $this->assertSame('{{route.params.id}}', data_get($orderDetail, 'builder_nodes.0.bindings.order_id'));
        $this->assertSame('{{route.params.id}}', data_get($orderDetail, 'builder_nodes.0.props.data.query.binding'));
    }

    public function test_architecture_doc_documents_binding_generation_rules_and_pipeline_scope(): void
    {
        $path = base_path('docs/architecture/CMS_AI_BINDING_GENERATION_RULES_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Binding Generation Rules v1', $doc);
        $this->assertStringContainsString('Data-binding generation rules', $doc);
        $this->assertStringContainsString('CmsAiPageGenerationEngine', $doc);
        $this->assertStringContainsString('CmsAiComponentPlacementStylingEngine', $doc);
        $this->assertStringContainsString('{{route.params.slug}}', $doc);
        $this->assertStringContainsString('{{route.params.id}}', $doc);
        $this->assertStringContainsString('ecommerce.product', $doc);
        $this->assertStringContainsString('ecommerce.checkout', $doc);
        $this->assertStringContainsString('page_revisions.content_json', $doc);
        $this->assertStringContainsString('P4-E3-01', $doc);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function pageOutput(string $slug, string $path, array $nodes): array
    {
        return [
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'path' => $path,
            'route_pattern' => str_contains($path, '{') ? $path : null,
            'status' => 'draft',
            'builder_nodes' => $nodes,
            'meta' => [
                'source' => 'generated',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(string $type): array
    {
        return [
            'type' => $type,
            'props' => [
                'content' => [],
                'data' => [],
                'style' => [],
                'advanced' => [],
                'responsive' => [],
                'states' => [],
            ],
            'bindings' => [],
            'meta' => [
                'schema_version' => 1,
                'source' => 'generated',
            ],
        ];
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
                'prompt' => 'Generate ecommerce pages',
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
                'request_id' => 'req-bind-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }
}
