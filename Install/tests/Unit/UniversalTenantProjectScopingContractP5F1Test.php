<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalTenantProjectScopingContractP5F1Test extends TestCase
{
    public function test_p5_f1_03_scoping_contract_doc_and_route_rollout_are_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_TENANT_PROJECT_SCOPING_CONTRACT_P5_F1_03.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $bootstrap = File::get(base_path('bootstrap/app.php'));
        $routes = File::get(base_path('routes/web.php'));

        $this->assertStringContainsString('P5-F1-03', $doc);
        $this->assertStringContainsString('TenantProjectRouteScopeValidatorContract', $doc);
        $this->assertStringContainsString('EnforceTenantProjectRouteScope', $doc);
        $this->assertStringContainsString('tenant.route.scope', $doc);
        $this->assertStringContainsString('tenant_scope_route_binding_mismatch', $doc);
        $this->assertStringContainsString('CMS', $doc);
        $this->assertStringContainsString('Ecommerce', $doc);
        $this->assertStringContainsString('Booking', $doc);

        $this->assertStringContainsString("'tenant.route.scope' => \\App\\Http\\Middleware\\EnforceTenantProjectRouteScope::class", $bootstrap);
        $this->assertStringContainsString("Route::middleware(['auth', 'verified', 'tenant.route.scope'])->prefix('panel/sites/{site}')", $routes);
    }
}

