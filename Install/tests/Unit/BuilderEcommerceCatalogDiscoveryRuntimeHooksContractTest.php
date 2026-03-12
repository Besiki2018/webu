<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest extends TestCase
{
    public function test_builder_ecommerce_runtime_script_keeps_catalog_discovery_search_and_categories_hook_contract(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

        foreach ([
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'function normalizeCatalogListQuery(params) {',
            'query.search = search;',
            'query.q = search;',
            'query.category_slug = category;',
            'query.category = category;',
            'query.per_page = limit;',
            'query.page = Math.floor(parsedOffset / parsedLimit) + 1;',
            'function listCategories(params) {',
            "source: 'products_derived'",
            'function mountSearchWidget(container, options) {',
            'data-webby-ecommerce-search-bound',
            'data-webby-ecommerce-search-state',
            'function mountCategoriesWidget(container, options) {',
            'data-webby-ecommerce-categories-bound',
            'data-webby-ecommerce-categories-state',
            '[data-webby-ecommerce-search]',
            '[data-webby-ecommerce-categories]',
            'function mountWidgets() {',
            'var searchSelector = (ecommerce.widgets && ecommerce.widgets.search_selector)',
            'var categoriesSelector = (ecommerce.widgets && ecommerce.widgets.categories_selector)',
            'mountSearchWidget(node, {});',
            'mountCategoriesWidget(node, {});',
            'listCategories: listCategories,',
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}

