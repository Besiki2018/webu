<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderApiCoreContractApi01SyncTest extends TestCase
{
    public function test_api_01_core_contract_audit_doc_has_evidence_for_scoping_headers_and_envelope_drift(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_API_CORE_CONTRACT_AUDIT_API_01_2026_02_25.md');

        foreach ([$roadmapPath, $docPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Backend → Builder Integration Contract (Exact API Spec v1)', $roadmap);
        $this->assertStringContainsString('# 0) Core Concepts', $roadmap);
        $this->assertStringContainsString('# 1) Standard Response Format', $roadmap);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:2065', $doc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:2161', $doc);
        $this->assertStringContainsString('`API-01`', $doc);

        foreach ([
            '## Canonical Scoping Decision (Verified Runtime Baseline)',
            'path-scoped site route binding',
            '/public/sites/resolve',
            '/public/sites/{site}/...',
            'preview/editor flows use CMS bridge bootstrap',
            '## Header Contract Audit (Spec vs Runtime)',
            'X-Store-Id',
            'X-Request-Id',
            'Idempotency-Key',
            'X-Webu-Trace-Id',
            '## Envelope / Error-Code Contract Audit',
            'no single source-of-truth envelope is currently enforced',
            'tenant_scope_route_binding_mismatch',
            'cart_identity_mismatch',
            'BuilderFirestoreController',
            '## Result',
            '`API-01` is complete as an audit task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $anchors = [
            // Routes/docs scoping evidence
            'Install/routes/web.php',
            'Install/routes/api.php',
            'Install/docs/openapi/webu-public-core-minimal.v1.openapi.yaml',
            'Install/docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml',
            'Install/docs/openapi/README.md',
            'Install/docs/cms-frontend-contract.md',

            // Runtime/header/scoping implementation evidence
            'Install/app/Http/Middleware/EnforceTenantProjectRouteScope.php',
            'Install/app/Http/Middleware/VerifyProjectToken.php',
            'Install/app/Http/Middleware/CapturePublicApiObservabilityTelemetry.php',
            'Install/app/Services/ProjectOperationGuardService.php',
            'Install/app/Http/Controllers/Cms/PublicSiteController.php',
            'Install/app/Http/Controllers/Cms/PublicFormController.php',
            'Install/app/Http/Controllers/Ecommerce/PublicStorefrontController.php',
            'Install/app/Http/Controllers/Api/BuilderFirestoreController.php',

            // Tests that lock current behavior
            'Install/tests/Feature/Security/TenantProjectRouteScopingMiddlewareTest.php',
            'Install/tests/Unit/TenantProjectRouteScopeValidatorServiceTest.php',
            'Install/tests/Unit/UniversalTenantProjectScopingContractP5F1Test.php',
            'Install/tests/Unit/BuilderCmsRuntimeScriptContractsTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiTest.php',
            'Install/tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing audit evidence anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing evidence file on disk: {$relativePath}");
        }

        // Lock the doc's stated envelope drift classifications.
        $this->assertStringContainsString('public CMS/ecommerce endpoints mostly return domain-shaped payloads (no global `success/data/meta` wrapper)', $doc);
        $this->assertStringContainsString('scope middleware emits standardized JSON error payload with `code` + `violations`', $doc);
        $this->assertStringContainsString('mixed `{success: bool, ...}` patterns exist in other APIs', $doc);
        $this->assertStringContainsString('OpenAPI minimal docs are route-coverage oriented and do not define shared success/error components', $doc);

        // Lock follow-up drift statements to avoid silent regressions in API-01 truthfulness.
        foreach ([
            'Spec `store` terminology vs runtime `site` terminology mismatch remains',
            'Spec headers `X-Store-Id`, `X-Request-Id`, `X-Locale`, `X-Currency` are not standardized across current runtime',
            'No unified global API envelope middleware/transformer is enforced across public + panel + builder APIs',
        ] as $driftLine) {
            $this->assertStringContainsString($driftLine, $doc);
        }
    }
}
