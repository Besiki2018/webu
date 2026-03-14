<?php

namespace Tests\Feature\Project;

use App\Jobs\RunProjectGeneration;
use App\Models\AiProvider;
use App\Models\Builder;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ManualProjectBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_user_can_create_project_in_manual_mode_without_ai_prompt(): void
    {
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $user = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'manual-template',
            'name' => 'Manual Template',
            'is_system' => true,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero', 'services']],
                    ['slug' => 'services', 'title' => 'Services', 'sections' => ['services', 'contact']],
                ],
                'default_sections' => [
                    'home' => [['key' => 'hero'], ['key' => 'services']],
                    'services' => [['key' => 'services'], ['key' => 'contact']],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->post('/projects', [
            'mode' => 'manual',
            'project_name' => 'Manual Restaurant Site',
            'template_id' => $template->id,
            'theme_preset' => 'arctic',
        ]);

        $project = Project::query()->latest('created_at')->firstOrFail();

        $response
            ->assertRedirect(route('project.cms', $project))
            ->assertSessionHas('create_pending_redirect_url', route('project.cms', $project));

        $this->assertSame('Manual Restaurant Site', $project->name);
        $this->assertNull($project->initial_prompt);
        $this->assertSame($template->id, (int) $project->template_id);
        $this->assertSame('arctic', $project->theme_preset);

        $site = Site::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertDatabaseHas('pages', [
            'site_id' => $site->id,
            'slug' => 'services',
        ]);
    }

    public function test_ai_mode_requires_prompt(): void
    {
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $user = User::factory()->withPlan($plan)->create();

        $this->actingAs($user)
            ->post('/projects', [
                'mode' => 'ai',
                'template_id' => null,
            ])
            ->assertSessionHasErrors('prompt');
    }

    public function test_ai_mode_redirects_create_page_requests_to_generation_screen(): void
    {
        Bus::fake();

        $builder = Builder::factory()->create();
        $provider = AiProvider::factory()->openai()->create();
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withBuildCredits(1000)
            ->withAiProvider($provider)
            ->withBuilder($builder)
            ->create();
        $user = User::factory()->withPlan($plan)->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post('/projects', [
                'mode' => 'ai',
                'prompt' => 'Create a consulting landing page',
            ]);

        $project = Project::query()->latest('created_at')->firstOrFail();
        $generationRun = ProjectGenerationRun::query()
            ->where('project_id', $project->id)
            ->latest('created_at')
            ->first();

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('project.generation', [
                'project' => $project,
            ]))
            ->assertSessionHas('create_pending_redirect_url', route('project.generation', [
                'project' => $project,
            ]));

        $this->assertSame('Create a consulting landing page', $project->initial_prompt);
        $this->assertNotNull($generationRun);
        $this->assertSame(ProjectGenerationRun::STATUS_QUEUED, $generationRun->status);
        Bus::assertDispatched(RunProjectGeneration::class);
    }
}
