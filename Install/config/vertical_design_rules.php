<?php

/**
 * Vertical design rules (Section 8 — Template Metadata + AI Scoring + Vertical Rules).
 *
 * Per-vertical: preferred_templates (template slugs), hero_style, typography, spacing,
 * section_priority, avoid. Used to guide template choice, tokens, layout density, section order.
 * Theme token presets per vertical come from business_template_map / theme-presets.
 *
 * @see new tasks.txt — PART 4 Vertical Design Rules
 */
return [
    'fashion' => [
        'preferred_templates' => ['ecommerce-luxury-boutique', 'ecommerce-fashion', 'ecommerce-creative-boutique', 'ecommerce-soft-pastel'],
        'hero_style' => 'large_image',
        'typography' => 'editorial',
        'spacing' => 'large',
        'section_priority' => ['hero', 'category_grid', 'product_grid', 'testimonials', 'newsletter'],
        'avoid' => ['dense_layouts'],
        'design_style' => 'editorial',
    ],
    'electronics' => [
        'preferred_templates' => ['ecommerce-dark-modern', 'ecommerce-corporate-clean', 'ecommerce-electronics'],
        'hero_style' => 'product_highlight',
        'typography' => 'technical',
        'spacing' => 'compact',
        'section_priority' => ['hero', 'product_grid', 'specs'],
        'required_sections' => ['specs_table'],
        'design_style' => 'technical',
    ],
    'beauty' => [
        'preferred_templates' => ['ecommerce-soft-pastel', 'ecommerce-beauty', 'ecommerce-cosmetics', 'ecommerce-luxury-boutique'],
        'hero_style' => 'model_imagery',
        'typography' => 'elegant',
        'spacing' => 'balanced',
        'section_priority' => ['hero', 'product_grid', 'benefits', 'testimonials', 'newsletter'],
        'design_style' => 'elegant',
    ],
    'cosmetics' => [
        'preferred_templates' => ['ecommerce-soft-pastel', 'ecommerce-beauty', 'ecommerce-cosmetics'],
        'hero_style' => 'model_imagery',
        'typography' => 'elegant',
        'spacing' => 'balanced',
        'design_style' => 'elegant',
    ],
    'furniture' => [
        'preferred_templates' => ['ecommerce-luxury-boutique', 'ecommerce-furniture', 'ecommerce-corporate-clean'],
        'hero_style' => 'room_scene',
        'typography' => 'editorial',
        'spacing' => 'wide',
        'section_priority' => ['hero', 'product_grid', 'materials', 'dimensions'],
        'design_style' => 'editorial',
    ],
    'pet' => [
        'preferred_templates' => ['ecommerce-pet', 'ecommerce-soft-pastel', 'ecommerce-bold-startup'],
        'hero_style' => 'playful',
        'typography' => 'friendly',
        'spacing' => 'balanced',
        'design_style' => 'playful',
    ],
    'kids' => [
        'preferred_templates' => ['ecommerce-kids', 'ecommerce-soft-pastel'],
        'hero_style' => 'playful',
        'typography' => 'friendly',
        'spacing' => 'balanced',
        'design_style' => 'playful',
    ],
    'jewelry' => [
        'preferred_templates' => ['ecommerce-jewelry', 'ecommerce-luxury-boutique', 'ecommerce-fashion'],
        'hero_style' => 'large_image',
        'typography' => 'editorial',
        'spacing' => 'large',
        'design_style' => 'editorial',
    ],
    'sports' => [
        'preferred_templates' => ['ecommerce-sports', 'ecommerce-dark-modern', 'ecommerce-bold-startup'],
        'hero_style' => 'backgroundimage',
        'typography' => 'bold',
        'spacing' => 'compact',
        'design_style' => 'bold',
    ],
    'food' => [
        'preferred_templates' => ['ecommerce-grocery', 'ecommerce-food-delivery', 'ecommerce-organic-food'],
        'hero_style' => 'centered',
        'typography' => 'clean',
        'spacing' => 'balanced',
        'design_style' => 'clean',
    ],
    'default' => [
        'preferred_templates' => ['ecommerce-storefront', 'ecommerce-corporate-clean'],
        'hero_style' => 'centered',
        'typography' => 'clean',
        'spacing' => 'balanced',
        'design_style' => 'clean',
    ],
];
