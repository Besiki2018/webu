<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Services\CmsAiSchemaValidationService;
use App\Services\CmsAiPageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiPageGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTemplateForCatalog();

        config()->set('theme-presets', [
            'default' => ['name' => 'Default'],
            'arctic' => ['name' => 'Arctic'],
            'summer' => ['name' => 'Summer'],
            'forest' => ['name' => 'Forest'],
            'ocean' => ['name' => 'Ocean'],
            'slate' => ['name' => 'Slate'],
        ]);
    }

    public function test_it_generates_builder_native_ecommerce_pages_with_route_metadata_and_canonical_nodes(): void
    {
        $service = app(CmsAiPageGenerationService::class);
        $schemaValidator = app(CmsAiSchemaValidationService::class);

        $result = $service->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate an ecommerce pet store with cart, checkout, account and order history pages.',
                'constraints' => [
                    'allow_ecommerce' => true,
                ],
                'user_context' => [
                    'business_name' => 'Paw Pantry',
                ],
            ],
            'platform_context' => [
                'template_blueprint' => [
                    'template_slug' => 'webu-shop-01',
                ],
                'module_registry' => [
                    'modules' => [[
                        'key' => 'ecommerce',
                        'enabled' => true,
                        'available' => true,
                    ]],
                ],
            ],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertSame('rule_based_page_generation', data_get($result, 'engine.kind'));
        $this->assertSame('ecommerce', data_get($result, 'page_plan.site_family'));
        $this->assertSame('ecommerce', data_get($result, 'page_plan.ai_industry_component_mapping.industry_family'));
        $this->assertContains('ecommerce', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('webu_ecom_product_detail_01', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.component_keys', []));
        $this->assertSame('ecommerce', data_get($result, 'page_plan.component_library_spec_aliases.industry_family'));
        $this->assertGreaterThanOrEqual(6, (int) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_key_count'));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertContains('ecom.productDetail', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertTrue((bool) data_get($result, 'page_plan.component_library_spec_aliases.alias_coverage.ok'));
        $this->assertSame(1, data_get($result, 'page_plan.placement_styling_rules.placement_rules_version'));
        $this->assertSame(1, data_get($result, 'page_plan.placement_styling_rules.styling_rules_version'));
        $this->assertGreaterThan(0, (int) data_get($result, 'page_plan.placement_styling_rules.nodes_touched'));
        $this->assertSame('p6-g3-02.v1', data_get($result, 'page_plan.reproducibility.version'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'page_plan.reproducibility.output_fingerprint'));
        $this->assertSame('ecommerce', data_get($result, 'page_plan.reproducibility.component_library_spec_aliases.industry_family'));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.reproducibility.component_library_spec_aliases.source_spec_component_keys', []));

        $pages = $result['pages'];
        $this->assertIsArray($pages);
        $this->assertCount(10, $pages);

        $slugs = array_column($pages, 'slug');
        foreach (['home', 'shop', 'product', 'cart', 'checkout', 'login', 'account', 'orders', 'order', 'contact'] as $expected) {
            $this->assertContains($expected, $slugs);
        }

        $productPage = $this->findPageBySlug($pages, 'product');
        $this->assertSame('/product/:slug', $productPage['path']);
        $this->assertSame('/product/:slug', $productPage['route_pattern']);
        $this->assertSame('product-detail', $productPage['template_key']);
        $this->assertSame('draft', $productPage['status']);
        $this->assertSame('Product Detail | Paw Pantry', data_get($productPage, 'seo.seo_title'));
        $this->assertSame(1, data_get($productPage, 'meta.ai_layout_rules.placement_version'));
        $this->assertSame('p6-g3-02.v1', data_get($productPage, 'meta.reproducibility.version'));
        $this->assertSame(data_get($result, 'page_plan.reproducibility.output_fingerprint'), data_get($productPage, 'meta.reproducibility.output_fingerprint'));
        $this->assertStringContainsString('AI placement+styling rules v1 applied: product', (string) ($productPage['page_css'] ?? ''));

        $firstNode = $productPage['builder_nodes'][0] ?? null;
        $this->assertIsArray($firstNode);
        $this->assertSame('section', $firstNode['type'] ?? null);
        $this->assertSame(
            ['content', 'data', 'style', 'advanced', 'responsive', 'states'],
            array_keys($firstNode['props'] ?? [])
        );
        $this->assertSame('product', data_get($firstNode, 'meta.ai_slot'));
        $this->assertSame('stack', data_get($firstNode, 'props.style.layout'));
        $this->assertSame('section_y', data_get($firstNode, 'props.style.spacing.padding_y_token'));

        $productDetailNode = $this->findFirstNodeByType($productPage['builder_nodes'], 'product-detail');
        $this->assertNotNull($productDetailNode);
        $this->assertSame('{{route.params.slug}}', $productDetailNode['bindings']['props.data.slug'] ?? null);
        $this->assertSame('product_detail', data_get($productDetailNode, 'meta.ai_slot'));
        $this->assertSame('card', data_get($productDetailNode, 'props.style.surface'));

        $orderPage = $this->findPageBySlug($pages, 'order');
        $orderDetailNode = $this->findFirstNodeByType($orderPage['builder_nodes'], 'order-detail');
        $this->assertNotNull($orderDetailNode);
        $this->assertSame('{{route.params.id}}', $orderDetailNode['bindings']['props.data.id'] ?? null);

        $outputEnvelope = $this->minimalOutputEnvelope($pages);
        $validation = $schemaValidator->validateOutputPayload($outputEnvelope);
        $this->assertTrue((bool) ($validation['valid'] ?? false), 'AI output schema validation failed: '.json_encode($validation));
    }

    public function test_it_filters_requested_page_slugs_and_respects_max_new_pages_constraint(): void
    {
        $service = app(CmsAiPageGenerationService::class);

        $result = $service->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_pages',
                'prompt' => 'Generate only store pages',
                'target' => [
                    'page_slugs' => ['shop', 'product', 'checkout', 'missing-page'],
                    'route_scope' => 'pages',
                ],
                'constraints' => [
                    'allow_ecommerce' => true,
                    'max_new_pages' => 2,
                ],
            ],
            'platform_context' => [
                'template_blueprint' => [
                    'template_slug' => 'webu-shop-01',
                ],
            ],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['pages']);
        $this->assertSame(['shop', 'product'], array_column($result['pages'], 'slug'));
        $this->assertSame(1, data_get($result, 'page_plan.placement_styling_rules.placement_rules_version'));
        $this->assertContains('max_new_pages constraint applied; page generation output was truncated.', $result['warnings']);
        $this->assertStringContainsString('missing-page', implode(' | ', $result['warnings']));
    }

    public function test_it_attaches_ai_industry_component_mapping_for_vertical_prompts_even_when_page_catalog_falls_back_to_business_pages(): void
    {
        $service = app(CmsAiPageGenerationService::class);

        $result = $service->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a boutique hotel website with room listings, room availability and reservations.',
            ],
            'platform_context' => [
                'site' => [
                    'theme_settings' => [
                        'project_type' => 'hotel',
                    ],
                ],
                'module_registry' => [
                    'modules' => [
                        ['key' => 'hotel', 'enabled' => true, 'available' => true],
                        ['key' => 'booking', 'enabled' => true, 'available' => true],
                    ],
                ],
            ],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertSame('hotel', data_get($result, 'page_plan.site_family'));
        $this->assertSame('project_type', data_get($result, 'page_plan.ai_industry_component_mapping.decision_source'));
        $this->assertSame('hotel', data_get($result, 'page_plan.ai_industry_component_mapping.industry_family'));
        $this->assertFalse((bool) data_get($result, 'page_plan.ai_industry_component_mapping.page_generation_catalog_supported'));
        $this->assertContains('hotel', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('booking', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('webu_hotel_room_detail_01', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.component_keys', []));
        $this->assertContains('webu_hotel_reservation_form_01', (array) data_get($result, 'page_plan.ai_industry_component_mapping.builder_component_mapping.component_keys', []));
        $this->assertContains('hotel.roomDetail', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertContains('hotel.reservationForm', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertNotEmpty($result['pages']);
        $this->assertContains(
            'AI industry mapping resolved [hotel] component groups; page generation uses business-page fallback while preserving industry component hints.',
            $result['warnings']
        );
    }

    public function test_it_returns_input_validation_errors_when_ai_input_payload_is_invalid(): void
    {
        $service = app(CmsAiPageGenerationService::class);

        $result = $service->generatePagesFragment([
            'schema_version' => 1,
            'request' => [
                'prompt' => 'missing required fields',
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['pages']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('missing_required_key', array_column($result['errors'], 'code'));
    }

    public function test_it_emits_structured_ai_generation_trace_log_for_page_generation(): void
    {
        Log::spy();

        $service = app(CmsAiPageGenerationService::class);
        $result = $service->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_pages',
                'prompt' => 'Generate a storefront with product and checkout pages.',
                'constraints' => ['allow_ecommerce' => true, 'max_new_pages' => 2],
            ],
        ]));

        $this->assertTrue($result['valid']);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'cms.ai.generation.trace'
                    && ($context['flow'] ?? null) === 'ai_generation'
                    && ($context['step'] ?? null) === 'page_generation'
                    && ($context['trace_id'] ?? null) === 'ai-gen-req-pages-1'
                    && ($context['status'] ?? null) === 'ok'
                    && is_int($context['duration_ms'] ?? null)
                    && ($context['duration_ms'] ?? -1) >= 0;
            })
            ->once();
    }

    /** @group docs-sync */
    public function test_architecture_doc_documents_builder_native_page_trees_route_metadata_and_no_parallel_storage_handoff(): void
    {
        $path = base_path('docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Page Generation Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-02', $doc);
        $this->assertStringContainsString('builder-native page output fragments', $doc);
        $this->assertStringContainsString('route/page metadata', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1', $doc);
        $this->assertStringContainsString('no parallel page storage', $doc);
        $this->assertStringContainsString('P4-E2-04', $doc);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function findPageBySlug(array $pages, string $slug): array
    {
        foreach ($pages as $page) {
            if (($page['slug'] ?? null) === $slug) {
                return $page;
            }
        }

        $this->fail("Page not found: {$slug}");
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findFirstNodeByType(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === $type) {
                return $node;
            }

            $children = $node['children'] ?? null;
            if (is_array($children)) {
                $match = $this->findFirstNodeByType($children, $type);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function minimalOutputEnvelope(array $pages): array
    {
        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => [
                    'preset' => 'default',
                ],
            ],
            'pages' => $pages,
            'header' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'footer' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => '2026-02-24T12:00:00Z',
                'contracts' => [
                    'ai_input_schema' => 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    'canonical_page_node_schema' => 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    'canonical_component_registry_schema' => 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                ],
                'validation_expectations' => [
                    'strict_top_level' => true,
                    'no_parallel_storage' => true,
                    'builder_native_pages' => true,
                    'component_availability_check_required' => true,
                    'binding_validation_required' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInputPayload(array $overrides = []): array
    {
        $base = [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate site',
                'locale' => 'en',
                'target' => [
                    'route_scope' => 'site',
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
                    'theme_settings' => [
                        'preset' => 'default',
                    ],
                ],
                'template_blueprint' => [
                    'template_id' => 1,
                    'template_slug' => null,
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
                    'summary' => [
                        'total' => 0,
                        'available' => 0,
                        'disabled' => 0,
                        'not_entitled' => 0,
                    ],
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
                'request_id' => 'req-pages-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'internal_tool',
            ],
        ];

        return $this->mergeRecursiveDistinct($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeRecursiveDistinct(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                /** @var array<string, mixed> $baseValue */
                $baseValue = $base[$key];
                /** @var array<string, mixed> $overrideValue */
                $overrideValue = $value;
                $base[$key] = $this->mergeRecursiveDistinct($baseValue, $overrideValue);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Ensure templates table has a record for ecommerce-storefront so ReadyTemplatesService::loadBySlug works.
     */
    private function seedTemplateForCatalog(): void
    {
        Template::query()->firstOrCreate(
            ['slug' => 'ecommerce-storefront'],
            [
                'name' => 'Ecommerce Storefront',
                'description' => 'Test template',
                'category' => 'ecommerce',
                'is_system' => true,
                'metadata' => [
                    'theme_preset' => 'luxury_minimal',
                    'default_pages' => [
                        ['slug' => 'home', 'title' => 'Home', 'sections' => []],
                        ['slug' => 'shop', 'title' => 'Shop', 'sections' => []],
                        ['slug' => 'product', 'title' => 'Product', 'sections' => []],
                        ['slug' => 'cart', 'title' => 'Cart', 'sections' => []],
                        ['slug' => 'checkout', 'title' => 'Checkout', 'sections' => []],
                        ['slug' => 'contact', 'title' => 'Contact', 'sections' => []],
                    ],
                ],
            ]
        );
    }
}
