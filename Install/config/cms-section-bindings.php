<?php

/**
 * Default CMS bindings for ecommerce section keys (PART 5 — CMS Binding).
 *
 * All product content must come from Webu CMS. No hardcoded demo data.
 * When a section is not in the section library, these defaults ensure correct binding:
 * product_grid → products, product_details → product_by_slug, category_menu → categories,
 * cart → cart, checkout → checkout.
 *
 * @see new tasks.txt — PART 5 CMS Binding
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Section key pattern => default bindings
    | Keys are normalized (lowercase, trimmed). Pattern match: section key contains pattern.
    |--------------------------------------------------------------------------
    */
    'default_bindings' => [
        'product_grid' => [
            'source' => 'products',
            'bindings' => [
                'source' => 'products',
                'filters' => [],
                'sort' => 'newest',
                'limit' => 24,
            ],
        ],
        'product_detail' => [
            'source' => 'product_by_slug',
            'bindings' => [
                'source' => 'product_by_slug',
                'slugParam' => 'slug',
            ],
        ],
        'category_menu' => [
            'source' => 'categories',
            'bindings' => [
                'source' => 'categories',
                'limit' => 20,
            ],
        ],
        'cart' => [
            'source' => 'cart',
            'bindings' => [
                'source' => 'cart',
            ],
        ],
        'checkout' => [
            'source' => 'checkout',
            'bindings' => [
                'source' => 'checkout',
            ],
        ],
        'products_search' => [
            'source' => 'products_search',
            'bindings' => [
                'source' => 'products_search',
                'limit' => 24,
            ],
        ],
    ],

    /**
     * Section key patterns (substrings) that map to a default_bindings key.
     * First match wins. Keys in default_bindings above are matched by substring in section key.
     * Section 7: full ecommerce component catalog → CMS bindings.
     */
    'pattern_to_binding_key' => [
        'webu_ecom_product_grid' => 'product_grid',
        'webu_ecom_product_detail' => 'product_detail',
        'webu_ecom_product_details' => 'product_detail',
        'webu_ecom_product_gallery' => 'product_detail',
        'webu_ecom_product_tabs' => 'product_detail',
        'webu_ecom_category' => 'category_menu',
        'webu_ecom_cart' => 'cart',
        'webu_ecom_cart_page' => 'cart',
        'webu_ecom_checkout' => 'checkout',
        'webu_ecom_checkout_form' => 'checkout',
        'webu_ecom_product_search' => 'products_search',
    ],
];
