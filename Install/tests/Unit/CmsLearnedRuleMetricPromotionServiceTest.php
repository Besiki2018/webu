<?php

namespace Tests\Unit;

use App\Models\CmsLearnedRule;
use App\Models\CmsTelemetryDailyAggregate;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsLearnedRuleMetricPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsLearnedRuleMetricPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_candidate_rule_when_metric_uplift_and_sample_thresholds_are_met(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        $rule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $site->project_id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_candidate_promote',
            'status' => 'candidate',
            'active' => false,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_product_grid_01'],
            'patch_json' => ['format' => 'json_patch_template'],
            'evidence_json' => [
                'metric_observation' => [
                    'metric' => 'conversion_rate',
                    'before' => 0.10,
                    'after' => 0.135,
                    'before_samples' => 300,
                    'after_samples' => 320,
                    'meta' => ['source' => 'ab_experiment_baseline'],
                ],
            ],
            'confidence' => 0.7,
            'sample_size' => 12,
            'delta_count' => 12,
        ]);

        $clock = Carbon::parse('2026-02-24 21:00:00');
        $result = app(CmsLearnedRuleMetricPromotionService::class)->evaluateRules($site, $clock, [
            'min_before_samples' => 100,
            'min_after_samples' => 100,
            'min_promotion_uplift' => 0.02,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['evaluated_rules']);
        $this->assertSame(1, $result['promoted']);
        $this->assertSame(0, $result['rolled_back']);
        $this->assertSame('p6-learning-targets.v1', data_get($result, 'learning_targets.catalog_version'));
        $this->assertSame(
            ['conversion_rate', 'builder_publish_per_open_rate', 'runtime_hydrate_success_rate'],
            data_get($result, 'learning_targets.rule_promotion_metric_priority')
        );
        $this->assertContains('builder_friction', (array) data_get($result, 'learning_targets.catalog_keys', []));

        $rule->refresh();
        $this->assertSame('active', $rule->status);
        $this->assertTrue((bool) $rule->active);
        $this->assertNotNull($rule->promoted_at);
        $this->assertSame('promoted', data_get($rule->evidence_json, 'metric_evaluation.last_result.decision'));
        $this->assertSame('conversion_rate', data_get($rule->evidence_json, 'metric_evaluation.last_result.metric'));
        $this->assertSame(0.035, (float) data_get($rule->evidence_json, 'metric_evaluation.last_result.uplift'));
    }

    public function test_it_rolls_back_active_rule_when_post_promotion_conversion_rate_drops_below_threshold(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $promotedAt = Carbon::parse('2026-02-15 09:00:00');

        $rule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $site->project_id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_active_rollback',
            'status' => 'active',
            'active' => true,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_button_01'],
            'patch_json' => ['format' => 'json_patch_template'],
            'evidence_json' => [],
            'confidence' => 0.9,
            'sample_size' => 25,
            'delta_count' => 25,
            'promoted_at' => $promotedAt,
        ]);

        foreach ([
            ['date' => '2026-02-13', 'conversion_rate' => 0.120, 'samples' => 200],
            ['date' => '2026-02-14', 'conversion_rate' => 0.125, 'samples' => 180],
            ['date' => '2026-02-15', 'conversion_rate' => 0.070, 'samples' => 220],
            ['date' => '2026-02-16', 'conversion_rate' => 0.065, 'samples' => 210],
        ] as $row) {
            CmsTelemetryDailyAggregate::query()->create([
                'metric_date' => $row['date'],
                'site_id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'total_events' => 100,
                'runtime_events' => 100,
                'runtime_route_hydrated_count' => $row['samples'],
                'unique_sessions_total' => $row['samples'],
                'unique_sessions_runtime' => $row['samples'],
                'metrics_json' => [
                    'derived_rates' => [
                        'conversion_rate' => $row['conversion_rate'],
                    ],
                    'derived_rates_samples' => [
                        'conversion_rate' => $row['samples'],
                    ],
                ],
                'generated_at' => now(),
            ]);
        }

        $clock = Carbon::parse('2026-02-24 21:30:00');
        $result = app(CmsLearnedRuleMetricPromotionService::class)->evaluateRules($site, $clock, [
            'min_before_samples' => 100,
            'min_after_samples' => 100,
            'rollback_drop_threshold' => 0.02,
            'before_days' => 2,
            'after_days' => 2,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['evaluated_rules']);
        $this->assertSame(0, $result['promoted']);
        $this->assertSame(1, $result['rolled_back']);

        $rule->refresh();
        $this->assertSame('rolled_back', $rule->status);
        $this->assertFalse((bool) $rule->active);
        $this->assertNotNull($rule->disabled_at);
        $this->assertSame('rolled_back', data_get($rule->evidence_json, 'metric_evaluation.last_result.decision'));
        $this->assertSame('conversion_rate', data_get($rule->evidence_json, 'metric_evaluation.last_result.metric'));
        $this->assertLessThan(-0.02, (float) data_get($rule->evidence_json, 'metric_evaluation.last_result.uplift'));
        $this->assertSame('telemetry_daily_aggregates', data_get($rule->evidence_json, 'metric_evaluation.last_result.source'));
    }

    public function test_it_keeps_candidate_when_samples_are_below_threshold(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        $rule = CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $site->project_id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_candidate_small_samples',
            'status' => 'candidate',
            'active' => false,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_card_01'],
            'patch_json' => ['format' => 'json_patch_template'],
            'evidence_json' => [
                'metric_observation' => [
                    'metric' => 'conversion_rate',
                    'before' => 0.10,
                    'after' => 0.20,
                    'before_samples' => 5,
                    'after_samples' => 6,
                ],
            ],
        ]);

        $result = app(CmsLearnedRuleMetricPromotionService::class)->evaluateRules($site, now(), [
            'min_before_samples' => 50,
            'min_after_samples' => 50,
            'min_promotion_uplift' => 0.01,
        ]);

        $this->assertSame(0, $result['promoted']);
        $this->assertSame(1, $result['unchanged']);

        $rule->refresh();
        $this->assertSame('candidate', $rule->status);
        $this->assertFalse((bool) $rule->active);
        $this->assertSame('before_sample_threshold_not_met', data_get($rule->evidence_json, 'metric_evaluation.last_result.reason'));
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(10)))->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
