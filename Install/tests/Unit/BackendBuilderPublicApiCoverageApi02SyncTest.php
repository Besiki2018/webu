<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderPublicApiCoverageApi02SyncTest extends TestCase
{
    public function test_api_02_public_api_coverage_audit_doc_maps_spec_endpoints_to_runtime_or_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');
        $webRoutesPath = base_path('routes/web.php');
        $openApiPublicCorePath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');
        $openApiEcommercePath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');

        foreach ([$roadmapPath, $backlogPath, $docPath, $webRoutesPath, $openApiPublicCorePath, $openApiEcommercePath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $webRoutes = File::get($webRoutesPath);
        $openApiPublicCore = File::get($openApiPublicCorePath);
        $openApiEcommerce = File::get($openApiEcommercePath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Backend → Builder Integration Contract (Exact API Spec v1)', $roadmap);
        $this->assertStringContainsString('2) Public Settings (Builder needs this first)', $roadmap);
        $this->assertStringContainsString('7) Shipping APIs', $roadmap);
        $this->assertStringContainsString('8) Checkout + Orders APIs', $roadmap);

        $this->assertStringContainsString('- `API-02` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderPublicApiCoverageApi02SyncTest.php', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:2161',
            'PROJECT_ROADMAP_TASKS_KA.md:2459',
            '## Endpoint Coverage Matrix (Spec 2.x → 7.x)',
            '`GET /public/settings`',
            '`GET /pages/{slug}`',
            '`GET /menus`',
            '`GET /theme`',
            '`GET /products`',
            '`GET /products/{slug}`',
            '`GET /categories`',
            '`GET /cart`',
            '`POST /cart/items`',
            '`PUT /cart/items/{id}`',
            '`DELETE /cart/items/{id}`',
            '`POST /coupons/apply`',
            '`POST /coupons/remove`',
            '`POST /shipping/calc`',
            '## Payload / Error Examples Verification',
            'cart_identity_mismatch',
            'shipping_not_enabled',
            'courier_provider_not_allowed',
            '## Gaps / Follow-up (From API-02 Audit)',
            'No dedicated public categories endpoint',
            'No aggregate public menus endpoint',
            '`API-02` is complete as an audit task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $anchors = [
            'Install/routes/web.php',
            'Install/app/Http/Controllers/Cms/PublicSiteController.php',
            'Install/app/Http/Controllers/Ecommerce/PublicStorefrontController.php',
            'Install/app/Cms/Services/CmsPublicSiteService.php',
            'Install/docs/openapi/webu-public-core-minimal.v1.openapi.yaml',
            'Install/docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml',
            'Install/tests/Feature/Cms/CmsLocalizationTest.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/tests/Feature/Cms/CmsTypographyContractTest.php',
            'Install/tests/Feature/Cms/TenantIsolationTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php',
            'Install/tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing evidence anchor in API-02 doc: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing evidence file on disk: {$relativePath}");
        }

        // Current runtime/public route truth used for gap classifications.
        $this->assertStringContainsString("Route::get('/{site}/menu/{key}'", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/ecommerce/products'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/coupon'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/shipping/options'", $webRoutes);
        $this->assertStringNotContainsString("public.sites.ecommerce.categories", $webRoutes);

        // Minimal OpenAPI docs also reflect current route coverage (and the categories gap).
        $this->assertStringContainsString('/public/sites/{site}/menu/{key}:', $openApiPublicCore);
        $this->assertStringContainsString('/public/sites/{site}/ecommerce/products:', $openApiEcommerce);
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/categories:', $openApiEcommerce);
    }
}
