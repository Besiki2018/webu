<?php

/**
 * Canonical binding namespaces for the CMS visual builder "Dynamic" field picker.
 * Single source of truth for which namespaces and example paths to show per control kind.
 * Aligns with docs/architecture/CMS_BINDING_NAMESPACE_STANDARDIZATION.md (P1-B2-01).
 *
 * Keys: text | link | image (control kinds used by resolveDynamicControlHookForField).
 * Each value: binding_namespaces (order shown in UI), examples (suggested {{path}} expressions).
 */

return [
    'text' => [
        'binding_namespaces' => [
            'site',
            'page',
            'route',
            'global',
            'menu',
            'ecommerce',
            'booking',
            'content',
            'customer',
        ],
        'examples' => [
            '{{site.name}}',
            '{{page.title}}',
            '{{global.contact.phone}}',
        ],
    ],
    'link' => [
        'binding_namespaces' => [
            'route',
            'page',
            'site',
            'global',
            'ecommerce',
            'booking',
            'customer',
        ],
        'examples' => [
            '{{page.slug}}',
            '{{route.slug}}',
        ],
    ],
    'image' => [
        'binding_namespaces' => [
            'global',
            'site',
            'ecommerce',
            'content',
        ],
        'examples' => [
            '{{global.logo.url}}',
        ],
    ],
];
