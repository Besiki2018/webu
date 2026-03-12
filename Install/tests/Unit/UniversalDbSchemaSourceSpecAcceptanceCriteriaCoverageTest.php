<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest extends TestCase
{
    public function test_source_spec_acceptance_criteria_are_mapped_to_existing_automated_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_SOURCE_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');

        $evidencePaths = [
            base_path('tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php'),
            base_path('tests/Unit/UniversalDemoProjectTypeSeedersDeliverableTest.php'),
            base_path('tests/Feature/Templates/TemplateProvisioningSmokeTest.php'),
            base_path('tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php'),
            base_path('tests/Feature/Cms/CmsPagesManagementTest.php'),
            base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php'),
            base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php'),
            base_path('tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php'),
            base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php'),
            base_path('tests/Feature/Booking/BookingPublicApiTest.php'),
            base_path('tests/Feature/Booking/BookingTeamSchedulingTest.php'),
            base_path('tests/Feature/Booking/BookingAcceptanceTest.php'),
            base_path('tests/Feature/Booking/BookingAdvancedAcceptanceTest.php'),
            base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php'),
            base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php'),
            base_path('tests/Feature/Cms/CmsBlogPostsManagementTest.php'),
            base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php'),
            base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
            base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'),
            base_path('tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php'),
            base_path('tests/Unit/UniversalRestaurantModuleComponentsP5F4Test.php'),
            base_path('tests/Unit/UniversalHotelModuleComponentsP5F4Test.php'),
            base_path('tests/Feature/Cms/TenantIsolationTest.php'),
            base_path('tests/Unit/UniversalTenantProjectScopingContractP5F1Test.php'),
            base_path('tests/Feature/Security/TenantProjectRouteScopingMiddlewareTest.php'),
            base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts'),
            base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md'),
            base_path('docs/qa/UNIVERSAL_DEMO_PROJECT_TYPE_SEEDERS_DELIVERABLE_BASELINE.md'),
            base_path('docs/openapi/README.md'),
            base_path('docs/qa/UNIVERSAL_SOFT_DELETE_STRATEGY_OPTIONAL_V1.md'),
        ];

        foreach (array_merge([$roadmapPath, $docPath], $evidencePaths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        foreach ([
            '# Acceptance Criteria',
            'Can create tenant → create project → publish pages',
            'For ecommerce project: products/cart/orders/payments work',
            'For service/booking project: services/staff/availability/bookings work',
            'For portfolio/company: portfolio/blog/forms work',
            'All modules are builder-ready via APIs',
            'No cross-tenant data leakage',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6429', $doc);
        $this->assertStringContainsString('Acceptance Criteria Mapping', $doc);
        $this->assertStringContainsString('Can create tenant -> create project -> publish pages', $doc);
        $this->assertStringContainsString('For ecommerce project: products/cart/orders/payments work', $doc);
        $this->assertStringContainsString('For service/booking project: services/staff/availability/bookings work', $doc);
        $this->assertStringContainsString('For portfolio/company: portfolio/blog/forms work', $doc);
        $this->assertStringContainsString('All modules are builder-ready via APIs', $doc);
        $this->assertStringContainsString('No cross-tenant data leakage', $doc);

        foreach ([
            'TemplateProvisioningSmokeTest.php',
            'TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'EcommerceCheckoutAcceptanceTest.php',
            'BookingAcceptanceTest.php',
            'CmsBlogPostsManagementTest.php',
            'FormsLeadsModuleApiTest.php',
            'MinimalOpenApiBaseModulesDeliverableTest.php',
            'UniversalComponentLibraryActivationP5F5Test.php',
            'TenantIsolationTest.php',
            'UniversalTenantProjectScopingContractP5F1Test.php',
            'CmsAiGenerationLearningAcceptanceTest.php',
            'CmsPortfolioBuilderCoverage.contract.test.ts',
            'UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md',
            'UNIVERSAL_SOFT_DELETE_STRATEGY_OPTIONAL_V1.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $this->assertStringContainsString('**covered by automated evidence**', $doc);
    }
}
