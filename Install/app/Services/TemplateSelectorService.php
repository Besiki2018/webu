<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Selects best template from DesignBrief or prompt using BusinessTemplateMap and template metadata.
 * AI must choose template using this service; no freestyle layout.
 * PART 6: When multiple templates match (e.g. same vertical), prefer higher quality_score.
 * Section 8: Uses TemplateScoreCalculator when selecting from DesignBrief; minimum score 70, else fallback.
 *
 * @see new tasks.txt — Template Selection Engine, Template Metadata PART 2–3, Design Guard PART 6, Section 8
 */
class TemplateSelectorService
{
    public function __construct(
        protected ?TemplateScoreCalculator $scoreCalculator = null
    ) {
        $this->scoreCalculator = $scoreCalculator ?? new TemplateScoreCalculator;
    }
    /**
     * Select template slug from DesignBrief (vertical, vibe, recommended_templates).
     *
     * @param  array{vertical?: string, vibe?: string, recommended_templates?: array<int, string>}  $designBrief
     * @return array{template_id: string, theme_variant: string, reason: string}
     */
    public function selectFromDesignBrief(array $designBrief): array
    {
        $vertical = Str::lower(trim((string) ($designBrief['vertical'] ?? 'ecommerce')));
        $vibe = Str::lower(trim((string) ($designBrief['vibe'] ?? 'luxury_minimal')));
        $recommended = $designBrief['recommended_templates'] ?? [];

        // Section 8: score all templates; minimum score 70, else fallback
        $result = $this->scoreCalculator->selectBest($designBrief);
        $templateId = $result['template_id'];
        $usedFallback = $result['used_fallback'];

        if (! $usedFallback) {
            $templateId = $this->preferHigherQualityTemplateForSlug($templateId);
            $themePreset = $this->vibeToThemePreset($vibe);
            return [
                'template_id' => $templateId,
                'theme_variant' => $themePreset,
                'reason' => 'design_brief_score',
                'score' => $result['score'],
                'alternatives' => $result['alternatives'],
            ];
        }

        // Section 8: score < 70 → use config fallback template
        $fallback = config('business_template_map.fallback_template', 'ecommerce-storefront');
        $themeFallback = config('business_template_map.fallback_theme_preset', 'luxury_minimal');
        return [
            'template_id' => $fallback,
            'theme_variant' => $themeFallback,
            'reason' => 'score_below_minimum',
        ];
    }

    /**
     * Select template from prompt text (e.g. "fashion store", "pet shop").
     * Used when no DesignBrief is available.
     *
     * @return array{template_id: string, theme_variant: string, reason: string}
     */
    public function selectFromPrompt(string $prompt): array
    {
        $prompt = Str::lower($prompt);
        $map = config('business_template_map.business_type_to_template', []);
        $fallback = config('business_template_map.fallback_template', 'ecommerce-storefront');
        $themeFallback = config('business_template_map.fallback_theme_preset', 'luxury_minimal');

        $keywords = [
            'fashion' => 'ecommerce-fashion',
            'electronics' => 'ecommerce-electronics',
            'beauty' => 'ecommerce-beauty',
            'cosmetics' => 'ecommerce-cosmetics',
            'pet' => 'ecommerce-pet',
            'kids' => 'ecommerce-kids',
            'furniture' => 'ecommerce-furniture',
            'jewelry' => 'ecommerce-jewelry',
            'luxury' => 'ecommerce-luxury-boutique',
            'sports' => 'ecommerce-sports',
            'sneaker' => 'ecommerce-sports',
            'grocery' => 'ecommerce-grocery',
            'food' => 'ecommerce-food-delivery',
            'digital' => 'ecommerce-digital',
            'startup' => 'ecommerce-minimal-startup',
            'gadget' => 'ecommerce-electronics',
            'organic' => 'ecommerce-grocery',
        ];

        foreach ($keywords as $keyword => $slug) {
            if (str_contains($prompt, $keyword)) {
                $byPref = config('business_template_map.by_design_preference', []);
                $vibe = $themeFallback;
                if (str_contains($prompt, 'dark')) {
                    $vibe = 'dark_modern';
                } elseif (str_contains($prompt, 'minimal') || str_contains($prompt, 'luxury')) {
                    $vibe = 'luxury_minimal';
                } elseif (str_contains($prompt, 'bold') || str_contains($prompt, 'modern')) {
                    $vibe = 'bold_startup';
                } elseif (str_contains($prompt, 'soft') || str_contains($prompt, 'pastel')) {
                    $vibe = 'soft_pastel';
                }
                $verticalKey = str_contains($prompt, 'fashion') ? 'fashion' : (str_contains($prompt, 'electronics') ? 'electronics' : $keyword);
                if (isset($byPref[$verticalKey][$vibe])) {
                    $pair = $byPref[$verticalKey][$vibe];
                    $slug = is_array($pair) ? ($pair[0] ?? $slug) : $slug;
                    $vibe = is_array($pair) ? ($pair[1] ?? $vibe) : $vibe;
                }
                $templateId = $map[$keyword] ?? $slug;
                $templateId = $this->preferHigherQualityTemplateForSlug($templateId);
                return [
                    'template_id' => $templateId,
                    'theme_variant' => $vibe,
                    'reason' => 'prompt_keyword',
                ];
            }
        }

        return [
            'template_id' => $fallback,
            'theme_variant' => $themeFallback,
            'reason' => 'fallback',
        ];
    }

