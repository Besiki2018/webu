<?php

/**
 * Business Template Map — single source for AI template selection.
 *
 * Maps business_type (+ optional design_preference, target_market) to template_slug and theme_variant.
 * AI must choose template using this map; no freestyle layout generation.
 *
 * @see new tasks.txt PART 2 — Implement Template Selection Engine
 * @see App\Services\DesignDecisionService
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Primary mapping: business_type => template_slug
    | Used when no design_preference is provided.
    |--------------------------------------------------------------------------
    */
    'business_type_to_template' => [
        'fashion_store' => 'ecommerce-fashion',
        'fashion' => 'ecommerce-fashion',
        'electronics_store' => 'ecommerce-electronics',
        'electronics' => 'ecommerce-electronics',
        'beauty_store' => 'ecommerce-beauty',
        'beauty' => 'ecommerce-beauty',
        'cosmetics' => 'ecommerce-cosmetics',
        'pet_store' => 'ecommerce-pet',
        'pet' => 'ecommerce-pet',
        'furniture_store' => 'ecommerce-furniture',
        'furniture' => 'ecommerce-furniture',
        'kids_store' => 'ecommerce-kids',
        'kids' => 'ecommerce-kids',
        'sports_store' => 'ecommerce-sports',
        'sports' => 'ecommerce-sports',
        'jewelry_store' => 'ecommerce-jewelry',
        'jewelry' => 'ecommerce-jewelry',
        'organic_food' => 'ecommerce-grocery',
        'grocery' => 'ecommerce-grocery',
        'food_delivery' => 'ecommerce-food-delivery',
        'food' => 'ecommerce-food-delivery',
        'digital_products' => 'ecommerce-digital',
        'digital' => 'ecommerce-digital',
        'luxury_watch' => 'ecommerce-luxury-boutique',
        'luxury' => 'ecommerce-luxury-boutique',
        'sneaker' => 'ecommerce-sneaker',
        'gadget_shop' => 'ecommerce-electronics',
        'home_decor' => 'ecommerce-furniture',
        'startup_store' => 'ecommerce-minimal-startup',
        'startup' => 'ecommerce-minimal-startup',
        'creative_brand' => 'ecommerce-creative-boutique',
        'streetwear' => 'ecommerce-streetwear',
        'watches' => 'ecommerce-watches',
        'skincare' => 'ecommerce-skincare',
        'makeup_studio' => 'ecommerce-makeup-studio',
        'gaming' => 'ecommerce-gaming',
        'smart_home' => 'ecommerce-smart-home',
        'phone_store' => 'ecommerce-phone-store',
        'scandinavian' => 'ecommerce-scandinavian',
        'lighting' => 'ecommerce-lighting',
        'kitchen_dining' => 'ecommerce-kitchen-dining',
        'dog_accessories' => 'ecommerce-dog-accessories',
        'cat_store' => 'ecommerce-cat-store',
        'pet_food' => 'ecommerce-pet-food',
        'educational_toys' => 'ecommerce-educational-toys',
        'outdoor' => 'ecommerce-outdoor',
        'fitness' => 'ecommerce-fitness',
        'supplements' => 'ecommerce-supplements',
        'coffee' => 'ecommerce-coffee',
        'tea' => 'ecommerce-tea',
        'default' => 'ecommerce-storefront',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extended mapping: (business_type, design_preference) => [template_slug, theme_variant]
    | When design_preference is provided, override template for that vertical.
    |--------------------------------------------------------------------------
    */
    'by_design_preference' => [
        'fashion' => [
            'luxury_minimal' => ['ecommerce-luxury-boutique', 'luxury_minimal'],
            'minimal' => ['ecommerce-fashion', 'luxury_minimal'],
            'bold' => ['ecommerce-bold-startup', 'bold_startup'],
            'dark' => ['ecommerce-dark-modern', 'dark_modern'],
        ],
        'electronics' => [
            'modern' => ['ecommerce-electronics', 'corporate_clean'],
            'dark' => ['ecommerce-dark-modern', 'dark_modern'],
        ],
        'beauty' => [
            'soft' => ['ecommerce-soft-pastel', 'soft_pastel'],
            'luxury' => ['ecommerce-beauty', 'luxury_minimal'],
        ],
        'pet' => [
            'playful' => ['ecommerce-pet', 'bold_startup'],
        ],
        'kids' => [
            'colorful' => ['ecommerce-kids', 'soft_pastel'],
        ],
        'furniture' => [
            'minimal' => ['ecommerce-furniture', 'luxury_minimal'],
            'modern' => ['ecommerce-furniture', 'corporate_clean'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback template when business_type is unknown
    |--------------------------------------------------------------------------
    */
    'fallback_template' => 'ecommerce-storefront',

    /*
    |--------------------------------------------------------------------------
    | Default theme preset when not specified by mapping
    |--------------------------------------------------------------------------
    */
    'fallback_theme_preset' => 'luxury_minimal',
];
