<?php

namespace Tests\Feature\Cms;

use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GenerateWebsiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_inertia_requests_redirect_to_visual_builder_after_generation(): void
    {
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $user = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($user)->create();
        $expectedRedirect = route('chat', [
            'project' => $project,
            'tab' => 'inspect',
        ]);

        $this->mock(GenerateWebsiteProjectService::class, function (MockInterface $mock) use ($project): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'project' => $project,
                ]);
        });

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('projects.generate-website'), [
                'prompt' => 'Create a yoga studio website',
            ]);

        $response
            ->assertRedirect($expectedRedirect)
            ->assertSessionHas('create_pending_redirect_url', $expectedRedirect);
    }
}
