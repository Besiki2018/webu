<?php

namespace Tests\Unit;

use App\Models\CmsLearnedRule;
use App\Services\CmsAiPageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiPageGenerationLearningRulesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('theme-presets', [
            'default' => ['name' => 'Default'],
            'arctic' => ['name' => 'Arctic'],
        ]);
    }

    public function test_page_generation_applies_learned_rules_and_exposes_applied_rules_debug_metadata(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => '1',
            'site_id' => '1',
            'rule_key' => 'lr_shop_grid_columns_3',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
                'prompt_intent_tags' => ['luxury'],
                'page_template_key' => 'shop',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 3,
            ],
            'confidence' => 0.9,
            'sample_size' => 20,
            'delta_count' => 20,
        ]);

        $result = app(CmsAiPageGenerationService::class)->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a luxury ecommerce store with catalog pages.',
                'constraints' => [
                    'allow_ecommerce' => true,
                ],
            ],
            'platform_context' => [
                'template_blueprint' => ['template_slug' => 'webu-shop-01'],
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
        $this->assertSame('p6-g3-01.v1', data_get($result, 'page_plan.learning_generation_version'));
        $this->assertGreaterThanOrEqual(1, count((array) data_get($result, 'page_plan.applied_rules', [])));
        $this->assertGreaterThanOrEqual(1, (int) data_get($result, 'page_plan.learned_rules_application.applied_rule_count'));
        $this->assertSame('ok', data_get($result, 'page_plan.learned_rules_application.rule_fetch.status'));
        $this->assertSame('p6-g3-02.v1', data_get($result, 'page_plan.reproducibility.version'));
        $this->assertSame('sha256', data_get($result, 'page_plan.reproducibility.fingerprint_algorithm'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'page_plan.reproducibility.input_fingerprint'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'page_plan.reproducibility.output_fingerprint'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'page_plan.reproducibility.learned_rules.versioning.applied_rule_set_version'));
        $this->assertSame('ecommerce', data_get($result, 'page_plan.component_library_spec_aliases.industry_family'));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.reproducibility.component_library_spec_aliases.source_spec_component_keys', []));

        $shopPage = $this->findPageBySlug((array) ($result['pages'] ?? []), 'shop');
        $gridNode = $this->findFirstNodeByType((array) ($shopPage['builder_nodes'] ?? []), 'products-grid');

        $this->assertNotNull($gridNode);
        $this->assertSame(3, data_get($gridNode, 'props.style.columns'));
        $this->assertSame('lr_shop_grid_columns_3', data_get($result, 'page_plan.applied_rules.0.rule_key'));
        $this->assertSame(
            data_get($result, 'page_plan.reproducibility.output_fingerprint'),
            data_get($shopPage, 'meta.reproducibility.output_fingerprint')
        );
        $this->assertContains('lr_shop_grid_columns_3', (array) data_get($shopPage, 'meta.reproducibility.applied_rule_keys', []));
        $this->assertStringStartsWith('cms-ai-replay:p6-g3-02.v1:sha256:', (string) data_get($shopPage, 'meta.reproducibility.replay_key'));

        $resultRepeat = app(CmsAiPageGenerationService::class)->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a luxury ecommerce store with catalog pages.',
                'constraints' => [
                    'allow_ecommerce' => true,
                ],
            ],
            'platform_context' => [
                'template_blueprint' => ['template_slug' => 'webu-shop-01'],
                'module_registry' => [
                    'modules' => [[
                        'key' => 'ecommerce',
                        'enabled' => true,
                        'available' => true,
                    ]],
                ],
            ],
        ]));
        $this->assertSame(
            data_get($result, 'page_plan.reproducibility.output_fingerprint'),
            data_get($resultRepeat, 'page_plan.reproducibility.output_fingerprint')
        );
        $this->assertSame(
            data_get($result, 'page_plan.reproducibility.replay_key'),
            data_get($resultRepeat, 'page_plan.reproducibility.replay_key')
        );
        $this->assertSame(
            data_get($result, 'page_plan.reproducibility.component_library_spec_aliases'),
            data_get($resultRepeat, 'page_plan.reproducibility.component_library_spec_aliases')
        );
    }

    public function test_page_generation_enforces_tenant_opt_out_and_disables_learning_reproducibility_metadata(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => '1',
            'site_id' => '1',
            'rule_key' => 'lr_shop_grid_columns_3',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
                'page_template_key' => 'shop',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 3,
            ],
            'confidence' => 0.9,
            'sample_size' => 20,
            'delta_count' => 20,
        ]);

        $result = app(CmsAiPageGenerationService::class)->generatePagesFragment($this->validInputPayload([
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate an ecommerce store with catalog.',
                'constraints' => [
                    'allow_ecommerce' => true,
                ],
            ],
            'platform_context' => [
                'site' => [
                    'theme_settings' => [
                        'preset' => 'default',
                        'ai_learning' => [
                            'opt_out' => true,
                        ],
                    ],
                ],
                'template_blueprint' => ['template_slug' => 'webu-shop-01'],
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
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.learning_privacy.status'));
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.learned_rules_application.rule_fetch.reason'));
        $this->assertSame(0, count((array) data_get($result, 'page_plan.applied_rules', [])));
        $this->assertFalse((bool) data_get($result, 'page_plan.reproducibility.enabled'));
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.reproducibility.reason'));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.component_library_spec_aliases.source_spec_component_keys', []));
        $this->assertContains('ecom.productGrid', (array) data_get($result, 'page_plan.reproducibility.component_library_spec_aliases.source_spec_component_keys', []));

        $shopPage = $this->findPageBySlug((array) ($result['pages'] ?? []), 'shop');
        $this->assertNull(data_get($shopPage, 'meta.reproducibility'));
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

            if (is_array($node['children'] ?? null)) {
                $match = $this->findFirstNodeByType($node['children'], $type);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
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
                'target' => ['route_scope' => 'site'],
            ],
            'platform_context' => [
                'project' => ['id' => '1', 'name' => 'Demo Project'],
                'site' => [
                    'id' => '1',
                    'name' => 'Demo Site',
                    'status' => 'draft',
                    'locale' => 'en',
                    'theme_settings' => ['preset' => 'default'],
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
                'request_id' => 'req-pages-learning-1',
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
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
