<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_13_01_progress_audit_doc_locks_account_auth_components_parity_endpoint_and_runtime_gap_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_13_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $authRoutesPath = base_path('routes/auth.php');
        $apiRoutesPath = base_path('routes/api.php');
        $authSessionControllerPath = base_path('app/Http/Controllers/Auth/AuthenticatedSessionController.php');
        $socialLoginControllerPath = base_path('app/Http/Controllers/Auth/SocialLoginController.php');
        $passwordControllerPath = base_path('app/Http/Controllers/Auth/PasswordController.php');
        $profileControllerPath = base_path('app/Http/Controllers/ProfileController.php');
        $adminSettingsControllerPath = base_path('app/Http/Controllers/Admin/SettingsController.php');
        $publishedProjectControllerPath = base_path('app/Http/Controllers/PublishedProjectController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $authCustomersOpenApiPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');

        $api03SyncTestPath = base_path('tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php');
        $c6SummarySyncTestPath = base_path('tests/Unit/Phase2CustomerAuthAccountOrdersC6CompletionSummaryStatusSyncTest.php');
        $c6SummaryDocPath = base_path('docs/qa/CMS_PHASE2_AUTH_ACCOUNT_ORDERS_C6_COMPLETION_SUMMARY.md');
        $c6ContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts');
        $ecomCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $cmsPublicCustomerAuthRuntimeFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicCustomerAuthRuntimeEndpointsTest.php');
        $storefrontSmokeTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $customerAuthTablesSchemaTestPath = base_path('tests/Feature/Platform/UniversalCustomerAuthTablesSchemaTest.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $aliasMapPath,
            $webRoutesPath,
            $authRoutesPath,
            $apiRoutesPath,
            $authSessionControllerPath,
            $socialLoginControllerPath,
            $passwordControllerPath,
            $profileControllerPath,
            $adminSettingsControllerPath,
            $publishedProjectControllerPath,
            $builderServicePath,
            $authCustomersOpenApiPath,
            $api03SyncTestPath,
            $c6SummarySyncTestPath,
            $c6SummaryDocPath,
            $c6ContractPath,
            $ecomCoverageContractPath,
            $cmsPublicCustomerAuthRuntimeFeatureTestPath,
            $storefrontSmokeTestPath,
            $customerAuthTablesSchemaTestPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $coverageGapAuditUnitTestPath,
            $aliasMapUnitTestPath,
            $runtimeContractTestPath,
            $closureSyncTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $aliasMap = File::get($aliasMapPath);
        $webRoutes = File::get($webRoutesPath);
        $authRoutes = File::get($authRoutesPath);
        $apiRoutes = File::get($apiRoutesPath);
        $authSessionController = File::get($authSessionControllerPath);
        $socialLoginController = File::get($socialLoginControllerPath);
        $passwordController = File::get($passwordControllerPath);
        $profileController = File::get($profileControllerPath);
        $adminSettingsController = File::get($adminSettingsControllerPath);
        $publishedProjectController = File::get($publishedProjectControllerPath);
        $builderService = File::get($builderServicePath);
        $authCustomersOpenApi = File::get($authCustomersOpenApiPath);
        $api03SyncTest = File::get($api03SyncTestPath);
        $c6SummarySyncTest = File::get($c6SummarySyncTestPath);
        $c6SummaryDoc = File::get($c6SummaryDocPath);
        $c6Contract = File::get($c6ContractPath);
        $ecomCoverageContract = File::get($ecomCoverageContractPath);
        $cmsPublicCustomerAuthRuntimeFeatureTest = File::get($cmsPublicCustomerAuthRuntimeFeatureTestPath);
        $storefrontSmokeTest = File::get($storefrontSmokeTestPath);
        $customerAuthTablesSchemaTest = File::get($customerAuthTablesSchemaTestPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $coverageGapAuditUnitTest = File::get($coverageGapAuditUnitTestPath);
        $aliasMapUnitTest = File::get($aliasMapUnitTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 13) ACCOUNT / AUTH COMPONENTS (Universal)',
            '## 13.1 auth.auth',
            'email/password',
            'sms otp',
            'google/facebook',
            '/customers/login',
            '/auth/otp/request',
            '/auth/otp/verify',
            '/auth/google',
            '/auth/facebook',
            '## 13.2 auth.profile',
            'GET/PUT /customers/me',
            '## 13.3 auth.security',
            'change password, sessions list (optional)',
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
            'BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Phase2CustomerAuthAccountOrdersC6CompletionSummaryStatusSyncTest.php',
            'CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts',
            '`✅` account/auth parity matrix documented for `auth.auth`, `auth.profile`, `auth.security` with canonical alias mappings (`webu_ecom_auth_01`, `webu_ecom_account_profile_01`, `webu_ecom_account_security_01`)',
            '`✅` builder preview placeholders + component-specific preview-update branches evidenced for all 3 components; auth method gating and unauthorized fallback preview behavior documented (`use_backend_auth_settings`, `show_register_tab`, `show_otp`, `show_social`, `resolveEcomAuthBackendFeatureToggles`, `unauthorized_*`)',
            '`✅` published storefront account/auth route-shell baseline + customer-auth platform schema baseline evidenced via `PublishedProjectController.php`, `TemplateStorefrontE2eFlowMatrixSmokeTest.php`, and `UniversalCustomerAuthTablesSchemaTest.php`',
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
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-13-01`)',
            '## Account / Auth Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/auth-method-gating-profile-security/gating/tests`)',
            '`auth.auth`',
            '`auth.profile`',
            '`auth.security`',
            '`webu_ecom_auth_01`',
            '`webu_ecom_account_profile_01`',
            '`webu_ecom_account_security_01`',
            '## Endpoint Contract Verification (`/customers/login`, `/auth/otp/*`, `/auth/google`, `/auth/facebook`, `/customers/me`)',
            '`variant_session_web_only`',
            '`variant_social_redirect_get_only`',
            '`partial_web_profile_only`',
            '## Auth Method Gating Matrix (Email/Password, SMS OTP, Social)',
            'resolveEcomAuthBackendFeatureToggles()',
            'allow_otp',
            'passwordless_otp_enabled',
            'Admin\\SettingsController::updateAuth',
            '## Profile / Security Component Parity Checks',
            '## Runtime Flow Baseline (Published Storefront Route Shells)',
            '/account/login',
            '/account/orders/{id}',
            '## Runtime Widget / Binding Status (`auth`, `profile`, `security`)',
            'window.WebbyEcommerce',
            'products/cart widget mounts/selectors',
            '## Customer Auth Data-Model Baseline (Supporting Context)',
            '`otp_requests`',
            '`social_accounts`',
            '## DoD Verdict (`RS-13-01`)',
            'Conclusion: `RS-13-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_ecom_auth_01',
            'webu_ecom_account_profile_01',
            'webu_ecom_account_security_01',
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-account-profile',
            'data-webby-ecommerce-account-security',
            'use_backend_auth_settings',
            'show_register_tab',
            'show_otp',
            'show_social',
            'resolveEcomAuthBackendFeatureToggles',
            'allow_register',
            'otp_enabled',
            'allow_otp',
            'passwordless_otp_enabled',
            'social_login_enabled',
            'unauthorized_title',
            'unauthorized_cta_url',
            'data-webu-role-state="unauthorized"',
            'show_phone_field',
            'show_marketing_opt_in',
            'show_addresses_summary',
            'save_label',
            'show_password_panel',
            'show_two_factor_panel',
            'active_sessions_count',
            'trusted_devices_count',
            "if (normalized === 'webu_ecom_auth_01')",
            "if (normalized === 'webu_ecom_account_profile_01')",
            "if (normalized === 'webu_ecom_account_security_01')",
            "if (normalizedSectionType === 'webu_ecom_auth_01')",
            "if (normalizedSectionType === 'webu_ecom_account_profile_01')",
            "if (normalizedSectionType === 'webu_ecom_account_security_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "auth.auth"',
            'webu_ecom_auth_01',
            'source_component_key": "auth.profile"',
            'webu_ecom_account_profile_01',
            'source_component_key": "auth.security"',
            'webu_ecom_account_security_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "require __DIR__.'/auth.php';",
            "Route::middleware('auth')->group(function () {",
            "Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');",
            "Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');",
            "Route::put('settings/auth', [SettingsController::class, 'updateAuth'])->name('admin.settings.auth');",
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
            "Route::post('register', [RegisteredUserController::class, 'store'])",
            "Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])",
            "Route::get('auth/{provider}', [SocialLoginController::class, 'redirect'])",
            "Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])",
            "Route::put('password', [PasswordController::class, 'update'])->name('password.update');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $authRoutes);
        }
        foreach ([
            "Route::post('auth/google'",
            "Route::post('auth/facebook'",
            'auth/otp/request',
            'auth/otp/verify',
            'customers/me',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $authRoutes);
        }

        foreach ([
            'customers/login',
            'customers/me',
            'auth/otp/request',
            'auth/otp/verify',
            'auth/google',
            'auth/facebook',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $apiRoutes);
        }

        foreach ([
            'public function store(LoginRequest $request): RedirectResponse',
            '$request->authenticate();',
            '$request->session()->regenerate();',
        ] as $needle) {
            $this->assertStringContainsString($needle, $authSessionController);
        }

        foreach ([
            'protected array $providers = [\'google\', \'facebook\', \'github\'];',
            'public function redirect(string $provider): RedirectResponse',
            'public function callback(string $provider): RedirectResponse',
            'isProviderEnabled',
            'isProviderConfigured',
            'SystemSetting::get("{$provider}_login_enabled", false);',
            "SystemSetting::get('enable_registration', true)",
            'Socialite::driver($provider)->redirect();',
        ] as $needle) {
            $this->assertStringContainsString($needle, $socialLoginController);
        }

        foreach ([
            'public function update(Request $request): RedirectResponse',
            "SystemSetting::get('password_min_length', 8)",
            "'current_password' => ['required', 'current_password']",
            '\'password\' => [\'required\', Password::min($minLength), \'confirmed\']',
        ] as $needle) {
            $this->assertStringContainsString($needle, $passwordController);
        }

        foreach ([
            'public function edit(Request $request): Response',
            'public function update(ProfileUpdateRequest $request): RedirectResponse',
            'public function updateConsents(Request $request): RedirectResponse',
            'public function destroy(Request $request): RedirectResponse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $profileController);
        }

        foreach ([
            'public function updateAuth(Request $request)',
            "'enable_registration' => 'boolean'",
            "'google_login_enabled' => 'boolean'",
            "'facebook_login_enabled' => 'boolean'",
            "'github_login_enabled' => 'boolean'",
            'SystemSetting::setMany($validated, \'auth\');',
        ] as $needle) {
            $this->assertStringContainsString($needle, $adminSettingsController);
        }

        foreach ([
            'if ($first === \'account\') {',
            'if (in_array($second, [\'login\', \'register\'], true))',
            'return [\'login\', $params];',
            'if ($second === \'orders\' && isset($segments[2]))',
            'return [\'order\', $params];',
            'return [\'account\', $params];',
            'if (in_array($first, [\'login\', \'register\', \'auth\'], true))',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publishedProjectController);
        }

        foreach ([
            'window.WebbyEcommerce',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-cart',
            'mountProductsWidget',
            'mountCartWidget',
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-account-profile',
            'data-webby-ecommerce-account-security',
            'mountAuthWidget',
            'mountAccountProfileWidget',
            'mountAccountSecurityWidget',
            'getCustomerMe',
            'loginCustomer',
            'updateCustomerMe',
            'requestOtp',
            'verifyOtp',
            'startGoogleAuth',
            'startFacebookAuth',
            'customer_login_url',
            'customer_me_update_url',
            'auth_otp_request_url',
            'auth_otp_verify_url',
            'auth_google_url',
            'auth_facebook_url',
            '/customers/me',
            '/customers/login',
            '/auth/otp/request',
            '/auth/otp/verify',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/login:',
            '/register:',
            '/logout:',
            '/auth/{provider}:',
            '/auth/{provider}/callback:',
            'session-backed',
            'public site-scoped JSON helper routes now exist for customer auth/account widget parity',
            '/public/sites/{site}/customers/me:',
            '/public/sites/{site}/customers/login:',
            '/public/sites/{site}/auth/otp/request:',
            '/public/sites/{site}/auth/otp/verify:',
            '/public/sites/{site}/auth/google:',
            '/public/sites/{site}/auth/facebook:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $authCustomersOpenApi);
        }

        foreach ([
            'Customer Auth APIs',
            '`10.2 POST /customers/login`',
            '`10.3 POST /auth/otp/request`',
            '`10.4 POST /auth/otp/verify`',
            '`10.5 POST /auth/google`',
            '`10.6 POST /auth/facebook`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03SyncTest);
        }
        $this->assertTrue(
            str_contains($api03SyncTest, 'do not yet expose a dedicated `/customers/me` JSON API route')
            || str_contains($api03SyncTest, 'public site-scoped JSON helper routes now exist')
        );

        foreach ([
            'resolveEcomAuthBackendFeatureToggles',
            'unauthorized_title',
            'CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts',
            '/account/login',
            '/account/orders',
        ] as $needle) {
            $this->assertStringContainsString($needle, $c6SummarySyncTest);
            $this->assertStringContainsString($needle, $c6SummaryDoc);
        }
        $this->assertStringContainsString('CMS_PHASE2_AUTH_ACCOUNT_ORDERS_C6_COMPLETION_SUMMARY.md', $c6SummarySyncTest);

        foreach ([
            'CMS ecommerce C6 auth/account/orders builder contracts',
            'webu_ecom_auth_01',
            'resolveEcomAuthBackendFeatureToggles',
            'use_backend_auth_settings',
            'show_otp',
            'social_login_enabled',
            'webu_ecom_account_profile_01',
            'webu_ecom_account_security_01',
            'unauthorized_cta_url',
            'Login required to manage profile',
            'Login required to manage security',
        ] as $needle) {
            $this->assertStringContainsString($needle, $c6Contract);
        }

        foreach ([
            'CMS ecommerce builder component coverage contracts',
            'webu_ecom_auth_01',
            'webu_ecom_account_profile_01',
            'webu_ecom_account_security_01',
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-account-profile',
            'data-webby-ecommerce-account-security',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecomCoverageContract);
        }

        foreach ([
            'CmsPublicCustomerAuthRuntimeEndpointsTest',
            'public.sites.customers.login',
            'public.sites.customers.me',
            'public.sites.customers.me.update',
            'public.sites.auth.otp.request',
            'public.sites.auth.otp.verify',
            'public.sites.auth.google',
            'public.sites.auth.facebook',
            'passwordless_otp_enabled',
            'social_provider_disabled',
            'customer_auth_required',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicCustomerAuthRuntimeFeatureTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            "'customer_login_url' =>",
            "'auth_otp_request_url' =>",
            'window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;',
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
            'assertPublishedRouteHtml($host, \'/account/login\'',
            'assertPublishedRouteHtml($host, \'/account\'',
            'assertPublishedRouteHtml($host, \'/account/orders\'',
            'assertPublishedRouteHtml($host, \'/account/orders/\'.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $storefrontSmokeTest);
        }

        foreach ([
            'customers',
            'customer_sessions',
            'customer_addresses',
            'otp_requests',
            'social_accounts',
            'test_universal_customer_auth_tables_exist_with_canonical_columns',
            'test_customer_auth_tables_support_relational_insert_flow',
        ] as $needle) {
            $this->assertStringContainsString($needle, $customerAuthTablesSchemaTest);
        }

        foreach ([
            'CMS universal component library activation contracts',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
            'builderSectionAvailabilityMatrix',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        foreach ([
            "key: 'ecommerce'",
            "key: 'restaurant'",
            "key: 'hotel'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            "rowsByKey['auth.auth']",
            "rowsByKey['auth.profile']",
            "rowsByKey['auth.security']",
            'webu_ecom_account_profile_01',
            'webu_ecom_account_security_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            "rowsByKey['auth.security']",
            'webu_ecom_account_security_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapUnitTest);
        }

        foreach ([
            'webu-auth-customers-minimal.v1.openapi.yaml',
            '/login:',
            '/auth/{provider}:',
            '/customers/me',
            "Route::post('login'",
            "Route::get('auth/{provider}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
