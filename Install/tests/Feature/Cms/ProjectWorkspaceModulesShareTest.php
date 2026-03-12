<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProjectWorkspaceModulesShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_project_workspace_shared_modules_resolve_when_route_project_is_uuid_string(): void
    {
        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'ecommerce-share-test',
            'category' => 'ecommerce',
            'metadata' => [
                'module_flags' => [
                    'cms_pages' => true,
                    'cms_settings' => true,
                    'cms_menus' => true,
                    'media_library' => true,
                    'ecommerce' => true,
                    'booking' => false,
                ],
            ],
        ]);

        $project = Project::factory()
            ->for($owner)
            ->published('share-test-shop')
            ->create([
                'template_id' => $template->id,
            ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get('/project/'.(string) $project->id.'/cms')
            ->assertOk()
            ->assertJsonPath('props.projectWorkspaceModules.project_id', (string) $project->id)
            ->assertJsonPath('props.projectWorkspaceModules.available.ecommerce', true)
            ->assertJsonPath('props.projectWorkspaceModules.available.booking', false);
    }

    public function test_project_cms_editor_tab_route_loads_successfully(): void
    {
        $owner = User::factory()->create();
        $template = Template::factory()->create([
            'slug' => 'ecommerce-editor-test',
            'category' => 'ecommerce',
        ]);

        $project = Project::factory()
            ->for($owner)
            ->published('editor-open-test')
            ->create([
                'template_id' => $template->id,
            ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get('/project/'.(string) $project->id.'/cms?tab=editor')
            ->assertOk()
            ->assertJsonPath('component', 'Project/Cms')
            ->assertJsonPath('props.project.id', (string) $project->id);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $middleware = app(HandleInertiaRequests::class);
        $version = (string) ($middleware->version(Request::create('/')) ?? '');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
