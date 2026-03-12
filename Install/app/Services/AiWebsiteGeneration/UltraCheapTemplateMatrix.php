<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Resolves template_id from (websiteType, category, style) for Ultra Cheap Mode.
 */
class UltraCheapTemplateMatrix
{
    public function resolve(string $websiteType, string $category, string $style): string
    {
        $matrix = config('ultra_cheap.template_matrix', []);
        $fallbacks = config('ultra_cheap.template_fallbacks', []);

        $byType = $matrix[$websiteType] ?? null;
        if (! is_array($byType)) {
            return $fallbacks[$websiteType] ?? $websiteType.'_default_modern_01';
        }

        $byCategory = $byType[$category] ?? $byType['general'] ?? null;
        if (! is_array($byCategory)) {
            return $fallbacks[$websiteType] ?? $websiteType.'_default_modern_01';
        }

        $templateId = $byCategory[$style] ?? $byCategory['modern'] ?? null;
        if (is_string($templateId)) {
            return $templateId;
        }

        return $fallbacks[$websiteType] ?? $websiteType.'_default_modern_01';
    }
}
