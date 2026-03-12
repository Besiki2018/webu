<?php

namespace Tests\Unit;

use App\Services\DesignBriefGenerator;
use Tests\TestCase;

/**
 * Design Brief Generator — sample inputs and output structure.
 *
 * @see new tasks.txt — AI Design Director System PART 1 Deliverables: brief tests with sample inputs
 */
class DesignBriefGeneratorTest extends TestCase
{
    private function requiredOutputKeys(): array
    {
        return [
            'vertical', 'vibe', 'tone', 'layout_density', 'primary_cta_style', 'image_style',
            'recommended_templates', 'must_have_sections', 'avoid',
        ];
    }

    public function test_generate_returns_all_required_keys(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([]);
        foreach ($this->requiredOutputKeys() as $key) {
            $this->assertArrayHasKey($key, $out, "Output must contain key: {$key}");
        }
        $this->assertIsString($out['vertical']);
        $this->assertIsString($out['vibe']);
        $this->assertIsArray($out['recommended_templates']);
        $this->assertIsArray($out['must_have_sections']);
        $this->assertIsArray($out['avoid']);
    }

    public function test_generate_fashion_business_type_maps_to_fashion_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'fashion']);
        $this->assertSame('fashion', $out['vertical']);
    }

    public function test_generate_electronics_business_type_maps_to_electronics_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'electronics']);
        $this->assertSame('electronics', $out['vertical']);
    }

    public function test_generate_beauty_business_type_maps_to_beauty_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'beauty']);
        $this->assertSame('beauty', $out['vertical']);
    }

    public function test_generate_cosmetics_maps_to_beauty_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'cosmetics']);
        $this->assertSame('beauty', $out['vertical']);
    }

    public function test_generate_pet_business_type_maps_to_pet_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'pet']);
        $this->assertSame('pet', $out['vertical']);
    }

    public function test_generate_furniture_business_type_maps_to_furniture_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'furniture']);
        $this->assertSame('furniture', $out['vertical']);
    }

    public function test_generate_kids_business_type_maps_to_kids_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'kids']);
        $this->assertSame('kids', $out['vertical']);
    }

    public function test_generate_jewelry_maps_to_luxury_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'jewelry']);
        $this->assertSame('luxury', $out['vertical']);
    }

    public function test_generate_unknown_business_type_falls_back_to_ecommerce_vertical(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'unknown_vertical_xyz']);
        $this->assertSame('ecommerce', $out['vertical']);
    }

    public function test_generate_empty_input_uses_defaults(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([]);
        $this->assertSame('ecommerce', $out['vertical']);
        $this->assertNotEmpty($out['vibe']);
        $this->assertContains($out['tone'], ['premium', 'specialist', 'accessible']);
        $this->assertContains($out['layout_density'], ['compact', 'comfortable', 'balanced']);
        $this->assertNotEmpty($out['recommended_templates']);
    }

    public function test_generate_target_audience_premium_sets_tone_premium_and_comfortable_density(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'business_type' => 'fashion',
            'target_audience' => 'premium',
        ]);
        $this->assertSame('premium', $out['tone']);
        $this->assertSame('comfortable', $out['layout_density']);
    }

    public function test_generate_target_audience_niche_sets_tone_specialist(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'business_type' => 'electronics',
            'target_audience' => 'niche',
        ]);
        $this->assertSame('specialist', $out['tone']);
    }

    public function test_generate_required_features_compact_sets_layout_density_compact(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'required_features' => ['compact'],
        ]);
        $this->assertSame('compact', $out['layout_density']);
    }

    public function test_generate_required_features_minimal_ui_sets_primary_cta_style_outline(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'required_features' => ['minimal_ui'],
        ]);
        $this->assertSame('outline', $out['primary_cta_style']);
    }

    public function test_generate_required_features_testimonials_adds_testimonials_to_must_have_sections(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'required_features' => ['testimonials'],
        ]);
        $this->assertContains('testimonials', $out['must_have_sections']);
    }

    public function test_generate_required_features_promo_banner_adds_promo_banner_to_must_have_sections(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'required_features' => ['promo_banner'],
        ]);
        $this->assertContains('promo_banner', $out['must_have_sections']);
    }

    public function test_generate_fashion_vertical_has_editorial_image_style(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'fashion']);
        $this->assertSame('editorial', $out['image_style']);
    }

    public function test_generate_beauty_vertical_has_editorial_image_style(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'beauty']);
        $this->assertSame('editorial', $out['image_style']);
    }

    public function test_generate_electronics_vertical_has_product_focus_image_style(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'electronics']);
        $this->assertSame('product_focus', $out['image_style']);
    }

    public function test_generate_must_have_sections_includes_base_ecommerce_sections(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([]);
        $base = ['hero', 'categories_or_collections', 'featured_products', 'best_sellers', 'newsletter'];
        foreach ($base as $section) {
            $this->assertContains($section, $out['must_have_sections'], "must_have_sections must include: {$section}");
        }
    }

    public function test_generate_recommended_templates_non_empty_when_config_has_mapping(): void
    {
        config([
            'business_template_map.business_type_to_template' => [
                'fashion' => 'ecommerce-fashion',
                'electronics' => 'ecommerce-electronics',
                'default' => 'ecommerce-storefront',
            ],
            'template_metadata' => [
                'ecommerce-fashion' => ['verticals' => ['fashion'], 'template_id' => 'ecommerce-fashion'],
                'ecommerce-electronics' => ['verticals' => ['electronics'], 'template_id' => 'ecommerce-electronics'],
                'ecommerce-storefront' => ['verticals' => ['general'], 'template_id' => 'ecommerce-storefront'],
            ],
        ]);
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'fashion']);
        $this->assertNotEmpty($out['recommended_templates']);
    }

    public function test_generate_recommended_templates_fallback_to_storefront_when_no_vertical_match(): void
    {
        config([
            'business_template_map.business_type_to_template' => [],
            'template_metadata' => [],
        ]);
        $generator = new DesignBriefGenerator;
        $out = $generator->generate(['business_type' => 'fashion']);
        $this->assertSame(['ecommerce-storefront'], $out['recommended_templates']);
    }

    public function test_generate_luxury_vertical_can_include_avoid_sections(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'business_type' => 'jewelry',
            'required_features' => [],
        ]);
        $this->assertIsArray($out['avoid']);
        // luxury without testimonials feature may avoid dense_testimonials
        $this->assertContains($out['vertical'], ['luxury', 'ecommerce']);
    }

    public function test_generate_brand_vibe_normalized(): void
    {
        $generator = new DesignBriefGenerator;
        $out = $generator->generate([
            'business_type' => 'fashion',
            'brand_vibe' => 'luxury minimal',
        ]);
        $this->assertNotEmpty($out['vibe']);
        $this->assertIsString($out['vibe']);
    }
}
