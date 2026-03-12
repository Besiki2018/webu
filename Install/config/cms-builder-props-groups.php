<?php

/**
 * Canonical props grouping for CMS builder sections (logical groups only; storage stays flat).
 * See docs/architecture/CMS_BUILDER_PROPS_GROUPING_CONTRACT.md (G1).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical group names (order used for UI / adapter)
    |--------------------------------------------------------------------------
    */
    'groups' => [
        'content',
        'data',
        'style',
        'advanced',
        'responsive',
        'states',
    ],

    /*
    |--------------------------------------------------------------------------
    | Human-readable labels (optional; builder can use for tab or group headers)
    |--------------------------------------------------------------------------
    */
    'labels' => [
        'content' => 'Content',
        'data' => 'Data',
        'style' => 'Style',
        'advanced' => 'Advanced',
        'responsive' => 'Responsive',
        'states' => 'States',
    ],
];
