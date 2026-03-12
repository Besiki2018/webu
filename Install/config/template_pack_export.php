<?php

/**
 * Template Pack Export/Import format (ZIP structure).
 *
 * HTML/CSS is the presentation layer; layout schema (JSON) is the source of truth.
 * Local editing should modify CSS, tokens, and variant classes—not break bindings.
 *
 * @see docs/EXPORT_IMPORT_GUIDE.md
 * @see docs/TEMPLATE_SKIN_SYSTEM.md
 * @see docs/VALIDATION_RULES.md
 */
return [
    'format_version' => 1,

    'zip_root' => 'webu-template-pack',

    'paths' => [
        'manifest' => 'manifest.json',
        'layout' => [
            'pages' => 'layout/pages.json',
            'theme_tokens' => 'layout/theme.tokens.json',
            'variants' => 'layout/variants.json',
            'bindings_map' => 'layout/bindings.map.json',
        ],
        'components' => [
            'registry_snapshot' => 'components/registry.snapshot.json',
            'component_class_map' => 'overrides/component-class-map.json',
        ],
        'presentation' => [
            'css_tokens' => 'presentation/css/tokens.css',
            'css_base' => 'presentation/css/base.css',
            'css_components' => 'presentation/css/components.css',
            'css_template' => 'presentation/css/template.css',
            'preview_html' => 'presentation/html/preview.html',
            'mock_data' => 'presentation/html/mock-data',
        ],
        'assets' => [
            'images' => 'assets/images',
            'fonts' => 'assets/fonts',
        ],
        'docs' => [
            'readme' => 'docs/README.md',
            'bindings' => 'docs/BINDINGS.md',
            'builder_controls' => 'docs/BUILDER_CONTROLS.md',
        ],
    ],

    'manifest_required_keys' => [
        'format_version',
        'name',
        'slug',
        'exported_at',
        'source',
        'layout_version',
    ],

    /**
     * Required ecommerce page slugs for validation on import.
     */
    'required_ecommerce_pages' => ['home', 'shop', 'product', 'cart', 'checkout', 'contact'],
];
