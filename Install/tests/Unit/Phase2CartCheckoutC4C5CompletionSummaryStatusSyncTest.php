<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase2CartCheckoutC4C5CompletionSummaryStatusSyncTest extends TestCase
{
    public function test_phase2_c4_c5_detailed_lines_match_existing_builder_and_api_evidence(): void
    {
        $headerTemplatePath = base_path('../themeplate/webu-shop/components/header/component.html');
        if (! file_exists($headerTemplatePath)) {
            $this->markTestSkipped('themeplate/webu-shop/components/header not present (optional external fixture).');
        }

        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE2_CART_CHECKOUT_C4_C5_COMPLETION_SUMMARY.md');
        $builderCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $pageTemplatesContractPath = base_path('resources/js/Pages/Project/__tests__/CmsStorefrontPageTemplates.contract.test.ts');
        $ecommerceApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $checkoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $storefrontSmokeTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $builderCoverageContractPath,
            $pageTemplatesContractPath,
            $ecommerceApiTestPath,
            $checkoutAcceptanceTestPath,
            $storefrontSmokeTestPath,
            $headerTemplatePath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $builderCoverageContract = File::get($builderCoverageContractPath);
        $pageTemplatesContract = File::get($pageTemplatesContractPath);
        $ecommerceApiTest = File::get($ecommerceApiTestPath);
        $checkoutAcceptanceTest = File::get($checkoutAcceptanceTestPath);
        $storefrontSmokeTest = File::get($storefrontSmokeTestPath);
        $headerTemplate = File::get($headerTemplatePath);

        // Closed C4/C5 lines
        $this->assertStringContainsString("`P2-C4-01` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C4-02` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C4-03` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C4-04` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C5-01` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C5-02` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C5-03` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P2-C5-04` (✅ `DONE`)", $roadmap);

        // Related C6 lines now close via separate summary doc; keep pointer instead of stale "open" wording here.
        $this->assertStringContainsString("`P2-C6-01` (✅ `DONE`)", $roadmap);

        // Summary doc references all closure lines + still-open neighbors
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:380', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:381', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:382', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:383', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:389', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:392', $summaryDoc);
        $this->assertStringContainsString('Related Follow-Up Summaries', $summaryDoc);
        $this->assertStringContainsString('CMS_PHASE2_AUTH_ACCOUNT_ORDERS_C6_COMPLETION_SUMMARY.md', $summaryDoc);
        $this->assertStringNotContainsString('PROJECT_ROADMAP_TASKS_KA.md:382`) Coupon apply/remove UI', $summaryDoc);
        $this->assertStringNotContainsString('PROJECT_ROADMAP_TASKS_KA.md:383`) Guest/customer cart identity persistence', $summaryDoc);

        // Builder component/marker evidence
        $this->assertStringContainsString('webu_ecom_cart_icon_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_cart_page_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_checkout_form_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_order_summary_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_shipping_selector_01', $builderCoverageContract);
        $this->assertStringContainsString('webu_ecom_payment_selector_01', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-cart-icon', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-cart', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-coupon', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-checkout-form', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-order-summary', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-shipping-selector', $builderCoverageContract);
        $this->assertStringContainsString('data-webby-ecommerce-payment-selector', $builderCoverageContract);

        // Header-safe integration bindings evidence
        $this->assertStringContainsString('cart_view_button', $pageTemplatesContract);
        $this->assertStringContainsString('cart_checkout_button', $pageTemplatesContract);
        $this->assertStringContainsString('cart_total_label', $pageTemplatesContract);
        $this->assertStringContainsString('data-webu-field="cart_total_label"', $headerTemplate);
        $this->assertStringContainsString('data-webu-field="cart_view_button"', $headerTemplate);
        $this->assertStringContainsString('data-webu-field="cart_checkout_button"', $headerTemplate);

        // Cart/checkout/shipping/payment API + smoke evidence
        $this->assertStringContainsString('public.sites.ecommerce.carts.items.update', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.carts.items.destroy', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.carts.coupon.apply', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.carts.coupon.remove', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.carts.shipping.options', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.carts.shipping.update', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.payment.options', $ecommerceApiTest);
        $this->assertStringContainsString('public.sites.ecommerce.orders.payment.start', $ecommerceApiTest);
        $this->assertStringContainsString("assertJsonPath('cart.subtotal'", $ecommerceApiTest);
        $this->assertStringContainsString("assertJsonPath('cart.discount_total'", $ecommerceApiTest);
        $this->assertStringContainsString("assertJsonPath('coupon.code', 'SAVE10')", $ecommerceApiTest);
        $this->assertStringContainsString('cart_identity_mismatch', $ecommerceApiTest);
        $this->assertStringContainsString('meta.cart_identity_token', $ecommerceApiTest);
        $this->assertStringContainsString("assertJsonPath('order.shipping_total'", $ecommerceApiTest);
        $this->assertStringContainsString("assertJsonPath('order.grand_total'", $ecommerceApiTest);

        $this->assertStringContainsString('test_checkout_happy_path_and_payment_success_flow', $checkoutAcceptanceTest);
        $this->assertStringContainsString("assertJsonPath('error', 'Cart is empty.')", $checkoutAcceptanceTest);
        $this->assertStringContainsString("assertJsonPath('error', 'Requested quantity exceeds available stock.')", $checkoutAcceptanceTest);

        $this->assertStringContainsString("ensurePublishedPage(\$site, \$owner, 'cart'", $storefrontSmokeTest);
        $this->assertStringContainsString("ensurePublishedPage(\$site, \$owner, 'checkout'", $storefrontSmokeTest);
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/cart'", $storefrontSmokeTest);
        $this->assertStringContainsString("assertPublishedRouteHtml(\$host, '/checkout'", $storefrontSmokeTest);
    }
}