    private function resolveFromBusinessType(string $vertical, string $vibe): array
    {
        $map = config('business_template_map.business_type_to_template', []);
        $byPref = config('business_template_map.by_design_preference', []);
        $fallback = config('business_template_map.fallback_template', 'ecommerce-storefront');
        $themeFallback = config('business_template_map.fallback_theme_preset', 'luxury_minimal');

        $templateId = $map[$vertical] ?? $fallback;
        $themeVariant = $themeFallback;
        if (isset($byPref[$vertical]) && is_array($byPref[$vertical])) {
            $themeVariant = $byPref[$vertical][$vibe] ?? $themeVariant;
            if (is_array($themeVariant)) {
                $templateId = $themeVariant[0] ?? $templateId;
                $themeVariant = $themeVariant[1] ?? $themeFallback;
            }
        }

        return [
            'template_id' => $templateId,
            'theme_variant' => is_string($themeVariant) ? $themeVariant : $themeFallback,
        ];
    }

    private function vibeAliasMatch(string $vibe, array $vibes): bool
    {
        $aliases = [
            'luxury_minimal' => ['luxury_minimal', 'minimal', 'luxury'],
            'dark_modern' => ['dark_modern', 'dark'],
            'bold_startup' => ['bold_startup', 'bold', 'modern'],
            'soft_pastel' => ['soft_pastel', 'soft', 'pastel'],
            'corporate_clean' => ['corporate_clean', 'corporate'],
        ];
        $check = $aliases[$vibe] ?? [$vibe];
        foreach ($vibes as $v) {
            if (in_array($v, $check, true)) {
                return true;
            }
        }
        return false;
    }

    private function vibeToThemePreset(string $vibe): string
    {
        $catalog = config('theme-presets', []);
        $key = Str::slug($vibe, '_');
        if (is_array($catalog) && isset($catalog[$key])) {
            return $key;
        }
        $map = [
            'luxury_minimal' => 'luxury_minimal',
            'dark_modern' => 'dark_modern',
            'bold_startup' => 'bold_startup',
            'soft_pastel' => 'soft_pastel',
            'corporate_clean' => 'corporate_clean',
            'creative_portfolio' => 'creative_portfolio',
        ];
        return $map[$key] ?? config('business_template_map.fallback_theme_preset', 'luxury_minimal');
    }

    /**
     * PART 6: Prefer template with higher quality_score within same vertical(s).
     * Given a candidate slug from keyword/map, return the slug with highest quality_score
     * among templates that share at least one vertical with the candidate.
     */
    private function preferHigherQualityTemplateForSlug(string $candidateSlug): string
    {
        $metadata = config('template_metadata', []);
        if (! is_array($metadata) || $metadata === []) {
            return $candidateSlug;
        }
        $candidateMeta = $metadata[$candidateSlug] ?? null;
        if (! is_array($candidateMeta)) {
            return $candidateSlug;
        }
        $candidateVerticals = array_map('strtolower', (array) ($candidateMeta['verticals'] ?? []));
        if ($candidateVerticals === []) {
            return $candidateSlug;
        }
        $candidates = [];
        foreach ($metadata as $slug => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $verticals = array_map('strtolower', (array) ($meta['verticals'] ?? []));
            $overlap = array_intersect($candidateVerticals, $verticals);
            if ($overlap !== []) {
                $candidates[] = [
                    'slug' => $slug,
                    'quality_score' => (int) ($meta['quality_score'] ?? 0),
                ];
            }
        }
        if ($candidates === []) {
            return $candidateSlug;
        }
        usort($candidates, static fn (array $a, array $b): int => $b['quality_score'] <=> $a['quality_score']);
        return (string) ($candidates[0]['slug'] ?? $candidateSlug);
    }
}
