<?php

namespace Tests\Unit;

use App\Services\DesignQualityEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PART 4 Design Quality Rules: typography scale, 8px spacing, card consistency, mobile, threshold from config.
 */
class DesignQualityEvaluatorDesignRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_min_design_score_uses_config_when_valid(): void
    {
        config(['design-defaults.min_design_score' => 90]);
        $evaluator = new DesignQualityEvaluator;
        $this->assertSame(90, $evaluator->getMinDesignScore());
    }

    public function test_get_min_design_score_fallback_when_config_invalid(): void
    {
        config(['design-defaults.min_design_score' => 150]);
        $evaluator = new DesignQualityEvaluator;
        $this->assertSame(DesignQualityEvaluator::MIN_DESIGN_SCORE_THRESHOLD, $evaluator->getMinDesignScore());
    }

    public function test_evaluate_returns_threshold_in_result(): void
    {
        config(['design-defaults.min_design_score' => 85]);
        $evaluator = new DesignQualityEvaluator;
        $layout = [
            'pages' => [
                ['slug' => 'home', 'sections' => [['type' => 'webu_general_heading_01'], ['type' => 'webu_ecom_product_grid_01']]],
                ['slug' => 'shop', 'sections' => [['type' => 'webu_ecom_product_grid_01']]],
                ['slug' => 'product', 'sections' => []],
                ['slug' => 'cart', 'sections' => []],
                ['slug' => 'checkout', 'sections' => []],
                ['slug' => 'contact', 'sections' => []],
            ],
        ];
        $result = $evaluator->evaluate($layout, ['preset' => 'luxury_minimal'], 'ecommerce');
        $this->assertArrayHasKey('threshold', $result);
        $this->assertSame(85, $result['threshold']);
    }

    public function test_evaluate_flags_typography_scale_when_invalid_font_size_in_props(): void
    {
        $evaluator = new DesignQualityEvaluator;
        $layout = [
            'pages' => [
                ['slug' => 'home', 'sections' => [
                    ['type' => 'webu_general_heading_01', 'props' => ['font_size' => 99]],
                    ['type' => 'webu_ecom_product_grid_01'],
                ]],
                ['slug' => 'shop', 'sections' => [['type' => 'webu_ecom_product_grid_01']]],
                ['slug' => 'product', 'sections' => []],
                ['slug' => 'cart', 'sections' => []],
                ['slug' => 'checkout', 'sections' => []],
                ['slug' => 'contact', 'sections' => []],
            ],
        ];
        $result = $evaluator->evaluate($layout, ['preset' => 'luxury_minimal'], null);
        $codes = array_column($result['design_issues'], 'code');
        $this->assertContains('typography_scale', $codes);
    }

    public function test_evaluate_flags_spacing_when_non_8px_grid_in_props(): void
    {
        $evaluator = new DesignQualityEvaluator;
        $layout = [
            'pages' => [
                ['slug' => 'home', 'sections' => [
                    ['type' => 'webu_general_heading_01', 'props' => ['padding_y' => 7]],
                    ['type' => 'webu_ecom_product_grid_01'],
                ]],
                ['slug' => 'shop', 'sections' => [['type' => 'webu_ecom_product_grid_01']]],
                ['slug' => 'product', 'sections' => []],
                ['slug' => 'cart', 'sections' => []],
                ['slug' => 'checkout', 'sections' => []],
                ['slug' => 'contact', 'sections' => []],
            ],
        ];
        $result = $evaluator->evaluate($layout, ['preset' => 'luxury_minimal'], null);
        $codes = array_column($result['design_issues'], 'code');
        $this->assertContains('spacing_not_8px_grid', $codes);
    }

    public function test_evaluate_uses_allowed_spacing_from_design_defaults(): void
    {
        $evaluator = new DesignQualityEvaluator;
        $layout = [
            'pages' => [
                ['slug' => 'home', 'sections' => [
                    ['type' => 'webu_general_heading_01', 'props' => ['padding_y' => 16]],
                    ['type' => 'webu_ecom_product_grid_01'],
                ]],
                ['slug' => 'shop', 'sections' => [['type' => 'webu_ecom_product_grid_01']]],
                ['slug' => 'product', 'sections' => []],
                ['slug' => 'cart', 'sections' => []],
                ['slug' => 'checkout', 'sections' => []],
                ['slug' => 'contact', 'sections' => []],
            ],
        ];
        $result = $evaluator->evaluate($layout, ['preset' => 'luxury_minimal'], null);
        $codes = array_column($result['design_issues'], 'code');
        $this->assertNotContains('spacing_not_8px_grid', $codes);
    }
}
