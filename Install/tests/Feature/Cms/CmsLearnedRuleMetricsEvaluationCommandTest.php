<?php

namespace Tests\Feature\Cms;

use App\Models\CmsLearnedRule;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsLearnedRuleMetricsEvaluationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cms_learned_rules_evaluate_metrics_command_promotes_candidate_rule(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        CmsLearnedRule::query()->create([
            'scope' => 'tenant',
            'project_id' => (string) $site->project_id,
            'site_id' => (string) $site->id,
            'rule_key' => 'lr_cmd_promote',
            'status' => 'candidate',
            'active' => false,
            'source' => 'builder_delta_cluster',
            'conditions_json' => ['component_type' => 'webu_product_grid_01'],
            'patch_json' => ['format' => 'json_patch_template'],
            'evidence_json' => [
                'metric_observation' => [
                    'metric' => 'conversion_rate',
                    'before' => 0.10,
                    'after' => 0.125,
                    'before_samples' => 150,
                    'after_samples' => 170,
                ],
            ],
            'confidence' => 0.8,
            'sample_size' => 10,
            'delta_count' => 10,
        ]);

        $this->artisan('cms:learned-rules-evaluate-metrics', [
            '--site' => (string) $site->id,
            '--promote-uplift' => 0.02,
            '--min-before-samples' => 100,
            '--min-after-samples' => 100,
        ])
            ->expectsOutputToContain('Evaluated learned rule metric thresholds')
            ->assertSuccessful();

        $rule = CmsLearnedRule::query()->firstOrFail();
        $this->assertSame('active', $rule->status);
        $this->assertTrue((bool) $rule->active);
        $this->assertNotNull($rule->promoted_at);
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
