<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminTemplateDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_open_template_demo_page(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();

        $response = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.ai-templates.demo', $template))
            ->assertOk();

        $this->assertSame('Admin/Templates/Demo', $response->json('component'));
        $this->assertSame('demo-template', $response->json('props.template.slug'));
        $this->assertSame('home', $response->json('props.demo.meta.active_page_slug'));
    }

    public function test_admin_can_switch_demo_page_through_backend_data_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();

        $this->actingAs($admin)
            ->getJson(route('admin.ai-templates.demo-data', $template).'?page=contact')
            ->assertOk()
            ->assertJsonPath('meta.active_page_slug', 'contact')
            ->assertJsonPath('active_page.slug', 'contact');
    }

    public function test_admin_live_demo_route_redirects_to_static_demo_when_configured(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();
        $demoDir = public_path('template-demos/test-live-demo-static');
        File::ensureDirectoryExists($demoDir);
        File::put($demoDir.'/index.html', '<html><body>demo</body></html>');

        $template->update([
            'metadata' => array_merge($template->metadata ?? [], [
                'live_demo' => [
                    'path' => 'template-demos/test-live-demo-static/index.html',
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-templates.live-demo', $template))
            ->assertRedirect('/template-demos/test-live-demo-static/index.html');

        File::deleteDirectory($demoDir);
    }

    public function test_admin_live_demo_route_falls_back_to_public_template_demo_when_no_static_path(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();

        $response = $this->actingAs($admin)
            ->get(route('admin.ai-templates.live-demo', $template));
        $response->assertRedirect();
        $this->assertStringContainsString('/template-demos/'.$template->slug, $response->headers->get('Location') ?? '');
    }

    public function test_admin_live_admin_route_redirects_to_unified_project_cms_for_template_site(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'template_id' => $template->id,
        ]);
        $site = Site::query()->where('project_id', $project->id)->firstOrFail();
        $site->update([
            'name' => 'Demo Site',
            'status' => 'published',
            'locale' => 'ka',
            'subdomain' => 'demo-live-admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-templates.live-admin', $template))
            ->assertRedirect(route('project.cms', ['project' => $project->id]));
    }

    public function test_admin_live_admin_route_auto_provisions_demo_project_when_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();

        $response = $this->actingAs($admin)
            ->get(route('admin.ai-templates.live-admin', $template))
            ->assertRedirect();

        $project = Project::query()
            ->where('template_id', $template->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($project);
        $this->assertNotNull($project?->site);

        $response->assertRedirect(route('project.cms', ['project' => $project->id]));
    }

    public function test_admin_live_builder_route_redirects_to_project_cms_editor_tab(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->makeDemoTemplate();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'template_id' => $template->id,
        ]);
        Site::query()
            ->where('project_id', $project->id)
            ->firstOrFail()
            ->update([
                'name' => 'Builder Demo Site',
                'status' => 'published',
                'locale' => 'ka',
                'subdomain' => 'demo-live-builder',
            ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-templates.live-builder', $template))
            ->assertRedirect(route('project.cms', [
                'project' => $project->id,
                'tab' => 'editor',
            ]));
    }

    public function test_non_admin_cannot_access_template_demo_routes(): void
    {
        $user = User::factory()->create();
        $template = $this->makeDemoTemplate();

        $this->actingAs($user)
            ->get(route('admin.ai-templates.demo', $template))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.ai-templates.demo-data', $template))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.ai-templates.live-demo', $template))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.ai-templates.live-admin', $template))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.ai-templates.live-builder', $template))
            ->assertForbidden();
    }

    private function makeDemoTemplate(): Template
    {
        return Template::factory()->create([
            'slug' => 'demo-template',
            'name' => 'Demo Template',
            'category' => 'ecommerce',
            'version' => '1.0.0',
            'metadata' => [
                'module_flags' => [
                    'cms_pages' => true,
                    'ecommerce' => true,
                    'payments' => true,
                ],
                'typography_tokens' => [
                    'heading' => 'heading',
                    'body' => 'body',
                    'button' => 'body',
                ],
                'default_pages' => [
                    [
                        'slug' => 'home',
                        'title' => 'Home',
                        'sections' => ['hero_split_image', 'ecommerce_product_grid'],
                    ],
                    [
                        'slug' => 'contact',
                        'title' => 'Contact',
                        'sections' => ['contact_split_form'],
                    ],
                ],
                'default_sections' => [
                    'home' => [
                        ['key' => 'trust_badges_inline', 'enabled' => true],
                    ],
                ],
            ],
        ]);
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
