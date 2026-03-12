<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecAiPromptMappingRulesCoverageTest extends TestCase
{
    public function test_component_library_source_spec_ai_prompt_mapping_rules_are_covered_by_deterministic_mapping_service(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_AI_PROMPT_MAPPING_RULES_COVERAGE.md');
        $servicePath = base_path('app/Services/CmsAiIndustryComponentMappingService.php');
        $mappingTestPath = base_path('tests/Unit/CmsAiIndustryComponentMappingServiceTest.php');
        $pageGenTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');
        $pageGenLearningRulesTestPath = base_path('tests/Unit/CmsAiPageGenerationLearningRulesIntegrationTest.php');
        $aliasMapServicePath = base_path('app/Services/CmsComponentLibrarySpecEquivalenceAliasMapService.php');

        foreach ([$roadmapPath, $docPath, $servicePath, $mappingTestPath, $pageGenTestPath, $pageGenLearningRulesTestPath, $aliasMapServicePath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);
        $serviceCode = File::get($servicePath);

        foreach ([
            '# 15) AI Prompt → Industry Mapping Rules (must)',
            'online store, shop, buy',
            'clinic, dentist, doctor',
            'salon, beauty, barber',
            '"hotel" → room grid + reservation form + gallery',
            '"restaurant" → menu + table reservation + gallery',
            '"portfolio, photographer, designer"',
            '"real estate" → property grid + map + lead form',
            '"course, academy" → services(courses) + booking/enroll + blog',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6859',
            'Rule Coverage Matrix',
            'online store, shop, buy',
            'clinic, dentist, doctor',
            'salon, beauty, barber',
            'course, academy',
            'CmsAiIndustryComponentMappingService.php',
            'CmsComponentLibrarySpecEquivalenceAliasMapService.php',
            'CmsAiIndustryComponentMappingServiceTest.php',
            'CmsAiPageGenerationServiceTest.php',
            'CmsAiPageGenerationLearningRulesIntegrationTest.php',
            '**covered by deterministic implementation and automated evidence**',
            'source_spec_component_keys',
            'source_spec_alias_coverage',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            "'clinic', 'dentist', 'doctor'",
            "'salon', 'beauty', 'barber'",
            "'course', 'academy', 'enroll'",
            "'online store'",
            "'buy'",
            'webu_blog_post_list_01',
            'webu_blog_category_list_01',
            'webu_general_form_wrapper_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $serviceCode);
        }

        $service = app(CmsAiIndustryComponentMappingService::class);

        $cases = [
            [
                'prompt' => 'Build an online store where customers can buy products.',
                'family' => 'ecommerce',
                'mustContain' => ['webu_ecom_product_grid_01'],
                'mustContainGroups' => ['ecommerce'],
            ],
            [
                'prompt' => 'Create a clinic website with doctor booking and appointments.',
                'family' => 'booking',
                'mustContain' => ['webu_svc_services_list_01', 'webu_book_booking_form_01', 'webu_general_form_wrapper_01'],
                'mustContainGroups' => ['booking', 'forms'],
            ],
            [
                'prompt' => 'Design a beauty salon and barber website with bookings.',
                'family' => 'booking',
                'mustContain' => ['webu_svc_staff_grid_01', 'webu_general_image_01', 'webu_general_grid_01'],
                'mustContainGroups' => ['booking'],
            ],
            [
                'prompt' => 'Generate a hotel website with rooms and reservations.',
                'family' => 'hotel',
                'mustContain' => ['webu_hotel_room_grid_01', 'webu_hotel_reservation_form_01'],
                'mustContainGroups' => ['hotel', 'booking'],
            ],
            [
                'prompt' => 'Build a restaurant site with menu and table reservation.',
                'family' => 'restaurant',
                'mustContain' => ['webu_rest_menu_items_01', 'webu_rest_reservation_form_01'],
                'mustContainGroups' => ['restaurant', 'booking'],
            ],
            [
                'prompt' => 'Create a photography portfolio and designer case study website.',
                'family' => 'portfolio',
                'mustContain' => ['webu_portfolio_projects_grid_01', 'webu_portfolio_gallery_01'],
                'mustContainGroups' => ['portfolio'],
            ],
            [
                'prompt' => 'Create a real estate website with property listings and map.',
                'family' => 'real_estate',
                'mustContain' => ['webu_realestate_property_grid_01', 'webu_realestate_map_01'],
                'mustContainGroups' => ['real_estate'],
            ],
            [
                'prompt' => 'Build an academy website with course enroll flow and blog articles.',
                'family' => 'booking',
                'mustContain' => ['webu_svc_services_list_01', 'webu_book_booking_form_01', 'webu_blog_post_list_01', 'webu_blog_category_list_01'],
                'mustContainGroups' => ['booking', 'blog'],
            ],
        ];

        foreach ($cases as $case) {
            $result = $service->mapFromAiInput($this->aiInput($case['prompt']));

            $this->assertSame($case['family'], $result['industry_family'], $case['prompt']);
            $this->assertSame('prompt_keyword', $result['decision_source'], $case['prompt']);

            foreach ($case['mustContain'] as $componentKey) {
                $this->assertContains($componentKey, data_get($result, 'builder_component_mapping.component_keys', []), $case['prompt']);
            }
            foreach ($case['mustContainGroups'] as $groupKey) {
                $this->assertContains($groupKey, data_get($result, 'builder_component_mapping.taxonomy_groups', []), $case['prompt']);
            }

            $this->assertTrue((bool) data_get($result, 'builder_component_mapping.source_spec_alias_coverage.ok'), $case['prompt']);
            $this->assertSame('v1', data_get($result, 'builder_component_mapping.source_spec_alias_coverage.alias_map_version'), $case['prompt']);
            $this->assertNotEmpty(data_get($result, 'builder_component_mapping.source_spec_component_keys', []), $case['prompt']);
        }

        $ecommerce = $service->mapFromAiInput($this->aiInput('Build an online store where customers can buy products.'));
        $this->assertContains('ecom.productGrid', data_get($ecommerce, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.productDetail', data_get($ecommerce, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertTrue((bool) data_get($ecommerce, 'builder_component_mapping.source_spec_alias_coverage.ok'));
    }

    /**
     * @return array<string, mixed>
     */
    private function aiInput(string $prompt): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => $prompt,
                'locale' => 'en',
                'target' => ['route_scope' => 'site'],
            ],
            'platform_context' => [
                'site' => [
                    'id' => 'site-1',
                    'name' => 'Demo',
                    'theme_settings' => [],
                ],
                'template_blueprint' => [
                    'template_slug' => null,
                ],
                'module_registry' => [
                    'modules' => [],
                ],
            ],
            'meta' => [
                'request_id' => 'req-spec-ai-mapping-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'test',
            ],
        ];
    }
}
