<?php

namespace Tests\Unit;

use App\Models\CmsExperiment;
use App\Models\CmsExperimentAssignment;
use App\Models\CmsExperimentVariant;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsExperimentAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsExperimentAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_stably_for_the_same_session(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $experiment = $this->createExperiment($site, [
            'key' => 'header-layout-ab',
            'status' => 'active',
            'traffic_percent' => 100,
        ]);
        $this->createVariants($experiment);

        $service = app(CmsExperimentAssignmentService::class);
        $requestA = $this->makeRequest('sess_abc_123', null, '127.0.0.1');
        $requestB = $this->makeRequest('sess_abc_123', null, '127.0.0.1');

        $first = $service->assignForExperiment($site, $experiment, $requestA, [
            'source' => 'runtime',
            'route' => ['path' => '/shop', 'slug' => 'shop'],
        ]);
        $second = $service->assignForExperiment($site, $experiment, $requestB, [
            'source' => 'runtime',
            'route' => ['path' => '/shop', 'slug' => 'shop'],
        ]);

        $this->assertTrue($first['assigned']);
        $this->assertFalse($first['reused']);
        $this->assertSame((string) $site->id, $first['site_id']);
        $this->assertSame((string) $project->id, $first['project_id']);
        $this->assertContains($first['variant_key'], ['control', 'layout_b']);
        $this->assertSame('session', $first['basis']);
        $this->assertNotEmpty($first['session_id_hash']);
        $this->assertNull($first['device_id_hash']);
        $this->assertSame(64, strlen((string) $first['subject_hash']));
        $this->assertSame('deterministic_weighted_hash_v1', data_get($first, 'meta.strategy'));
        $this->assertSame(200, (int) data_get($first, 'meta.total_weight'));
        $this->assertSame(100, (int) data_get($first, 'meta.traffic_percent'));

        $this->assertTrue($second['assigned']);
        $this->assertTrue($second['reused']);
        $this->assertSame($first['variant_key'], $second['variant_key']);
        $this->assertSame($first['subject_hash'], $second['subject_hash']);
        $this->assertSame(1, CmsExperimentAssignment::query()->count());

        $row = CmsExperimentAssignment::query()->firstOrFail();
        $this->assertSame((string) $site->id, $row->site_id);
        $this->assertSame((string) $project->id, $row->project_id);
        $this->assertSame($first['variant_key'], $row->variant_key);
        $this->assertSame('session', $row->assignment_basis);
        $this->assertSame($first['session_id_hash'], $row->session_id_hash);
    }

    public function test_it_falls_back_to_device_assignment_when_session_is_missing(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $experiment = $this->createExperiment($site, [
            'key' => 'cta-copy-ab',
            'status' => 'active',
        ]);
        $this->createVariants($experiment, [
            ['variant_key' => 'control', 'weight' => 100],
            ['variant_key' => 'cta_alt', 'weight' => 50],
        ]);

        $service = app(CmsExperimentAssignmentService::class);
        $requestA = $this->makeRequest(null, 'Mozilla/5.0 (Macintosh)', '10.0.0.2');
        $requestB = $this->makeRequest(null, 'Mozilla/5.0 (Macintosh)', '10.0.0.2');

        $first = $service->assignForExperiment($site, $experiment, $requestA, ['source' => 'runtime']);
        $second = $service->assignForExperiment($site, $experiment, $requestB, ['source' => 'runtime']);

        $this->assertTrue($first['assigned']);
        $this->assertSame('device', $first['basis']);
        $this->assertNull($first['session_id_hash']);
        $this->assertNotEmpty($first['device_id_hash']);
        $this->assertTrue($second['reused']);
        $this->assertSame($first['variant_key'], $second['variant_key']);
        $this->assertSame(1, CmsExperimentAssignment::query()->count());
    }

    public function test_it_respects_status_time_window_and_traffic_rules_for_new_assignments(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $service = app(CmsExperimentAssignmentService::class);

        $futureExperiment = $this->createExperiment($site, [
            'key' => 'future-exp',
            'status' => 'active',
            'starts_at' => now()->addHour(),
        ]);
        $this->createVariants($futureExperiment);
        $futureResult = $service->assignForExperiment($site, $futureExperiment, $this->makeRequest('sess_future'));

        $this->assertFalse($futureResult['assigned']);
        $this->assertSame('experiment_not_started', data_get($futureResult, 'reason.code'));

        $trafficExperiment = $this->createExperiment($site, [
            'key' => 'zero-traffic',
            'status' => 'active',
            'traffic_percent' => 0,
        ]);
        $this->createVariants($trafficExperiment);
        $trafficResult = $service->assignForExperiment($site, $trafficExperiment, $this->makeRequest('sess_zero'));

        $this->assertFalse($trafficResult['assigned']);
        $this->assertSame('outside_traffic_allocation', data_get($trafficResult, 'reason.code'));
        $this->assertSame(0, (int) data_get($trafficResult, 'meta.traffic_percent'));
        $this->assertSame(0, CmsExperimentAssignment::query()->where('experiment_id', $trafficExperiment->id)->count());
    }

    public function test_it_can_assign_active_experiments_batch_for_request(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $active = $this->createExperiment($site, ['key' => 'header-layout', 'status' => 'active']);
        $future = $this->createExperiment($site, ['key' => 'future-layout', 'status' => 'active', 'starts_at' => now()->addDay()]);
        $draft = $this->createExperiment($site, ['key' => 'draft-layout', 'status' => 'draft']);
        $this->createVariants($active);
        $this->createVariants($future);
        $this->createVariants($draft);

        $result = app(CmsExperimentAssignmentService::class)->assignActiveExperimentsForRequest(
            $site,
            $this->makeRequest('sess_batch'),
            ['source' => 'runtime', 'route' => ['path' => '/', 'slug' => 'home']]
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['evaluated']);
        $this->assertSame(1, $result['assigned']);
        $this->assertCount(1, $result['assignments']);
        $this->assertSame('header-layout', data_get($result, 'assignments.0.experiment_key'));
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('future-layout', data_get($result, 'skipped.0.experiment_key'));
        $this->assertSame('experiment_not_started', data_get($result, 'skipped.0.reason.code'));
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExperiment(Site $site, array $overrides = []): CmsExperiment
    {
        return CmsExperiment::query()->create(array_merge([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'key' => 'exp_'.Str::lower(Str::random(8)),
            'name' => 'Experiment '.Str::random(4),
            'status' => 'draft',
            'assignment_unit' => 'session_or_device',
            'traffic_percent' => 100,
        ], $overrides));
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function createVariants(CmsExperiment $experiment, ?array $rows = null): void
    {
        $rows ??= [
            ['variant_key' => 'control', 'weight' => 100, 'sort_order' => 0],
            ['variant_key' => 'layout_b', 'weight' => 100, 'sort_order' => 1],
        ];

        foreach ($rows as $row) {
            CmsExperimentVariant::query()->create(array_merge([
                'experiment_id' => $experiment->id,
                'status' => 'active',
                'weight' => 100,
                'sort_order' => 0,
                'payload_json' => [
                    'theme_patch' => ['tokens' => []],
                    'page_patch' => ['sections' => []],
                ],
            ], $row));
        }
    }

    private function makeRequest(?string $sessionId, ?string $userAgent = null, string $ip = '127.0.0.1'): Request
    {
        $server = [
            'REMOTE_ADDR' => $ip,
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_USER_AGENT' => $userAgent ?: 'PHPUnitBrowser/1.0',
        ];

        $request = Request::create('/shop', 'GET', [], [], [], $server);
        if ($sessionId !== null) {
            $request->headers->set('X-Cms-Session-Id', $sessionId);
        }

        return $request;
    }
}
