<?php

namespace Tests\Unit\AiWebsiteGeneration;

use App\Services\AiWebsiteGeneration\StructureGenerator;
use Tests\TestCase;

class StructureGeneratorTest extends TestCase
{
    public function test_business_home_generation_uses_canonical_reusable_components(): void
    {
        $structure = app(StructureGenerator::class)->generate([
            'websiteType' => 'business',
            'style' => 'modern',
            'mustHavePages' => ['home', 'about', 'services', 'contact'],
        ]);

        $homePage = collect($structure['pages'])->firstWhere('slug', 'home');

        $this->assertIsArray($homePage);
        $this->assertSame([
            'webu_general_hero_01',
            'webu_general_cards_01',
            'webu_general_testimonials_01',
            'webu_general_cta_01',
        ], array_column($homePage['sections'], 'section_type'));
        $this->assertNotContains('banner', array_column($homePage['sections'], 'section_type'));
    }

    public function test_portfolio_and_ecommerce_page_plans_match_component_library_expectations(): void
    {
        $portfolio = app(StructureGenerator::class)->generate([
            'websiteType' => 'portfolio',
            'style' => 'modern',
            'mustHavePages' => ['home', 'work'],
        ]);
        $ecommerce = app(StructureGenerator::class)->generate([
            'websiteType' => 'ecommerce',
            'style' => 'modern',
            'mustHavePages' => ['home', 'shop'],
        ]);

        $portfolioHome = collect($portfolio['pages'])->firstWhere('slug', 'home');
        $ecommerceHome = collect($ecommerce['pages'])->firstWhere('slug', 'home');
        $shopPage = collect($ecommerce['pages'])->firstWhere('slug', 'shop');

        $this->assertSame([
            'webu_general_hero_01',
            'webu_general_grid_01',
            'webu_general_testimonials_01',
            'webu_general_cta_01',
        ], array_column($portfolioHome['sections'], 'section_type'));
        $this->assertSame([
            'webu_general_hero_01',
            'webu_general_cards_01',
            'webu_ecom_product_grid_01',
            'webu_general_cta_01',
        ], array_column($ecommerceHome['sections'], 'section_type'));
        $this->assertSame([
            'webu_general_heading_01',
            'webu_ecom_product_grid_01',
        ], array_column($shopPage['sections'], 'section_type'));
    }
}
