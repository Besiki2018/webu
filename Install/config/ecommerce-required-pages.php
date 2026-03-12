<?php

/**
 * Required ecommerce page slugs — cannot be deleted in ecommerce projects (new tasks.txt § 10).
 * Builder must warn / require confirmation / or disallow deletion of these pages.
 *
 * @see new tasks.txt — Required Ecommerce Guardrails
 * @see docs/architecture/WEBU_VISUAL_BUILDER_ARCHITECTURE.md
 */
return [
    'slugs' => [
        'home',
        'shop',
        'product',
        'cart',
        'checkout',
        'contact',
    ],

    /**
     * Template slugs or categories that indicate an ecommerce project (guard applies).
     */
    'ecommerce_template_slugs' => [
        'ecommerce-storefront',
        'ecommerce',
    ],

    'ecommerce_template_categories' => [
        'ecommerce',
    ],
];
