<?php

namespace Tests\Feature\Cms;

use App\Models\CmsTelemetryDailyAggregate;
use App\Models\CmsTelemetryEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTelemetryAggregateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cms_telemetry_aggregate_command_builds_daily_metrics_rows(): void
    {
        $targetDate = Carbon::parse('2026-02-24 12:00:00');
        [, $site] = $this->createPublishedProjectWithSite();

        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'panel',
            'source' => 'builder',
            'event_name' => 'cms_builder.open',
            'session_hash' => str_repeat('1', 64),
            'actor_scope' => 'authenticated',
            'anonymized_at' => now(),
            'retention_expires_at' => now()->addDays(30),
            'occurred_at' => $targetDate->copy()->setTime(9, 0),
        ]);
        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'public',
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'session_hash' => str_repeat('2', 64),
            'page_slug' => 'home',
            'route_slug' => 'home',
            'actor_scope' => 'guest',
            'anonymized_at' => now(),
            'retention_expires_at' => now()->addDays(30),
            'occurred_at' => $targetDate->copy()->setTime(9, 5),
        ]);

        $this->artisan('cms:telemetry-aggregate', ['--date' => '2026-02-24'])
            ->expectsOutputToContain('Aggregated CMS telemetry metrics for 2026-02-24')
            ->assertSuccessful();

        /** @var CmsTelemetryDailyAggregate $row */
        $row = CmsTelemetryDailyAggregate::query()->firstOrFail();
        $this->assertSame('2026-02-24', $row->metric_date?->toDateString());
        $this->assertSame(2, (int) $row->total_events);
        $this->assertSame(1, (int) $row->builder_open_count);
        $this->assertSame(1, (int) $row->runtime_route_hydrated_count);
        $this->assertSame(0.0, (float) data_get($row->metrics_json, 'derived_rates.builder_publish_per_open_rate'));
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
