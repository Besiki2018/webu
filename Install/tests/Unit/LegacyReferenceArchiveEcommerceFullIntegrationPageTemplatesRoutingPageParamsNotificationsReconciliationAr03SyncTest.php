<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class LegacyReferenceArchiveEcommerceFullIntegrationPageTemplatesRoutingPageParamsNotificationsReconciliationAr03SyncTest extends TestCase
{
    public function test_ar_03_legacy_page_templates_routing_params_notifications_reconciliation_audit_locks_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $cmsStorefrontContractPath = base_path('resources/js/Pages/Project/__tests__/CmsStorefrontPageTemplates.contract.test.ts');
        $siteProvisioningPath = base_path('app/Services/SiteProvisioningService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');

        $templateProvisioningSmokePath = base_path('tests/Feature/Templates/TemplateProvisioningSmokeTest.php');
        $templateImportServiceTestPath = base_path('tests/Feature/Templates/TemplateImportServiceTest.php');
        $templateStorefrontE2ePath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $cmsPreviewPublishAlignmentPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');
        $partialParitySchemaTestPath = base_path('tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php');
        $builderRuntimeContractsTestPath = base_path('tests/Unit/BuilderCmsRuntimeScriptContractsTest.php');
        $bindingResolverTestPath = base_path('tests/Unit/CmsCanonicalBindingResolverTest.php');

        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceTransactionalNotificationsTestPath = base_path('tests/Feature/Ecommerce/EcommerceTransactionalNotificationsTest.php');

        $api05DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');
        $api03DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $rs0003DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $cmsPath,
            $cmsStorefrontContractPath,
            $siteProvisioningPath,
            $builderServicePath,
            $templateProvisioningSmokePath,
            $templateImportServiceTestPath,
            $templateStorefrontE2ePath,
            $cmsPreviewPublishAlignmentPath,
            $partialParitySchemaTestPath,
            $builderRuntimeContractsTestPath,
            $bindingResolverTestPath,
            $ecommercePublicApiTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceTransactionalNotificationsTestPath,
            $api05DocPath,
            $api03DocPath,
            $rs0003DocPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $cms = File::get($cmsPath);
        $cmsStorefrontContract = File::get($cmsStorefrontContractPath);
        $siteProvisioning = File::get($siteProvisioningPath);
        $builderService = File::get($builderServicePath);

        $templateProvisioningSmoke = File::get($templateProvisioningSmokePath);
        $templateImportServiceTest = File::get($templateImportServiceTestPath);
        $templateStorefrontE2e = File::get($templateStorefrontE2ePath);
        $cmsPreviewPublishAlignment = File::get($cmsPreviewPublishAlignmentPath);
        $partialParitySchemaTest = File::get($partialParitySchemaTestPath);
        $builderRuntimeContractsTest = File::get($builderRuntimeContractsTestPath);
        $bindingResolverTest = File::get($bindingResolverTestPath);

        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);
        $ecommerceTransactionalNotificationsTest = File::get($ecommerceTransactionalNotificationsTestPath);

        $api05Doc = File::get($api05DocPath);
        $api03Doc = File::get($api03DocPath);
        $rs0003Doc = File::get($rs0003DocPath);

        foreach ([
            '5) Builder Page Templates (Create Ready Pages)',
            'Generate default pages in new theme:',
            'Home (hero + featured products + categories)',
            'Product Listing page',
            'Product Detail page',
            'Cart page',
            'Checkout page',
            'Login/Register page',
            'Account page',
            'Orders page',
            'Order detail page',
            'Contact page (optional)',
            'Each page must be saved as pages with page_json and page_css.',
            '6) Routing & Page Params (Dynamic Pages)',
            '/products/:slug',
            '/category/:slug',
            '/orders/:id',
            '/account',
            '{{route.params.slug}}',
            '{{route.params.id}}',
            '7) Notifications Integration (Backend → UI)',
            'success toast on order created',
            'payment status feedback',
            'error messages for out-of-stock, invalid coupon, etc.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `AR-03` (`DONE`, `P0`)',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md',
            'LegacyReferenceArchiveEcommerceFullIntegrationPageTemplatesRoutingPageParamsNotificationsReconciliationAr03SyncTest.php',
            'CmsStorefrontPageTemplates.contract.test.ts',
            'TemplateProvisioningSmokeTest.php',
            'TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'CmsPreviewPublishAlignmentTest.php',
            'BuilderCmsRuntimeScriptContractsTest.php',
            'CmsCanonicalBindingResolverTest.php',
            'EcommercePublicApiTest.php',
            'EcommerceCheckoutAcceptanceTest.php',
            'EcommerceTransactionalNotificationsTest.php',
            'WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            '`✅` legacy source page-template list (`home/listing/detail/cart/checkout/login/account/orders/order/contact`) reconciled against canonical storefront page presets + template metadata/provisioning flow with reproducibility evidence',
            '`✅` dynamic route + `{{route.params.slug}}` / `{{route.params.id}}` binding requirements reconciled to runtime route parser + canonical binding resolver + preview/published bridge tests',
            '`✅` order-event feedback requirements reconciled to backend notifications + storefront API error/status payloads + runtime error propagation (with explicit exactness notes)',
            '`⚠️` default `webu-shop` pack still lacks auth/account/orders/order-detail blueprints even though canonical builder presets + runtime route support exist',
            '`⚠️` source storefront "success toast on order created" wording is only partially covered today; no dedicated published ecommerce toast/event dispatcher contract is evidenced in `BuilderService` runtime',
            '`🧪` AR-03 reconciliation sync lock added (page templates + routes/page params + notifications feedback mapping)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `AR-03` Can Be `DONE`)',
            '## Builder Page Templates (Source `5`) — Generation / Storage / Reproducibility Audit',
            '### Page Template Coverage Matrix (`implemented / equivalent / partial`)',
            '### Generation / Provisioning Reproducibility Evidence',
            '### Storage Wording Parity Matrix (`exact / equivalent / partial`)',
            '## Routing & Page Params (Source `6`) — Dynamic Routes + Binding Verification',
            '### Dynamic Route Mapping Matrix (`exact / equivalent / partial`)',
            '### Page Param Binding Verification Matrix (`exact / equivalent / partial`)',
            '## Notifications Integration (Source `7`) — Order-Event UI Feedback Reconciliation',
            '### Feedback Requirement Matrix (`implemented / equivalent / partial / missing`)',
            '### Truthful Notification/UI Gap Notes (Important)',
            '## Key Truthful Variants and Gaps (AR-03 Synthesis Notes)',
            '## DoD Verdict (`AR-03`)',
            'Conclusion: `AR-03` is `DONE`.',
            '## Follow-up Mapping (Non-blocking for `AR-03` Closure)',
            '`AR-04`',
            '`RS-05-02`',
            '`RS-05-03`',
            '`RS-13-01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '| `Home` |',
            '| `Product Listing page` |',
            '| `Product Detail page` |',
            '| `Login/Register page` |',
            '| `Order detail page` |',
            '| `/products/:slug` |',
            '| `/category/:slug` |',
            '| `/orders/:id` |',
            '| `{{route.params.slug}}` binding support |',
            '| success feedback on order created |',
            '| invalid coupon error message |',
            '| out-of-stock error message |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            "key: 'home'",
            "key: 'product-listing'",
            "key: 'product-detail'",
            "key: 'login-register'",
            "key: 'orders-list'",
            "key: 'order-detail'",
            "route_pattern: '/product/:slug'",
            "route_pattern: '/account/orders'",
            "route_pattern: '/account/orders/:id'",
            "optional: true",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsStorefrontContract);
        }

        foreach ([
            'CANONICAL_STOREFRONT_PAGE_TEMPLATE_PRESETS',
            "key: 'checkout'",
            "key: 'login-register'",
            "key: 'account'",
            "key: 'orders-list'",
            "key: 'order-detail'",
            "route_pattern: '/account/login'",
            "route_pattern: '/account/orders'",
            "route_pattern: '/account/orders/:id'",
            "{{route.params.slug}}",
            "{{route.params.id}}",
            "createStorefrontTemplateSection('webu_ecom_order_detail_01', { title: 'Order Detail', order_id: '{{route.params.id}}' })",
            "loadingTitle: t('Validating coupon...')",
            "errorTitle: t('Coupon invalid or unavailable')",
            "msg.setAttribute('data-webu-role', 'ecom-coupon-message');",
            "t('Loading payment methods...')",
            "t('Payment methods unavailable')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'public function provisionForProject(Project $project): Site',
            '$this->ensureDefaultPages($site, $project);',
            'private function ensureDefaultPages(Site $site, Project $project, ?array $overrideBlueprints = null): void',
            '$existingRevisions = $this->repository->countPageRevisions($site, $page);',
            '$this->repository->createRevision($site, $page, [',
            '\'content_json\' => $this->bindContentSections($pageConfig[\'content\'])',
            '$templatePages = Arr::get($metadata, \'default_pages\', []);',
            '$templateSections = Arr::get($metadata, \'default_sections\', []);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $siteProvisioning);
        }

        foreach ([
            'Template provisioning should create default pages.',
            'Home page should have a published revision after provisioning.',
            'Re-provision should not duplicate pages.',
            'Re-provision should not duplicate menus.',
            'Re-provision should not create extra page revisions.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateProvisioningSmoke);
        }

        foreach ([
            'window.WebuStorefront = window.WebbyEcommerce;',
            "setIfMissing('products_menu_url', '/shop');",
            "setIfMissing('login_url', '/account/login');",
            "setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });",
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateImportServiceTest);
        }

        foreach ([
            'ensurePublishedPage($site, $owner, \'login\', \'Login\', \'Login SEO\');',
            'ensurePublishedPage($site, $owner, \'account\', \'Account\', \'Account SEO\');',
            'ensurePublishedPage($site, $owner, \'orders\', \'Orders\', \'Orders SEO\');',
            'ensurePublishedPage($site, $owner, \'order\', \'Order Detail\', \'Order Detail SEO\');',
            'assertPublishedRouteHtml($host, \'/account/orders\', \'Orders SEO\');',
            'assertPublishedRouteHtml($host, \'/account/orders/\'.$orderId, \'Order Detail SEO\');',
            'Found unresolved placeholder in route {$path}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontE2e);
        }

        foreach ([
            'test_runtime_bridge_exposes_route_params_from_query_for_canonical_bindings',
            "->assertJsonPath('route.params.slug', 'premium-dog-snack')",
            "->assertJsonPath('route.params.id', '1001')",
            'test_runtime_bridge_normalizes_dynamic_storefront_paths_and_aliases_category_order_params',
            "->assertJsonPath('route.params.order_id', '1001')",
            'meta.endpoints.ecommerce_checkout',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPreviewPublishAlignment);
        }

        foreach ([
            "Schema::hasColumns('pages', ['tenant_id', 'project_id', 'page_json', 'page_css', 'og_image_media_id', 'published_at', 'version'])",
            "Schema::hasColumns('page_revisions', ['page_json', 'page_css'])",
            "'page_json' => json_encode(['sections' => []])",
            "'page_css' => '/* page css */'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $partialParitySchemaTest);
        }

        foreach ([
            'function resolveCmsRoute(pathname, projectId)',
            "if (first === 'product' || first === 'products')",
            "if (first === 'account')",
            "route.params.order_id = thirdRaw;",
            "route.params.id = thirdRaw;",
            'var routeParams = normalizeRouteParamsForQuery(route.params);',
            'params: routeParams,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderRuntimeContractsTest);
        }

        foreach ([
            'resolve($payload, \'{{route.params.slug}}\')',
            "normalizeExpression('route.params.slug')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingResolverTest);
        }

        foreach ([
            'function parseResponse(response) {',
            "var message = (payload && (payload.error || payload.message)) || ('HTTP_' + response.status);",
            'function checkout(cartId, payload) {',
            'function startPayment(orderId, payload) {',
            'window.WebbyEcommerce = {',
            'checkout: checkout,',
            'startPayment: startPayment,',
            'window.dispatchEvent(new CustomEvent(cartUpdatedEventName, { detail: cart || null }));',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'orderCreatedEventName',
            'paymentStatusEventName',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $builderService, 'Gap note should remain truthful until dedicated storefront order/payment events are implemented.');
        }

        foreach ([
            "->assertCreated()",
            "->assertJsonPath('payment.status', 'pending')",
            "->assertJsonPath('coupon.code', 'SAVE10')",
            "->assertJsonPath('error', 'Coupon code is invalid.')",
            "->assertJsonPath('error', 'Selected payment provider is not available.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            'test_checkout_happy_path_and_payment_success_flow',
            'test_add_to_cart_fails_when_requested_quantity_exceeds_stock',
            "->assertJsonPath('error', 'Requested quantity exceeds available stock.')",
            "->assertJsonPath('error', 'Cart is empty.')",
            "->assertJsonPath('error', 'This order has no outstanding balance.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCheckoutAcceptanceTest);
        }

        foreach ([
            'test_order_placed_notification_is_sent_to_merchant_on_checkout',
            'test_order_paid_notification_is_sent_on_successful_webhook_sync',
            'test_order_failed_notification_is_sent_on_failed_webhook_sync',
            'EcommerceOrderMerchantNotification::EVENT_ORDER_PLACED',
            'EcommerceOrderMerchantNotification::EVENT_ORDER_PAID',
            'EcommerceOrderMerchantNotification::EVENT_ORDER_FAILED',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceTransactionalNotificationsTest);
        }

        foreach ([
            'default `webu-shop` pack does **not** currently include `login/account/orders/order-detail` page blueprints',
            'Builder/runtime route parsing supports account/login/orders/order-detail routes and dynamic params',
            '/products/:slug',
            '/account/orders/:id',
            'runtime supported but default pack gap',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api05Doc);
        }

        foreach ([
            'checkout -> payment start -> webhook sync -> notification',
            'merchant notifications (checkout/webhook): `EcommerceTransactionalNotificationsTest.php`',
            'payment/order status synchronization is tested',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03Doc);
        }

        foreach ([
            '`{{key.path}}` syntax parse + normalization',
            'Runtime payload path resolution',
            'CmsCanonicalBindingResolverTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0003Doc);
        }
    }
}
