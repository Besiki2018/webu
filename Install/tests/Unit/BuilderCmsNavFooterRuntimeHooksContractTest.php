<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderCmsNavFooterRuntimeHooksContractTest extends TestCase
{
    public function test_builder_cms_runtime_script_keeps_nav_footer_and_cart_icon_runtime_hooks_with_search_and_customer_endpoints(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

        foreach ([
            "'search_url' =>",
            "'customer_me_url' =>",
            'function cmsSearchUrl()',
            'function cmsCustomerMeUrl()',
            'function bindNavSearchWidget(node)',
            'function renderNavAccountWidget(node)',
            'function renderFooterWidget(node, payload)',
            'function renderCartIconWidgets(cart)',
            'function bindCartIconRuntime()',
            'function mountNavFooterRuntime(payload)',
            "[data-webby-nav-logo]",
            "[data-webby-nav-menu]",
            "[data-webby-nav-search]",
            "[data-webby-nav-account-icon]",
            "[data-webby-footer-layout]",
            "[data-webby-ecommerce-cart-icon]",
            'data-webby-nav-search-bound',
            'data-webby-nav-account-state',
            "jsonFetch(endpoint + '?' + searchParams.toString())",
            'window.WebbyEcommerce.onCartUpdated',
            'mountNavFooterRuntime(payload);',
            'mountNavFooterRuntime(window.__WEBBY_CMS__ || null);',
            'mountNavFooterRuntime: function () {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}

