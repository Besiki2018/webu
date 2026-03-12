<?php

namespace Tests\Feature\Operations;

use App\Models\Builder;
use App\Models\AiProvider;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiBuildPublishSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('landing_page_enabled', true, 'boolean', 'landing');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domains');
        SystemSetting::set('domain_base_domain', 'platform.example.com', 'string', 'domains');
    }

    public function test_ai_create_start_complete_and_publish_smoke_flow(): void
    {
        $builder = Builder::factory()->create([
            'url' => 'https://builder-smoke.example',
            'port' => 443,
            'status' => 'active',
        ]);

        $provider = AiProvider::factory()->openai()->create([
            'status' => 'active',
        ]);

        $plan = Plan::factory()
            ->withSubdomains()
            ->withBuildCredits(1000)
            ->create([
                'price' => 49.00,
                'ai_provider_id' => $provider->id,
                'builder_id' => $builder->id,
            ]);

        $user = User::factory()->withPlan($plan)->create();

        Subscription::factory()
            ->for($user)
            ->for($plan)
            ->active()
            ->create([
                'amount' => $plan->price,
                'payment_method' => Subscription::PAYMENT_MANUAL,
            ]);

        Cache::put('broadcast:health_status', [
            'configured' => true,
            'working' => true,
            'error' => null,
        ], 120);

        Http::fake([
            '*api/run' => Http::response([
                'session_id' => 'sess-smoke-001',
            ], 200),
            '*api/complete' => Http::response([
                'success' => true,
            ], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        $createResponse = $this->actingAs($user)
            ->post(route('projects.store'), [
                'mode' => 'ai',
                'prompt' => 'Build a modern pet clinic website',
            ]);

        $createResponse->assertSessionHasNoErrors();
        $createResponse->assertRedirect();

        /** @var Project $project */
        $project = Project::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $startResponse = $this->actingAs($user)
            ->postJson(route('builder.start', ['project' => $project->id]), [
                'prompt' => 'Use clean typography and service cards',
                'builder_id' => $builder->id,
            ]);

        $this->assertSame(200, $startResponse->status(), (string) $startResponse->getContent());
        $startResponse->assertJsonPath('session_id', 'sess-smoke-001');

        $this->actingAs($user)
            ->postJson(route('builder.complete', ['project' => $project->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($user)
            ->postJson("/project/{$project->id}/publish", [
                'subdomain' => 'smoke-build-publish',
                'visibility' => 'public',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $project->refresh();

        $this->assertSame('completed', $project->build_status);
        $this->assertSame('smoke-build-publish', $project->subdomain);
        $this->assertNotNull($project->published_at);
    }

    public function test_landing_page_renders_without_undefined_fallback_variables(): void
    {
        $this->get(route('welcome'))->assertOk();
    }
}
