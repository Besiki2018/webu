<?php

namespace Tests\Unit;

use App\Services\TemplateVariantPlanner;
use Tests\TestCase;

/**
 * Template + Variant Plan (Director PART 2) — unit tests.
 *
 * @see new tasks.txt — AI Design Director System PART 2 Deliverables: unit tests
 */
class TemplateVariantPlannerTest extends TestCase
{
    public function test_plan_returns_required_structure(): void
    {
        config([
            'template_metadata' => [
                'ecommerce-fashion' => [
                    'template_id' => 'ecommerce-fashion',
                    'verticals' => ['fashion'],
                    'quality_score' => 90,
                    'hero_variants_supported' => ['centered', 'backgroundimage'],
                    'product_card_variants_supported' => ['premium', 'standard'],
                ],
            ],
            'business_template_map' => ['fallback_template' => 'ecommerce-storefront'],
        ]);

        $planner = new TemplateVariantPlanner;
        $brief = [
            'vertical' => 'fashion',
            'vibe' => 'luxury_minimal',
            'layout_density' => 'balanced',
            'recommended_templates' => ['ecommerce-fashion'],
        ];
        $out = $planner->plan($brief);

        $this->assertArrayHasKey('template_id', $out);
        $this->assertArrayHasKey('theme_variant', $out);
        $this->assertArrayHasKey('page_variants', $out);
        $this->assertArrayHasKey('home', $out['page_variants']);
        $this->assertArrayHasKey('shop', $out['page_variants']);
        $this->assertArrayHasKey('product', $out['page_variants']);
        $this->assertArrayHasKey('hero_variant', $out['page_variants']['home']);
        $this->assertArrayHasKey('product_card_variant', $out['page_variants']['home']);
        $this->assertArrayHasKey('category_variant', $out['page_variants']['home']);
        $this->assertArrayHasKey('grid_variant', $out['page_variants']['shop']);
        $this->assertArrayHasKey('gallery_variant', $out['page_variants']['product']);
        $this->assertArrayHasKey('upsell_variant', $out['page_variants']['product']);
    }

    public function test_plan_prefers_higher_quality_score_within_vertical(): void
    {
        config([
            'template_metadata' => [
                'ecommerce-fashion' => [
                    'template_id' => 'ecommerce-fashion',
                    'verticals' => ['fashion'],
                    'quality_score' => 88,
                    'hero_variants_supported' => ['centered'],
                    'product_card_variants_supported' => ['standard'],
                ],
                'ecommerce-luxury-boutique' => [
                    'template_id' => 'ecommerce-luxury-boutique',
                    'verticals' => ['fashion', 'luxury'],
                    'quality_score' => 92,
                    'hero_variants_supported' => ['centered'],
                    'product_card_variants_supported' => ['premium'],
                ],
            ],
        ]);

        $planner = new TemplateVariantPlanner;
        $brief = [
            'vertical' => 'fashion',
            'vibe' => 'luxury_minimal',
            'layout_density' => 'balanced',
            'recommended_templates' => ['ecommerce-fashion', 'ecommerce-luxury-boutique'],
        ];
        $out = $planner->plan($brief);

        $this->assertSame('ecommerce-luxury-boutique', $out['template_id']);
    }

    public function test_plan_fallback_when_no_recommended_uses_config_fallback(): void
    {
        config([
            'template_metadata' => [],
            'business_template_map' => ['fallback_template' => 'ecommerce-storefront'],
        ]);

        $planner = new TemplateVariantPlanner;
        $brief = [
            'vertical' => 'fashion',
            'vibe' => 'luxury_minimal',
            'layout_density' => 'balanced',
            'recommended_templates' => [],
        ];
        $out = $planner->plan($brief);

        $this->assertSame('ecommerce-storefront', $out['template_id']);
    }

    public function test_plan_compact_density_sets_shop_grid_variant_compact(): void
    {
        config([
            'template_metadata' => ['ecommerce-storefront' => ['verticals' => ['general'], 'hero_variants_supported' => ['centered'], 'product_card_variants_supported' => ['standard']]],
            'business_template_map' => ['fallback_template' => 'ecommerce-storefront'],
        ]);

        $planner = new TemplateVariantPlanner;
        $out = $planner->plan([
            'vertical' => 'ecommerce',
            'vibe' => 'luxury_minimal',
            'layout_density' => 'compact',
        ]);

        $this->assertSame('compact_filters_top', $out['page_variants']['shop']['grid_variant']);
    }

    public function test_plan_balanced_density_sets_shop_grid_variant_clean(): void
    {
        config([
            'template_metadata' => ['ecommerce-storefront' => ['verticals' => ['general'], 'hero_variants_supported' => ['centered'], 'product_card_variants_supported' => ['standard']]],
            'business_template_map' => ['fallback_template' => 'ecommerce-storefront'],
        ]);

        $planner = new TemplateVariantPlanner;
        $out = $planner->plan([
            'vertical' => 'ecommerce',
            'vibe' => 'luxury_minimal',
            'layout_density' => 'balanced',
        ]);

        $this->assertSame('clean_filters_left', $out['page_variants']['shop']['grid_variant']);
    }

    public function test_plan_vibe_maps_to_hero_and_product_card_variants(): void
    {
        config([
            'template_metadata' => [
                'ecommerce-dark' => [
                    'template_id' => 'ecommerce-dark',
                    'verticals' => ['electronics'],
                    'hero_variants_supported' => ['backgroundimage', 'centered'],
                    'product_card_variants_supported' => ['outline', 'standard'],
                ],
            ],
        ]);

        $planner = new TemplateVariantPlanner;
        $out = $planner->plan([
            'vertical' => 'electronics',
            'vibe' => 'dark_modern',
            'layout_density' => 'balanced',
            'recommended_templates' => ['ecommerce-dark'],
        ]);

        $this->assertSame('backgroundimage', $out['page_variants']['home']['hero_variant']);
        $this->assertSame('outline', $out['page_variants']['home']['product_card_variant']);
    }
}
