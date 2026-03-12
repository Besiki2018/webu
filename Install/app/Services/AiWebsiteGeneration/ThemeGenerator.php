<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Generates DesignTheme JSON: palette, typography, radius, spacing, component tokens.
 * Stored in website.theme (no hardcoded colors in components).
 */
class ThemeGenerator
{
    private const PRESETS = [
        'modern' => [
            'palette' => [
                'primary' => '#2563eb',
                'primary_foreground' => '#ffffff',
                'secondary' => '#f1f5f9',
                'background' => '#ffffff',
                'foreground' => '#0f172a',
                'muted' => '#f8fafc',
                'muted_foreground' => '#64748b',
            ],
            'typography' => [
                'font_family' => 'Inter, system-ui, sans-serif',
                'heading_size' => '1.5rem',
                'body_size' => '1rem',
            ],
            'radius' => ['default' => '0.5rem', 'button' => '0.375rem'],
            'spacing' => ['section' => '4rem', 'block' => '1.5rem'],
        ],
        'minimal' => [
            'palette' => [
                'primary' => '#18181b',
                'primary_foreground' => '#fafafa',
                'secondary' => '#f4f4f5',
                'background' => '#ffffff',
                'foreground' => '#18181b',
                'muted' => '#fafafa',
                'muted_foreground' => '#71717a',
            ],
            'typography' => [
                'font_family' => 'system-ui, sans-serif',
                'heading_size' => '1.25rem',
                'body_size' => '0.9375rem',
            ],
            'radius' => ['default' => '0.25rem', 'button' => '0'],
            'spacing' => ['section' => '3rem', 'block' => '1.25rem'],
        ],
        'luxury' => [
            'palette' => [
                'primary' => '#1e3a5f',
                'primary_foreground' => '#f5f5f4',
                'secondary' => '#f5f5f4',
                'background' => '#fafaf9',
                'foreground' => '#1c1917',
                'muted' => '#f5f5f4',
                'muted_foreground' => '#78716c',
            ],
            'typography' => [
                'font_family' => 'Georgia, serif',
                'heading_size' => '1.75rem',
                'body_size' => '1rem',
            ],
            'radius' => ['default' => '0', 'button' => '0'],
            'spacing' => ['section' => '5rem', 'block' => '2rem'],
        ],
        'playful' => [
            'palette' => [
                'primary' => '#c026d3',
                'primary_foreground' => '#ffffff',
                'secondary' => '#fdf2f8',
                'background' => '#ffffff',
                'foreground' => '#4c0519',
                'muted' => '#fce7f3',
                'muted_foreground' => '#9d174d',
            ],
            'typography' => [
                'font_family' => 'system-ui, sans-serif',
                'heading_size' => '1.5rem',
                'body_size' => '1rem',
            ],
            'radius' => ['default' => '1rem', 'button' => '9999px'],
            'spacing' => ['section' => '4rem', 'block' => '1.5rem'],
        ],
        'corporate' => [
            'palette' => [
                'primary' => '#0f766e',
                'primary_foreground' => '#ffffff',
                'secondary' => '#ccfbf1',
                'background' => '#ffffff',
                'foreground' => '#134e4a',
                'muted' => '#f0fdfa',
                'muted_foreground' => '#0f766e',
            ],
            'typography' => [
                'font_family' => 'system-ui, sans-serif',
                'heading_size' => '1.375rem',
                'body_size' => '1rem',
            ],
            'radius' => ['default' => '0.375rem', 'button' => '0.375rem'],
            'spacing' => ['section' => '3.5rem', 'block' => '1.5rem'],
        ],
    ];

    /**
     * @param  array{style: string}  $brief
     * @return array<string, mixed>
     */
    public function generate(array $brief): array
    {
        $style = $brief['style'] ?? 'modern';
        $preset = self::PRESETS[$style] ?? self::PRESETS['modern'];
        return array_merge($preset, [
            'preset' => $style,
            'component_tokens' => [
                'button' => ['padding' => '0.5rem 1.25rem', 'font_weight' => '600'],
                'card' => ['padding' => '1.5rem', 'border_width' => '1px'],
                'input' => ['padding' => '0.5rem 0.75rem', 'border_radius' => '0.375rem'],
            ],
        ]);
    }
}
