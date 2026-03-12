<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Generates theme_tokens from DesignBrief and optional brand colors.
 * Enforces contrast, 8px spacing, single primary + accent, typography hierarchy.
 *
 * @see new tasks.txt — AI Design Director PART 3
 */
class ThemeTokenComposer
{
    public function __construct(
        protected CmsThemeTokenValueValidator $validator
    ) {}

    /**
     * @param  array{vertical?: string, vibe?: string, layout_density?: string}  $designBrief
     * @param  array{primary_color?: string, secondary_color?: string}  $brandColors
     * @return array{valid: bool, theme_tokens: array<string, mixed>, errors: array}
     */
    public function compose(array $designBrief, array $brandColors = []): array
    {
        $vibe = Str::lower(trim((string) ($designBrief['vibe'] ?? 'luxury_minimal')));
        $density = Str::lower(trim((string) ($designBrief['layout_density'] ?? 'balanced')));

        $tokens = [
            'version' => 1,
            'colors' => $this->colorsForVibe($vibe, $brandColors),
            'radii' => $this->radiiForVibe($vibe),
            'spacing' => $this->spacingForDensity($density),
            'typography' => $this->typographyForVibe($vibe),
        ];

        $themeSettings = ['theme_tokens' => $tokens];
        $validation = $this->validator->validate($themeSettings);
        if (! ($validation['valid'] ?? false)) {
            $tokens = $this->fallbackTokens();
            $themeSettings = ['theme_tokens' => $tokens];
            $validation = $this->validator->validate($themeSettings);
        }

        return [
            'valid' => $validation['valid'],
            'theme_tokens' => $tokens,
            'errors' => $validation['errors'] ?? [],
        ];
    }

    /**
     * @param  array{primary_color?: string, secondary_color?: string}  $brandColors
     * @return array<string, string>
     */
    private function colorsForVibe(string $vibe, array $brandColors): array
    {
        $primary = $brandColors['primary_color'] ?? $brandColors['primary'] ?? null;
        $secondary = $brandColors['secondary_color'] ?? $brandColors['secondary'] ?? null;
        if ($primary !== null && is_string($primary) && $primary !== '') {
            $colors = ['primary' => $this->normalizeHex($primary)];
            if ($secondary !== null && is_string($secondary) && $secondary !== '') {
                $colors['accent'] = $this->normalizeHex($secondary);
            }
            return $colors;
        }

        return match (true) {
            str_contains($vibe, 'dark') => ['primary' => '#e2e8f0', 'accent' => '#94a3b8'],
            str_contains($vibe, 'bold') => ['primary' => '#0f172a', 'accent' => '#3b82f6'],
            str_contains($vibe, 'soft') => ['primary' => '#64748b', 'accent' => '#fda4af'],
            str_contains($vibe, 'corporate') => ['primary' => '#1e293b', 'accent' => '#0ea5e9'],
            str_contains($vibe, 'luxury') => ['primary' => '#1c1917', 'accent' => '#a8a29e'],
            default => ['primary' => '#1e293b', 'accent' => '#64748b'],
        };
    }

    private function normalizeHex(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $color)) {
            return $color;
        }
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
            return '#' . $color;
        }
        return '#1e293b';
    }

    /**
     * @return array<string, string>
     */
    private function radiiForVibe(string $vibe): array
    {
        return match (true) {
            str_contains($vibe, 'luxury') => ['base' => '0.25rem', 'button' => '0.375rem'],
            str_contains($vibe, 'tech') => ['base' => '0.375rem', 'button' => '0.5rem'],
            str_contains($vibe, 'soft') => ['base' => '0.5rem', 'button' => '0.75rem'],
            default => ['base' => '0.375rem', 'button' => '0.5rem'],
        };
    }

    /**
     * 8px grid: 8, 16, 24, 32, 48, 64
     *
     * @return array<string, string>
     */
    private function spacingForDensity(string $density): array
    {
        $base = config('design-defaults.spacing.base_unit', 8);
        $values = [
            'xs' => ($base * 1) . 'px',
            'sm' => ($base * 2) . 'px',
            'md' => ($base * 3) . 'px',
            'lg' => ($base * 4) . 'px',
            'xl' => ($base * 6) . 'px',
            '2xl' => ($base * 8) . 'px',
        ];
        if ($density === 'compact') {
            $values['section_y'] = ($base * 4) . 'px';
        } elseif ($density === 'comfortable') {
            $values['section_y'] = ($base * 10) . 'px';
        } else {
            $values['section_y'] = ($base * 8) . 'px';
        }
        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function typographyForVibe(string $vibe): array
    {
        $scale = [
            'h1' => '48px',
            'h2' => '36px',
            'h3' => '24px',
            'body' => '16px',
        ];
        return array_merge(config('design-defaults.typography', []), $scale);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackTokens(): array
    {
        return [
            'version' => 1,
            'colors' => ['primary' => '#1e293b'],
            'radii' => ['base' => '0.375rem'],
            'spacing' => [
                'xs' => '8px', 'sm' => '16px', 'md' => '24px',
                'lg' => '32px', 'xl' => '48px', '2xl' => '64px',
            ],
        ];
    }
}
