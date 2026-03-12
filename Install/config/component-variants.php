<?php

/**
 * Component variant registry: layout and style variants per section type.
 * Every component has exactly 5 design variants. variant_labels are for UI/titles — edit here to set your own names.
 *
 * @see App\Services\ComponentVariantRegistry
 * @see docs/architecture/CMS_COMPONENT_VARIANT_REGISTRY.md
 */
return [
    'design_rules' => [
        'typography_scale' => ['h1' => 48, 'h2' => 36, 'h3' => 24, 'body' => 16],
        'allowed_spacing_px' => [8, 16, 24, 32, 48, 64],
        'card_padding_px' => 16,
        'section_gap_px' => 64,
    ],

    /* header → იგივეა რაც webu_header_01; კატალოგში მხოლოდ webu_header_01, preview/header გადამისამართება webu_header_01-ზე */

    'hero' => [
        'layout_variants' => ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05', 'Design 06', 'Finwave Home 11'],
        'style_variants' => [],
        'default_layout' => 'hero-1',
        'default_style' => 'default',
    ],

    'footer' => [
        'layout_variants' => ['footer-1', 'footer-2', 'footer-3', 'footer-4'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04'],
        'style_variants' => [],
        'default_layout' => 'footer-1',
        'default_style' => 'default',
    ],

    'webu_header_01' => [
        'layout_variants' => ['header-1', 'header-2', 'header-3', 'header-4', 'header-5', 'header-6'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05', 'Clotya'],
        'style_variants' => [],
        'default_layout' => 'header-1',
        'default_style' => 'default',
    ],

    'webu_footer_01' => [
        'layout_variants' => ['footer-1', 'footer-2', 'footer-3', 'footer-4'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04'],
        'style_variants' => [],
        'default_layout' => 'footer-1',
        'default_style' => 'default',
    ],

    'hero_split_image' => [
        'layout_variants' => ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'hero-1',
        'default_style' => 'default',
    ],

    'banner' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_general_heading_01' => [
        'layout_variants' => ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05', 'Design 06', 'Finwave Home 11'],
        'style_variants' => [],
        'default_layout' => 'hero-1',
        'default_style' => 'default',
    ],

    'webu_general_text_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_general_spacer_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_general_newsletter_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_general_card_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_general_testimonials_01' => [
        'layout_variants' => ['single', 'carousel', 'grid', 'masonry', 'alternate'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => ['card', 'quote', 'minimal'],
        'default_layout' => 'single',
        'default_style' => 'card',
    ],

    'webu_ecom_product_card_01' => [
        'layout_variants' => ['classic', 'minimal', 'modern', 'premium', 'compact'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'classic',
        'default_style' => 'default',
    ],

    'webu_ecom_product_grid_01' => [
        'layout_variants' => ['standard', 'compact', 'imagefocus', 'masonry', 'list'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => ['premium', 'clean', 'dense', 'soft', 'softshadow'],
        'default_layout' => 'standard',
        'default_style' => 'softshadow',
    ],

    'product_grid' => [
        'layout_variants' => ['standard', 'compact', 'imagefocus', 'masonry', 'list'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'standard',
        'default_style' => 'default',
    ],

    'product_detail' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_product_search_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_product_carousel_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_cart_icon_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_product_gallery_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_add_to_cart_button_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_product_tabs_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_coupon_ui_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_order_summary_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_shipping_selector_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_payment_selector_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_auth_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_account_dashboard_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_account_profile_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_account_security_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_orders_list_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'webu_ecom_order_detail_01' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'cart_page' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'checkout_form' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'faq_accordion_plus' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'contact_split_form' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'map_contact_block' => [
        'layout_variants' => ['design-01', 'design-02', 'design-03', 'design-04', 'design-05'],
        'variant_labels' => ['Design 01', 'Design 02', 'Design 03', 'Design 04', 'Design 05'],
        'style_variants' => [],
        'default_layout' => 'design-01',
        'default_style' => 'default',
    ],

    'allowed_section_keys' => [],
];
