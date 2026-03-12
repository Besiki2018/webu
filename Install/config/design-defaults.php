<?php

/**
 * Design defaults for generated sites (Part 2 – premium Shopify-style quality).
 * Used by AI placement styling, theme generation, and Design Guard (PART 4 Design Quality Rules).
 *
 * @see new tasks.txt — PART 4 Design Quality Rules, PART 3 Design Threshold
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Minimum design score (Design Guard threshold)
    |--------------------------------------------------------------------------
    */
    'min_design_score' => (int) (env('DESIGN_MIN_SCORE', 85)),

    /*
    |--------------------------------------------------------------------------
    | Spacing scale (8px base)
    |--------------------------------------------------------------------------
    */
    'spacing' => [
        'base_unit' => 8,
        'section_y' => '4rem',       // 64px – vertical rhythm between sections
        'stack_gap' => '1rem',       // 16px – gap within stacks/cards
        'container_x' => '1.25rem',  // 20px – horizontal padding of container
        'card_padding' => '1rem',    // 16px – consistent card inner padding
    ],

    /*
    |--------------------------------------------------------------------------
    | Container and layout
    |--------------------------------------------------------------------------
    */
    'container' => [
        'max_width' => '1290px',     // General component container
        'narrow' => '720px',         // Contact, about, account
    ],

    /*
    |--------------------------------------------------------------------------
    | Typography hierarchy (H1 H2 H3 body — premium design rules)
    |--------------------------------------------------------------------------
    */
    'typography' => [
        'h1' => '48px',
        'h2' => '36px',
        'h3' => '24px',
        'body' => '16px',
        'hero_scale' => 'clamp(2rem, 4vw, 3.5rem)',
        'section_title_scale' => 'clamp(1.25rem, 2vw, 1.5rem)',
        'body_size' => '1rem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed spacing values (8px grid)
    |--------------------------------------------------------------------------
    */
    'allowed_spacing_px' => [8, 16, 24, 32, 48, 64],

    /*
    |--------------------------------------------------------------------------
    | AI theme customization — allowed keys only (PART 6)
    |--------------------------------------------------------------------------
    | AI may modify only: primary color, secondary color, font/typography,
    | button radius (radii), spacing density. No layout structure changes.
    | Top-level keys allowed in theme_settings_patch from AI.
    */
    'cms_ai_theme_allowed_patch_keys' => ['preset', 'colors', 'theme_tokens', 'typography'],

    /*
    | Within theme_tokens from AI only these groups are allowed (no breakpoints/shadows).
    */
    'cms_ai_theme_allowed_token_groups' => ['version', 'colors', 'radii', 'spacing'],
];
