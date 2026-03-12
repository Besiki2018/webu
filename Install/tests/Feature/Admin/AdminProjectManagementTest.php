<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_list_and_filter_projects(): void
    {
        $admin = User::factory()->admin()->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $matchingProject = Project::factory()->for($ownerA)->create([
            'name' => 'Alpha Builder',
            'build_status' => 'building',
        ]);

        Project::factory()->for($ownerB)->create([
            'name' => 'Beta Live',
            'build_status' => 'completed',
            'published_at' => now(),
            'subdomain' => 'beta-live-101',
        ]);

        $trashedProject = Project::factory()->for($ownerA)->create([
            'name' => 'Gamma Trash',
            'build_status' => 'failed',
        ]);
        $trashedProject->delete();

        $activeResponse = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.projects', [
                'search' => 'Alpha',
                'state' => 'active',
            ]))
            ->assertOk();

        $this->assertSame('Admin/Projects', $activeResponse->json('component'));
        $this->assertCount(1, $activeResponse->json('props.projects.data'));
        $this->assertSame(
            $matchingProject->id,
            $activeResponse->json('props.projects.data.0.id')
        );

        $trashedResponse = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.projects', [
                'state' => 'trashed',
                'owner_user_id' => $ownerA->id,
            ]))
            ->assertOk();

        $this->assertSame('Admin/Projects', $trashedResponse->json('component'));
        $this->assertCount(1, $trashedResponse->json('props.projects.data'));
        $this->assertSame(
            $trashedProject->id,
            $trashedResponse->json('props.projects.data.0.id')
        );
    }

    public function test_admin_can_create_project_for_any_user(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $owner = User::factory()->withPlan($plan)->create();
        $template = Template::factory()->create([
            'slug' => 'admin-manual-template',
            'is_system' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.projects.store'), [
                'name' => 'Admin Created Project',
                'description' => 'Managed by admin',
                'owner_user_id' => $owner->id,
                'is_public' => true,
                'template_id' => $template->id,
                'theme_preset' => 'ocean',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Admin Created Project',
            'user_id' => $owner->id,
            'is_public' => 1,
            'template_id' => $template->id,
            'theme_preset' => 'ocean',
        ]);
    }

    public function test_admin_can_edit_and_reassign_project(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $oldOwner = User::factory()->withPlan($plan)->create();
        $newOwner = User::factory()->withPlan($plan)->create();
        $template = Template::factory()->create([
            'slug' => 'admin-edit-template',
            'is_system' => true,
        ]);
        $project = Project::factory()->for($oldOwner)->create([
            'name' => 'Original Name',
            'description' => 'Old description',
            'is_public' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'owner_user_id' => $newOwner->id,
                'is_public' => true,
                'template_id' => $template->id,
                'theme_preset' => 'ruby',
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame('Updated Name', $project->name);
        $this->assertSame('Updated description', $project->description);
        $this->assertSame($newOwner->id, $project->user_id);
        $this->assertTrue($project->is_public);
        $this->assertSame($template->id, (int) $project->template_id);
        $this->assertSame('ruby', $project->theme_preset);
    }

    public function test_non_admin_cannot_manage_admin_project_routes(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($user)
            ->get(route('admin.projects'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.projects.store'), [
                'name' => 'Blocked',
                'owner_user_id' => $owner->id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Blocked update',
                'owner_user_id' => $owner->id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.projects.access-as-admin', $project))
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('admin.projects.access-audits', $project))
            ->assertForbidden();
    }

    public function test_admin_owner_handoff_preserves_project_workspace_for_new_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $ownerPlan = Plan::factory()->withProjectLimit(10)->create();
        $oldOwner = User::factory()->withPlan($ownerPlan)->create();
        $newOwner = User::factory()->withPlan($ownerPlan)->create();

        $template = Template::factory()->create([
            'slug' => 'handoff-template',
            'name' => 'Handoff Template',
            'is_system' => true,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero', 'services']],
                    ['slug' => 'about', 'title' => 'About', 'sections' => ['text']],
                ],
                'default_sections' => [
                    'home' => [['key' => 'hero'], ['key' => 'services']],
                    'about' => [['key' => 'text']],
                ],
            ],
        ]);

        $project = Project::factory()->for($oldOwner)->create([
            'name' => 'Handoff Project',
            'template_id' => $template->id,
            'theme_preset' => 'forest',
        ]);

        $site = Site::query()->where('project_id', $project->id)->firstOrFail();
        $homePage = $site->pages()->where('slug', 'home')->firstOrFail();
        $revisionCountBefore = $homePage->revisions()->count();

        $this->actingAs($admin)
            ->put(route('admin.projects.update', $project), [
                'owner_user_id' => $newOwner->id,
            ])
            ->assertRedirect();

        $project->refresh();
        $site->refresh();
        $homePage->refresh();

        $this->assertSame($newOwner->id, $project->user_id);
        $this->assertSame('forest', $project->theme_preset);
        $this->assertSame($template->id, (int) $project->template_id);
        $this->assertSame($revisionCountBefore, $homePage->revisions()->count());

        $this->actingAs($newOwner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('project.cms', $project))
            ->assertOk();

        $this->actingAs($oldOwner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('project.cms', $project))
            ->assertForbidden();
    }

    public function test_admin_can_open_tenant_workspace_and_query_access_audits(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create([
            'name' => 'Tenant Ops Project',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.projects.access-as-admin', $project), [
                'target' => 'cms',
            ])
            ->assertOk()
            ->assertJsonPath('target', 'cms')
            ->assertJsonPath('workspace_url', route('project.cms', $project));

        $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('project.cms', $project))
            ->assertOk();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.projects.access-audits', $project))
            ->assertOk();

        $events = collect($response->json('data', []))
            ->pluck('event')
            ->all();

        $this->assertContains('admin_override_entrypoint', $events);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $version = (string) ($middleware->version(Request::create('/')) ?? '');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
