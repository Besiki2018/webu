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

    public function test_inertia_requests_redirect_to_generation_screen_before_builder_is_ready(): void
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
        $expectedRedirect = route('project.generation', ['project' => $project]);

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $expectedRedirect)
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
        $firstResponse
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('project.generation', ['project' => $project]));

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

    public function test_new_project_flow_redirects_into_the_generation_screen_after_creation(): void
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
                'prompt' => 'Create a premium website for a vet clinic',
            ]);

        $project = Project::query()->where('user_id', $user->id)->latest('created_at')->firstOrFail();
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', route('project.generation', ['project' => $project]));

        $run = ProjectGenerationRun::query()
            ->where('project_id', $project->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(ProjectGenerationRun::STATUS_QUEUED, $run->status);
        $this->assertSame('Create a premium website for a vet clinic', $project->initial_prompt);
        $this->assertSame('Create a premium website for a vet clinic', $run->requested_prompt);
        $this->assertSame('Create a premium website for a vet clinic', data_get($run->requested_input, 'prompt'));
        $this->assertNull(data_get($run->requested_input, 'template_id'));
        $this->assertNull($project->template_id);
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
            ->assertJsonPath('generation.project_generation_version', (string) $run->id)
            ->assertJsonPath('generation.source_generation_type', 'new')
            ->assertJsonPath('generation.progress_message', 'Writing project files to the workspace.');
    }

    public function test_generation_page_renders_progress_screen_until_generation_finishes(): void
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

        $response = $this->actingAs($user)->get(route('project.generation', $project));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Project/Generation')
            ->where('project.id', (string) $project->id)
            ->where('generation.id', (string) $run->id)
            ->where('generation.status', ProjectGenerationRun::STATUS_BUILDING_PREVIEW)
            ->where('generation.is_active', true)
            ->where('generation.ready_for_builder', false)
            ->where('builderUrl', route('chat', $project))
        );
    }

    public function test_chat_route_redirects_back_to_generation_screen_while_build_is_unfinished(): void
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
            'status' => ProjectGenerationRun::STATUS_BUILDING_PREVIEW,
            'requested_prompt' => 'Create a yoga studio website',
            'progress_message' => 'Rendering the preview and validating the workspace.',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('chat', $project));

        $response->assertRedirect(route('project.generation', ['project' => $project]));
    }

    public function test_project_cms_redirects_back_to_generation_screen_while_generation_is_active(): void
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

        $response->assertRedirect(route('project.generation', ['project' => $project]));
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
            ->assertJsonPath('generation.project_generation_version', (string) $run->id)
            ->assertJsonPath('generation.preview_build_id', (string) $run->id)
            ->assertJsonPath('generation.workspace_manifest_exists', true)
            ->assertJsonPath('generation.workspace_preview_ready', true)
            ->assertJsonPath('generation.workspace_preview_phase', ProjectGenerationRun::STATUS_READY);
    }

    public function test_generation_page_stays_active_when_run_is_ready_but_manifest_is_not_ready(): void
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

        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_READY,
            'requested_prompt' => 'Create a yoga studio website',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('project.generation', $project));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Project/Generation')
            ->where('generation.id', (string) $run->id)
            ->where('generation.status', ProjectGenerationRun::STATUS_READY)
            ->where('generation.ready_for_builder', false)
            ->where('generation.workspace_preview_ready', false)
        );
    }

    public function test_generation_page_only_enters_resume_draft_mode_when_requested_explicitly(): void
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
            'initial_prompt' => 'Create a premium fitness studio website',
        ]);

        ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_BUILDING_PREVIEW,
            'requested_prompt' => 'Create a premium fitness studio website',
            'started_at' => now(),
        ]);

        File::ensureDirectoryExists(storage_path('app/private/previews'));
        File::put(storage_path('app/private/previews/'.$project->id), 'preview');

        $defaultResponse = $this->actingAs($user)->get(route('project.generation', $project));
        $defaultResponse->assertInertia(fn (Assert $page) => $page
            ->component('Project/Generation')
            ->where('resumeDraftAvailable', true)
            ->where('resumeDraftMode', false)
            ->where('resumeDraftPreviewUrl', null)
        );

        $resumeResponse = $this->actingAs($user)->get(route('project.generation', [
            'project' => $project,
            'resume_draft' => 1,
        ]));

        $resumeResponse->assertInertia(fn (Assert $page) => $page
            ->component('Project/Generation')
            ->where('resumeDraftAvailable', true)
            ->where('resumeDraftMode', true)
            ->where('resumeDraftPreviewUrl', "/preview/{$project->id}")
        );
    }

    public function test_ready_generation_does_not_reenter_initial_blocking_state_after_workspace_edits(): void
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
            'initial_prompt' => 'Create an online fashion store',
        ]);

        app(SiteProvisioningService::class)->provisionForProject($project);

        $run = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_READY,
            'requested_prompt' => 'Create an online fashion store',
            'completed_at' => now(),
        ]);

        $workspace = app(ProjectWorkspaceService::class);
        $workspace->syncInitialGenerationState($project, [
            'active_generation_run_id' => (string) $run->id,
            'phase' => ProjectGenerationRun::STATUS_READY,
        ]);

        $existingStyles = $workspace->readFile($project, 'src/styles/globals.css') ?? '';
        $workspace->writeFile(
            $project,
            'src/styles/globals.css',
            $existingStyles."\n/* visual builder change */\n",
            [
                'actor' => 'visual_builder',
                'source' => 'feature_test',
            ]
        );

        $statusResponse = $this->actingAs($user)
            ->getJson(route('project.generation.status', $project));

        $statusResponse
            ->assertOk()
            ->assertJsonPath('generation.status', ProjectGenerationRun::STATUS_READY)
            ->assertJsonPath('generation.ready_for_builder', true)
            ->assertJsonPath('generation.workspace_preview_ready', false)
            ->assertJsonPath('generation.workspace_preview_phase', ProjectGenerationRun::STATUS_BUILDING_PREVIEW);

        $chatResponse = $this->actingAs($user)->get(route('chat', $project));

        $chatResponse->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('project.generation.status', ProjectGenerationRun::STATUS_READY)
            ->where('project.generation.ready_for_builder', true)
            ->where('project.generation.workspace_preview_ready', false)
            ->where('project.generation.workspace_preview_phase', ProjectGenerationRun::STATUS_BUILDING_PREVIEW)
            ->where('project.cms_preview_url', fn ($value) => is_string($value) && $value !== '')
            ->where('generatedPages.0.slug', 'home')
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
