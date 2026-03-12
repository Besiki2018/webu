<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest extends TestCase
{
    public function test_api_03_audit_doc_maps_checkout_payment_webhook_and_customer_auth_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $webRoutesPath = base_path('routes/web.php');
        $authRoutesPath = base_path('routes/auth.php');
        $apiRoutesPath = base_path('routes/api.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $publicStorefrontServicePath = base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php');
        $paymentGatewayControllerPath = base_path('app/Http/Controllers/PaymentGatewayController.php');
        $webhookServicePath = base_path('app/Ecommerce/Services/EcommercePaymentWebhookService.php');
        $projectOperationGuardPath = base_path('app/Services/ProjectOperationGuardService.php');
        $openApiEcommercePath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $openApiAuthCustomersPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $webRoutesPath,
            $authRoutesPath,
            $apiRoutesPath,
            $publicStorefrontControllerPath,
            $publicStorefrontServicePath,
            $paymentGatewayControllerPath,
            $webhookServicePath,
            $projectOperationGuardPath,
            $openApiEcommercePath,
            $openApiAuthCustomersPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $webRoutes = File::get($webRoutesPath);
        $authRoutes = File::get($authRoutesPath);
        $apiRoutes = File::get($apiRoutesPath);
        $publicStorefrontController = File::get($publicStorefrontControllerPath);
        $paymentGatewayController = File::get($paymentGatewayControllerPath);
        $projectOperationGuard = File::get($projectOperationGuardPath);
        $openApiEcommerce = File::get($openApiEcommercePath);
        $openApiAuthCustomers = File::get($openApiAuthCustomersPath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Backend → Builder Integration Contract (Exact API Spec v1)', $roadmap);
        $this->assertStringContainsString('8) Checkout + Orders APIs', $roadmap);
        $this->assertStringContainsString('9) Payments APIs', $roadmap);
        $this->assertStringContainsString('10) Customer Auth APIs', $roadmap);

        $this->assertStringContainsString('- `API-03` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:2460',
            'PROJECT_ROADMAP_TASKS_KA.md:2628',
            '## Endpoint Coverage Matrix (Spec 8.x → 10.x)',
            '`8.1 POST /checkout/validate`',
            '`8.2 POST /orders`',
            '`8.3 GET /orders/my`',
            '`8.4 GET /orders/{id}`',
            '`9.1 GET /payments/methods`',
            '`9.2 POST /payments/init`',
            '`9.3 POST /payments/webhook/{provider}`',
            '`10.1 POST /customers/register`',
            '`10.2 POST /customers/login`',
            '`10.3 POST /auth/otp/request`',
            '`10.4 POST /auth/otp/verify`',
            '`10.5 POST /auth/google`',
            '`10.6 POST /auth/facebook`',
            '## Idempotency / Auth Handling Verification (`API-03` Deliverable)',
            'Idempotency-Key',
            'ProjectOperationGuardService',
            '## Payload / Error / Lifecycle Verification',
            'EcommercePaymentWebhookOrchestrationTest.php',
            'EcommerceTransactionalNotificationsTest.php',
            'RateLimitProtectionTest.php',
            'checkout -> payment start -> webhook sync -> notification',
            'OpenAPI-vs-runtime minor baseline mismatch observed',
            '`API-03` is complete as an audit task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $anchors = [
            'Install/routes/web.php',
            'Install/routes/auth.php',
            'Install/routes/api.php',
            'Install/app/Http/Controllers/Ecommerce/PublicStorefrontController.php',
            'Install/app/Ecommerce/Services/EcommercePublicStorefrontService.php',
            'Install/app/Http/Controllers/PaymentGatewayController.php',
            'Install/app/Ecommerce/Services/EcommercePaymentWebhookService.php',
            'Install/app/Services/ProjectOperationGuardService.php',
            'Install/docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml',
            'Install/docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceAdvancedAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceTransactionalNotificationsTest.php',
            'Install/tests/Feature/Security/RateLimitProtectionTest.php',
            'Install/tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing API-03 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing API-03 evidence file on disk: {$relativePath}");
        }

        // Public runtime routes present for checkout / payment start / shipment tracking.
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/checkout'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/orders/{order}/payments/start'", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/ecommerce/shipments/track'", $webRoutes);
        $this->assertStringContainsString("Route::post('payment-gateways/{plugin}/webhook'", $webRoutes);
        $this->assertStringContainsString('throttle:public-checkout', $webRoutes);

        // Public customer-auth order routes are currently absent (panel routes exist separately).
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/my'", $webRoutes);
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/{order}'", $webRoutes);
        $this->assertStringContainsString("Route::get('/ecommerce/orders/{order}'", $webRoutes);

        // Auth runtime still includes session/social redirect routes in auth.php (closure adds site-scoped public JSON helpers in web.php).
        $this->assertStringContainsString("Route::post('login'", $authRoutes);
        $this->assertStringContainsString("Route::post('register'", $authRoutes);
        $this->assertStringContainsString("Route::post('logout'", $authRoutes);
        $this->assertStringContainsString("Route::get('auth/{provider}'", $authRoutes);
        $this->assertStringContainsString("Route::get('auth/{provider}/callback'", $authRoutes);
        $this->assertStringNotContainsString("Route::post('auth/google'", $authRoutes);
        $this->assertStringNotContainsString("Route::post('auth/facebook'", $authRoutes);

        foreach ([
            'customers/register',
            'customers/login',
            'auth/otp/request',
            'auth/otp/verify',
            'auth/google',
            'auth/facebook',
        ] as $unexpectedPathFragment) {
            $this->assertStringNotContainsString($unexpectedPathFragment, $apiRoutes);
        }

        // No global (non-site-scoped) customer-auth JSON helper routes exist in web.php.
        foreach ([
            "Route::post('/customers/login'",
            "Route::get('/customers/me'",
            "Route::put('/customers/me'",
            "Route::post('/auth/otp/request'",
            "Route::post('/auth/otp/verify'",
            "Route::post('/auth/google'",
            "Route::post('/auth/facebook'",
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $webRoutes);
        }

        // RS-13-01 closure adds site-scoped public customer-auth JSON runtime variants in web.php.
        foreach ([
            "Route::post('/{site}/customers/login', [PublicSiteController::class, 'customerLogin'])",
            "Route::get('/{site}/customers/me', [PublicSiteController::class, 'customerMe'])",
            "Route::put('/{site}/customers/me', [PublicSiteController::class, 'customerMeUpdate'])",
            "Route::post('/{site}/auth/otp/request', [PublicSiteController::class, 'customerOtpRequest'])",
            "Route::post('/{site}/auth/otp/verify', [PublicSiteController::class, 'customerOtpVerify'])",
            "Route::post('/{site}/auth/google', [PublicSiteController::class, 'customerGoogleAuth'])",
            "Route::post('/{site}/auth/facebook', [PublicSiteController::class, 'customerFacebookAuth'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        // Controller/runtime truth: checkout returns 201 and no explicit idempotency-key handling on storefront controller.
        $this->assertStringContainsString('public function checkout(Request $request, Site $site, EcommerceCart $cart): JsonResponse', $publicStorefrontController);
        $this->assertStringContainsString('return $this->corsJson($payload, 201);', $publicStorefrontController);
        $this->assertStringContainsString('public function startPayment(Request $request, Site $site, EcommerceOrder $order): JsonResponse', $publicStorefrontController);
        $this->assertStringNotContainsString('Idempotency-Key', $publicStorefrontController);

        // Webhook idempotency + ecommerce sync orchestration evidence in controller.
        $this->assertStringContainsString("payment-webhook:%s:%s", $paymentGatewayController);
        $this->assertStringContainsString('Cache::add($idempotencyCacheKey', $paymentGatewayController);
        $this->assertStringContainsString('$this->ecommerceWebhookSync->synchronize', $paymentGatewayController);

        // Reusable idempotency helper exists (used as gap/follow-up evidence for checkout/order creation).
        $this->assertStringContainsString("header('Idempotency-Key')", $projectOperationGuard);
        $this->assertStringContainsString("header('X-Idempotency-Key')", $projectOperationGuard);

        // Minimal OpenAPI reflects current public ecommerce/auth surfaces; customer-auth JSON helper coverage may be closure-superseded by RS-13-01.
        foreach ([
            '/public/sites/{site}/ecommerce/carts/{cart}/checkout:',
            '/public/sites/{site}/ecommerce/orders/{order}/payments/start:',
            '/public/sites/{site}/ecommerce/shipments/track:',
        ] as $path) {
            $this->assertStringContainsString($path, $openApiEcommerce);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/orders/my:', $openApiEcommerce);
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/orders/{order}:', $openApiEcommerce);

        foreach ([
            '/login:',
            '/register:',
            '/auth/{provider}:',
            '/auth/{provider}/callback:',
            'session-backed',
            'public site-scoped JSON helper routes now exist for customer auth/account widget parity',
        ] as $needle) {
            $this->assertStringContainsString($needle, $openApiAuthCustomers);
        }
        foreach ([
            '/public/sites/{site}/customers/login:',
            '/public/sites/{site}/customers/me:',
            '/public/sites/{site}/auth/otp/request:',
            '/public/sites/{site}/auth/otp/verify:',
            '/public/sites/{site}/auth/google:',
            '/public/sites/{site}/auth/facebook:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $openApiAuthCustomers);
        }
    }
}
