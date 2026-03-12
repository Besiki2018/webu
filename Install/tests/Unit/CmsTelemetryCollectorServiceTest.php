<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CmsTelemetryCollectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTelemetryCollectorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_and_normalizes_builder_and_runtime_event_batches(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        SystemSetting::set('data_retention_days_cms_telemetry', 21, 'integer', 'privacy');
        $service = new CmsTelemetryCollectorService;

        $request = Request::create('/public/sites/'.$site->id.'/cms/telemetry', 'POST');
        $payload = $service->collect($site, [
            'schema_version' => 'cms.telemetry.event.v1',
            'source' => 'runtime',
            'session_id' => 'runtime-session-abc',
            'route' => [
                'path' => '/product/demo-item',
                'slug' => 'product',
                'params' => [
                    'slug' => 'demo-item',
                    'id' => 55,
                ],
            ],
            'context' => [
                'surface' => 'cms_runtime',
                'host' => 'demo.example.com',
            ],
            'events' => [
                [
                    'name' => 'cms_runtime.route_hydrated',
                    'at' => '2026-02-24T12:00:00Z',
                    'page_slug' => 'product',
                    'meta' => [
                        'source' => 'bridge',
                        'nested' => [
                            'ok' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'cms_runtime.hydrate_failed',
                    'at' => 'invalid-date',
                    'meta' => [
                        'error_code' => 'HTTP_500',
                    ],
                ],
            ],
        ], $request, ['channel' => 'public']);

        $this->assertTrue((bool) $payload['ok']);
        $this->assertSame('cms_telemetry_collector_v1', $payload['collector']);
        $this->assertSame((string) $site->id, $payload['site_id']);
        $this->assertSame((string) $project->id, $payload['project_id']);
        $this->assertSame('public', $payload['channel']);
        $this->assertSame('runtime', $payload['source']);
        $this->assertSame(2, $payload['accepted']);
        $this->assertSame(0, $payload['rejected']);
        $this->assertSame(2, $payload['stored']);
        $this->assertSame(21, $payload['retention_days']);
        $this->assertSame('hashed', data_get($payload, 'privacy.session_id'));

        $warnings = collect($payload['warnings'] ?? []);
        $this->assertTrue($warnings->contains(fn (array $warning): bool => ($warning['code'] ?? null) === 'invalid_event_timestamp'));
    }

    public function test_it_rejects_invalid_events_and_flags_unknown_sources(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $service = new CmsTelemetryCollectorService;

        $payload = $service->collect(
            $site,
            [
                'source' => 'desktop_app',
                'events' => [
                    ['name' => 'INVALID NAME'],
                    'not-an-object',
                ],
            ],
            Request::create('/panel/sites/'.$site->id.'/cms/telemetry', 'POST'),
            ['channel' => 'panel']
        );

        $this->assertSame('unknown', $payload['source']);
        $this->assertSame(0, $payload['accepted']);
        $this->assertSame(2, $payload['rejected']);
        $this->assertSame(0, $payload['stored']);

        $warningCodes = collect($payload['warnings'] ?? [])->pluck('code')->all();
        $this->assertContains('unsupported_source', $warningCodes);
        $this->assertContains('invalid_event_name', $warningCodes);
        $this->assertContains('invalid_event_payload', $warningCodes);
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
