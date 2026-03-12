<?php

namespace Tests\Feature\Cms;

use App\Models\CmsLearnedRule;
use App\Models\SystemSetting;
use App\Services\CmsAiLearningPrivacyPolicyService;
use App\Services\CmsAiPageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAiGenerationLearningAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('theme-presets', [
            'default' => ['name' => 'Default'],
            'arctic' => ['name' => 'Arctic'],
            'ocean' => ['name' => 'Ocean'],
            'summer' => ['name' => 'Summer'],
        ]);

        SystemSetting::set(CmsAiLearningPrivacyPolicyService::FLAG_GENERATION_LEARNING_ENABLED, true, 'boolean', 'privacy');
        SystemSetting::set(CmsAiLearningPrivacyPolicyService::FLAG_ALLOW_GLOBAL_LEARNED_RULES, true, 'boolean', 'privacy');
        SystemSetting::set(CmsAiLearningPrivacyPolicyService::FLAG_REPRODUCIBILITY_ENABLED, true, 'boolean', 'privacy');
    }

    public function test_acceptance_no_cross_tenant_leakage_and_deterministic_replay_for_learning_enhanced_generation(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-a',
            'site_id' => 'site-a',
            'rule_key' => 'lr_site_a_shop_grid_columns_2',
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
                'value' => 2,
            ],
            'confidence' => 0.81,
            'sample_size' => 10,
            'delta_count' => 10,
        ]);

        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-b',
            'site_id' => 'site-b',
            'rule_key' => 'lr_site_b_shop_grid_columns_6',
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
                'value' => 6,
            ],
            'confidence' => 0.99,
            'sample_size' => 99,
            'delta_count' => 99,
        ]);

        $service = app(CmsAiPageGenerationService::class);

        $tenantAInput = $this->validInputPayload('project-a', 'site-a', [
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate an ecommerce storefront with catalog and checkout.',
                'constraints' => ['allow_ecommerce' => true],
            ],
        ]);
        $tenantBInput = $this->validInputPayload('project-b', 'site-b', [
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate an ecommerce storefront with catalog and checkout.',
                'constraints' => ['allow_ecommerce' => true],
            ],
        ]);

        $tenantAFirst = $service->generatePagesFragment($tenantAInput);
        $tenantAReplay = $service->generatePagesFragment($tenantAInput);
        $tenantBFirst = $service->generatePagesFragment($tenantBInput);

        $this->assertTrue($tenantAFirst['valid'], json_encode($tenantAFirst, JSON_PRETTY_PRINT));
        $this->assertTrue($tenantAReplay['valid'], json_encode($tenantAReplay, JSON_PRETTY_PRINT));
        $this->assertTrue($tenantBFirst['valid'], json_encode($tenantBFirst, JSON_PRETTY_PRINT));

        $this->assertSame(1, (int) data_get($tenantAFirst, 'page_plan.learned_rules_application.eligible_rules'));
        $this->assertSame(1, (int) data_get($tenantBFirst, 'page_plan.learned_rules_application.eligible_rules'));
        $this->assertSame('site-a', data_get($tenantAFirst, 'page_plan.learned_rules_application.site_id'));
        $this->assertSame('site-b', data_get($tenantBFirst, 'page_plan.learned_rules_application.site_id'));

        $this->assertSame('lr_site_a_shop_grid_columns_2', data_get($tenantAFirst, 'page_plan.applied_rules.0.rule_key'));
        $this->assertSame('lr_site_b_shop_grid_columns_6', data_get($tenantBFirst, 'page_plan.applied_rules.0.rule_key'));
        $this->assertNotEquals(
            data_get($tenantAFirst, 'page_plan.applied_rules.0.rule_key'),
            data_get($tenantBFirst, 'page_plan.applied_rules.0.rule_key')
        );

        $tenantAShop = $this->findPageBySlug((array) $tenantAFirst['pages'], 'shop');
        $tenantBShop = $this->findPageBySlug((array) $tenantBFirst['pages'], 'shop');
        $tenantAGrid = $this->findFirstNodeByType((array) ($tenantAShop['builder_nodes'] ?? []), 'products-grid');
        $tenantBGrid = $this->findFirstNodeByType((array) ($tenantBShop['builder_nodes'] ?? []), 'products-grid');

        $this->assertNotNull($tenantAGrid);
        $this->assertNotNull($tenantBGrid);
        $this->assertSame(2, data_get($tenantAGrid, 'props.style.columns'));
        $this->assertSame(6, data_get($tenantBGrid, 'props.style.columns'));

        $this->assertTrue((bool) data_get($tenantAFirst, 'page_plan.reproducibility.enabled'));
        $this->assertTrue((bool) data_get($tenantBFirst, 'page_plan.reproducibility.enabled'));
        $this->assertSame(
            data_get($tenantAFirst, 'page_plan.reproducibility.output_fingerprint'),
            data_get($tenantAReplay, 'page_plan.reproducibility.output_fingerprint')
        );
        $this->assertSame(
            data_get($tenantAFirst, 'page_plan.reproducibility.replay_key'),
            data_get($tenantAReplay, 'page_plan.reproducibility.replay_key')
        );
        $this->assertSame(
            data_get($tenantAFirst, 'page_plan.learned_rules_application.versioning.applied_rule_set_version'),
            data_get($tenantAReplay, 'page_plan.learned_rules_application.versioning.applied_rule_set_version')
        );

        $this->assertNotSame(
            data_get($tenantAFirst, 'page_plan.reproducibility.replay_key'),
            data_get($tenantBFirst, 'page_plan.reproducibility.replay_key')
        );
        $this->assertNotSame(
            data_get($tenantAFirst, 'page_plan.reproducibility.output_fingerprint'),
            data_get($tenantBFirst, 'page_plan.reproducibility.output_fingerprint')
        );
    }

    public function test_acceptance_tenant_opt_out_disables_learning_application_and_replay_metadata(): void
    {
        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => 'project-optout',
            'site_id' => 'site-optout',
            'rule_key' => 'lr_site_optout_shop_grid_columns_3',
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
            'confidence' => 0.8,
            'sample_size' => 12,
            'delta_count' => 12,
        ]);

        $result = app(CmsAiPageGenerationService::class)->generatePagesFragment($this->validInputPayload('project-optout', 'site-optout', [
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate an ecommerce storefront.',
                'constraints' => ['allow_ecommerce' => true],
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
            ],
        ]));

        $this->assertTrue($result['valid'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.learning_privacy.status'));
        $this->assertFalse((bool) data_get($result, 'page_plan.learned_rules_application.enabled'));
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.learned_rules_application.rule_fetch.reason'));
        $this->assertFalse((bool) data_get($result, 'page_plan.reproducibility.enabled'));
        $this->assertSame('tenant_opt_out', data_get($result, 'page_plan.reproducibility.reason'));

        $shopPage = $this->findPageBySlug((array) ($result['pages'] ?? []), 'shop');
        $gridNode = $this->findFirstNodeByType((array) ($shopPage['builder_nodes'] ?? []), 'products-grid');
        $this->assertNull(data_get($gridNode, 'props.style.columns')); // learned patch not applied
        $this->assertSame(4, data_get($gridNode, 'props.style.grid.columns.desktop')); // placement baseline still applies
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
    private function validInputPayload(string $projectId, string $siteId, array $overrides = []): array
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
                'project' => ['id' => $projectId, 'name' => 'Demo Project'],
                'site' => [
                    'id' => $siteId,
                    'name' => 'Demo Site',
                    'status' => 'draft',
                    'locale' => 'en',
                    'theme_settings' => ['preset' => 'default'],
                ],
                'template_blueprint' => [
                    'template_id' => 1,
                    'template_slug' => 'webu-shop-01',
                    'default_pages' => [],
                    'default_sections' => [],
                ],
                'site_settings_snapshot' => [
                    'site' => [
                        'id' => $siteId,
                        'project_id' => $projectId,
                        'name' => 'Demo Site',
                        'status' => 'draft',
                        'locale' => 'en',
                        'theme_settings' => ['preset' => 'default'],
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
                    'site_id' => $siteId,
                    'project_id' => $projectId,
                    'modules' => [[
                        'key' => 'ecommerce',
                        'enabled' => true,
                        'available' => true,
                    ]],
                    'summary' => [
                        'total' => 1,
                        'available' => 1,
                        'disabled' => 0,
                        'not_entitled' => 0,
                    ],
                ],
                'module_entitlements' => [
                    'site_id' => $siteId,
                    'project_id' => $projectId,
                    'features' => [],
                    'modules' => ['ecommerce' => true],
                    'reasons' => [],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req-g3-acceptance-'.$siteId,
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
