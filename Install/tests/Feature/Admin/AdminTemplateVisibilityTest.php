<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminTemplateVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_view_templates_page_from_new_admin_templates_route(): void
    {
        $admin = User::factory()->admin()->create();
        Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Template Visibility Test',
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.templates'))
            ->assertOk();

        $this->assertSame('Admin/Templates/Index', $response->json('component'));
        $templates = $response->json('props.templates.data') ?? $response->json('props.templates') ?? [];
        $this->assertNotEmpty($templates, 'Admin templates index should return at least one template.');
        $names = array_column($templates, 'name');
        $this->assertContains('Template Visibility Test', $names, 'Created template should appear in admin templates list.');
    }

    public function test_non_admin_cannot_view_templates_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.templates'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.ai-templates'))
            ->assertForbidden();
    }

    public function test_admin_root_route_redirects_to_admin_overview(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertRedirect(route('admin.overview', absolute: false));
    }

    public function test_non_admin_cannot_access_admin_root_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.index'))
            ->assertForbidden();
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
