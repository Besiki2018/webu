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
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\SiteProvisioningService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
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

    public function test_inertia_requests_redirect_to_chat_workspace_before_generation_is_ready(): void
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
        $expectedRedirect = route('chat', ['project' => $project]);

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

    public function test_second_project_generation_request_does_not_create_duplicate_draft_run(): void
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

        $firstResponse = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('projects.generate-website'), [
                'prompt' => 'Create a yoga studio website',
            ]);

        $project = Project::query()->where('user_id', $user->id)->latest('created_at')->firstOrFail();
        $firstResponse->assertRedirect(route('chat', ['project' => $project]));

        $secondResponse = $this->from('/create')
            ->actingAs($user)
            ->post(route('projects.generate-website'), [
                'prompt' => 'Create the same yoga studio website again',
            ]);

        $secondResponse
            ->assertRedirect('/create')
            ->assertSessionHasErrors('prompt');

        $this->assertSame(1, Project::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, ProjectGenerationRun::query()->where('user_id', $user->id)->count());
        Bus::assertDispatchedTimes(RunProjectGeneration::class, 1);
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
        File::deleteDirectory(storage_path('workspaces/'.$project->id));
        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_WRITING_FILES,
            'requested_prompt' => 'Create a yoga studio website',
            'progress_message' => 'Writing project files to the workspace.',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('project.generation.status', $project));

        $response
            ->assertOk()
            ->assertJsonPath('generation.id', (string) $run->id)
            ->assertJsonPath('generation.status', ProjectGenerationRun::STATUS_WRITING_FILES)
            ->assertJsonPath('generation.is_active', true)
            ->assertJsonPath('generation.ready_for_builder', false)
            ->assertJsonPath('generation.progress_message', 'Writing project files to the workspace.');
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
            'status' => ProjectGenerationRun::STATUS_BUILDING_PREVIEW,
            'requested_prompt' => 'Create a yoga studio website',
            'progress_message' => 'Building the preview and validating workspace readiness.',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('chat', $project));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('project.cms_preview_url', null)
            ->where('generatedPages', [])
            ->where('project.generation.id', (string) $run->id)
            ->where('project.generation.status', ProjectGenerationRun::STATUS_BUILDING_PREVIEW)
            ->where('project.generation.is_active', true)
            ->where('project.generation.ready_for_builder', false)
        );
    }

    public function test_project_cms_redirects_back_to_chat_workspace_while_generation_is_active(): void
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
            'status' => ProjectGenerationRun::STATUS_SCAFFOLDING,
            'requested_prompt' => 'Create a yoga studio website',
        ]);

        $response = $this->actingAs($user)
            ->get(route('project.cms', $project));

        $response->assertRedirect(route('chat', ['project' => $project]));
    }

    public function test_generation_status_endpoint_marks_ready_for_builder_when_manifest_is_ready(): void
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
            'status' => ProjectGenerationRun::STATUS_READY,
            'requested_prompt' => 'Create a yoga studio website',
            'completed_at' => now(),
        ]);

        app(ProjectWorkspaceService::class)->syncInitialGenerationState($project, [
            'active_generation_run_id' => (string) $run->id,
            'phase' => ProjectGenerationRun::STATUS_READY,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('project.generation.status', $project));

        $response
            ->assertOk()
            ->assertJsonPath('generation.status', ProjectGenerationRun::STATUS_READY)
            ->assertJsonPath('generation.is_active', false)
            ->assertJsonPath('generation.ready_for_builder', true)
            ->assertJsonPath('generation.workspace_manifest_exists', true)
            ->assertJsonPath('generation.workspace_preview_ready', true)
            ->assertJsonPath('generation.workspace_preview_phase', ProjectGenerationRun::STATUS_READY);
    }

    public function test_chat_page_keeps_preview_frozen_until_ready_manifest_exists(): void
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
        File::deleteDirectory(storage_path('workspaces/'.$project->id));

        app(SiteProvisioningService::class)->provisionForProject($project);

        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_READY,
            'requested_prompt' => 'Create a yoga studio website',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('chat', $project));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('project.cms_preview_url', null)
            ->where('generatedPages', [])
            ->where('project.generation.id', (string) $run->id)
            ->where('project.generation.status', ProjectGenerationRun::STATUS_READY)
            ->where('project.generation.ready_for_builder', false)
            ->where('project.generation.workspace_preview_ready', false)
        );
    }

    public function test_code_first_generation_route_is_blocked_when_rollout_flag_is_disabled(): void
    {
        config()->set('webu_v2.flags.code_first_initial_generation', false);

        $provider = AiProvider::factory()->openai()->create();
        $builder = Builder::factory()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();

        $response = $this->from('/create')
            ->actingAs($user)
            ->post(route('projects.generate-website'), [
                'prompt' => 'Create a yoga studio website',
            ]);

        $response
            ->assertRedirect('/create')
            ->assertSessionHasErrors('prompt');

        $this->assertSame(0, Project::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, ProjectGenerationRun::query()->where('user_id', $user->id)->count());
    }
}
