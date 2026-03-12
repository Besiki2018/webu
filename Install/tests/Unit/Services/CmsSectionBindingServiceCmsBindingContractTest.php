<?php

namespace Tests\Unit\Services;

use App\Services\CmsSectionBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PART 5 — CMS Binding: default ecommerce bindings (product_grid → products, etc.) when section not in library.
 */
class CmsSectionBindingServiceCmsBindingContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_grid_section_gets_products_binding_when_not_in_library(): void
    {
        config()->set('cms-section-bindings.default_bindings', [
            'product_grid' => [
                'source' => 'products',
                'bindings' => ['source' => 'products', 'filters' => [], 'sort' => 'newest', 'limit' => 24],
            ],
        ]);
        config()->set('cms-section-bindings.pattern_to_binding_key', [
            'webu_ecom_product_grid' => 'product_grid',
        ]);

        $service = app(CmsSectionBindingService::class);
        $binding = $service->resolveBinding('webu_ecom_product_grid_01');

        $this->assertSame('cms_binding_contract', $binding['source'] ?? null);
        $this->assertArrayHasKey('bindings', $binding);
        $this->assertSame('products', $binding['bindings']['source'] ?? null);
    }

    public function test_product_detail_section_gets_product_by_slug_binding(): void
    {
        config()->set('cms-section-bindings.default_bindings', [
            'product_detail' => [
                'source' => 'product_by_slug',
                'bindings' => ['source' => 'product_by_slug', 'slugParam' => 'slug'],
            ],
        ]);
        config()->set('cms-section-bindings.pattern_to_binding_key', [
            'webu_ecom_product_detail' => 'product_detail',
        ]);

        $service = app(CmsSectionBindingService::class);
        $binding = $service->resolveBinding('webu_ecom_product_details_01');

        $this->assertSame('cms_binding_contract', $binding['source'] ?? null);
        $this->assertSame('product_by_slug', $binding['bindings']['source'] ?? null);
    }

    public function test_category_section_gets_categories_binding(): void
    {
        config()->set('cms-section-bindings.default_bindings', [
            'category_menu' => [
                'source' => 'categories',
                'bindings' => ['source' => 'categories', 'limit' => 20],
            ],
        ]);
        config()->set('cms-section-bindings.pattern_to_binding_key', [
            'webu_ecom_category' => 'category_menu',
        ]);

        $service = app(CmsSectionBindingService::class);
        $binding = $service->resolveBinding('webu_ecom_category_menu_01');

        $this->assertSame('categories', $binding['bindings']['source'] ?? null);
    }
}
