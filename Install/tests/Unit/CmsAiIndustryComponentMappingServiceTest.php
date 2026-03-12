<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiIndustryComponentMappingServiceTest extends TestCase
{
    public function test_it_maps_restaurant_prompt_and_modules_to_restaurant_plus_booking_component_groups(): void
    {
        $service = app(CmsAiIndustryComponentMappingService::class);

        $result = $service->mapFromAiInput($this->aiInput([
            'request' => [
                'prompt' => 'Build a modern restaurant site with menu sections and table reservation flow.',
            ],
            'platform_context' => [
                'module_registry' => [
                    'modules' => [
                        ['key' => 'restaurant'],
                        ['key' => 'booking'],
                    ],
                ],
            ],
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['version']);
        $this->assertSame('restaurant', $result['industry_family']);
        $this->assertContains('restaurant', data_get($result, 'builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('booking', data_get($result, 'builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('webu_rest_menu_items_01', data_get($result, 'builder_component_mapping.component_keys', []));
        $this->assertContains('webu_rest_reservation_form_01', data_get($result, 'builder_component_mapping.component_keys', []));
        $this->assertContains('rest.menuList', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('rest.tableReservationForm', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertTrue((bool) data_get($result, 'builder_component_mapping.source_spec_alias_coverage.ok'));
        $this->assertSame('v1', data_get($result, 'builder_component_mapping.source_spec_alias_coverage.alias_map_version'));
    }

    public function test_it_prefers_explicit_project_type_for_hotel_industry_mapping(): void
    {
        $service = app(CmsAiIndustryComponentMappingService::class);

        $result = $service->mapFromAiInput($this->aiInput([
            'request' => [
                'prompt' => 'Generate a general brochure site.',
            ],
            'platform_context' => [
                'site' => [
                    'theme_settings' => [
                        'project_type' => 'hotel',
                    ],
                ],
                'module_registry' => [
                    'modules' => [
                        ['key' => 'hotel'],
                        ['key' => 'booking'],
                    ],
                ],
            ],
        ]));

        $this->assertSame('hotel', $result['industry_family']);
        $this->assertSame('project_type', $result['decision_source']);
        $this->assertSame('hotel', $result['decision_evidence']);
        $this->assertContains('webu_hotel_room_availability_01', data_get($result, 'builder_component_mapping.component_keys', []));
        $this->assertContains('booking', data_get($result, 'builder_component_mapping.requires_modules', []));
        $this->assertFalse((bool) ($result['page_generation_catalog_supported'] ?? true));
        $this->assertContains('hotel.roomGrid', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('hotel.roomDetail', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('hotel.reservationForm', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
    }

    public function test_it_maps_real_estate_and_portfolio_keywords_without_module_registry_data(): void
    {
        $service = app(CmsAiIndustryComponentMappingService::class);

        $realEstate = $service->mapFromAiInput($this->aiInput([
            'request' => [
                'prompt' => 'Create a real estate property listing website with realtor search and map.',
            ],
        ]));
        $portfolio = $service->mapFromAiInput($this->aiInput([
            'request' => [
                'prompt' => 'Generate a clean photography portfolio and case study site.',
            ],
        ]));

        $this->assertSame('real_estate', $realEstate['industry_family']);
        $this->assertSame('prompt_keyword', $realEstate['decision_source']);
        $this->assertContains('webu_realestate_map_01', data_get($realEstate, 'builder_component_mapping.component_keys', []));

        $this->assertSame('portfolio', $portfolio['industry_family']);
        $this->assertSame('prompt_keyword', $portfolio['decision_source']);
        $this->assertContains('webu_portfolio_gallery_01', data_get($portfolio, 'builder_component_mapping.component_keys', []));
        $this->assertContains('re.map', data_get($realEstate, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('port.gallery', data_get($portfolio, 'builder_component_mapping.source_spec_component_keys', []));
    }

    public function test_it_covers_component_library_source_spec_prompt_to_industry_mapping_examples(): void
    {
        $service = app(CmsAiIndustryComponentMappingService::class);

        $cases = [
            ['prompt' => 'Build an online store where customers can buy products.', 'family' => 'ecommerce'],
            ['prompt' => 'Create a clinic website with doctor booking and appointments.', 'family' => 'booking'],
            ['prompt' => 'Design a salon website for beauty services and barber appointments.', 'family' => 'booking'],
            ['prompt' => 'Generate a hotel website with rooms and reservations.', 'family' => 'hotel'],
            ['prompt' => 'Build a restaurant site with menu and table reservation.', 'family' => 'restaurant'],
            ['prompt' => 'Create a photography portfolio and designer case study website.', 'family' => 'portfolio'],
            ['prompt' => 'Create a real estate platform with property map and realtor listings.', 'family' => 'real_estate'],
            ['prompt' => 'Build an academy website with course enroll flow and blog articles.', 'family' => 'booking'],
        ];

        foreach ($cases as $case) {
            $result = $service->mapFromAiInput($this->aiInput([
                'request' => ['prompt' => $case['prompt']],
            ]));

            $this->assertSame($case['family'], $result['industry_family'], "Failed prompt mapping: {$case['prompt']}");
            $this->assertSame('prompt_keyword', $result['decision_source'], "Expected prompt_keyword source for: {$case['prompt']}");
            $this->assertNotEmpty(data_get($result, 'builder_component_mapping.component_keys', []));
        }

        $academy = $service->mapFromAiInput($this->aiInput([
            'request' => ['prompt' => 'Build an academy website with course enroll flow and blog articles.'],
        ]));
        $this->assertContains('blog', data_get($academy, 'builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('webu_blog_post_list_01', data_get($academy, 'builder_component_mapping.component_keys', []));
        $this->assertContains('blog.postList', data_get($academy, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('blog.categoryList', data_get($academy, 'builder_component_mapping.source_spec_component_keys', []));

        $clinic = $service->mapFromAiInput($this->aiInput([
            'request' => ['prompt' => 'Create a clinic services website for dentists and doctors.'],
        ]));
        $this->assertContains('forms', data_get($clinic, 'builder_component_mapping.taxonomy_groups', []));
        $this->assertContains('webu_general_form_wrapper_01', data_get($clinic, 'builder_component_mapping.component_keys', []));
        $this->assertContains('forms.form', data_get($clinic, 'builder_component_mapping.source_spec_component_keys', []));

        $salon = $service->mapFromAiInput($this->aiInput([
            'request' => ['prompt' => 'Build a beauty salon and barber site with service booking.'],
        ]));
        $this->assertContains('webu_general_image_01', data_get($salon, 'builder_component_mapping.component_keys', []));
        $this->assertContains('webu_general_grid_01', data_get($salon, 'builder_component_mapping.component_keys', []));
        $this->assertContains('basic.image', data_get($salon, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('layout.grid', data_get($salon, 'builder_component_mapping.source_spec_component_keys', []));
    }

    public function test_it_exposes_source_spec_alias_coverage_for_composite_canonical_components(): void
    {
        $service = app(CmsAiIndustryComponentMappingService::class);

        $result = $service->mapFromAiInput($this->aiInput([
            'request' => ['prompt' => 'Build an online store for buying products.'],
        ]));

        $this->assertSame('ecommerce', $result['industry_family']);
        $this->assertTrue((bool) data_get($result, 'builder_component_mapping.source_spec_alias_coverage.ok'));
        $this->assertContains('ecom.productGrid', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.productDetail', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.cart', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.checkoutForm', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.ordersList', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertContains('ecom.orderDetail', data_get($result, 'builder_component_mapping.source_spec_component_keys', []));
        $this->assertSame([], data_get($result, 'builder_component_mapping.source_spec_alias_coverage.unmapped_canonical_component_keys', []));
    }

    public function test_architecture_doc_documents_f5_mapping_integration_and_page_generation_fallback_behavior(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_AI_INDUSTRY_COMPONENT_MAPPING_P5_F5_04.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F5-04', $doc);
        $this->assertStringContainsString('CmsAiIndustryComponentMappingService', $doc);
        $this->assertStringContainsString('CmsAiPageGenerationService', $doc);
        $this->assertStringContainsString('taxonomy_groups', $doc);
        $this->assertStringContainsString('component_keys', $doc);
        $this->assertStringContainsString('business-page fallback', $doc);
        $this->assertStringContainsString('hotel', $doc);
        $this->assertStringContainsString('restaurant', $doc);
        $this->assertStringContainsString('real_estate', $doc);
        $this->assertStringContainsString('portfolio', $doc);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function aiInput(array $overrides = []): array
    {
        $base = [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a site',
                'locale' => 'en',
                'target' => ['route_scope' => 'site'],
            ],
            'platform_context' => [
                'site' => [
                    'id' => '1',
                    'name' => 'Demo Site',
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
                'request_id' => 'req-mapping-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'test',
            ],
        ];

        return $this->mergeRecursiveDistinct($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeRecursiveDistinct(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
