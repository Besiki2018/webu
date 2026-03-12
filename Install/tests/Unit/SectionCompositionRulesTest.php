<?php

namespace Tests\Unit;

use App\Services\SectionCompositionRules;
use Tests\TestCase;

/**
 * Section Composition Rules (Director PART 4) — rule engine + validation.
 *
 * @see new tasks.txt — AI Design Director System PART 4 Deliverables: integration tests
 */
class SectionCompositionRulesTest extends TestCase
{
    public function test_required_home_order_constant(): void
    {
        $rules = new SectionCompositionRules;
        $home = $rules->requiredHomeConceptual();
        $this->assertContains('hero', $home);
        $this->assertContains('categories_or_collections', $home);
        $this->assertContains('featured_products', $home);
        $this->assertContains('newsletter', $home);
    }

    public function test_validate_page_sections_detects_missing_hero(): void
    {
        $rules = new SectionCompositionRules;
        $sections = [
            ['type' => 'webu_ecom_product_grid_01', 'key' => 'webu_ecom_product_grid_01'],
        ];
        $required = ['hero', 'featured_products'];
        $result = $rules->validatePageSections($sections, $required);

        $this->assertFalse($result['valid']);
        $this->assertContains('hero', $result['missing']);
    }

    public function test_validate_page_sections_infers_hero_from_heading(): void
    {
        $rules = new SectionCompositionRules;
        $sections = [
            ['type' => 'webu_general_heading_01', 'key' => 'webu_general_heading_01'],
        ];
        $result = $rules->validatePageSections($sections, ['hero']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
    }

    public function test_required_shop_product_cart_checkout_contact(): void
    {
        $rules = new SectionCompositionRules;
        $this->assertNotEmpty($rules->requiredShopConceptual());
        $this->assertNotEmpty($rules->requiredProductConceptual());
        $this->assertNotEmpty($rules->requiredCartConceptual());
        $this->assertNotEmpty($rules->requiredCheckoutConceptual());
        $this->assertNotEmpty($rules->requiredContactConceptual());
    }

    public function test_validate_passes_when_all_required_present(): void
    {
        $rules = new SectionCompositionRules;
        $sections = [
            ['key' => 'webu_general_heading_01'],
            ['key' => 'webu_ecom_category_list_01'],
            ['key' => 'webu_ecom_product_grid_01'],
        ];
        $result = $rules->validatePageSections($sections, ['hero', 'categories_or_collections', 'featured_products']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
    }
}
