<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceComponentSpecAcceptanceCriteriaVerificationEcm04SyncTest extends TestCase
{
    public function test_ecm_04_acceptance_criteria_verification_audit_locks_pass_fail_matrix_and_follow_up_links_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_ACCEPTANCE_CRITERIA_VERIFICATION_AUDIT_ECM_04_2026_02_25.md');

        $ecm01DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md');
        $ecm02DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md');
        $ecm03DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_SHARED_UI_SUBCOMPONENTS_DELIVERABLES_RECONCILIATION_AUDIT_ECM_03_2026_02_25.md');
        $rs0101DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md');
        $rs0502DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $rs1301DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');
        $api03DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $api05DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $previewPublishAlignmentTestPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');
        $templateStorefrontE2ePath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $ecm01DocPath,
            $ecm02DocPath,
            $ecm03DocPath,
            $rs0101DocPath,
            $rs0502DocPath,
            $rs0503DocPath,
            $rs1301DocPath,
            $api03DocPath,
            $api05DocPath,
            $cmsPath,
            $builderServicePath,
            $ecommerceCoverageContractPath,
            $previewPublishAlignmentTestPath,
            $templateStorefrontE2ePath,
            $ecommercePublicApiTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $ecm01Doc = File::get($ecm01DocPath);
        $ecm02Doc = File::get($ecm02DocPath);
        $ecm03Doc = File::get($ecm03DocPath);
        $rs0101Doc = File::get($rs0101DocPath);
        $rs0502Doc = File::get($rs0502DocPath);
        $rs0503Doc = File::get($rs0503DocPath);
        $rs1301Doc = File::get($rs1301DocPath);
        $api03Doc = File::get($api03DocPath);
        $api05Doc = File::get($api05DocPath);

        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $previewPublishAlignmentTest = File::get($previewPublishAlignmentTestPath);
        $templateStorefrontE2e = File::get($templateStorefrontE2ePath);
        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);

        foreach ([
            '# 16) Acceptance Criteria',
            'All components render with mock data in builder and real API in preview/publish',
            'All components have Content/Style/Advanced',
            'Responsive columns work',
            'Add-to-cart works end-to-end',
            'Checkout creates order and starts payment init',
            'Auth supports enabled methods per store',
            'Orders list/detail work for logged-in customers',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `ECM-04` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_ACCEPTANCE_CRITERIA_VERIFICATION_AUDIT_ECM_04_2026_02_25.md',
            'UniversalComponentLibraryEcommerceComponentSpecAcceptanceCriteriaVerificationEcm04SyncTest.php',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_SHARED_UI_SUBCOMPONENTS_DELIVERABLES_RECONCILIATION_AUDIT_ECM_03_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            '`✅` source `#16` acceptance criteria are reconciled into an explicit `pass/fail` evidence matrix with linked code/docs/tests and follow-up owners for every row',
            '`✅` `Add-to-cart works end-to-end` and `Checkout creates order and starts payment init` are marked `pass` with storefront API + checkout acceptance evidence',
            '`✅` `ECM-04` is closed truthfully as a verification task because DoD requires criterion-by-criterion evidence/follow-up linkage (not all criteria passing today)',
            '`⚠️` matrix result is mixed (`pass=2`, `fail=5`) due open runtime/API/component parity gaps already tracked in `ECM-01`, `ECM-02`, `RS-01-01`, `RS-05-03`, `RS-13-01`, `API-03`, and `API-05`',
            '`⚠️` failed rows often have strong builder-preview or route-shell baselines, but do not satisfy full source wording (`all components`, customer auth/order runtime parity, or default demo-page completeness)',
            '`🧪` ECM-04 verification sync lock added (acceptance matrix + evidence/follow-up linkage closure state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `ECM-04` Can Be `DONE`)',
            '## Acceptance Criteria Verification Matrix (Source `16`)',
            '### Matrix (`pass/fail` + evidence + follow-up)',
            '## Acceptance Summary (`ECM-04`)',
            '- `pass`: `2`',
            '- `fail`: `5`',
            '- `unmapped`: `0`',
            '## Criterion Coverage Notes (Why `ECM-04` Is Closed Despite Fails)',
            '## DoD Verdict (`ECM-04`)',
            'DoD: every criterion has linked evidence or follow-up task.',
            'Result: `PASS`',
            'Conclusion: `ECM-04` is `DONE` as an acceptance verification/reconciliation task.',
            '## Follow-up Mapping (Implementation Work Still Open)',
            '`ECM-01`',
            '`ECM-02`',
            '`RS-01-01`',
            '`RS-13-01`',
            '`API-05`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '| `All components render with mock data in builder and real API in preview/publish` | `fail` |',
            '| `All components have Content/Style/Advanced` | `fail` |',
            '| `Responsive columns work` | `fail` |',
            '| `Add-to-cart works end-to-end` | `pass` |',
            '| `Checkout creates order and starts payment init` | `pass` |',
            '| `Auth supports enabled methods per store` | `fail` |',
            '| `Orders list/detail work for logged-in customers` | `fail` |',
            'published `BuilderService` widget auto-mount is still narrow (`products/cart`)',
            'OTP endpoints missing',
            '/orders/my`, `/orders/{id}`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Published runtime widget contract is not yet reusable across the full ecommerce component set (`products/cart` are mounted; many others are preview-only/runtime-gap at widget-hook level).',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm01Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '13 partial / 0 implemented / 0 missing',
            'published runtime `BuilderService` ecommerce widget auto-mount contract remains narrow (`products` + `cart`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm02Doc);
        }

        foreach ([
            'Status: `DONE`',
            '### Inventory Summary',
            '### Deliverables Summary',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm03Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'responsive behavior evidence (desktop/tablet/mobile) | `partial`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0101Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'add-to-cart and coupon apply/remove backend scenarios are implemented and feature-tested (`EcommercePublicApiTest.php`)',
            'Conclusion: `RS-05-02` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0502Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '/checkout/validate',
            'no public `/orders/my`',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0503Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Auth Method Gating Matrix (Email/Password, SMS OTP, Social)',
            'OTP verify endpoint missing',
            'Conclusion: `RS-13-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs1301Doc);
        }

        foreach ([
            'no dedicated endpoint; validation/precondition checks happen inside checkout execution',
            'no public customer-auth order list endpoint found',
            'no public customer-auth order detail endpoint found',
            'no dedicated `/customers/me` JSON API route exists in baseline',
            'customer auth APIs (register/login/otp/social)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03Doc);
        }

        foreach ([
            'runtime supported but default pack gap',
            '/account/orders/:id',
            'default `webu-shop` pack does **not** currently include `login/account/orders/order-detail` page blueprints',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api05Doc);
        }

        foreach ([
            'webu_ecom_product_grid_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_auth_01',
            'webu_ecom_orders_list_01',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-orders-list',
            'preview_state',
            'loading_title',
            'error_title',
            'empty_title',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-cart',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'function mountProductsWidget(container, options)',
            'function renderCartWidget(container)',
            'function mountWidgets()',
            'window.WebbyEcommerce = {',
            'mountProductsWidget: mountProductsWidget',
            'mountCartWidget: renderCartWidget',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_runtime_bridge_normalizes_dynamic_storefront_paths_and_aliases_category_order_params',
            "->assertJsonPath('route.params.id', '1001')",
            'meta.endpoints.ecommerce_checkout',
        ] as $needle) {
            $this->assertStringContainsString($needle, $previewPublishAlignmentTest);
        }

        foreach ([
            'assertPublishedRouteHtml($host, \'/cart\'',
            'assertPublishedRouteHtml($host, \'/checkout\'',
            'assertPublishedRouteHtml($host, \'/account/orders\'',
            'assertPublishedRouteHtml($host, \'/account/orders/\'.$orderId',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontE2e);
        }

        foreach ([
            'test_public_cart_checkout_and_payment_start_flow_works',
            "->assertJsonPath('payment.status', 'pending')",
            "->assertJsonPath('coupon.code', 'SAVE10')",
            "route('public.sites.ecommerce.orders.payment.start'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            'test_checkout_happy_path_and_payment_success_flow',
            'test_add_to_cart_fails_when_requested_quantity_exceeds_stock',
            "->assertJsonPath('error', 'Requested quantity exceeds available stock.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCheckoutAcceptanceTest);
        }
    }
}
