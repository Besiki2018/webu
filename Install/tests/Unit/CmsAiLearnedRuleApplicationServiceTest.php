<?php

namespace Tests\Unit;

use App\Models\CmsLearnedRule;
use App\Services\CmsAiLearnedRuleApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAiLearnedRuleApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fetches_and_applies_matching_active_learned_rules_in_deterministic_order_with_conflict_skips(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-1',
            'site_id' => 'site-1',
            'rule_key' => 'lr_products_grid_columns_3_high',
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
            'confidence' => 0.9000,
            'sample_size' => 50,
            'delta_count' => 50,
        ]);

        // Lower-priority conflicting rule should be skipped on the same target.
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-1',
            'site_id' => 'site-1',
            'rule_key' => 'lr_products_grid_columns_4_low',
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
                'value' => 4,
            ],
            'confidence' => 0.4000,
            'sample_size' => 10,
            'delta_count' => 10,
        ]);

        // Different component/path should still apply.
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-1',
            'site_id' => 'site-1',
            'rule_key' => 'lr_button_radius_pill',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_button_01',
                'prompt_intent_tags' => ['luxury'],
                'page_template_key' => 'shop',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_button_01',
                'path_suffix' => '/props/style/border_radius',
                'value' => 999,
            ],
            'confidence' => 0.7000,
            'sample_size' => 30,
            'delta_count' => 30,
        ]);

        // Foreign site rule must not leak.
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-2',
            'site_id' => 'site-2',
            'rule_key' => 'lr_foreign_site',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 2,
            ],
            'confidence' => 0.9900,
            'sample_size' => 99,
            'delta_count' => 99,
        ]);

        $pages = [[
            'slug' => 'shop',
            'template_key' => 'shop',
            'builder_nodes' => [[
                'type' => 'section',
                'props' => [
                    'content' => ['label' => 'Catalog'],
                    'data' => [],
                    'style' => ['layout' => 'stack'],
                    'advanced' => [],
                    'responsive' => [],
                    'states' => [],
                ],
                'bindings' => [],
                'meta' => ['schema_version' => 1],
                'children' => [
                    [
                        'type' => 'products-grid',
                        'props' => [
                            'content' => ['title' => 'All products'],
                            'data' => ['page_size' => 12],
                            'style' => ['columns' => 5],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['schema_version' => 1],
                    ],
                    [
                        'type' => 'button',
                        'props' => [
                            'content' => ['label' => 'Shop now'],
                            'data' => ['url' => '/shop'],
                            'style' => ['border_radius' => 8],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['schema_version' => 1],
                    ],
                ],
            ]],
        ]];

        $aiInput = [
            'request' => [
                'prompt' => 'Generate a luxury ecommerce storefront',
            ],
            'platform_context' => [
                'site' => ['id' => 'site-1'],
                'project' => ['id' => 'project-1'],
            ],
        ];

        $result = app(CmsAiLearnedRuleApplicationService::class)->applyToGeneratedPages($pages, $aiInput, [
            'site_family' => 'ecommerce',
        ]);

        $this->assertSame(CmsAiLearnedRuleApplicationService::GENERATION_VERSION, data_get($result, 'diagnostics.generation_version'));
        $this->assertSame(3, (int) data_get($result, 'diagnostics.eligible_rules')); // foreign-site rule excluded
        $this->assertSame(3, (int) data_get($result, 'diagnostics.matched_rules'));
        $this->assertSame(2, (int) data_get($result, 'diagnostics.applied_rule_count'));
        $this->assertSame(1, (int) data_get($result, 'diagnostics.conflicts_skipped'));
        $this->assertGreaterThanOrEqual(2, (int) data_get($result, 'diagnostics.nodes_touched'));
        $this->assertSame('p6-g3-02.v1', data_get($result, 'diagnostics.versioning.versioning_version'));
        $this->assertSame('sha256', data_get($result, 'diagnostics.versioning.fingerprint_algorithm'));
        $this->assertSame('confidence_desc_sample_size_desc_id_asc.v1', data_get($result, 'diagnostics.versioning.selection_order_version'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'diagnostics.versioning.eligible_rule_set_version'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'diagnostics.versioning.matched_rule_set_version'));
        $this->assertStringStartsWith('sha256:', (string) data_get($result, 'diagnostics.versioning.applied_rule_set_version'));

        $applied = (array) data_get($result, 'diagnostics.applied_rules', []);
        $this->assertCount(2, $applied);
        $this->assertSame('lr_products_grid_columns_3_high', data_get($applied, '0.rule_key'));
        $this->assertSame('lr_button_radius_pill', data_get($applied, '1.rule_key'));

        $skipped = collect((array) data_get($result, 'diagnostics.skipped_rules', []));
        $this->assertSame('conflict_with_higher_priority_rule', $skipped->firstWhere('rule_key', 'lr_products_grid_columns_4_low')['reason'] ?? null);

        $updatedPages = (array) ($result['pages'] ?? []);
        $gridNode = $this->findFirstNodeByType($updatedPages[0]['builder_nodes'] ?? [], 'products-grid');
        $buttonNode = $this->findFirstNodeByType($updatedPages[0]['builder_nodes'] ?? [], 'button');
        $this->assertSame(3, data_get($gridNode, 'props.style.columns'));
        $this->assertSame(999, data_get($buttonNode, 'props.style.border_radius'));

        $resultRepeat = app(CmsAiLearnedRuleApplicationService::class)->applyToGeneratedPages($pages, $aiInput, [
            'site_family' => 'ecommerce',
        ]);
        $this->assertSame(
            data_get($result, 'diagnostics.versioning.eligible_rule_set_version'),
            data_get($resultRepeat, 'diagnostics.versioning.eligible_rule_set_version')
        );
        $this->assertSame(
            data_get($result, 'diagnostics.versioning.applied_rule_set_version'),
            data_get($resultRepeat, 'diagnostics.versioning.applied_rule_set_version')
        );
    }

    public function test_it_skips_when_prompt_tags_or_store_type_do_not_match(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-1',
            'site_id' => 'site-1',
            'rule_key' => 'lr_non_matching_prompt',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
                'prompt_intent_tags' => ['luxury'],
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 3,
            ],
        ]);

        $pages = [[
            'slug' => 'shop',
            'template_key' => 'shop',
            'builder_nodes' => [[
                'type' => 'products-grid',
                'props' => [
                    'content' => [],
                    'data' => [],
                    'style' => ['columns' => 4],
                    'advanced' => [],
                    'responsive' => [],
                    'states' => [],
                ],
                'bindings' => [],
                'meta' => ['schema_version' => 1],
            ]],
        ]];

        $result = app(CmsAiLearnedRuleApplicationService::class)->applyToGeneratedPages($pages, [
            'request' => ['prompt' => 'simple ecommerce storefront'],
            'platform_context' => ['site' => ['id' => 'site-1'], 'project' => ['id' => 'project-1']],
        ], [
            'site_family' => 'ecommerce',
        ]);

        $this->assertSame(1, (int) data_get($result, 'diagnostics.eligible_rules'));
        $this->assertSame(0, (int) data_get($result, 'diagnostics.matched_rules'));
        $this->assertSame(0, (int) data_get($result, 'diagnostics.applied_rule_count'));
        $this->assertSame(4, data_get($result, 'pages.0.builder_nodes.0.props.style.columns'));
        $this->assertSame('prompt_intent_tags_mismatch', data_get($result, 'diagnostics.skipped_rules.0.reason'));
    }

    public function test_it_enforces_privacy_policy_tenant_opt_out_and_global_rule_disable_paths(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'global',
            'project_id' => null,
            'site_id' => null,
            'rule_key' => 'lr_global_products_grid_columns_2',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 2,
            ],
            'confidence' => 0.8,
            'sample_size' => 30,
            'delta_count' => 30,
        ]);

        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-1',
            'site_id' => 'site-1',
            'rule_key' => 'lr_tenant_products_grid_columns_3',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'strategy' => 'component_type_path_suffix',
                'op' => 'replace',
                'component_type' => 'webu_product_grid_01',
                'path_suffix' => '/props/style/columns',
                'value' => 3,
            ],
            'confidence' => 0.7,
            'sample_size' => 20,
            'delta_count' => 20,
        ]);

        $pages = [[
            'slug' => 'shop',
            'template_key' => 'shop',
            'builder_nodes' => [[
                'type' => 'products-grid',
                'props' => [
                    'content' => [],
                    'data' => [],
                    'style' => ['columns' => 4],
                    'advanced' => [],
                    'responsive' => [],
                    'states' => [],
                ],
                'bindings' => [],
                'meta' => ['schema_version' => 1],
            ]],
        ]];

        $aiInput = [
            'request' => ['prompt' => 'ecommerce storefront'],
            'platform_context' => ['site' => ['id' => 'site-1'], 'project' => ['id' => 'project-1']],
        ];

        $globalDisabled = app(CmsAiLearnedRuleApplicationService::class)->applyToGeneratedPages($pages, $aiInput, [
            'site_family' => 'ecommerce',
            'privacy_policy' => [
                'effective' => [
                    'tenant_opt_out' => false,
                    'apply_learned_rules' => true,
                    'allow_global_learned_rules' => false,
                ],
                'diagnostics' => [
                    'status' => 'global_rules_limited',
                    'reasons' => ['global_learned_rules_disabled'],
                ],
            ],
        ]);

        $this->assertSame(1, (int) data_get($globalDisabled, 'diagnostics.eligible_rules'));
        $this->assertSame(3, data_get($globalDisabled, 'pages.0.builder_nodes.0.props.style.columns'));
        $this->assertFalse((bool) data_get($globalDisabled, 'diagnostics.privacy_enforcement.allow_global_learned_rules'));
        $this->assertSame('global_rules_limited', data_get($globalDisabled, 'diagnostics.privacy_enforcement.policy.status'));

        $tenantOptOut = app(CmsAiLearnedRuleApplicationService::class)->applyToGeneratedPages($pages, $aiInput, [
            'site_family' => 'ecommerce',
            'privacy_policy' => [
                'effective' => [
                    'tenant_opt_out' => true,
                    'apply_learned_rules' => false,
                    'allow_global_learned_rules' => false,
                ],
                'diagnostics' => [
                    'status' => 'tenant_opt_out',
                    'reasons' => ['tenant_opt_out'],
                ],
            ],
        ]);

        $this->assertFalse((bool) data_get($tenantOptOut, 'diagnostics.enabled'));
        $this->assertSame('tenant_opt_out', data_get($tenantOptOut, 'diagnostics.rule_fetch.reason'));
        $this->assertSame(4, data_get($tenantOptOut, 'pages.0.builder_nodes.0.props.style.columns'));
        $this->assertFalse((bool) data_get($tenantOptOut, 'diagnostics.privacy_enforcement.apply_learned_rules'));
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
}
