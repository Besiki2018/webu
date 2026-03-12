<?php

namespace Tests\Unit;

use App\Services\CmsBindingExpressionValidator;
use App\Services\CmsCanonicalBindingResolver;
use Tests\TestCase;

class CmsBindingExpressionValidatorTest extends TestCase
{
    public function test_it_collects_binding_warnings_from_section_props(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_home_hero_01',
                    'props' => [
                        'headline' => '{{site.name}}',
                        'subtitle' => '{{ site.name + 1 }}',
                        'primary_cta' => [
                            'label' => 'Go',
                            'url' => '{{unknown.path}}',
                        ],
                        'items' => [
                            ['title' => '{{ecommerce.products}}'], // deferred semantic binding: allowed
                            ['title' => 'Normal text'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['warnings']);

        $errors = collect($result['warnings'])->pluck('error')->all();
        $this->assertContains('invalid_syntax', $errors);
        $this->assertContains('unsupported_namespace', $errors);

        $fieldPaths = collect($result['warnings'])->pluck('field_path')->all();
        $this->assertContains('props.subtitle', $fieldPaths);
        $this->assertContains('props.primary_cta.url', $fieldPaths);
    }

    public function test_it_returns_valid_when_no_binding_warnings_exist(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_footer_01',
                    'props' => [
                        'copyright_text' => '2026 Webu',
                        'phone' => '{{global.contact.phone}}',
                        'products_link' => 'route.slug',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_it_warns_when_canonical_product_detail_sections_are_missing_route_slug_binding(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_ecom_product_detail_01',
                    'props' => [
                        'headline' => 'Demo Product',
                        'product_slug' => '',
                    ],
                ],
                [
                    'type' => 'webu_ecom_product_gallery_01',
                    'props' => [
                        'product_slug' => 'premium-dog-snack',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $errors = collect($result['warnings'])->pluck('error')->all();
        $this->assertContains('missing_route_product_slug_binding', $errors);
        $this->assertContains('invalid_route_product_slug_binding', $errors);
    }

    public function test_it_accepts_route_param_slug_binding_for_canonical_product_detail_sections(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_ecom_product_detail_01',
                    'props' => [
                        'product_slug' => '{{route.params.slug}}',
                    ],
                ],
                [
                    'type' => 'webu_ecom_product_gallery_01',
                    'props' => [
                        'product_slug' => 'route.params.slug',
                    ],
                ],
                [
                    'type' => 'webu_ecom_product_tabs_01',
                    'props' => [
                        'product_slug' => '{{route.slug}}', // legacy compatibility
                    ],
                ],
            ],
        ]);

        $routeWarnings = collect($result['warnings'])
            ->filter(fn (array $warning): bool => ($warning['type'] ?? null) === 'route_param_binding')
            ->values()
            ->all();

        $this->assertSame([], $routeWarnings);
        $this->assertTrue($result['valid']);
    }

    public function test_it_accepts_canonical_route_bindings_for_universal_vertical_detail_components(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_ecom_order_detail_01',
                    'props' => [
                        'order_id' => '{{route.params.id}}',
                    ],
                ],
                [
                    'type' => 'webu_book_slots_01',
                    'props' => [
                        'service_id' => 'route.params.service_id',
                        'items' => [
                            ['label' => '{{booking.services}}'],
                        ],
                    ],
                ],
                [
                    'type' => 'webu_portfolio_project_hero_01',
                    'props' => [
                        'project_slug' => '{{route.params.slug}}',
                        'headline' => '{{content.projects[0].title}}',
                    ],
                ],
                [
                    'type' => 'webu_realestate_property_hero_01',
                    'props' => [
                        'property_slug' => '{{route.params.slug}}',
                        'price' => '{{content.properties[0].price}}',
                    ],
                ],
                [
                    'type' => 'webu_hotel_room_detail_01',
                    'props' => [
                        'room_slug' => '{{route.params.slug}}',
                        'room_name' => '{{content.rooms[0].name}}',
                    ],
                ],
                [
                    'type' => 'webu_hotel_reservation_form_01',
                    'props' => [
                        'room_slug' => '{{route.params.slug}}',
                    ],
                ],
            ],
        ]);

        $routeWarnings = collect($result['warnings'])
            ->filter(fn (array $warning): bool => ($warning['type'] ?? null) === 'route_param_binding')
            ->values()
            ->all();

        $this->assertSame([], $routeWarnings);
        $this->assertTrue($result['valid'], 'Unexpected warnings: '.json_encode($result['warnings']));
    }

    public function test_it_warns_when_universal_vertical_components_use_missing_or_invalid_route_bindings(): void
    {
        $validator = new CmsBindingExpressionValidator(new CmsCanonicalBindingResolver);

        $result = $validator->validateContentJson([
            'sections' => [
                [
                    'type' => 'webu_ecom_order_detail_01',
                    'props' => [
                        'order_id' => '',
                    ],
                ],
                [
                    'type' => 'webu_book_slots_01',
                    'props' => [
                        'service_id' => '{{route.params.slug}}',
                    ],
                ],
                [
                    'type' => 'webu_portfolio_gallery_01',
                    'props' => [
                        'project_slug' => '{{route.params.project_id}}',
                    ],
                ],
                [
                    'type' => 'webu_realestate_map_01',
                    'props' => [
                        'property_slug' => '{{route.slug}}',
                    ],
                ],
                [
                    'type' => 'webu_hotel_room_availability_01',
                    'props' => [
                        'room_slug' => '',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $errors = collect($result['warnings'])->pluck('error')->all();
        $this->assertContains('missing_route_order_id_binding', $errors);
        $this->assertContains('invalid_route_service_id_binding', $errors);
        $this->assertContains('invalid_route_project_slug_binding', $errors);
        $this->assertContains('invalid_route_property_slug_binding', $errors);
        $this->assertContains('missing_route_room_slug_binding', $errors);
    }
}
