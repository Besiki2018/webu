<?php

return [
    'demo_content' => [
        'enabled' => (bool) env('CMS_DEMO_CONTENT_ENABLED', true),
        'seed_in_testing' => (bool) env('CMS_DEMO_CONTENT_SEED_IN_TESTING', false),
    ],

    'typography' => [
        'default_font_key' => 'tbc-contractica',
        'fallback_stack' => '"Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
        'fonts' => array_merge(
            [
                [
                    'key' => 'tbc-contractica',
                    'label' => 'TBC Contractica',
                    'stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                    'font_faces' => [
                        [
                            'font_family' => 'TBC Contractica',
                            'src_url' => '/fonts/TBCContractica-Light.woff2',
                            'format' => 'woff2',
                            'font_weight' => 300,
                            'font_style' => 'normal',
                            'font_display' => 'swap',
                        ],
                        [
                            'font_family' => 'TBC Contractica',
                            'src_url' => '/fonts/TBCContractica-Regular.woff2',
                            'format' => 'woff2',
                            'font_weight' => 400,
                            'font_style' => 'normal',
                            'font_display' => 'swap',
                        ],
                        [
                            'font_family' => 'TBC Contractica',
                            'src_url' => '/fonts/TBCContractica-Medium.woff2',
                            'format' => 'woff2',
                            'font_weight' => 500,
                            'font_style' => 'normal',
                            'font_display' => 'swap',
                        ],
                        [
                            'font_family' => 'TBC Contractica',
                            'src_url' => '/fonts/TBCContractica-Bold.woff2',
                            'format' => 'woff2',
                            'font_weight' => 700,
                            'font_style' => 'normal',
                            'font_display' => 'swap',
                        ],
                        [
                            'font_family' => 'TBC Contractica',
                            'src_url' => '/fonts/TBCContractica-Black.woff2',
                            'format' => 'woff2',
                            'font_weight' => 900,
                            'font_style' => 'normal',
                            'font_display' => 'swap',
                        ],
                    ],
                    'source_type' => 'system',
                ],
            ],
            require __DIR__.'/cms_typography_web_fonts.php'
        ),
    ],
];
