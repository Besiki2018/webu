<?php

namespace App\Services;

use Illuminate\Support\Str;

class ComponentVariantRegistry
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Resolve layout and style variant from section type and props.
     * Falls back to default when missing or invalid.
     *
     * @param  array<string, mixed>  $props
     * @return array{layout: string, style: string}
     */
    public function resolveFromProps(string $sectionType, array $props): array
    {
        $normalized = Str::lower(Str::trim($sectionType));
        if ($normalized === '') {
            return ['layout' => '', 'style' => ''];
        }

        $entry = $this->config[$normalized] ?? null;
        if (! is_array($entry)) {
            return ['layout' => '', 'style' => ''];
        }

        $layoutVariants = is_array($entry['layout_variants'] ?? null) ? $entry['layout_variants'] : [];
        $styleVariants = is_array($entry['style_variants'] ?? null) ? $entry['style_variants'] : [];
        $defaultLayout = is_string($entry['default_layout'] ?? null) ? $entry['default_layout'] : '';
        $defaultStyle = is_string($entry['default_style'] ?? null) ? $entry['default_style'] : '';

        $layout = $this->pickVariant($props, ['layout_variant', 'layoutVariant', 'hero_variant'], $layoutVariants, $defaultLayout);
        $style = $this->pickVariant($props, ['style_variant', 'styleVariant'], $styleVariants, $defaultStyle);

        return ['layout' => $layout, 'style' => $style];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $propKeys
     * @param  array<int, string>  $allowed
     */
    private function pickVariant(array $props, array $propKeys, array $allowed, string $default): string
    {
        $value = null;
        foreach ($propKeys as $key) {
            if (isset($props[$key]) && is_string($props[$key])) {
                $value = Str::lower(Str::trim($props[$key]));
                break;
            }
        }
        if ($value === null || $value === '') {
            return $default;
        }
        $allowedNormalized = array_map(static fn (string $v): string => Str::lower(Str::trim($v)), $allowed);
        if (in_array($value, $allowedNormalized, true)) {
            return $value;
        }

        return $default;
    }
}
