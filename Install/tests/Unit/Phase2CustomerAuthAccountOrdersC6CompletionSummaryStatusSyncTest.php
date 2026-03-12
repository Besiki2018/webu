<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase2CustomerAuthAccountOrdersC6CompletionSummaryStatusSyncTest extends TestCase
{
    public function test_phase2_c6_detailed_lines_match_builder_route_and_fallback_state_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE2_AUTH_ACCOUNT_ORDERS_C6_COMPLETION_SUMMARY.md');
        $cmsPagePath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $c6ContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts');
        $storefrontSmokeTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $cmsPagePath,
            $builderCoverageContractPath,
            $c6ContractPath,
            $storefrontSmokeTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $cmsPage = File::get($cmsPagePath);
        $builderCoverageContract = File::get($builderCoverageContractPath);
        $c6Contract = File::get($c6ContractPath);
        $storefrontSmokeTest = File::get($storefrontSmokeTestPath);

        $this->assertStringContainsString("`P2-C6-01` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C6-02` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C6-03` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C6-04` (✅ `DONE`)", $roadmap);

        // C6 summary doc includes all detailed lines.
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:398', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:399', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:400', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:401', $summaryDoc);
        $this->assertStringContainsString('Scope Note', $summaryDoc);
        $this->assertStringContainsString('builder-native component scaffolds', $summaryDoc);

        // Builder keys + markers remain covered.
        $this->assertStringContainsString('webu_ecom_auth_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_account_dashboard_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_orders_list_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_order_detail_01', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-auth', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-account-dashboard', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-orders-list', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-order-detail', $builderCoverageContract);

        // C6-specific auth toggle inheritance + unauthorized fallback UX states.
        $this->assertStringContainsString('use_backend_auth_settings', $cmsPage);
        $this->assertStringContainsString('resolveEcomAuthBackendFeatureToggles', $cmsPage);
        $this->assertStringContainsString('allow_register', $cmsPage);
        $this->assertStringContainsString('otp_enabled', $cmsPage);
        $this->assertStringContainsString('social_login_enabled', $cmsPage);
        $this->assertStringContainsString('unauthorized_title', $cmsPage);
        $this->assertStringContainsString('unauthorized_cta_url', $cmsPage);
        $this->assertStringContainsString('data-webu-role-state="unauthorized"', $cmsPage);
        $this->assertStringContainsString('CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts', $summaryDoc);
        $this->assertStringContainsString('use_backend_auth_settings', $c6Contract);
        $this->assertStringContainsString('unauthorized_title', $c6Contract);

        // Published storefront route pack coverage for login/account/orders pages.
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/account/login'", $storefrontSmokeTest);
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/account'", $storefrontSmokeTest);
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/account/orders'", $storefrontSmokeTest);
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/account/orders/'.\$orderId", $storefrontSmokeTest);
    }
}
