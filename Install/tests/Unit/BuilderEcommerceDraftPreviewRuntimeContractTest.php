<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderEcommerceDraftPreviewRuntimeContractTest extends TestCase
{
    public function test_ecommerce_runtime_scripts_preserve_draft_preview_request_forwarding(): void
    {
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $templateImportServicePath = base_path('app/Services/TemplateImportService.php');

        $this->assertFileExists($builderServicePath);
        $this->assertFileExists($templateImportServicePath);

        $builderService = File::get($builderServicePath);
        $templateImportService = File::get($templateImportServicePath);

        foreach ([
            'function isDraftPreviewRequest() {',
            'function appendDraftPreviewUrl(url) {',
            'return fetch(appendDraftPreviewUrl(url), init).then(parseResponse);',
            'appendDraftPreviewUrl(template(ecommerce.product_url_pattern, { slug: productSlug }))',
            'appendDraftPreviewUrl(template(ecommerce.product_url_pattern, { slug: row.slug }))',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'function ecommerceUrl(siteId, path, queryString) {',
            "return jsonFetch(ecommerceUrl(siteId, '/products', queryString));",
            "return jsonFetch(ecommerceUrl(siteId, '/carts'), {",
            "return jsonFetch(ecommerceUrl(siteId, '/customer-orders', queryString));",
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateImportService);
        }
    }
}
