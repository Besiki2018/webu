<?php

/**
 * Builder Component Registry — allowed component IDs for AI and builder.
 *
 * Single source of truth for the backend: AI may only add components in this list.
 * Must stay in sync with resources/js/builder/componentRegistry.ts (getAvailableComponents()).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed component (section) IDs
    |--------------------------------------------------------------------------
    */
    'component_ids' => [
        'webu_general_hero_01',
        'webu_general_heading_01',
        'webu_general_text_01',
        'webu_general_image_01',
        'webu_general_button_01',
        'webu_general_video_01',
        'webu_general_spacer_01',
        'webu_general_section_01',
        'webu_general_newsletter_01',
        'webu_general_cta_01',
        'webu_general_features_01',
        'webu_general_cards_01',
        'webu_general_grid_01',
        'webu_general_navigation_01',
        'webu_general_card_01',
        'webu_general_form_wrapper_01',
        'webu_general_testimonials_01',
        'webu_general_banner_01',
        'webu_general_offcanvas_menu_01',
        'webu_ecom_product_grid_01',
        'webu_ecom_featured_categories_01',
        'webu_ecom_category_list_01',
        'webu_ecom_cart_page_01',
        'webu_ecom_product_detail_01',
        'webu_header_01',
        'webu_footer_01',
    ],

    /*
    |--------------------------------------------------------------------------
    | Category order for API / builder panel
    |--------------------------------------------------------------------------
    */
    'category_order' => [
        'header',
        'sections',
        'content',
        'marketing',
        'ecommerce',
        'layout',
        'footer',
        'general',
        'booking',
        'blog',
        'portfolio',
    ],
];
