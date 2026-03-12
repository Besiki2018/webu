<?php

namespace Tests\Feature\Cms;

use App\Models\CmsTelemetryEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTelemetryPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cms_telemetry_prune_command_deletes_expired_events_only(): void
    {
        SystemSetting::set('data_retention_days_cms_telemetry', 7, 'integer', 'privacy');
        [, $site] = $this->createPublishedProjectWithSite();

        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'public',
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'actor_scope' => 'guest',
            'anonymized_at' => now(),
            'retention_expires_at' => now()->subMinutes(5),
        ]);
        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'panel',
            'source' => 'builder',
            'event_name' => 'cms_builder.open',
            'actor_scope' => 'authenticated',
            'anonymized_at' => now(),
            'retention_expires_at' => now()->addDays(2),
        ]);

        $this->artisan('cms:telemetry-prune')
            ->expectsOutputToContain('Pruned 1 expired CMS telemetry events')
            ->assertSuccessful();

        $this->assertSame(1, CmsTelemetryEvent::query()->count());
        $this->assertSame('cms_builder.open', CmsTelemetryEvent::query()->value('event_name'));
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
