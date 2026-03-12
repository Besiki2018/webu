<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class TenantProjectScopingContractsP5F1Test extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_uses_canonical_attribute_precedence_before_route_parameters(): void
    {
        $attributeProject = Project::factory()->create();
        $routeProject = Project::factory()->create();

        $request = $this->requestWithRoute('GET', '/projects/{project}', [
            'project' => (string) $routeProject->id,
        ]);
        $request->attributes->set('custom_domain_project', $attributeProject);

        $resolver = app(TenantProjectRequestResolver::class);
        $resolved = $resolver->resolveProject($request);

        $this->assertNotNull($resolved);
        $this->assertSame((string) $attributeProject->id, (string) $resolved->id);
        $this->assertTrue($resolver->requestNeedsTenantContext($request));
    }

    public function test_resolver_supports_route_bound_project_model_and_project_identifier_aliases(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $resolver = app(TenantProjectRequestResolver::class);

        $requestWithProjectModel = $this->requestWithRoute('GET', '/projects/{project}', [
            'project' => $projectA,
        ]);

        $this->assertSame((string) $projectA->id, (string) $resolver->extractRouteProjectIdentifier($requestWithProjectModel));
        $this->assertSame((string) $projectA->id, (string) optional($resolver->extractRouteProject($requestWithProjectModel))->id);

        $requestWithProjectIdAlias = $this->requestWithRoute('GET', '/projects/{projectId}', [
            'projectId' => (string) $projectB->id,
        ]);

        $this->assertSame((string) $projectB->id, (string) $resolver->extractRouteProjectIdentifier($requestWithProjectIdAlias));
        $this->assertSame((string) $projectB->id, (string) optional($resolver->extractRouteProject($requestWithProjectIdAlias))->id);
    }

    public function test_resolver_reports_no_context_requirement_when_request_has_no_project_attributes_or_route_params(): void
    {
        $resolver = app(TenantProjectRequestResolver::class);
        $request = Request::create('/health', 'GET');

        $this->assertFalse($resolver->requestNeedsTenantContext($request));
        $this->assertNull($resolver->extractRouteProjectIdentifier($request));
        $this->assertNull($resolver->extractRouteProject($request));
        $this->assertNull($resolver->resolveProject($request));
    }

    public function test_p5_f1_03_doc_documents_shared_resolver_and_middleware_contracts(): void
    {
        $path = base_path('docs/architecture/TENANT_PROJECT_SCOPING_CONTRACTS_P5_F1_03.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F1-03', $doc);
        $this->assertStringContainsString('TenantProjectRequestResolver', $doc);
        $this->assertStringContainsString('ResolveTenantContext', $doc);
        $this->assertStringContainsString('EnsureTenantContext', $doc);
        $this->assertStringContainsString('tenant_project', $doc);
        $this->assertStringContainsString('subdomain_project', $doc);
        $this->assertStringContainsString('custom_domain_project', $doc);
        $this->assertStringContainsString('projectId', $doc);
        $this->assertStringContainsString('BelongsToTenantProject', $doc);
        $this->assertStringContainsString('TenantContext', $doc);
        $this->assertStringContainsString('RequireSiteEntitlement', $doc);
        $this->assertStringContainsString('P5-F1-04', $doc);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function requestWithRoute(string $method, string $uri, array $parameters): Request
    {
        $request = Request::create('/test', $method);
        $route = new Route($method, $uri, fn () => 'ok');
        $route->bind($request);

        foreach ($parameters as $key => $value) {
            $route->setParameter($key, $value);
        }

        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
