<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderUniversalPlatformCapabilityCoverageDependencySle04SyncTest extends TestCase
{
    public function test_sle_04_audit_doc_locks_universal_platform_capability_coverage_and_dependency_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_UNIVERSAL_PLATFORM_CAPABILITY_COVERAGE_DEPENDENCY_AUDIT_SLE_04_2026_02_25.md');

        $projectModelPath = base_path('app/Models/Project.php');
        $routesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $publicFormControllerPath = base_path('app/Http/Controllers/Cms/PublicFormController.php');
        $moduleRegistryPath = base_path('app/Cms/Services/CmsModuleRegistryService.php');
        $featureFlagsPath = base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');

        $migrationPaths = [
            base_path('database/migrations/2026_02_24_236500_add_universal_core_columns_to_projects_table.php'),
            base_path('database/migrations/2026_02_24_237000_create_universal_customer_auth_tables.php'),
            base_path('database/migrations/2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php'),
            base_path('database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php'),
            base_path('database/migrations/2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php'),
        ];

        $platformSchemaTests = [
            base_path('tests/Feature/Platform/UniversalCoreTenantAccessFoundationSchemaTest.php'),
            base_path('tests/Feature/Platform/UniversalContentNormalizationTablesSchemaTest.php'),
            base_path('tests/Feature/Platform/UniversalCustomerAuthTablesSchemaTest.php'),
            base_path('tests/Feature/Platform/UniversalServicesNormalizationTablesSchemaTest.php'),
            base_path('tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php'),
            base_path('tests/Feature/Platform/UniversalPaymentsShippingNormalizationTablesSchemaTest.php'),
        ];

        $contractTests = [
            base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php'),
            base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php'),
            base_path('tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php'),
            base_path('tests/Unit/UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest.php'),
        ];

        $featureEvidence = [
            base_path('tests/Feature/Cms/CmsPagesManagementTest.php'),
            base_path('tests/Feature/Cms/CmsBlogPostsManagementTest.php'),
            base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php'),
            base_path('tests/Feature/Booking/BookingPublicApiTest.php'),
            base_path('tests/Feature/Booking/BookingPanelCrudTest.php'),
            base_path('tests/Feature/Booking/BookingFinanceLedgerTest.php'),
            base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php'),
            base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php'),
            base_path('tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php'),
        ];

        $frontendContracts = [
            base_path('resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsRealEstateBuilderCoverage.contract.test.ts'),
        ];

        $acceptanceCoverageDocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_SOURCE_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');

        foreach (array_merge([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $projectModelPath,
            $routesPath,
            $publicSiteControllerPath,
            $publicFormControllerPath,
            $moduleRegistryPath,
            $featureFlagsPath,
            $cmsPath,
            $acceptanceCoverageDocPath,
        ], $migrationPaths, $platformSchemaTests, $contractTests, $featureEvidence, $frontendContracts) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $projectModel = File::get($projectModelPath);
        $routes = File::get($routesPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $publicFormController = File::get($publicFormControllerPath);
        $moduleRegistry = File::get($moduleRegistryPath);
        $featureFlags = File::get($featureFlagsPath);
        $cms = File::get($cmsPath);
        $coreSchemaTest = File::get($platformSchemaTests[0]);
        $contentSchemaTest = File::get($platformSchemaTests[1]);
        $customerSchemaTest = File::get($platformSchemaTests[2]);
        $servicesSchemaTest = File::get($platformSchemaTests[3]);
        $verticalSchemaTest = File::get($platformSchemaTests[4]);
        $paymentsSchemaTest = File::get($platformSchemaTests[5]);
        $servicesBookingContract = File::get($contractTests[0]);
        $portfolioContract = File::get($contractTests[1]);
        $realEstateContract = File::get($contractTests[2]);
        $acceptanceCoverageTest = File::get($contractTests[3]);
        $acceptanceCoverageDoc = File::get($acceptanceCoverageDocPath);

        foreach ([
            'CORE CONCEPT — BUSINESS OBJECT MODEL',
            'DATABASE CORE',
            'UNIVERSAL CONTENT SYSTEM',
            'UNIVERSAL CUSTOMER SYSTEM',
            'UNIVERSAL SERVICES MODULE',
            'BOOKING SYSTEM (CRITICAL)',
            'STAFF / TEAM SYSTEM',
            'PORTFOLIO SYSTEM',
            'REAL ESTATE SYSTEM',
            'BLOG SYSTEM',
            'FORM SYSTEM (LEAD GENERATION)',
            'PAYMENT SYSTEM (UNIVERSAL)',
            'GET /pages/{slug}',
            'GET /menus',
            'GET /services',
            'POST /bookings',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        $this->assertStringContainsString('- `SLE-04` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_UNIVERSAL_PLATFORM_CAPABILITY_COVERAGE_DEPENDENCY_AUDIT_SLE_04_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderUniversalPlatformCapabilityCoverageDependencySle04SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` universal capability coverage matrix by module audited', $backlog);
        $this->assertStringContainsString('`✅` builder/API/schema dependency ownership map reconciled', $backlog);
        $this->assertStringContainsString('`✅` partial/gap rows (portfolio/real-estate/backend parity) truthfully classified', $backlog);
        $this->assertStringContainsString('`🧪` targeted evidence batch passed', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:5105',
            'PROJECT_ROADMAP_TASKS_KA.md:5462',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`SLE-04`)',
            '`SLE-04` is **complete as an audit/verification task**',
            '## Module Coverage Matrix (Implemented / Partial / Missing + Owning Subsystem)',
            '## Builder/API Dependencies Map (By Capability Cluster)',
            '## Source-to-Runtime Drift Notes (Truthful Reconciliation)',
            '## Cross-Slice Acceptance Coverage Reuse',
            '## DoD Verdict (`SLE-04`)',
            '1) Core Concept — Business Object Model',
            '3) Universal Content System',
            '6) Booking System (Critical)',
            '8) Portfolio System',
            '9) Real Estate System',
            '12) Payment System (Universal)',
            'partial (additive convergence)',
            'partial (capability yes, path parity mixed)',
            'partial (normalized field names)',
            'partial (core + ecommerce/booking strong)',
            'GET /menus',
            '`/{site}/menu/{key}`',
            'Normalized field drift',
            'location_text',
            'description_html',
            'property_images',
            'distributed universal platform baseline',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/database/migrations/2026_02_24_236500_add_universal_core_columns_to_projects_table.php',
            'Install/database/migrations/2026_02_24_237000_create_universal_customer_auth_tables.php',
            'Install/database/migrations/2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php',
            'Install/database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php',
            'Install/database/migrations/2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php',
            'Install/tests/Feature/Platform/UniversalCoreTenantAccessFoundationSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalContentNormalizationTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalCustomerAuthTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalServicesNormalizationTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalPaymentsShippingNormalizationTablesSchemaTest.php',
            'Install/tests/Unit/UniversalServicesBookingContractsP5F3Test.php',
            'Install/tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest.php',
            'Install/tests/Feature/Cms/CmsPagesManagementTest.php',
            'Install/tests/Feature/Cms/CmsBlogPostsManagementTest.php',
            'Install/tests/Feature/Forms/FormsLeadsModuleApiTest.php',
            'Install/tests/Feature/Booking/BookingPublicApiTest.php',
            'Install/tests/Feature/Booking/BookingPanelCrudTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php',
            'Install/resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts',
            'Install/resources/js/Pages/Project/__tests__/CmsRealEstateBuilderCoverage.contract.test.ts',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc);
            $this->assertFileExists(base_path('../'.$relativePath));
        }

        // Core/model/controller/route anchors.
        foreach (['tenant_id', 'type', 'default_currency', 'default_locale', 'timezone'] as $needle) {
            $this->assertStringContainsString("'{$needle}'", $projectModel);
        }

        $this->assertStringContainsString('public function typography(Request $request, Site $site): JsonResponse', $publicSiteController);
        $this->assertStringContainsString('public function menu(Request $request, Site $site, string $key): JsonResponse', $publicSiteController);
        $this->assertStringContainsString('public function page(Request $request, Site $site, string $slug): JsonResponse', $publicSiteController);
        $this->assertStringContainsString('public function submit(Request $request, Site $site, string $key): JsonResponse', $publicFormController);

        foreach ([
            "name('public.sites.menu')",
            "name('public.sites.page')",
            "name('public.sites.forms.submit')",
            "name('public.sites.booking.services')",
            "name('public.sites.booking.slots')",
            "name('public.sites.booking.bookings.store')",
            "name('public.sites.ecommerce.payment.options')",
            "name('public.sites.ecommerce.orders.payment.start')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        // Builder + vertical module anchors.
        $this->assertStringContainsString("public const MODULE_PORTFOLIO = 'portfolio';", $moduleRegistry);
        $this->assertStringContainsString("public const MODULE_REAL_ESTATE = 'real_estate';", $moduleRegistry);
        $this->assertStringContainsString("'portfolio' => [", $featureFlags);
        $this->assertStringContainsString("'real_estate' => [", $featureFlags);
        $this->assertStringContainsString('BUILDER_BOOKING_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('BUILDER_PORTFOLIO_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('BUILDER_REAL_ESTATE_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('webu_book_booking_form_01', $cms);
        $this->assertStringContainsString('webu_blog_post_list_01', $cms);
        $this->assertStringContainsString('webu_blog_post_detail_01', $cms);
        $this->assertStringContainsString('webu_general_form_wrapper_01', $cms);
        $this->assertStringContainsString('data-webby-portfolio-projects', $cms);
        $this->assertStringContainsString('data-webby-realestate-properties', $cms);

        // Schema and contract test anchors proving capability presence.
        $this->assertStringContainsString('tenant_id', $coreSchemaTest);
        $this->assertStringContainsString('menu_items', $contentSchemaTest);
        $this->assertStringContainsString('post_categories', $contentSchemaTest);
        $this->assertStringContainsString('customers', $customerSchemaTest);
        $this->assertStringContainsString('otp_requests', $customerSchemaTest);
        $this->assertStringContainsString('services', $servicesSchemaTest);
        $this->assertStringContainsString('staff', $servicesSchemaTest);
        $this->assertStringContainsString('availability_rules', $servicesSchemaTest);
        $this->assertStringContainsString('portfolio_items', $verticalSchemaTest);
        $this->assertStringContainsString('properties', $verticalSchemaTest);
        $this->assertStringContainsString('payments', $paymentsSchemaTest);
        $this->assertStringContainsString('payment_webhooks', $paymentsSchemaTest);
        $this->assertStringContainsString('shipping_methods', $paymentsSchemaTest);

        $this->assertStringContainsString("name('public.sites.booking.slots')", $servicesBookingContract);
        $this->assertStringContainsString("name('public.sites.booking.bookings.store')", $servicesBookingContract);
        $this->assertStringContainsString('MODULE_PORTFOLIO', $portfolioContract);
        $this->assertStringContainsString('CmsPortfolioBuilderCoverage.contract.test.ts', $portfolioContract);
        $this->assertStringContainsString('MODULE_REAL_ESTATE', $realEstateContract);
        $this->assertStringContainsString('CmsRealEstateBuilderCoverage.contract.test.ts', $realEstateContract);

        // Cross-slice acceptance mapping reuse.
        $this->assertStringContainsString('Acceptance Criteria Mapping', $acceptanceCoverageDoc);
        $this->assertStringContainsString('All modules are builder-ready via APIs', $acceptanceCoverageTest);
        $this->assertStringContainsString('No cross-tenant data leakage', $acceptanceCoverageTest);
    }
}
