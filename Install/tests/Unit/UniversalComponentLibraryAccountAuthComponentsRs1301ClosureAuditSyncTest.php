<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest extends TestCase
{
    public function test_rs_13_01_closure_audit_locks_account_auth_runtime_endpoints_widget_hooks_gating_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_13_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $authRoutesPath = base_path('routes/auth.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $passwordControllerPath = base_path('app/Http/Controllers/Auth/PasswordController.php');
        $profileControllerPath = base_path('app/Http/Controllers/ProfileController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $authCustomersOpenApiPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');

        $featureTestPath = base_path('tests/Feature/Cms/CmsPublicCustomerAuthRuntimeEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php');
        $api03SyncTestPath = base_path('tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $webRoutesPath,
            $authRoutesPath,
            $publicSiteControllerPath,
            $passwordControllerPath,
            $profileControllerPath,
            $builderServicePath,
            $authCustomersOpenApiPath,
            $featureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $api03SyncTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $webRoutes = File::get($webRoutesPath);
        $authRoutes = File::get($authRoutesPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $passwordController = File::get($passwordControllerPath);
        $profileController = File::get($profileControllerPath);
        $builderService = File::get($builderServicePath);
        $authCustomersOpenApi = File::get($authCustomersOpenApiPath);
        $featureTest = File::get($featureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $baselineSyncTest = File::get($baselineSyncTestPath);
        $api03SyncTest = File::get($api03SyncTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 13) ACCOUNT / AUTH COMPONENTS (Universal)',
            '## 13.1 auth.auth',
            '## 13.2 auth.profile',
            'GET/PUT /customers/me',
            '## 13.3 auth.security',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-13-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_13_01_2026_02_26.md',
            'UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest.php',
            'CmsPublicCustomerAuthRuntimeEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with site-scoped public customer auth/account JSON endpoints + `window.WebbyEcommerce` auth/account runtime helper/mount evidence',
            '`✅` public customer auth/account endpoint bindings are feature-tested (`POST /public/sites/{site}/customers/login`, `GET/PUT /public/sites/{site}/customers/me`, `POST /public/sites/{site}/auth/otp/request`, `POST /public/sites/{site}/auth/otp/verify`, `POST /public/sites/{site}/auth/google`, `POST /public/sites/{site}/auth/facebook`) including backend-setting-based enable/disable verification and auth/profile runtime flows via `CmsPublicCustomerAuthRuntimeEndpointsTest.php`',
            '`✅` `BuilderService` now exposes standalone ecommerce auth/account runtime helper APIs on `window.WebbyEcommerce` (`getCustomerMe`, `loginCustomer`, `updateCustomerMe`, `requestOtp`, `verifyOtp`, `startGoogleAuth`, `startFacebookAuth`) and auth/account widget mounts (`mountAuthWidget`, `mountAccountProfileWidget`, `mountAccountSecurityWidget`) with contract locks',
            '`✅` `webu-auth-customers-minimal` OpenAPI now includes site-scoped public auth/customer JSON runtime variants while preserving baseline session/social route coverage (`/login`, `/register`, `/auth/{provider}`, `/auth/{provider}/callback`)',
            '`✅` DoD closure achieved: backend-setting-based enable/disable is verified (OTP/social), and auth/profile/security runtime flows are evidenced (session login + `customers/me` GET/PUT + auth/account widget helpers/mounts + OTP/social runtime bootstrap variants)',
            '`⚠️` source exactness gaps remain (session-backed customer login/profile identity is accepted site-scoped JSON runtime variant; social JSON endpoints are redirect-bootstrap contracts; OTP verify flow is data-model/runtime parity without full customer-session handoff)',
            '`🧪` RS-13-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-13-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'POST /public/sites/{site}/customers/login',
            'GET /public/sites/{site}/customers/me',
            'PUT /public/sites/{site}/customers/me',
            'POST /public/sites/{site}/auth/otp/request',
            'POST /public/sites/{site}/auth/otp/verify',
            'POST /public/sites/{site}/auth/google',
            'POST /public/sites/{site}/auth/facebook',
            'customerLogin(...)',
            'customerMe(...)',
            'customerMeUpdate(...)',
            'customerOtpRequest(...)',
            'customerOtpVerify(...)',
            'customerGoogleAuth(...)',
            'customerFacebookAuth(...)',
            'passwordless_otp_enabled',
            'otp_enabled',
            'allow_otp',
            'google_login_enabled',
            'facebook_login_enabled',
            'window.WebbyEcommerce',
            'getCustomerMe',
            'loginCustomer',
            'updateCustomerMe',
            'requestOtp',
            'verifyOtp',
            'startGoogleAuth',
            'startFacebookAuth',
            'mountAuthWidget',
            'mountAccountProfileWidget',
            'mountAccountSecurityWidget',
            'customer_login_url',
            'customer_me_update_url',
            'auth_otp_request_url',
            'auth_otp_verify_url',
            'auth_google_url',
            'auth_facebook_url',
            '## Account / Auth Runtime Closure Matrix (`auth.auth`, `auth.profile`, `auth.security`)',
            'accepted_equivalent_variant',
            'redirect bootstrap JSON',
            '## Backend-Setting-Based Enable/Disable Verification (DoD)',
            'OTP request endpoint returns `403` when `passwordless_otp_enabled` is disabled',
            'Google/Facebook JSON auth bootstrap endpoints return `403`',
            '## Auth / Profile / Security Runtime Flows Evidence',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '## API-03 Baseline Supersession Note (Customer Auth Segment Only)',
            '## DoD Closure Matrix (`RS-13-01`)',
            '`RS-13-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-account-profile',
            'data-webby-ecommerce-account-security',
            'resolveEcomAuthBackendFeatureToggles',
            'show_password_panel',
            'show_two_factor_panel',
            'active_sessions_count',
            'trusted_devices_count',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/customers/me', [PublicSiteController::class, 'customerMe'])->name('public.sites.customers.me');",
            "Route::put('/{site}/customers/me', [PublicSiteController::class, 'customerMeUpdate'])",
            "Route::post('/{site}/customers/login', [PublicSiteController::class, 'customerLogin'])",
            "Route::post('/{site}/auth/otp/request', [PublicSiteController::class, 'customerOtpRequest'])",
            "Route::post('/{site}/auth/otp/verify', [PublicSiteController::class, 'customerOtpVerify'])",
            "Route::post('/{site}/auth/google', [PublicSiteController::class, 'customerGoogleAuth'])",
            "Route::post('/{site}/auth/facebook', [PublicSiteController::class, 'customerFacebookAuth'])",
            "->middleware('throttle:30,1')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            "Route::post('login', [AuthenticatedSessionController::class, 'store'])",
            "Route::get('auth/{provider}', [SocialLoginController::class, 'redirect'])",
            "Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $authRoutes);
        }

        foreach ([
            'public function customerMe(Request $request, Site $site): JsonResponse',
            'public function customerLogin(Request $request, Site $site): JsonResponse',
            'public function customerMeUpdate(Request $request, Site $site): JsonResponse',
            'public function customerOtpRequest(Request $request, Site $site): JsonResponse',
            'public function customerOtpVerify(Request $request, Site $site): JsonResponse',
            'public function customerGoogleAuth(Request $request, Site $site): JsonResponse',
            'public function customerFacebookAuth(Request $request, Site $site): JsonResponse',
            'private function publicCustomerMePayload(Site $site, mixed $user): array',
            'private function isCustomerOtpEnabled(): bool',
            'private function customerSocialAuthBootstrap(Request $request, Site $site, string $provider): JsonResponse',
            "'reason' => 'invalid_credentials'",
            "'reason' => 'customer_auth_required'",
            "'reason' => 'otp_disabled'",
            "'reason' => 'otp_invalid_code'",
            "'reason' => 'social_provider_disabled'",
            "foreach (['passwordless_otp_enabled', 'otp_enabled', 'allow_otp'] as \$key)",
            "\$payload['otp_request']['debug_code'] = \$code;",
            "'redirect_url' => '/auth/'.\$provider,",
            "'callback_url' => '/auth/'.\$provider.'/callback',",
            "'session' => 'web',",
            "'session' => 'none',",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'public function update(Request $request): RedirectResponse',
            "'current_password' => ['required', 'current_password']",
            'public function edit(Request $request): Response',
            'public function update(ProfileUpdateRequest $request): RedirectResponse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $passwordController.$profileController);
        }

        foreach ([
            "'customer_me_url' =>",
            "'customer_login_url' =>",
            "'customer_me_update_url' =>",
            "'auth_otp_request_url' =>",
            "'auth_otp_verify_url' =>",
            "'auth_google_url' =>",
            "'auth_facebook_url' =>",
            "'auth_selector' => '[data-webby-ecommerce-auth]'",
            "'account_profile_selector' => '[data-webby-ecommerce-account-profile]'",
            "'account_security_selector' => '[data-webby-ecommerce-account-security]'",
            'function customerLogin(payload) {',
            'function customerMeUpdate(payload) {',
            'function customerOtpRequest(payload) {',
            'function customerOtpVerify(payload) {',
            'function customerSocialAuthStart(provider, payload) {',
            'function mountAuthWidget(container) {',
            'function mountAccountProfileWidget(container) {',
            'function mountAccountSecurityWidget(container) {',
            "document.querySelectorAll('[data-webby-ecommerce-auth]')",
            "document.querySelectorAll('[data-webby-ecommerce-account-profile]')",
            "document.querySelectorAll('[data-webby-ecommerce-account-security]')",
            'window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;',
            'window.WebbyEcommerce.mountAccountProfileWidget = mountAccountProfileWidget;',
            'window.WebbyEcommerce.mountAccountSecurityWidget = mountAccountSecurityWidget;',
            'window.WebbyEcommerce.getCustomerMe = customersMe;',
            'window.WebbyEcommerce.loginCustomer = customerLogin;',
            'window.WebbyEcommerce.updateCustomerMe = customerMeUpdate;',
            'window.WebbyEcommerce.requestOtp = customerOtpRequest;',
            'window.WebbyEcommerce.verifyOtp = customerOtpVerify;',
            "window.WebbyEcommerce.startGoogleAuth = function (payload) { return customerSocialAuthStart('google', payload); };",
            "window.WebbyEcommerce.startFacebookAuth = function (payload) { return customerSocialAuthStart('facebook', payload); };",
            "return cmsPublicJson('/customers/me');",
            "return cmsPublicJsonPost('/customers/login', payload);",
            "return cmsPublicJsonPut('/customers/me', payload);",
            "return cmsPublicJsonPost('/auth/otp/request', payload);",
            "return cmsPublicJsonPost('/auth/otp/verify', payload);",
            "/auth/' + encodeURIComponent(String(provider || ''))",
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'Minimal route-coverage OpenAPI for web/session auth routes plus current site-scoped storefront customer auth/account JSON runtime variants.',
            'public site-scoped JSON helper routes now exist for customer auth/account widget parity',
            '/public/sites/{site}/customers/me:',
            'summary: Storefront customer session identity probe (site-scoped JSON)',
            'summary: Update storefront customer session profile (site-scoped JSON)',
            '/public/sites/{site}/customers/login:',
            'summary: Storefront customer login JSON (session-backed runtime variant)',
            '/public/sites/{site}/auth/otp/request:',
            'summary: Request OTP login code (site-scoped JSON runtime variant)',
            "'201':",
            'OTP request created',
            '/public/sites/{site}/auth/otp/verify:',
            'summary: Verify OTP login code (site-scoped JSON runtime variant)',
            'OTP code verified',
            '/public/sites/{site}/auth/google:',
            'summary: Google auth bootstrap JSON (redirect contract variant)',
            'Google auth redirect bootstrap payload',
            '/public/sites/{site}/auth/facebook:',
            'summary: Facebook auth bootstrap JSON (redirect contract variant)',
            'Facebook auth redirect bootstrap payload',
            '/login:',
            '/register:',
            '/auth/{provider}:',
            '/auth/{provider}/callback:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $authCustomersOpenApi);
        }

        foreach ([
            'test_public_customer_session_login_me_and_profile_update_json_endpoints_are_site_scoped',
            'test_public_customer_me_update_requires_authentication',
            'test_public_customer_otp_and_social_auth_json_endpoints_honor_backend_settings_and_return_runtime_variants',
            'public.sites.customers.login',
            'public.sites.customers.me',
            'public.sites.customers.me.update',
            'public.sites.auth.otp.request',
            'public.sites.auth.otp.verify',
            'public.sites.auth.google',
            'public.sites.auth.facebook',
            'passwordless_otp_enabled',
            'google_login_enabled',
            'facebook_login_enabled',
            'otp_disabled',
            'social_provider_disabled',
            'otp_invalid_code',
            'customer_auth_required',
            "->assertJsonPath('auth.session', 'web')",
            "->assertJsonPath('auth.session', 'none')",
            "->assertJsonPath('mode', 'redirect')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $featureTest);
        }

        foreach ([
            "'customer_login_url' =>",
            "'customer_me_update_url' =>",
            "'auth_otp_request_url' =>",
            "'auth_otp_verify_url' =>",
            "'auth_google_url' =>",
            "'auth_facebook_url' =>",
            "'auth_selector' => '[data-webby-ecommerce-auth]'",
            "'account_profile_selector' => '[data-webby-ecommerce-account-profile]'",
            "'account_security_selector' => '[data-webby-ecommerce-account-security]'",
            'window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;',
            'window.WebbyEcommerce.mountAccountProfileWidget = mountAccountProfileWidget;',
            'window.WebbyEcommerce.mountAccountSecurityWidget = mountAccountSecurityWidget;',
            'window.WebbyEcommerce.getCustomerMe = customersMe;',
            'window.WebbyEcommerce.loginCustomer = customerLogin;',
            'window.WebbyEcommerce.updateCustomerMe = customerMeUpdate;',
            'window.WebbyEcommerce.requestOtp = customerOtpRequest;',
            'window.WebbyEcommerce.verifyOtp = customerOtpVerify;',
            "window.WebbyEcommerce.startGoogleAuth = function (payload) { return customerSocialAuthStart('google', payload); };",
            "window.WebbyEcommerce.startFacebookAuth = function (payload) { return customerSocialAuthStart('facebook', payload); };",
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'closure_supersession',
            'public site-scoped JSON helper routes now exist for customer auth/account widget parity',
            'CmsPublicCustomerAuthRuntimeEndpointsTest',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineSyncTest);
        }

        foreach ([
            'customers/login',
            'auth/otp/request',
            'auth/otp/verify',
            'auth/google',
            'auth/facebook',
            'public site-scoped JSON helper routes now exist',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03SyncTest);
        }

        foreach ([
            'webu-auth-customers-minimal.v1.openapi.yaml',
            '/customers/me',
            '/panel/sites/{site}/booking/customers/search:',
            "Route::post('login'",
            "Route::get('auth/{provider}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
