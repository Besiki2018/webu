<?php

namespace Tests\Feature\Cms;

use App\Models\CmsExperiment;
use App\Models\CmsExperimentAssignment;
use App\Models\CmsExperimentVariant;
use App\Models\CmsLearnedRule;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsLearningAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_site_owner_can_inspect_and_disable_learned_rules_and_experiments(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $rule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_test_rule',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => [
                'store_type' => 'ecommerce',
                'component_type' => 'webu_product_grid_01',
                'prompt_intent_tags' => ['dark', 'luxury'],
            ],
            'patch_json' => [
                'format' => 'json_patch_template',
                'op' => 'replace',
                'path_pattern' => '/sections/*/props/style/columns',
                'value' => 3,
            ],
            'evidence_json' => [
                'metric_evaluation' => [
                    'last_result' => [
                        'decision' => 'promoted',
                        'metric' => 'conversion_rate',
                        'uplift' => 0.031,
                    ],
                ],
            ],
            'confidence' => 0.82,
            'sample_size' => 14,
            'delta_count' => 14,
            'promoted_at' => now()->subDays(2),
        ]);

        $experiment = CmsExperiment::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $project->id,
            'key' => 'header-layout-ab',
            'name' => 'Header Layout AB',
            'status' => 'active',
            'assignment_unit' => 'session_or_device',
            'traffic_percent' => 75,
            'starts_at' => now()->subDay(),
        ]);
        $control = CmsExperimentVariant::query()->create([
            'experiment_id' => $experiment->id,
            'variant_key' => 'control',
            'status' => 'active',
            'weight' => 100,
            'sort_order' => 0,
            'payload_json' => ['theme_patch' => [], 'page_patch' => []],
        ]);
        $variantB = CmsExperimentVariant::query()->create([
            'experiment_id' => $experiment->id,
            'variant_key' => 'layout_b',
            'status' => 'active',
            'weight' => 100,
            'sort_order' => 1,
            'payload_json' => ['theme_patch' => ['header' => 'layout_b'], 'page_patch' => []],
        ]);
        CmsExperimentAssignment::query()->create([
            'experiment_id' => $experiment->id,
            'site_id' => (string) $site->id,
            'project_id' => (string) $project->id,
            'variant_key' => 'control',
            'assignment_basis' => 'session',
            'subject_hash' => str_repeat('a', 64),
            'session_id_hash' => str_repeat('a', 64),
            'assigned_at' => now()->subHour(),
        ]);
        CmsExperimentAssignment::query()->create([
            'experiment_id' => $experiment->id,
            'site_id' => (string) $site->id,
            'project_id' => (string) $project->id,
            'variant_key' => 'layout_b',
            'assignment_basis' => 'device',
            'subject_hash' => str_repeat('b', 64),
            'device_id_hash' => str_repeat('b', 64),
            'assigned_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.cms.learning.rules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('rules.0.id', $rule->id)
            ->assertJsonPath('rules.0.status', 'active')
            ->assertJsonPath('rules.0.active', true)
            ->assertJsonPath('rules.0.component_type', 'webu_product_grid_01')
            ->assertJsonPath('rules.0.last_metric_evaluation.metric', 'conversion_rate');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.cms.learning.rules.show', ['site' => $site->id, 'rule' => $rule->id]))
            ->assertOk()
            ->assertJsonPath('rule.id', $rule->id)
            ->assertJsonPath('rule.conditions_json.component_type', 'webu_product_grid_01')
            ->assertJsonPath('rule.patch_json.path_pattern', '/sections/*/props/style/columns');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.cms.learning.rules.disable', ['site' => $site->id, 'rule' => $rule->id]), [
                'reason' => 'Manual review disabled this rule',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rule.id', $rule->id)
            ->assertJsonPath('rule.status', 'disabled')
            ->assertJsonPath('rule.active', false)
            ->assertJsonPath('rule.evidence_json.admin_control.last_disable.reason', 'Manual review disabled this rule');

        $rule->refresh();
        $this->assertSame('disabled', $rule->status);
        $this->assertFalse((bool) $rule->active);
        $this->assertNotNull($rule->disabled_at);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.cms.learning.experiments.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('experiments.0.id', $experiment->id)
            ->assertJsonPath('experiments.0.status', 'active')
            ->assertJsonPath('experiments.0.variants_count', 2)
            ->assertJsonPath('experiments.0.active_variants_count', 2)
            ->assertJsonPath('experiments.0.assignments_count', 2);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.cms.learning.experiments.show', ['site' => $site->id, 'experiment' => $experiment->id]))
            ->assertOk()
            ->assertJsonPath('experiment.id', $experiment->id)
            ->assertJsonPath('experiment.variants.0.variant_key', $control->variant_key)
            ->assertJsonPath('experiment.variants.1.variant_key', $variantB->variant_key)
            ->assertJsonPath('experiment.variants.0.assignment_count', 1)
            ->assertJsonPath('experiment.variants.1.assignment_count', 1);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.cms.learning.experiments.disable', ['site' => $site->id, 'experiment' => $experiment->id]), [
                'reason' => 'Pause experiment due to review',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('experiment.id', $experiment->id)
            ->assertJsonPath('experiment.status', 'paused')
            ->assertJsonPath('experiment.admin_control.reason', 'Pause experiment due to review');

        $experiment->refresh();
        $this->assertSame('paused', $experiment->status);
        $this->assertNotNull($experiment->ends_at);
    }

    public function test_learning_admin_endpoints_are_forbidden_for_other_tenant_user(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        $intruder = User::factory()->withPlan(Plan::factory()->create())->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $rule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_forbidden',
            'status' => 'candidate',
            'active' => false,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_button_01'],
            'patch_json' => ['format' => 'json_patch_template'],
        ]);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.cms.learning.rules.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->putJson(route('panel.sites.cms.learning.rules.disable', ['site' => $site->id, 'rule' => $rule->id]), [
                'reason' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_learning_admin_endpoints_return_standardized_scope_mismatch_payload_for_cross_site_rule_binding(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        [$projectA, $siteA] = $this->createPublishedProjectWithSite($owner, 'public');
        [$projectB, $siteB] = $this->createPublishedProjectWithSite($owner, 'public');

        $foreignRule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $projectB->id,
            'site_id' => (string) $siteB->id,
            'rule_key' => 'lr_foreign',
            'status' => 'candidate',
            'active' => false,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_card_01'],
            'patch_json' => ['format' => 'json_patch_template'],
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.cms.learning.rules.show', [
                'site' => $siteA->id,
                'rule' => $foreignRule->id,
            ]))
            ->assertNotFound()
            ->assertJsonPath('code', 'tenant_scope_route_binding_mismatch')
            ->assertJsonPath('violations.0.code', 'route_model_site_scope_mismatch')
            ->assertJsonPath('violations.0.path', '$.route.rule.site_id')
            ->assertJsonPath('violations.0.expected', $siteA->id)
            ->assertJsonPath('violations.0.actual', $siteB->id);
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner, string $visibility): array
    {
        $factory = Project::factory()->for($owner);
        $subdomain = strtolower(Str::random(10));

        $factory = $visibility === 'private'
            ? $factory->privatePublished($subdomain)
            : $factory->published($subdomain);

        $project = $factory->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
