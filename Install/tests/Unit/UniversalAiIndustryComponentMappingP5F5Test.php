<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalAiIndustryComponentMappingP5F5Test extends TestCase
{
    public function test_p5_f5_04_ai_industry_component_mapping_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_AI_INDUSTRY_COMPONENT_MAPPING_P5_F5_04.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $mappingService = File::get(base_path('app/Services/CmsAiIndustryComponentMappingService.php'));
        $pageGeneration = File::get(base_path('app/Services/CmsAiPageGenerationService.php'));
        $mappingTest = File::get(base_path('tests/Unit/CmsAiIndustryComponentMappingServiceTest.php'));
        $pageGenerationTest = File::get(base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'));
        $roadmap = File::get(base_path('../PROJECT_ROADMAP_TASKS_KA.md'));

        $this->assertStringContainsString('P5-F5-04', $doc);
        $this->assertStringContainsString('CmsAiIndustryComponentMappingService', $doc);
        $this->assertStringContainsString('CmsAiPageGenerationService', $doc);
        $this->assertStringContainsString('taxonomy_groups', $doc);
        $this->assertStringContainsString('component_keys', $doc);
        $this->assertStringContainsString('business-page fallback', $doc);

        $this->assertStringContainsString('class CmsAiIndustryComponentMappingService', $mappingService);
        $this->assertStringContainsString('public const VERSION = 1;', $mappingService);
        $this->assertStringContainsString("'portfolio'", $mappingService);
        $this->assertStringContainsString("'real_estate'", $mappingService);
        $this->assertStringContainsString("'restaurant'", $mappingService);
        $this->assertStringContainsString("'hotel'", $mappingService);
        $this->assertStringContainsString('webu_rest_menu_items_01', $mappingService);
        $this->assertStringContainsString('webu_hotel_room_detail_01', $mappingService);

        $this->assertStringContainsString('CmsAiIndustryComponentMappingService $industryComponentMapping', $pageGeneration);
        $this->assertStringContainsString('$industryMapping = $this->industryComponentMapping->mapFromAiInput($aiInput);', $pageGeneration);
        $this->assertStringContainsString("'ai_industry_component_mapping' => \$industryMapping", $pageGeneration);
        $this->assertStringContainsString('page generation uses business-page fallback while preserving industry component hints', $pageGeneration);

        $this->assertStringContainsString('test_it_maps_restaurant_prompt_and_modules_to_restaurant_plus_booking_component_groups', $mappingTest);
        $this->assertStringContainsString('test_it_prefers_explicit_project_type_for_hotel_industry_mapping', $mappingTest);
        $this->assertStringContainsString('test_it_attaches_ai_industry_component_mapping_for_vertical_prompts_even_when_page_catalog_falls_back_to_business_pages', $pageGenerationTest);

        $this->assertStringContainsString('- ✅ AI industry mapping rules implementation', $roadmap);
        $this->assertStringContainsString("`P5-F5-04` (✅ `DONE`) AI prompt → industry component mapping integration.", $roadmap);
    }
}

