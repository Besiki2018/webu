<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Converts user requirements into a structured DesignBrief for the AI Design Director.
 *
 * Input: business type, brand vibe, target audience, required features, content assets.
 * Output: DesignBrief JSON (vertical, vibe, tone, layout_density, must_have_sections, avoid).
 *
 * @see new tasks.txt — AI Design Director System PART 1
 */
class DesignBriefGenerator
{
    /**
     * @param  array{
     *   business_type?: string,
     *   brand_vibe?: string,
     *   target_audience?: string,
     *   required_features?: array<int, string>,
     *   content_assets?: array{logo?: bool, brand_colors?: array, product_photos?: bool, social_links?: bool}
     * }  $input
     * @return array{
     *   vertical: string,
     *   vibe: string,
     *   tone: string,
     *   layout_density: string,
     *   primary_cta_style: string,
     *   image_style: string,
     *   recommended_templates: array<int, string>,
     *   must_have_sections: array<int, string>,
     *   avoid: array<int, string>
     * }
     */
    public function generate(array $input): array
    {
        $businessType = Str::lower(trim((string) ($input['business_type'] ?? 'ecommerce')));
        $brandVibe = Str::lower(trim((string) ($input['brand_vibe'] ?? 'modern')));
        $targetAudience = Str::lower(trim((string) ($input['target_audience'] ?? 'mass_market')));
        $features = is_array($input['required_features'] ?? null) ? $input['required_features'] : [];

        $vertical = $this->mapBusinessTypeToVertical($businessType);
        $vibe = $this->mapBrandVibeToVibe($brandVibe, $vertical);
        $tone = $targetAudience === 'premium' ? 'premium' : ($targetAudience === 'niche' ? 'specialist' : 'accessible');
        $layoutDensity = in_array('compact', $features, true) ? 'compact' : ($targetAudience === 'premium' ? 'comfortable' : 'balanced');
        $primaryCtaStyle = in_array('minimal_ui', $features, true) ? 'outline' : 'solid';
        $imageStyle = $vertical === 'fashion' || $vertical === 'beauty' ? 'editorial' : 'product_focus';

        $recommendedTemplates = $this->recommendedTemplatesForVertical($vertical, $vibe);
        $mustHaveSections = $this->mustHaveSectionsForEcommerce($features);
        $avoid = $this->avoidSections($vertical, $features);

        return [
            'vertical' => $vertical,
            'vibe' => $vibe,
            'tone' => $tone,
            'layout_density' => $layoutDensity,
            'primary_cta_style' => $primaryCtaStyle,
            'image_style' => $imageStyle,
            'recommended_templates' => $recommendedTemplates,
            'must_have_sections' => $mustHaveSections,
            'avoid' => $avoid,
        ];
    }

    private function mapBusinessTypeToVertical(string $businessType): string
    {
        $map = [
            'fashion' => 'fashion',
            'electronics' => 'electronics',
            'beauty' => 'beauty',
            'cosmetics' => 'beauty',
            'pet' => 'pet',
            'kids' => 'kids',
            'furniture' => 'furniture',
            'jewelry' => 'luxury',
            'luxury' => 'luxury',
            'grocery' => 'food',
            'food' => 'food',
            'sports' => 'sports',
            'digital' => 'digital',
            'startup' => 'startup',
        ];
        return $map[$businessType] ?? 'ecommerce';
    }

    private function mapBrandVibeToVibe(string $brandVibe, string $vertical): string
    {
        $vibes = ['luxury_minimal', 'corporate_clean', 'bold_startup', 'soft_pastel', 'dark_modern', 'creative_portfolio'];
        $normalized = Str::slug($brandVibe, '_');
        foreach ($vibes as $v) {
            if (str_contains($v, $normalized) || str_contains($normalized, $v)) {
                return $v;
            }
        }
        if ($vertical === 'fashion' || $vertical === 'luxury') {
            return 'luxury_minimal';
        }
        if ($vertical === 'electronics') {
            return 'dark_modern';
        }
        return 'luxury_minimal';
    }

    /**
     * @return array<int, string>
     */
    private function recommendedTemplatesForVertical(string $vertical, string $vibe): array
    {
        $map = config('business_template_map.business_type_to_template', []);
        $candidates = [];
        foreach ($map as $type => $slug) {
            if ($type === 'default') {
                continue;
            }
            $meta = config('template_metadata', [])[$slug] ?? null;
            if (is_array($meta) && in_array($vertical, $meta['verticals'] ?? [], true)) {
                $candidates[] = $slug;
            }
        }
        if ($candidates === []) {
            return ['ecommerce-storefront'];
        }
        return array_slice(array_unique($candidates), 0, 5);
    }

    /**
     * @param  array<int, string>  $features
     * @return array<int, string>
     */
    private function mustHaveSectionsForEcommerce(array $features): array
    {
        $base = ['hero', 'categories_or_collections', 'featured_products', 'best_sellers', 'newsletter'];
        if (in_array('testimonials', $features, true)) {
            $base[] = 'testimonials';
        }
        if (in_array('promo_banner', $features, true)) {
            $base[] = 'promo_banner';
        }
        return $base;
    }

    /**
     * @param  array<int, string>  $features
     * @return array<int, string>
     */
    private function avoidSections(string $vertical, array $features): array
    {
        $avoid = [];
        if ($vertical === 'luxury' && ! in_array('testimonials', $features, true)) {
            $avoid[] = 'dense_testimonials';
        }
        return $avoid;
    }
}
