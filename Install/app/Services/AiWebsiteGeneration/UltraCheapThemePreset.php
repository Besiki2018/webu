<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Resolves theme preset id for Ultra Cheap by (category, style).
 * Returns a key that maps to frontend theme presets (e.g. default, slate, feminine).
 */
class UltraCheapThemePreset
{
    public function resolve(string $category, string $style): array
    {
        $map = config('ultra_cheap.theme_preset_map', []);
        $categoryMap = $map[$category] ?? $map['general'] ?? ['modern' => 'default', 'minimal' => 'slate', 'luxury' => 'mocha', 'playful' => 'coral', 'corporate' => 'midnight'];
        $presetId = $categoryMap[$style] ?? $categoryMap['modern'] ?? 'default';

        return [
            'preset_id' => $presetId,
            'primary_color' => null,
        ];
    }
}
