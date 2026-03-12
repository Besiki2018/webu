<?php

namespace Tests\Feature\Security;

use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProjectRouteScopingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_site_group_returns_standardized_scope_mismatch_payload_for_cross_site_page_binding(): void
    {
        $owner = User::factory()->create();

        [, $siteA] = $this->createProjectWithSite($owner);
        [, $siteB] = $this->createProjectWithSite($owner);

        $foreignPage = $siteB->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', [
                'site' => $siteA->id,
                'page' => $foreignPage->id,
            ]))
            ->assertNotFound()
            ->assertJsonPath('code', 'tenant_scope_route_binding_mismatch')
            ->assertJsonPath('violations.0.code', 'route_model_site_scope_mismatch')
            ->assertJsonPath('violations.0.path', '$.route.page.site_id')
            ->assertJsonPath('violations.0.expected', $siteA->id)
            ->assertJsonPath('violations.0.actual', $siteB->id);
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createProjectWithSite(User $owner): array
    {
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}

