<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest extends TestCase
{
    public function test_api_05_audit_doc_locks_ecommerce_routing_template_pack_component_api_and_bootstrap_truth(): void
    {
        $themeManifestPath = base_path('../themeplate/webu-shop/template.json');
        if (! file_exists($themeManifestPath)) {
            $this->markTestSkipped('themeplate/webu-shop not present (optional external fixture).');
        }

        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');
        $homePageBlueprintPath = base_path('../themeplate/webu-shop/pages/home.json');
        $shopPageBlueprintPath = base_path('../themeplate/webu-shop/pages/shop.json');
        $productPageBlueprintPath = base_path('../themeplate/webu-shop/pages/product.json');
        $cartPageBlueprintPath = base_path('../themeplate/webu-shop/pages/cart.json');
        $checkoutPageBlueprintPath = base_path('../themeplate/webu-shop/pages/checkout.json');
        $loginPageBlueprintPath = base_path('../themeplate/webu-shop/pages/login.json');
        $accountPageBlueprintPath = base_path('../themeplate/webu-shop/pages/account.json');
        $ordersPageBlueprintPath = base_path('../themeplate/webu-shop/pages/orders.json');
        $orderPageBlueprintPath = base_path('../themeplate/webu-shop/pages/order.json');

        $headerComponentPath = base_path('../themeplate/webu-shop/components/header/component.html');
        $footerComponentPath = base_path('../themeplate/webu-shop/components/footer/component.html');
        $productListComponentPath = base_path('../themeplate/webu-shop/components/product-list/component.html');
        $productCardComponentPath = base_path('../themeplate/webu-shop/components/product-card/component.html');
        $themeContractDocPath = base_path('../themeplate/webu-shop/WEBU_CMS_DATA_CONTRACT.md');

        $runtimePayloadServicePath = base_path('app/Services/CmsRuntimePayloadService.php');
        $publishedControllerPath = base_path('app/Http/Controllers/PublishedProjectController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $templateImportServicePath = base_path('app/Services/TemplateImportService.php');
        $siteProvisioningServicePath = base_path('app/Services/SiteProvisioningService.php');

        $builderRuntimeContractsTestPath = base_path('tests/Unit/BuilderCmsRuntimeScriptContractsTest.php');
        $templateImportServiceTestPath = base_path('tests/Feature/Templates/TemplateImportServiceTest.php');
        $templateProvisioningSmokeTestPath = base_path('tests/Feature/Templates/TemplateProvisioningSmokeTest.php');
        $templatePreviewRenderSmokeTestPath = base_path('tests/Feature/Templates/TemplatePreviewRenderSmokeTest.php');
        $templateAppPreviewRenderSmokeTestPath = base_path('tests/Feature/Templates/TemplateAppPreviewRenderSmokeTest.php');
        $templatePublishedRenderSmokeTestPath = base_path('tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php');
        $templateStorefrontE2eFlowMatrixSmokeTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $templateImportContractServiceTestPath = base_path('tests/Feature/Templates/TemplateImportContractServiceTest.php');
        $cmsPreviewPublishAlignmentTestPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $themeManifestPath,
            $homePageBlueprintPath,
            $shopPageBlueprintPath,
            $productPageBlueprintPath,
            $cartPageBlueprintPath,
            $checkoutPageBlueprintPath,
            $loginPageBlueprintPath,
            $accountPageBlueprintPath,
            $ordersPageBlueprintPath,
            $orderPageBlueprintPath,
            $headerComponentPath,
            $footerComponentPath,
            $productListComponentPath,
            $productCardComponentPath,
            $themeContractDocPath,
            $runtimePayloadServicePath,
            $publishedControllerPath,
            $builderServicePath,
            $templateImportServicePath,
            $siteProvisioningServicePath,
            $builderRuntimeContractsTestPath,
            $templateImportServiceTestPath,
            $templateProvisioningSmokeTestPath,
            $templatePreviewRenderSmokeTestPath,
            $templateAppPreviewRenderSmokeTestPath,
            $templatePublishedRenderSmokeTestPath,
            $templateStorefrontE2eFlowMatrixSmokeTestPath,
            $templateImportContractServiceTestPath,
            $cmsPreviewPublishAlignmentTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $themeManifest = File::get($themeManifestPath);
        $homeBlueprint = File::get($homePageBlueprintPath);
        $shopBlueprint = File::get($shopPageBlueprintPath);
        $productBlueprint = File::get($productPageBlueprintPath);
        $cartBlueprint = File::get($cartPageBlueprintPath);
        $checkoutBlueprint = File::get($checkoutPageBlueprintPath);
        $loginBlueprint = File::get($loginPageBlueprintPath);
        $accountBlueprint = File::get($accountPageBlueprintPath);
        $ordersBlueprint = File::get($ordersPageBlueprintPath);
        $orderBlueprint = File::get($orderPageBlueprintPath);
        $headerComponent = File::get($headerComponentPath);
        $footerComponent = File::get($footerComponentPath);
        $productListComponent = File::get($productListComponentPath);
        $productCardComponent = File::get($productCardComponentPath);
        $themeContractDoc = File::get($themeContractDocPath);

        $runtimePayloadService = File::get($runtimePayloadServicePath);
        $publishedController = File::get($publishedControllerPath);
        $builderService = File::get($builderServicePath);
        $templateImportService = File::get($templateImportServicePath);
        $siteProvisioningService = File::get($siteProvisioningServicePath);

        $templateImportServiceTest = File::get($templateImportServiceTestPath);
        $templateProvisioningSmokeTest = File::get($templateProvisioningSmokeTestPath);
        $templatePublishedRenderSmokeTest = File::get($templatePublishedRenderSmokeTestPath);
        $templateStorefrontE2eFlowMatrixSmokeTest = File::get($templateStorefrontE2eFlowMatrixSmokeTestPath);
        $templateImportContractServiceTest = File::get($templateImportContractServiceTestPath);
        $cmsPreviewPublishAlignmentTest = File::get($cmsPreviewPublishAlignmentTestPath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Backend → Builder Integration Contract (Exact API Spec v1)', $roadmap);
        $this->assertStringContainsString('1. ROUTING STRUCTURE', $roadmap);
        $this->assertStringContainsString('/products → Product Listing', $roadmap);
        $this->assertStringContainsString('13. COMPONENT → API MAPPING SUMMARY', $roadmap);
        $this->assertStringContainsString('14. CREATE DEFAULT THEME', $roadmap);
        $this->assertStringContainsString('Webu Default Commerce Theme', $roadmap);

        $this->assertStringContainsString('- `API-05` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:2708',
            'PROJECT_ROADMAP_TASKS_KA.md:3167',
            '## Ecommerce Routing Structure Parity Audit (Spec `1`)',
            '## Default Page Template Pack Parity Audit (Spec `2`-`10`)',
            '## Header / Footer Template Audit (Spec `11`-`12`)',
            '## Component → API Mapping Verification (Spec `13`)',
            '## Default Theme Creation / Bootstrap Flow Check (Spec `14`)',
            'canonical runtime pages (`/shop`, `/product/:slug`)',
            '`/products` listing path currently maps to the `product` page slug in runtime route parsers',
            '`API-05` is **complete as an audit task**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'themeplate/webu-shop/template.json',
            'themeplate/webu-shop/pages/home.json',
            'themeplate/webu-shop/pages/shop.json',
            'themeplate/webu-shop/pages/product.json',
            'themeplate/webu-shop/pages/cart.json',
            'themeplate/webu-shop/pages/checkout.json',
            'themeplate/webu-shop/components/header/component.html',
            'themeplate/webu-shop/components/footer/component.html',
            'themeplate/webu-shop/components/product-list/component.html',
            'themeplate/webu-shop/components/product-card/component.html',
            'themeplate/webu-shop/WEBU_CMS_DATA_CONTRACT.md',
            'Install/app/Services/CmsRuntimePayloadService.php',
            'Install/app/Http/Controllers/PublishedProjectController.php',
            'Install/app/Services/BuilderService.php',
            'Install/app/Services/TemplateImportService.php',
            'Install/app/Services/SiteProvisioningService.php',
            'Install/tests/Unit/BuilderCmsRuntimeScriptContractsTest.php',
            'Install/tests/Feature/Templates/TemplateImportServiceTest.php',
            'Install/tests/Feature/Templates/TemplateProvisioningSmokeTest.php',
            'Install/tests/Feature/Templates/TemplatePreviewRenderSmokeTest.php',
            'Install/tests/Feature/Templates/TemplateAppPreviewRenderSmokeTest.php',
            'Install/tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php',
            'Install/tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'Install/tests/Feature/Templates/TemplateImportContractServiceTest.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing API-05 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing API-05 evidence file on disk: {$relativePath}");
        }

        // Default theme pack and page blueprints.
        $this->assertStringContainsString('"name": "Webu Shop"', $themeManifest);
        $this->assertStringContainsString('"key": "webu-shop"', $themeManifest);
        $this->assertStringContainsString('"home"', $themeManifest);
        $this->assertStringContainsString('"shop"', $themeManifest);
        $this->assertStringContainsString('"product"', $themeManifest);
        $this->assertStringContainsString('"cart"', $themeManifest);
        $this->assertStringContainsString('"checkout"', $themeManifest);
        $this->assertStringContainsString('"login"', $themeManifest);
        $this->assertStringContainsString('"account"', $themeManifest);
        $this->assertStringContainsString('"orders"', $themeManifest);
        $this->assertStringContainsString('"order"', $themeManifest);
        $this->assertStringContainsString('"pageBlueprintsPath": "pages/"', $themeManifest);

        $this->assertStringContainsString('"slug": "/"', $homeBlueprint);
        $this->assertStringContainsString('"slug": "/shop"', $shopBlueprint);
        $this->assertStringContainsString('"slug": "/product"', $productBlueprint);
        $this->assertStringContainsString('"slug": "/cart"', $cartBlueprint);
        $this->assertStringContainsString('"slug": "/checkout"', $checkoutBlueprint);
        $this->assertStringContainsString('"slug": "/account/login"', $loginBlueprint);
        $this->assertStringContainsString('"slug": "/account"', $accountBlueprint);
        $this->assertStringContainsString('"slug": "/account/orders"', $ordersBlueprint);
        $this->assertStringContainsString('"slug": "/account/orders/:id"', $orderBlueprint);

        // Header/footer and ecommerce component markers.
        $this->assertStringContainsString('data-webu-section="webu_header_01"', $headerComponent);
        $this->assertStringContainsString('data-webu-menu="header"', $headerComponent);
        $this->assertStringContainsString('data-webby-ecommerce-cart', $headerComponent);
        $this->assertStringContainsString('data-webu-section="webu_footer_01"', $footerComponent);
        $this->assertStringContainsString('data-webu-menu="footer"', $footerComponent);
        $this->assertStringContainsString('data-webu-section="webu_product_grid_01"', $productListComponent);
        $this->assertStringContainsString('data-webby-ecommerce-products', $productListComponent);
        $this->assertStringContainsString('data-webu-section="webu_product_card_01"', $productCardComponent);

        // Theme contract docs show site-scoped ecommerce endpoints and marker model (not exact source-spec auth/orders endpoints).
        $this->assertStringContainsString('GET /public/sites/{site}/ecommerce/products', $themeContractDoc);
        $this->assertStringContainsString('POST /public/sites/{site}/ecommerce/carts/{cart}/shipping/options', $themeContractDoc);
        $this->assertStringContainsString('POST /public/sites/{site}/ecommerce/orders/{order}/payments/start', $themeContractDoc);
        $this->assertStringContainsString('data-webby-ecommerce-products', $themeContractDoc);
        $this->assertStringContainsString('data-webby-ecommerce-cart', $themeContractDoc);
        $this->assertStringNotContainsString('/customers/me', $themeContractDoc);
        $this->assertStringNotContainsString('/orders/my', $themeContractDoc);

        // Runtime route normalization/resolution drift and support.
        $this->assertStringContainsString('if ($first === \'shop\' || $first === \'category\' || $first === \'categories\') {', $runtimePayloadService);
        $this->assertStringContainsString('if ($first === \'product\' || $first === \'products\') {', $runtimePayloadService);
        $this->assertStringContainsString('return \'shop\';', $runtimePayloadService);
        $this->assertStringContainsString('return \'product\';', $runtimePayloadService);
        $this->assertStringContainsString('if ($first === \'account\') {', $runtimePayloadService);
        $this->assertStringContainsString('return $second === \'orders\' && isset($segments[2]) ? \'order\' : ($second === \'orders\' ? \'orders\' : \'account\');', $runtimePayloadService);

        $this->assertStringContainsString('if (in_array($first, [\'product\', \'products\'], true)) {', $publishedController);
        $this->assertStringContainsString('return [\'product\', $params];', $publishedController);
        $this->assertStringContainsString('if ($first === \'shop\') {', $publishedController);
        $this->assertStringContainsString('return [\'shop\', $params];', $publishedController);
        $this->assertStringContainsString('if ($first === \'account\') {', $publishedController);

        $this->assertStringContainsString('function resolveCmsRoute(pathname, projectId)', $builderService);
        $this->assertStringContainsString("if (first === 'product' || first === 'products')", $builderService);
        $this->assertStringContainsString("return setSlug('product');", $builderService);
        $this->assertStringContainsString("if (first === 'shop')", $builderService);
        $this->assertStringContainsString("return setSlug('shop');", $builderService);
        $this->assertStringContainsString("if (first === 'account')", $builderService);
        $this->assertStringContainsString('function fetchViaPublicApi(routeInfo, locale)', $builderService);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/settings'", $builderService);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/theme/typography'", $builderService);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/pages/' + encodeURIComponent(slug)", $builderService);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/menu/' + encodeURIComponent(menuKey)", $builderService);

        // Template import/provisioning bootstrap and runtime link defaults.
        $this->assertStringContainsString('\'default_pages\' => $manifest[\'pages\']', $templateImportService);
        $this->assertStringContainsString('\'default_sections\' => $manifest[\'default_sections\']', $templateImportService);
        $this->assertStringContainsString('\'<header$1 data-webu-section="webu_header_01">\'', $templateImportService);
        $this->assertStringContainsString('\'<footer$1 data-webu-menu="footer">\'', $templateImportService);
        $this->assertStringContainsString('\'ecommerce_bindings\' => [', $templateImportService);
        $this->assertStringContainsString('\'products\' => \'window.WebbyEcommerce.listProducts\'', $templateImportService);
        $this->assertStringContainsString('\'checkout\' => \'window.WebbyEcommerce.checkout/startPayment\'', $templateImportService);
        $this->assertStringContainsString("setIfMissing('products_menu_url', '/shop');", $templateImportService);
        $this->assertStringContainsString("setIfMissing('login_url', '/account/login');", $templateImportService);
        $this->assertStringContainsString("setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });", $templateImportService);

        $this->assertStringContainsString('$templatePages = Arr::get($metadata, \'default_pages\', []);', $siteProvisioningService);
        $this->assertStringContainsString('$templateSections = Arr::get($metadata, \'default_sections\', []);', $siteProvisioningService);
        $this->assertStringContainsString('$this->repository->firstOrCreateMenu($site, \'header\', [\'items_json\' => $headerItems]);', $siteProvisioningService);
        $this->assertStringContainsString('$this->repository->firstOrCreateMenu($site, \'footer\', [\'items_json\' => $footerItems]);', $siteProvisioningService);

        // Automated evidence tests lock bootstrap behavior and route caveat.
        $this->assertStringContainsString("assertFileExists(base_path('templates/webu-shop-01/mapping.json'))", $templateImportServiceTest);
        $this->assertStringContainsString("setIfMissing('products_menu_url', '/shop');", $templateImportServiceTest);
        $this->assertStringContainsString("setIfMissing('login_url', '/account/login');", $templateImportServiceTest);
        $this->assertStringContainsString("setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });", $templateImportServiceTest);

        $this->assertStringContainsString('data_get($site->theme_settings, \'demo_content.seeded\')', $templateProvisioningSmokeTest);
        $this->assertStringContainsString('$headerMenu = $site->menus()->where(\'key\', \'header\')->first();', $templateProvisioningSmokeTest);
        $this->assertStringContainsString('$footerMenu = $site->menus()->where(\'key\', \'footer\')->first();', $templateProvisioningSmokeTest);

        $this->assertStringContainsString("->assertJsonPath('menus.header.key', 'header')", $templatePublishedRenderSmokeTest);
        $this->assertStringContainsString("->assertJsonPath('site.theme_settings.demo_content.seeded', true)", $templatePublishedRenderSmokeTest);
        $this->assertStringContainsString('data_get($payload, \'meta.endpoints.ecommerce_products\')', $templatePublishedRenderSmokeTest);

        $this->assertStringContainsString('ensurePublishedPage($site, $owner, \'login\', \'Login\', \'Login SEO\')', $templateStorefrontE2eFlowMatrixSmokeTest);
        $this->assertStringContainsString('ensurePublishedPage($site, $owner, \'account\', \'Account\', \'Account SEO\')', $templateStorefrontE2eFlowMatrixSmokeTest);
        $this->assertStringContainsString('ensurePublishedPage($site, $owner, \'orders\', \'Orders\', \'Orders SEO\')', $templateStorefrontE2eFlowMatrixSmokeTest);
        $this->assertStringContainsString('ensurePublishedPage($site, $owner, \'order\', \'Order Detail\', \'Order Detail SEO\')', $templateStorefrontE2eFlowMatrixSmokeTest);
        $this->assertStringContainsString('assertPublishedRouteHtml($host, \'/account/orders/\'.$orderId, \'Order Detail SEO\')', $templateStorefrontE2eFlowMatrixSmokeTest);

        $this->assertStringContainsString('test_validate_source_root_auth_account_and_orders_page_blueprints_pass_with_runtime_markers', $templateImportContractServiceTest);
        $this->assertStringContainsString('test_validate_source_root_auth_and_account_page_blueprints_pass_with_template_runtime_tokens', $templateImportContractServiceTest);
        $this->assertStringContainsString('assertJsonPath(\'meta.endpoints.ecommerce_products\'', $cmsPreviewPublishAlignmentTest);
    }
}
