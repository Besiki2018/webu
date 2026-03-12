<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Template matrix: (websiteType, category, style) -> template_id
    | Fallbacks: business_default_modern_01, ecommerce_default_modern_01, etc.
    |--------------------------------------------------------------------------
    */
    'template_matrix' => [
        'business' => [
            'beauty_salon' => [
                'modern' => 'biz_salon_modern_01',
                'minimal' => 'biz_salon_minimal_01',
                'luxury' => 'biz_salon_luxury_01',
                'playful' => 'biz_salon_playful_01',
                'corporate' => 'biz_salon_corporate_01',
            ],
            'restaurant' => [
                'modern' => 'biz_restaurant_modern_01',
                'minimal' => 'biz_restaurant_minimal_01',
                'luxury' => 'biz_restaurant_luxury_01',
                'playful' => 'biz_restaurant_playful_01',
                'corporate' => 'biz_restaurant_corporate_01',
            ],
            'clinic' => [
                'modern' => 'biz_clinic_modern_01',
                'minimal' => 'biz_clinic_minimal_01',
                'luxury' => 'biz_clinic_luxury_01',
                'playful' => 'biz_clinic_playful_01',
                'corporate' => 'biz_clinic_corporate_01',
            ],
            'legal' => [
                'modern' => 'biz_legal_modern_01',
                'minimal' => 'biz_legal_minimal_01',
                'luxury' => 'biz_legal_luxury_01',
                'playful' => 'biz_legal_playful_01',
                'corporate' => 'biz_legal_corporate_01',
            ],
            'general' => [
                'modern' => 'business_default_modern_01',
                'minimal' => 'business_default_minimal_01',
                'luxury' => 'business_default_luxury_01',
                'playful' => 'business_default_playful_01',
                'corporate' => 'business_default_corporate_01',
            ],
        ],
        'ecommerce' => [
            'electronics' => [
                'modern' => 'shop_electronics_modern_01',
                'minimal' => 'shop_electronics_minimal_01',
                'luxury' => 'shop_electronics_luxury_01',
                'playful' => 'shop_electronics_playful_01',
                'corporate' => 'shop_electronics_corporate_01',
            ],
            'fashion' => [
                'modern' => 'shop_fashion_modern_01',
                'minimal' => 'shop_fashion_minimal_01',
                'luxury' => 'shop_fashion_luxury_01',
                'playful' => 'shop_fashion_playful_01',
                'corporate' => 'shop_fashion_corporate_01',
            ],
            'general' => [
                'modern' => 'ecommerce_default_modern_01',
                'minimal' => 'ecommerce_default_minimal_01',
                'luxury' => 'ecommerce_default_luxury_01',
                'playful' => 'ecommerce_default_playful_01',
                'corporate' => 'ecommerce_default_corporate_01',
            ],
        ],
        'portfolio' => [
            'general' => [
                'modern' => 'portfolio_default_modern_01',
                'minimal' => 'portfolio_default_minimal_01',
                'luxury' => 'portfolio_default_luxury_01',
                'playful' => 'portfolio_default_playful_01',
                'corporate' => 'portfolio_default_corporate_01',
            ],
        ],
        'booking' => [
            'general' => [
                'modern' => 'booking_default_modern_01',
                'minimal' => 'booking_default_minimal_01',
                'luxury' => 'booking_default_luxury_01',
                'playful' => 'booking_default_playful_01',
                'corporate' => 'booking_default_corporate_01',
            ],
        ],
    ],

    'template_fallbacks' => [
        'business' => 'business_default_modern_01',
        'ecommerce' => 'ecommerce_default_modern_01',
        'portfolio' => 'portfolio_default_modern_01',
        'booking' => 'booking_default_modern_01',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme preset by (category, style) for Ultra Cheap. Keys match theme preset ids.
    |--------------------------------------------------------------------------
    */
    'theme_preset_map' => [
        'beauty_salon' => ['modern' => 'feminine', 'minimal' => 'slate', 'luxury' => 'ruby', 'playful' => 'coral', 'corporate' => 'midnight'],
        'restaurant' => ['modern' => 'summer', 'minimal' => 'slate', 'luxury' => 'mocha', 'playful' => 'coral', 'corporate' => 'midnight'],
        'clinic' => ['modern' => 'default', 'minimal' => 'slate', 'luxury' => 'midnight', 'playful' => 'ocean', 'corporate' => 'midnight'],
        'legal' => ['modern' => 'midnight', 'minimal' => 'slate', 'luxury' => 'mocha', 'playful' => 'ocean', 'corporate' => 'midnight'],
        'electronics' => ['modern' => 'midnight', 'minimal' => 'slate', 'luxury' => 'mocha', 'playful' => 'coral', 'corporate' => 'midnight'],
        'fashion' => ['modern' => 'feminine', 'minimal' => 'slate', 'luxury' => 'ruby', 'playful' => 'coral', 'corporate' => 'midnight'],
        'general' => ['modern' => 'default', 'minimal' => 'slate', 'luxury' => 'mocha', 'playful' => 'coral', 'corporate' => 'midnight'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deterministic SEO templates. Placeholders: {Brand}, {Service}, {City}
    |--------------------------------------------------------------------------
    */
    'seo_templates' => [
        'title' => '{Brand} | {Service}',
        'meta_description' => 'Learn about {Brand} services. Contact us today.',
    ],

    'copy_bank_path' => 'data/copy-bank',
    'section_library_path' => 'data/sections/library',
    'cache_ttl_seconds' => 86400 * 7, // 7 days
];
