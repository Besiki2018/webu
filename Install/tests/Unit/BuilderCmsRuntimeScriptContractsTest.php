<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderCmsRuntimeScriptContractsTest extends TestCase
{
    public function test_builder_cms_runtime_script_keeps_dynamic_storefront_route_parser_and_param_sensitive_rehydrate(): void
    {
        $source = $this->readBuilderServiceSource();

        $this->assertStringContainsString('function resolveCmsRoute(pathname, projectId)', $source);
        $this->assertStringContainsString("if (first === 'product' || first === 'products')", $source);
        $this->assertStringContainsString("if (first === 'shop')", $source);
        $this->assertStringContainsString("if (first === 'account')", $source);
        $this->assertStringContainsString("if (second === 'orders')", $source);
        $this->assertStringContainsString('route.params.product_slug = secondRaw;', $source);
        $this->assertStringContainsString('route.params.category_slug = thirdRaw;', $source);
        $this->assertStringContainsString('route.params.order_id = thirdRaw;', $source);
        $this->assertStringContainsString('route.params.id = thirdRaw;', $source);
        $this->assertStringContainsString("return setSlug('order');", $source);

        $this->assertStringContainsString('var lastRouteSignature = null;', $source);
        $this->assertStringContainsString('var routeKey = routeSignature(routeInfo);', $source);
        $this->assertStringContainsString('if (routeKey === lastRouteSignature)', $source);
        $this->assertStringContainsString('lastRouteSignature = routeKey;', $source);
    }

    public function test_builder_cms_runtime_script_keeps_site_scoped_public_api_calls_and_route_param_bridge_propagation(): void
    {
        $source = $this->readBuilderServiceSource();

        $this->assertStringContainsString('function fetchViaPublicApi(routeInfo, locale)', $source);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/settings'", $source);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/theme/typography'", $source);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/pages/' + encodeURIComponent(slug)", $source);
        $this->assertStringContainsString("'/public/sites/' + encodeURIComponent(siteId) + '/menu/' + encodeURIComponent(menuKey)", $source);

        $this->assertStringContainsString('var routeParams = normalizeRouteParamsForQuery(route.params);', $source);
        $this->assertStringContainsString('base.searchParams.set(key, queryParams[key]);', $source);
        $this->assertStringContainsString('params: routeParams,', $source);
        $this->assertStringContainsString('requested_slug: route.requested_slug || slug', $source);
        $this->assertStringContainsString('queryString(true)', $source);
    }

    private function readBuilderServiceSource(): string
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        return File::get($path);
    }
}
