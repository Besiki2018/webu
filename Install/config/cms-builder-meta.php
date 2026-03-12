<?php

/**
 * Canonical builder node meta contract (persisted meta keys).
 * Single source of truth for which meta keys are part of the persisted builder/node contract.
 * See docs/architecture/CMS_BUILDER_NODE_META_CONTRACT.md (G3 / P1-B1-03).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical persisted meta keys
    |--------------------------------------------------------------------------
    | Keys allowed in SectionLibrary.schema_json._meta and (future) content_json.sections[].meta.
    | Validators and adapters can use this list to allowlist or normalize meta.
    */
    'canonical_keys' => [
        'label',
        'locked',
        'hidden',
        'schema_version',
        'source',
        // Registry-only (already in use): description, design_variant
        'description',
        'design_variant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Keys that are registry-only (schema _meta), not per-node in content_json
    |--------------------------------------------------------------------------
    */
    'registry_only_keys' => [
        'description',
        'design_variant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default values for optional per-node meta (when sections[].meta is used)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'locked' => false,
        'hidden' => false,
    ],
];
