<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TenantProjectRouteScopeValidatorService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class TenantProjectRouteScopeValidatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_returns_ok_report_for_aligned_site_and_page_route_models(): void
    {
        $owner = User::factory()->create();
        [$project, $site] = $this->createProjectWithSite($owner);
        $page = $site->pages()->where('slug', 'home')->firstOrFail();

        $request = $this->makeRouteRequest('panel.sites.pages.show', [
            'site' => $site,
            'page' => $page,
        ]);

        app(TenantContext::class)->setProject($project);

        $report = app(TenantProjectRouteScopeValidatorService::class)->validate($request);

        $this->assertTrue($report['ok'], json_encode($report, JSON_PRETTY_PRINT));
        $this->assertSame([], $report['errors']);
        $this->assertSame('panel.sites.pages.show', data_get($report, 'snapshot.route_name'));
        $this->assertSame($project->id, data_get($report, 'snapshot.tenant_context_project_id'));
        $this->assertSame($site->id, data_get($report, 'snapshot.route_site_id'));
        $this->assertContains('site', data_get($report, 'snapshot.checked_route_model_params', []));
        $this->assertContains('page', data_get($report, 'snapshot.checked_route_model_params', []));
    }

    public function test_it_detects_cross_site_route_model_binding_mismatch(): void
    {
        $owner = User::factory()->create();
        [$projectA, $siteA] = $this->createProjectWithSite($owner);
        [, $siteB] = $this->createProjectWithSite($owner);
        $foreignPage = $siteB->pages()->where('slug', 'home')->firstOrFail();

        $request = $this->makeRouteRequest('panel.sites.pages.show', [
            'site' => $siteA,
            'page' => $foreignPage,
        ]);

        app(TenantContext::class)->setProject($projectA);

        $report = app(TenantProjectRouteScopeValidatorService::class)->validate($request);

        $this->assertFalse($report['ok']);
        $codes = collect($report['errors'])->pluck('code')->all();
        $this->assertContains('route_model_site_scope_mismatch', $codes);
        $this->assertSame('$.route.page.site_id', data_get($report, 'errors.0.path'));
    }

    public function test_it_detects_tenant_context_site_mismatch_even_without_resource_route_model(): void
    {
        $owner = User::factory()->create();
        [$projectA, $siteA] = $this->createProjectWithSite($owner);
        [$projectB] = $this->createProjectWithSite($owner);

        $request = $this->makeRouteRequest('panel.sites.pages.index', [
            'site' => $siteA,
        ]);

        app(TenantContext::class)->setProject($projectB);

        $report = app(TenantProjectRouteScopeValidatorService::class)->validate($request);

        $this->assertFalse($report['ok']);
        $codes = collect($report['errors'])->pluck('code')->all();
        $this->assertContains('tenant_context_site_mismatch', $codes);
        $this->assertSame($projectA->id, data_get($report, 'errors.0.expected'));
        $this->assertSame($projectB->id, data_get($report, 'errors.0.actual'));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function makeRouteRequest(string $routeName, array $parameters): Request
    {
        $request = Request::create('/test/a/b/c/d/e', 'GET');
        $route = new Route(['GET'], '/test/{p1?}/{p2?}/{p3?}/{p4?}/{p5?}', static fn () => response()->noContent());
        $route->name($routeName);
        $route->bind($request);

        foreach ($parameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(static fn () => $route);

        return $request;
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
