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

class CmsTelemetryCollectorEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_collector_accepts_builder_telemetry_for_site_owner(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.cms.telemetry.store', ['site' => $site->id]), [
                'schema_version' => 'cms.telemetry.event.v1',
                'source' => 'builder',
                'session_id' => 'builder-session-1',
                'route' => [
                    'path' => '/project/'.$site->project_id.'/cms',
                    'slug' => 'home',
                    'params' => [],
                ],
                'context' => [
                    'surface' => 'cms_builder',
                    'section' => 'editor',
                ],
                'events' => [
                    [
                        'name' => 'cms_builder.save_draft',
                        'page_id' => 10,
                        'page_slug' => 'home',
                        'meta' => [
                            'warning_count' => 0,
                        ],
                    ],
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('channel', 'panel')
            ->assertJsonPath('source', 'builder')
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('stored', 1)
            ->assertJsonPath('rejected', 0)
            ->assertJsonPath('site_id', $site->id);

        $event = CmsTelemetryEvent::query()->firstOrFail();
        $this->assertSame('cms_builder.save_draft', $event->event_name);
        $this->assertNotNull($event->session_hash);
        $this->assertNotNull($event->anonymized_at);
    }

    public function test_public_collector_accepts_runtime_telemetry_and_returns_cors_headers(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->withHeaders([
            'Origin' => 'https://demo.customer-site.test',
        ])->postJson(route('public.sites.cms.telemetry.store', ['site' => $site->id]), [
            'schema_version' => 'cms.telemetry.event.v1',
            'source' => 'runtime',
            'session_id' => 'runtime-session-1',
            'route' => [
                'path' => '/shop',
                'slug' => 'shop',
                'params' => [],
            ],
            'events' => [
                [
                    'name' => 'cms_runtime.route_hydrated',
                    'page_slug' => 'shop',
                    'meta' => [
                        'source' => 'public-cms-api',
                    ],
                ],
            ],
        ])
            ->assertAccepted()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->assertJsonPath('channel', 'public')
            ->assertJsonPath('source', 'runtime')
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('stored', 1);

        $event = CmsTelemetryEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('public', $event->channel);
        $this->assertSame('runtime', $event->source);
        $this->assertSame('cms_runtime.route_hydrated', $event->event_name);
        $this->assertSame('public-cms-api', data_get($event->meta_json, 'source'));
        $this->assertNotNull($event->client_ip_hash);
        $this->assertNotNull($event->retention_expires_at);
    }

    public function test_public_collector_exposes_options_preflight_response(): void
    {
        $owner = User::factory()->withPlan(Plan::factory()->create())->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->call('OPTIONS', route('public.sites.cms.telemetry.options', ['site' => $site->id]), [], [], [], [
            'HTTP_ORIGIN' => 'https://demo.customer-site.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');
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
