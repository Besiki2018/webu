<?php

namespace Tests\Feature\Cms;

use App\Jobs\RunProjectGeneration;
use App\Models\AiProvider;
use App\Models\Builder;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
        Bus::fake();

        $provider = AiProvider::factory()->openai()->create();
        $builder = Builder::factory()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('projects.generate-website'), [
                'prompt' => 'Create a yoga studio website',
            ]);

        $project = Project::query()->where('user_id', $user->id)->latest('created_at')->firstOrFail();
        $expectedRedirect = route('chat', [
            'project' => $project,
            'tab' => 'inspect',
        ]);

        $response
            ->assertRedirect($expectedRedirect)
            ->assertSessionHas('create_pending_redirect_url', $expectedRedirect);

        $generationRun = ProjectGenerationRun::query()
            ->where('project_id', $project->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($generationRun);
        $this->assertSame(ProjectGenerationRun::STATUS_QUEUED, $generationRun->status);
        $this->assertSame('Create a yoga studio website', $generationRun->requested_prompt);

        Bus::assertDispatched(RunProjectGeneration::class);
    }

    public function test_generation_status_endpoint_returns_latest_run_payload(): void
    {
        $provider = AiProvider::factory()->openai()->create();
        $builder = Builder::factory()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($user)->create();
        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_GENERATING,
            'requested_prompt' => 'Create a yoga studio website',
            'progress_message' => 'Generating pages and sections.',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('project.generation.status', $project));

        $response
            ->assertOk()
            ->assertJsonPath('generation.id', (string) $run->id)
            ->assertJsonPath('generation.status', ProjectGenerationRun::STATUS_GENERATING)
            ->assertJsonPath('generation.is_active', true)
            ->assertJsonPath('generation.progress_message', 'Generating pages and sections.');
    }

    public function test_chat_page_blocks_preview_until_generation_finishes(): void
    {
        $provider = AiProvider::factory()->openai()->create();
        $builder = Builder::factory()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($user)->create([
            'initial_prompt' => 'Create a yoga studio website',
        ]);

        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_GENERATING,
            'requested_prompt' => 'Create a yoga studio website',
            'progress_message' => 'Generating pages and sections.',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('chat', $project));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('project.cms_preview_url', null)
            ->where('generatedPages', [])
            ->where('project.generation.id', (string) $run->id)
            ->where('project.generation.status', ProjectGenerationRun::STATUS_GENERATING)
            ->where('project.generation.is_active', true)
        );
    }

    public function test_project_cms_redirects_back_to_inspect_while_generation_is_active(): void
    {
        $provider = AiProvider::factory()->openai()->create();
        $builder = Builder::factory()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($user)->create([
            'initial_prompt' => 'Create a yoga studio website',
        ]);

        ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_GENERATING,
            'requested_prompt' => 'Create a yoga studio website',
        ]);

        $response = $this->actingAs($user)
            ->get(route('project.cms', $project));

        $response->assertRedirect(route('chat', [
            'project' => $project,
            'tab' => 'inspect',
        ]));
    }
}
