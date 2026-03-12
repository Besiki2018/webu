<?php

namespace Tests\Unit;

use App\Models\CmsTelemetryEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CmsTelemetryEventStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTelemetryEventStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_telemetry_events_with_anonymized_request_identifiers_and_redacted_meta(): void
    {
        [$project, $site, $owner] = $this->createPublishedProjectWithSite();
        SystemSetting::set('data_retention_days_cms_telemetry', 14, 'integer', 'privacy');

        $request = Request::create('/panel/sites/'.$site->id.'/cms/telemetry', 'POST', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.44',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/537.36 Chrome/123.0 Safari/537.36',
        ]);
        $request->setUserResolver(fn () => $owner);

        $service = app(CmsTelemetryEventStorageService::class);
        $result = $service->storeBatch(
            $site,
            $request,
            'panel',
            'builder',
            'raw-session-id-123',
            ['path' => '/project/x/cms', 'slug' => 'home', 'params' => ['slug' => 'home']],
            ['surface' => 'cms_builder', 'editor_mode' => 'builder'],
            [
                [
                    'name' => 'cms_builder.save_draft',
                    'at' => '2026-02-24T16:00:00Z',
                    'page_id' => 5,
                    'page_slug' => 'home',
                    'meta' => [
                        'email' => 'user@example.com',
                        'phone_number' => '+995 555 12 34 56',
                        'warning_count' => 2,
                        'notes' => 'normal text',
                    ],
                ],
            ]
        );

        $this->assertSame(1, $result['stored']);
        $this->assertSame(14, $result['retention_days']);
        $this->assertSame('hashed', data_get($result, 'privacy.session_id'));

        /** @var CmsTelemetryEvent $event */
        $event = CmsTelemetryEvent::query()->firstOrFail();
        $this->assertSame((string) $site->id, $event->site_id);
        $this->assertSame((string) $project->id, $event->project_id);
        $this->assertSame('panel', $event->channel);
        $this->assertSame('builder', $event->source);
        $this->assertSame('cms_builder.save_draft', $event->event_name);
        $this->assertSame('home', $event->page_slug);
        $this->assertSame('/project/x/cms', $event->route_path);
        $this->assertNotNull($event->session_hash);
        $this->assertNotSame('raw-session-id-123', $event->session_hash);
        $this->assertNotNull($event->client_ip_hash);
        $this->assertSame('chrome', $event->user_agent_family);
        $this->assertSame('authenticated', $event->actor_scope);
        $this->assertNotNull($event->actor_hash);
        $this->assertSame('[redacted]', data_get($event->meta_json, 'email'));
        $this->assertSame('[redacted]', data_get($event->meta_json, 'phone_number'));
        $this->assertSame(2, data_get($event->meta_json, 'warning_count'));
        $this->assertSame('normal text', data_get($event->meta_json, 'notes'));
        $this->assertNotNull($event->anonymized_at);
        $this->assertNotNull($event->retention_expires_at);
        $this->assertSame(14, (int) ($event->created_at?->diffInDays($event->retention_expires_at) ?? 0));
    }

    public function test_it_prunes_events_past_retention_expiry(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'public',
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'actor_scope' => 'guest',
            'retention_expires_at' => now()->subDay(),
            'anonymized_at' => now(),
        ]);
        CmsTelemetryEvent::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'public',
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'actor_scope' => 'guest',
            'retention_expires_at' => now()->addDay(),
            'anonymized_at' => now(),
        ]);

        $result = app(CmsTelemetryEventStorageService::class)->pruneExpired(now());

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1, CmsTelemetryEvent::query()->count());
    }

    /**
     * @return array{0: Project, 1: Site, 2: User}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(10)))->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site, $owner];
    }
}
