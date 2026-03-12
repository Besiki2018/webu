<?php

/**
 * Canonical style capability flags for CMS builder sections (schema/resolver contract).
 * See docs/architecture/CMS_BUILDER_STYLE_RESOLUTION_CONTRACT.md (G6 Phase 1).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical capability flag names
    |--------------------------------------------------------------------------
    | Flags that section/control schema can carry (e.g. in _meta.capabilities) so
    | a future shared style resolver knows how to merge base → responsive → state.
    */
    'flags' => [
        'responsive',
        'state_style',
        'inherits_theme',
    ],

    /*
    |--------------------------------------------------------------------------
    | Descriptions (for docs / UI tooltips)
    |--------------------------------------------------------------------------
    */
    'descriptions' => [
        'responsive' => 'Supports breakpoint-specific overrides (desktop/tablet/mobile).',
        'state_style' => 'Supports per-state style (hover, focus, disabled). Reserved for Phase 3.',
        'inherits_theme' => 'Base style can inherit from theme tokens; resolver merges theme layer first.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolution layer order (for shared resolver Phase 3)
    |--------------------------------------------------------------------------
    */
    'layer_order' => ['base', 'responsive', 'state'],
];
