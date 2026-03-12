<?php

namespace Tests\Feature\Builder;

use App\Models\Builder;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderStatusQuickHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_quick_status_can_include_recent_history_for_chat_fallback_polling(): void
    {
        $user = User::factory()->create();
        $builder = Builder::factory()->create();
        $startedAt = now()->subSeconds(10);

        $project = Project::factory()->for($user)->create([
            'builder_id' => $builder->id,
            'build_status' => 'building',
            'build_session_id' => 'session-quick-history',
            'build_started_at' => $startedAt,
            'conversation_history' => [
                [
                    'role' => 'user',
                    'content' => 'Please update the hero section',
                    'timestamp' => $startedAt->copy()->toIso8601String(),
                ],
                [
                    'role' => 'assistant',
                    'content' => 'Working on the hero section now.',
                    'timestamp' => $startedAt->copy()->addSeconds(3)->toIso8601String(),
                ],
            ],
        ]);

        $this->actingAs($user)
            ->getJson(route('builder.status', $project).'?quick=1&history=1')
            ->assertOk()
            ->assertJsonPath('status', 'building')
            ->assertJsonPath('has_session', true)
            ->assertJsonPath('build_session_id', 'session-quick-history')
            ->assertJsonPath('can_reconnect', true)
            ->assertJsonPath('recent_history.0.content', 'Please update the hero section')
            ->assertJsonPath('recent_history.1.content', 'Working on the hero section now.');
    }

    /**
     * Quick status route uses builder-status limiter; start/chat use builder-operations.
     * So repeated status polls do not block start or chat routes.
     */
    public function test_status_route_and_start_route_use_separate_limiters(): void
    {
        $user = User::factory()->create();
        $builder = Builder::factory()->create();
        $project = Project::factory()->for($user)->create([
            'builder_id' => $builder->id,
            'build_status' => 'idle',
        ]);

        $statusUrl = route('builder.status', $project).'?quick=1';

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->getJson($statusUrl)->assertOk();
        }

        $response = $this->actingAs($user)->postJson(route('builder.start', $project), [
            'prompt' => 'Test prompt',
            'builder_id' => $builder->id,
        ]);

        $this->assertNotEquals(429, $response->status(), 'Start route must not return 429; status and start use separate limiters.');
    }

    /**
     * Quick status uses builder-status with Limit::none() for quick=1, so many quick polls do not hit 429.
     * Locks Task 7: status route does not block fallback polling.
     */
    public function test_quick_status_polls_do_not_return_429_under_normal_usage(): void
    {
        $user = User::factory()->create();
        $builder = Builder::factory()->create();
        $project = Project::factory()->for($user)->create([
            'builder_id' => $builder->id,
            'build_status' => 'idle',
        ]);

        $statusUrl = route('builder.status', $project).'?quick=1';
        $okCount = 0;
        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($user)->getJson($statusUrl);
            if ($response->status() === 200) {
                $okCount++;
            }
        }

        $this->assertGreaterThanOrEqual(25, $okCount, 'Quick status should not throttle under normal usage (builder-status quick limiter is none)');
    }
}
