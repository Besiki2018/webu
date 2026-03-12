<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderUniversalBuilderAiRoutingAcceptanceSle05SyncTest extends TestCase
{
    public function test_sle_05_audit_doc_locks_universal_builder_ai_routing_acceptance_reconciliation_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_UNIVERSAL_BUILDER_AI_ROUTING_ACCEPTANCE_RECONCILIATION_AUDIT_SLE_05_2026_02_25.md');

        $componentCoverageDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md');
        $componentAcceptanceDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');
        $api05DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');
        $api06DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md');
        $sle04DocPath = base_path('docs/qa/WEBU_UNIVERSAL_PLATFORM_CAPABILITY_COVERAGE_DEPENDENCY_AUDIT_SLE_04_2026_02_25.md');

        $componentAcceptanceLockTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecAcceptanceCriteriaCoverageTest.php');
        $componentLibraryActivationLockTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $bindingNamespaceLockTestPath = base_path('tests/Unit/UniversalBindingNamespaceCompatibilityP5F5Test.php');
        $industryMappingLockTestPath = base_path('tests/Unit/UniversalAiIndustryComponentMappingP5F5Test.php');
        $api05SyncTestPath = base_path('tests/Unit/BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php');
        $api06SyncTestPath = base_path('tests/Unit/BackendBuilderAiWebsiteGenerationFlowApi06SyncTest.php');
        $sle04SyncTestPath = base_path('tests/Unit/BackendBuilderUniversalPlatformCapabilityCoverageDependencySle04SyncTest.php');

        $cmsPagePath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aiPageGenerationServiceTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');
        $aiFeatureSpecParserTestPath = base_path('tests/Unit/CmsAiFeatureSpecParserTest.php');
        $aiLearningAcceptanceTestPath = base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php');
        $templateStorefrontRouteMatrixTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $builderRuntimeContractsTestPath = base_path('tests/Unit/BuilderCmsRuntimeScriptContractsTest.php');
        $previewPublishAlignmentTestPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');
        $bookingAcceptanceTestPath = base_path('tests/Feature/Booking/BookingAcceptanceTest.php');
        $formsLeadsApiTestPath = base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceAdvancedAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceAdvancedAcceptanceTest.php');
        $portfolioModuleLockTestPath = base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php');
        $blogModuleLockTestPath = base_path('tests/Unit/UniversalBlogContentModuleSummaryP5Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $componentCoverageDocPath,
            $componentAcceptanceDocPath,
            $api05DocPath,
            $api06DocPath,
            $sle04DocPath,
            $componentAcceptanceLockTestPath,
            $componentLibraryActivationLockTestPath,
            $bindingNamespaceLockTestPath,
            $industryMappingLockTestPath,
            $api05SyncTestPath,
            $api06SyncTestPath,
            $sle04SyncTestPath,
            $cmsPagePath,
            $aiPageGenerationServiceTestPath,
            $aiFeatureSpecParserTestPath,
            $aiLearningAcceptanceTestPath,
            $templateStorefrontRouteMatrixTestPath,
            $builderRuntimeContractsTestPath,
            $previewPublishAlignmentTestPath,
            $bookingAcceptanceTestPath,
            $formsLeadsApiTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceAdvancedAcceptanceTestPath,
            $portfolioModuleLockTestPath,
            $blogModuleLockTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $componentCoverageDoc = File::get($componentCoverageDocPath);
        $componentAcceptanceDoc = File::get($componentAcceptanceDocPath);
        $componentAcceptanceLockTest = File::get($componentAcceptanceLockTestPath);
        $industryMappingLockTest = File::get($industryMappingLockTestPath);
        $cmsPage = File::get($cmsPagePath);
        $aiPageGenerationServiceTest = File::get($aiPageGenerationServiceTestPath);
        $aiFeatureSpecParserTest = File::get($aiFeatureSpecParserTestPath);
        $templateStorefrontRouteMatrixTest = File::get($templateStorefrontRouteMatrixTestPath);
        $bookingAcceptanceTest = File::get($bookingAcceptanceTestPath);
        $formsLeadsApiTest = File::get($formsLeadsApiTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);
        $ecommerceAdvancedAcceptanceTest = File::get($ecommerceAdvancedAcceptanceTestPath);
        $portfolioModuleLockTest = File::get($portfolioModuleLockTestPath);
        $blogModuleLockTest = File::get($blogModuleLockTestPath);

        // Source anchors for SLE-05 slice.
        foreach ([
            '13. UNIVERSAL BUILDER COMPONENT LIBRARY',
            '14. UNIVERSAL AI WEBSITE GENERATOR',
            '15. ROUTING',
            '16. ACCEPTANCE CRITERIA',
            'User can create:',
            'Ecommerce website',
            'Service website',
            'Booking website',
            'Portfolio website',
            'Company website',
            'Booking works',
            'Forms work',
            'Payments work',
            'Builder works',
            'AI generator works',
            'FINAL RESULT',
            'Universal Website Builder',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence + icon notes.
        $this->assertStringContainsString('- `SLE-05` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_UNIVERSAL_BUILDER_AI_ROUTING_ACCEPTANCE_RECONCILIATION_AUDIT_SLE_05_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderUniversalBuilderAiRoutingAcceptanceSle05SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` source `13` builder component library list reconciled to canonical Webu builder coverage', $backlog);
        $this->assertStringContainsString('`✅` source `14` AI website generator expectations reconciled to `API-06` pipeline truth', $backlog);
        $this->assertStringContainsString('`✅` source `15` routing examples + source `16` acceptance criteria mapped to evidence/follow-up', $backlog);
        $this->assertStringContainsString('`🧪` targeted evidence batch passed', $backlog);

        // Audit doc structure + truthful findings.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:5463',
            'PROJECT_ROADMAP_TASKS_KA.md:5613',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`SLE-05`)',
            '`SLE-05` is **complete as an audit/verification task**',
            'canonical `webu_*_01` keys',
            '`equivalent`, not source-key `exact`',
            '## Source `13` Universal Builder Component Library Reconciliation',
            'exact = 0',
            'equivalent = 70',
            'partial = 0',
            'missing = 0',
            '## Source `14` Universal AI Website Generator Reconciliation',
            'industry mapping service',
            'API-06',
            '## Source `15` Routing Reconciliation (Examples)',
            '/services',
            '/services/:slug',
            '/book',
            '/portfolio',
            '/blog',
            '/contact',
            'example route intents',
            '## Source `16` Acceptance Criteria Reconciliation',
            'Acceptance Matrix (Evidence or Follow-Up Required)',
            'Portfolio website can be created',
            'Company website can be created',
            'AI generator works',
            '## "FINAL RESULT" Narrative Reconciliation',
            'narrative / strategic positioning',
            '## Non-Blocking Follow-Up (Already Tracked Elsewhere)',
            '`API-02` / `API-03`',
            '`API-05`',
            '`API-06`',
            '`SLE-04`',
            '## DoD Verdict (`SLE-05`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md',
            'Install/tests/Unit/UniversalComponentLibrarySpecAcceptanceCriteriaCoverageTest.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
            'Install/tests/Unit/UniversalBindingNamespaceCompatibilityP5F5Test.php',
            'Install/tests/Unit/UniversalAiIndustryComponentMappingP5F5Test.php',
            'Install/resources/js/Pages/Project/Cms.tsx',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            'Install/tests/Unit/BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php',
            'Install/tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'Install/tests/Unit/BuilderCmsRuntimeScriptContractsTest.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md',
            'Install/tests/Unit/BackendBuilderAiWebsiteGenerationFlowApi06SyncTest.php',
            'Install/tests/Unit/CmsAiPageGenerationServiceTest.php',
            'Install/tests/Unit/CmsAiFeatureSpecParserTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php',
            'Install/docs/qa/WEBU_UNIVERSAL_PLATFORM_CAPABILITY_COVERAGE_DEPENDENCY_AUDIT_SLE_04_2026_02_25.md',
            'Install/tests/Unit/BackendBuilderUniversalPlatformCapabilityCoverageDependencySle04SyncTest.php',
            'Install/tests/Feature/Booking/BookingAcceptanceTest.php',
            'Install/tests/Feature/Forms/FormsLeadsModuleApiTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceAdvancedAcceptanceTest.php',
            'Install/tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalBlogContentModuleSummaryP5Test.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing SLE-05 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing SLE-05 evidence file on disk: {$relativePath}");
        }

        // Component library coverage + acceptance truths.
        $this->assertStringContainsString('Total source-spec component keys audited: `70`', $componentCoverageDoc);
        $this->assertStringContainsString('- `exact`: `0`', $componentCoverageDoc);
        $this->assertStringContainsString('- `equivalent`: `70`', $componentCoverageDoc);
        $this->assertStringContainsString('- `partial`: `0`', $componentCoverageDoc);
        $this->assertStringContainsString('- `missing`: `0`', $componentCoverageDoc);
        $this->assertStringContainsString('Builder shows this library grouped by category', $componentAcceptanceDoc);
        $this->assertStringContainsString('AI can assemble correct industry site automatically', $componentAcceptanceDoc);
        $this->assertStringContainsString('Acceptance Criteria Mapping', $componentAcceptanceLockTest);
        $this->assertStringContainsString('CmsAiPageGenerationServiceTest.php', $componentAcceptanceLockTest);

        // Builder canonical component truths used in SLE-05 reconciliation.
        foreach ([
            'webu_general_section_01',
            'webu_general_heading_01',
            'webu_svc_services_list_01',
            'webu_book_booking_form_01',
            'webu_portfolio_projects_grid_01',
            'webu_blog_post_list_01',
            'webu_realestate_property_grid_01',
            'webu_ecom_product_grid_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_auth_01',
            'webu_ecom_account_profile_01',
            'webu_ecom_account_dashboard_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPage);
        }

        // AI industry mapping + page generation acceptance truths.
        $this->assertStringContainsString('CmsAiIndustryComponentMappingService', $industryMappingLockTest);
        $this->assertStringContainsString("'restaurant'", $industryMappingLockTest);
        $this->assertStringContainsString('business-page fallback', $industryMappingLockTest);
        $this->assertStringContainsString('test_it_maps_restaurant_prompt_and_modules_to_restaurant_plus_booking_component_groups', $industryMappingLockTest);

        $this->assertStringContainsString("'mode' => 'generate_site'", $aiPageGenerationServiceTest);
        $this->assertStringContainsString("'prompt' => 'Generate an ecommerce pet store with cart, checkout, account and order history pages.'", $aiPageGenerationServiceTest);
        $this->assertStringContainsString("foreach (['home', 'shop', 'product', 'cart', 'checkout', 'login', 'account', 'orders', 'order', 'contact'] as \$expected)", $aiPageGenerationServiceTest);
        $this->assertStringContainsString('ecom.productGrid', $aiPageGenerationServiceTest);
        $this->assertStringContainsString('ecom.productDetail', $aiPageGenerationServiceTest);
        $this->assertStringContainsString('hotel.reservationForm', $aiPageGenerationServiceTest);
        $this->assertStringContainsString("'contact_json' => []", $aiPageGenerationServiceTest);
        $this->assertStringContainsString("'ecommerce', 'booking', 'blog', 'services', 'software', 'universal'", $aiFeatureSpecParserTest);

        // Routing example evidence and exactness drift anchors.
        foreach ([
            "ensurePublishedPage(\$site, \$owner, 'shop'",
            "ensurePublishedPage(\$site, \$owner, 'login'",
            "ensurePublishedPage(\$site, \$owner, 'orders'",
            "assertPublishedRouteHtml(\$host, '/shop'",
            "assertPublishedRouteHtml(\$host, '/account/login'",
            "assertPublishedRouteHtml(\$host, '/account/orders'",
            "assertPublishedRouteHtml(\$host, '/account/orders/'.\$orderId",
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontRouteMatrixTest);
        }

        // Acceptance criteria evidence: booking/forms/payments.
        $this->assertStringContainsString("route('public.sites.booking.bookings.store'", $bookingAcceptanceTest);
        $this->assertStringContainsString("->assertJsonPath('booking.status', 'pending')", $bookingAcceptanceTest);
        $this->assertStringContainsString("->assertJsonPath('reason', 'slot_collision')", $bookingAcceptanceTest);

        $this->assertStringContainsString("route('panel.sites.forms.store'", $formsLeadsApiTest);
        $this->assertStringContainsString("route('public.sites.forms.submit'", $formsLeadsApiTest);
        $this->assertStringContainsString('site_form_leads', $formsLeadsApiTest);

        $this->assertStringContainsString('test_checkout_happy_path_and_payment_success_flow', $ecommerceCheckoutAcceptanceTest);
        $this->assertStringContainsString("route('public.sites.ecommerce.carts.checkout'", $ecommerceCheckoutAcceptanceTest);
        $this->assertStringContainsString("route('public.sites.ecommerce.orders.payment.start'", $ecommerceCheckoutAcceptanceTest);
        $this->assertStringContainsString("'/payment-gateways/paypal/webhook'", $ecommerceCheckoutAcceptanceTest);

        $this->assertStringContainsString("route('panel.sites.ecommerce.orders.rs.export'", $ecommerceAdvancedAcceptanceTest);
        $this->assertStringContainsString("'event_type' => 'payment.refunded'", $ecommerceAdvancedAcceptanceTest);
        $this->assertStringContainsString('partially_refunded', $ecommerceAdvancedAcceptanceTest);

        // Portfolio/blog builder-module coverage anchors support website-type acceptance rows.
        $this->assertStringContainsString('CMS portfolio builder component coverage contracts', $portfolioModuleLockTest);
        $this->assertStringContainsString('webu_portfolio_projects_grid_01', $portfolioModuleLockTest);
        $this->assertStringContainsString('Blog/content', $blogModuleLockTest);
        $this->assertStringContainsString('/panel/sites/{site}/blog-posts', $blogModuleLockTest);
    }
}
