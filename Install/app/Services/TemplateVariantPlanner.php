<?php

namespace App\Services;

/**
 * Selects template_id and page-level variants from DesignBrief and template library metadata.
 *
 * Input: DesignBrief, template library metadata.
 * Output: template_id, page_variants (home hero_variant, product_card_variant, shop grid_variant, etc.).
 *
 * @see new tasks.txt — AI Design Director System PART 2
 */
class TemplateVariantPlanner
{
    /**
     * @param  array{
     *   vertical: string,
     *   vibe: string,
     *   layout_density: string,
     *   recommended_templates?: array<int, string>
     * }  $designBrief
     * @param  array<string, array{template_id: string, quality_score?: int, hero_variants_supported?: array, product_card_variants_supported?: array}>  $templateLibraryMetadata
     * @return array{
     *   template_id: string,
     *   theme_variant: string,
     *   page_variants: array{
     *     home: array{hero_variant: string, product_card_variant: string, category_variant: string},
     *     shop: array{grid_variant: string},
     *     product: array{gallery_variant: string, upsell_variant: string}
     *   }
     * }
     */
    public function plan(array $designBrief, array $templateLibraryMetadata = []): array
    {
        $metadata = $templateLibraryMetadata ?: config('template_metadata', []);
        $recommended = $designBrief['recommended_templates'] ?? [];
        $templateId = $this->selectBestTemplate($recommended, $designBrief, $metadata);
        $templateMeta = $metadata[$templateId] ?? [];

        $heroVariants = $templateMeta['hero_variants_supported'] ?? ['centered', 'backgroundimage'];
        $productCardVariants = $templateMeta['product_card_variants_supported'] ?? ['standard', 'softshadow'];

        $vibe = (string) ($designBrief['vibe'] ?? 'luxury_minimal');
        $heroVariant = $this->vibeToHeroVariant($vibe, $heroVariants);
        $productCardVariant = $this->vibeToProductCardVariant($vibe, $productCardVariants);

        return [
            'template_id' => $templateId,
            'theme_variant' => $vibe,
            'page_variants' => [
                'home' => [
                    'hero_variant' => $heroVariant,
                    'product_card_variant' => $productCardVariant,
                    'category_variant' => 'image_cards',
                ],
                'shop' => [
                    'grid_variant' => $designBrief['layout_density'] === 'compact' ? 'compact_filters_top' : 'clean_filters_left',
                ],
                'product' => [
                    'gallery_variant' => 'large_media',
                    'upsell_variant' => 'minimal_related',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $recommended
     * @param  array{vertical: string, vibe: string}  $designBrief
     * @param  array<string, array{quality_score?: int, verticals?: array}>  $metadata
     */
    private function selectBestTemplate(array $recommended, array $designBrief, array $metadata): string
    {
        if ($recommended !== []) {
            $best = null;
            $bestScore = -1;
            foreach ($recommended as $slug) {
                $meta = $metadata[$slug] ?? null;
                if (! is_array($meta)) {
                    continue;
                }
                $score = (int) ($meta['quality_score'] ?? 0);
                $verticals = $meta['verticals'] ?? [];
                if (in_array($designBrief['vertical'], $verticals, true) && $score > $bestScore) {
                    $bestScore = $score;
                    $best = $slug;
                }
            }
            if ($best !== null) {
                return $best;
            }
            return $recommended[0];
        }
        return config('business_template_map.fallback_template', 'ecommerce-storefront');
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function vibeToHeroVariant(string $vibe, array $supported): string
    {
        $prefer = match (true) {
            str_contains($vibe, 'luxury') => 'centered',
            str_contains($vibe, 'dark') => 'backgroundimage',
            str_contains($vibe, 'bold') => 'classic',
            str_contains($vibe, 'soft') => 'centered',
            default => 'centered',
        };
        return in_array($prefer, $supported, true) ? $prefer : ($supported[0] ?? 'centered');
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function vibeToProductCardVariant(string $vibe, array $supported): string
    {
        $prefer = match (true) {
            str_contains($vibe, 'luxury') => 'premium',
            str_contains($vibe, 'soft') => 'soft',
            str_contains($vibe, 'dark') => 'outline',
            default => 'standard',
        };
        return in_array($prefer, $supported, true) ? $prefer : ($supported[0] ?? 'standard');
    }
}
