<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Template scoring for AI selection (Section 8 — Template Metadata + AI Scoring).
 *
 * Score formula:
 *   vertical_match * 40 + vibe_match * 25 + (quality_score * 0.2) + layout_density_match * 10 + design_style_match * 5
 *
 * Minimum acceptable score: 70; below that use fallback template.
 *
 * @see new tasks.txt — PART 2 Template Scoring Algorithm, PART 3 Template Selector
 */
class TemplateScoreCalculator
{
    public const MIN_ACCEPTABLE_SCORE = 70;

    /**
     * @param  array{vertical?: string, vibe?: string, density?: string, design_style?: string}  $designBrief
     * @param  array{verticals?: array, vibes?: array, quality_score?: int, density?: string, design_style?: string}  $templateMeta
     */
    public function scoreTemplate(array $designBrief, string $templateSlug, array $templateMeta): float
    {
        $vertical = Str::lower(trim((string) ($designBrief['vertical'] ?? 'ecommerce')));
        $vibe = Str::lower(trim((string) ($designBrief['vibe'] ?? 'luxury_minimal')));
        $density = Str::lower(trim((string) ($designBrief['density'] ?? 'balanced')));
        $briefDesignStyle = Str::lower(trim((string) ($designBrief['design_style'] ?? '')));

        $templateVerticals = array_map('strtolower', (array) ($templateMeta['verticals'] ?? []));
        $templateVibes = array_map('strtolower', (array) ($templateMeta['vibes'] ?? []));
        $qualityScore = (int) ($templateMeta['quality_score'] ?? 80);
        $templateDensity = Str::lower(trim((string) ($templateMeta['density'] ?? 'balanced')));
        $templateDesignStyle = Str::lower(trim((string) ($templateMeta['design_style'] ?? '')));

        $verticalMatch = $this->verticalMatchScore($vertical, $templateVerticals);
        $vibeMatch = $this->vibeMatchScore($vibe, $templateVibes);
        $qualityPart = min(100, max(0, $qualityScore)) * 0.2;
        $densityMatch = ($templateDensity === $density || $density === '') ? 10 : 0;
        $designStyleMatch = $this->designStyleMatchScore($briefDesignStyle, $templateDesignStyle, $templateVibes, $vibe);

        return $verticalMatch * 40 + $vibeMatch * 25 + $qualityPart + $densityMatch * 10 + $designStyleMatch * 5;
    }

    /**
     * Score all templates and return sorted by score descending.
     *
     * @param  array{vertical?: string, vibe?: string, density?: string, design_style?: string}  $designBrief
     * @return array<int, array{slug: string, score: float, meta: array}>
     */
    public function scoreAll(array $designBrief): array
    {
        $metadata = config('template_metadata', []);
        if (! is_array($metadata)) {
            return [];
        }
        $exclude = ['ecommerce', 'default'];
        $scored = [];
        foreach ($metadata as $slug => $meta) {
            if (in_array($slug, $exclude, true) || ! is_array($meta)) {
                continue;
            }
            $score = $this->scoreTemplate($designBrief, $slug, $meta);
            $scored[] = ['slug' => $slug, 'score' => $score, 'meta' => $meta];
        }
        usort($scored, static fn (array $a, array $b): int => (int) round($b['score'] - $a['score']));
        return $scored;
    }

    /**
     * Best template slug for design brief; if top score < MIN_ACCEPTABLE_SCORE returns fallback.
     *
     * @param  array{vertical?: string, vibe?: string, density?: string, design_style?: string}  $designBrief
     * @return array{template_id: string, score: float, alternatives: array<int, array{slug: string, score: float}>, used_fallback: bool}
     */
    public function selectBest(array $designBrief): array
    {
        $scored = $this->scoreAll($designBrief);
        $fallback = config('business_template_map.fallback_template', 'ecommerce-storefront');
        $top = $scored[0] ?? null;
        if ($top === null) {
            return [
                'template_id' => $fallback,
                'score' => 0.0,
                'alternatives' => [],
                'used_fallback' => true,
            ];
        }
        $usedFallback = $top['score'] < self::MIN_ACCEPTABLE_SCORE;
        $templateId = $usedFallback ? $fallback : $top['slug'];
        $alternatives = array_slice(array_map(static fn (array $r): array => [
            'slug' => $r['slug'],
            'score' => $r['score'],
        ], $scored), 0, 5);
        return [
            'template_id' => $templateId,
            'score' => $top['score'],
            'alternatives' => $alternatives,
            'used_fallback' => $usedFallback,
        ];
    }

    private function verticalMatchScore(string $briefVertical, array $templateVerticals): float
    {
        if ($templateVerticals === []) {
            return 0;
        }
        if (in_array($briefVertical, $templateVerticals, true)) {
            return 1.0; // exact 40
        }
        $related = $this->relatedVerticals($briefVertical);
        foreach ($templateVerticals as $v) {
            if (in_array($v, $related, true)) {
                return 0.625; // related 25/40
            }
        }
        return 0;
    }

    private function vibeMatchScore(string $briefVibe, array $templateVibes): float
    {
        if (in_array($briefVibe, $templateVibes, true)) {
            return 1.0; // exact 25
        }
        $aliases = [
            'luxury_minimal' => ['luxury_minimal', 'minimal', 'luxury'],
            'dark_modern' => ['dark_modern', 'dark'],
            'bold_startup' => ['bold_startup', 'bold', 'modern'],
            'soft_pastel' => ['soft_pastel', 'soft', 'pastel'],
            'corporate_clean' => ['corporate_clean', 'corporate'],
            'creative_portfolio' => ['creative_portfolio', 'creative'],
        ];
        $check = $aliases[$briefVibe] ?? [$briefVibe];
        foreach ($templateVibes as $v) {
            if (in_array($v, $check, true)) {
                return 0.6; // close 15/25
            }
        }
        return 0;
    }

    private function designStyleMatchScore(string $briefStyle, string $templateStyle, array $templateVibes, string $briefVibe): float
    {
        if ($briefStyle !== '' && $templateStyle !== '' && $briefStyle === $templateStyle) {
            return 1.0;
        }
        if ($briefStyle === '' && $templateVibes !== [] && in_array($briefVibe, $templateVibes, true)) {
            return 1.0;
        }
        return 0;
    }

    private function relatedVerticals(string $vertical): array
    {
        $map = [
            'fashion' => ['luxury', 'jewelry', 'beauty', 'creative'],
            'beauty' => ['cosmetics', 'fashion', 'kids'],
            'electronics' => ['tech', 'gaming', 'digital_products'],
            'pet' => ['kids'],
            'kids' => ['pet', 'beauty'],
            'furniture' => ['home_decor', 'general'],
            'food' => ['grocery', 'organic'],
            'ecommerce' => ['general'],
        ];
        return $map[$vertical] ?? [];
    }
}
