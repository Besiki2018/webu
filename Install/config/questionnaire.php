<?php

/**
 * E-commerce questionnaire: 10 core + 3 optional questions.
 * One question at a time; quick buttons; allow skip.
 * Answers → DesignBrief → template selection → blueprint.
 *
 * @see new tasks.txt — AI Chat Questionnaire System PART 1–3, 10
 */
return [
    'core_questions' => [
        [
            'key' => 'business_type',
            'label' => 'What kind of store do you want to create?',
            'placeholder' => 'e.g. fashion, cosmetics, electronics',
            'type' => 'choice',
            'options' => [
                ['value' => 'fashion', 'label' => 'Fashion'],
                ['value' => 'cosmetics', 'label' => 'Cosmetics'],
                ['value' => 'electronics', 'label' => 'Electronics'],
                ['value' => 'furniture', 'label' => 'Furniture'],
                ['value' => 'pet', 'label' => 'Pet products'],
                ['value' => 'kids', 'label' => 'Kids products'],
                ['value' => 'digital', 'label' => 'Digital products'],
                ['value' => 'general', 'label' => 'Other'],
            ],
            'required' => true,
            'skip' => false,
            'design_brief_key' => 'vertical',
        ],
        [
            'key' => 'store_name',
            'label' => 'What is the name of your store or brand?',
            'placeholder' => 'My Store',
            'type' => 'text',
            'required' => true,
            'skip' => false,
            'site_settings_key' => 'store_name',
        ],
        [
            'key' => 'brand_style',
            'label' => 'What style do you prefer?',
            'type' => 'choice',
            'options' => [
                ['value' => 'minimal', 'label' => 'Minimal'],
                ['value' => 'modern', 'label' => 'Modern'],
                ['value' => 'luxury', 'label' => 'Luxury'],
                ['value' => 'playful', 'label' => 'Playful'],
                ['value' => 'bold', 'label' => 'Bold'],
                ['value' => 'corporate', 'label' => 'Corporate'],
            ],
            'required' => true,
            'skip' => false,
            'design_brief_key' => 'vibe',
        ],
        [
            'key' => 'logo',
            'label' => 'Do you have a logo? You can upload it now or skip for later.',
            'type' => 'upload_or_skip',
            'required' => false,
            'skip' => true,
            'site_settings_key' => 'logo',
        ],
        [
            'key' => 'brand_colors',
            'label' => 'Do you have brand colors? (primary, optional secondary)',
            'type' => 'colors_or_skip',
            'required' => false,
            'skip' => true,
            'design_brief_key' => 'content_assets',
        ],
        [
            'key' => 'product_volume',
            'label' => 'How many products do you plan to sell initially?',
            'type' => 'choice',
            'options' => [
                ['value' => '1-10', 'label' => '1–10'],
                ['value' => '10-50', 'label' => '10–50'],
                ['value' => '50+', 'label' => '50+'],
            ],
            'required' => true,
            'skip' => false,
            'design_brief_key' => 'product_volume',
        ],
        [
            'key' => 'payments',
            'label' => 'What payment methods do you want?',
            'type' => 'multi_choice',
            'options' => [
                ['value' => 'card', 'label' => 'Card payment'],
                ['value' => 'cash_on_delivery', 'label' => 'Cash on delivery'],
                ['value' => 'bank_transfer', 'label' => 'Bank transfer'],
            ],
            'required' => true,
            'skip' => false,
            'design_brief_key' => 'payments',
        ],
        [
            'key' => 'shipping',
            'label' => 'How will you deliver orders?',
            'type' => 'multi_choice',
            'options' => [
                ['value' => 'courier', 'label' => 'Courier delivery'],
                ['value' => 'pickup', 'label' => 'Local pickup'],
                ['value' => 'digital', 'label' => 'Digital delivery'],
            ],
            'required' => true,
            'skip' => false,
            'design_brief_key' => 'shipping',
        ],
        [
            'key' => 'currency',
            'label' => 'Which currency will your store use?',
            'placeholder' => 'GEL, USD, EUR',
            'type' => 'text',
            'required' => true,
            'skip' => false,
            'site_settings_key' => 'currency',
            'design_brief_key' => 'currency',
        ],
        [
            'key' => 'contact',
            'label' => 'What is your contact phone or email?',
            'placeholder' => 'Email or phone',
            'type' => 'text',
            'required' => true,
            'skip' => false,
            'site_settings_key' => 'contact',
            'design_brief_key' => 'contact',
        ],
    ],

    'optional_questions' => [
        [
            'key' => 'target_audience',
            'label' => 'Who are your customers?',
            'type' => 'choice',
            'options' => [
                ['value' => 'women', 'label' => 'Women'],
                ['value' => 'men', 'label' => 'Men'],
                ['value' => 'kids', 'label' => 'Kids'],
                ['value' => 'everyone', 'label' => 'Everyone'],
            ],
            'required' => false,
            'skip' => true,
            'design_brief_key' => 'target_audience',
        ],
        [
            'key' => 'store_tone',
            'label' => 'Which tone fits your brand?',
            'type' => 'choice',
            'options' => [
                ['value' => 'premium', 'label' => 'Premium'],
                ['value' => 'friendly', 'label' => 'Friendly'],
                ['value' => 'technical', 'label' => 'Technical'],
                ['value' => 'luxury', 'label' => 'Luxury'],
            ],
            'required' => false,
            'skip' => true,
            'design_brief_key' => 'tone',
        ],
        [
            'key' => 'homepage_focus',
            'label' => 'What should the homepage highlight?',
            'type' => 'choice',
            'options' => [
                ['value' => 'products', 'label' => 'Products'],
                ['value' => 'categories', 'label' => 'Categories'],
                ['value' => 'brand_story', 'label' => 'Brand story'],
                ['value' => 'promotions', 'label' => 'Promotions'],
            ],
            'required' => false,
            'skip' => true,
            'design_brief_key' => 'homepage_focus',
        ],
    ],

    'fail_safe_defaults' => [
        'vertical' => 'ecommerce',
        'vibe' => 'luxury_minimal',
        'template' => 'ecommerce-storefront',
    ],
];
