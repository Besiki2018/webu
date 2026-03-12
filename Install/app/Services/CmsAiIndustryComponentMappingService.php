<?php

namespace App\Services;

use Illuminate\Support\Str;
use Throwable;

class CmsAiIndustryComponentMappingService
{
    public const VERSION = 1;

    public function __construct(
        private readonly CmsComponentLibrarySpecEquivalenceAliasMapService $componentAliasMapService,
    ) {}

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<string, mixed>
     */
    public function mapFromAiInput(array $aiInput): array
    {
        $prompt = Str::lower((string) data_get($aiInput, 'request.prompt', ''));
        $templateSlug = Str::lower(trim((string) data_get($aiInput, 'platform_context.template_blueprint.template_slug', '')));
        $projectType = $this->normalizeProjectType($aiInput);
        $moduleKeys = $this->moduleKeys($aiInput);

        $family = null;
        $decisionSource = 'fallback';
        $decisionEvidence = null;

        if ($projectType !== null) {
            $family = $this->mapProjectTypeToFamily($projectType);
            if ($family !== null) {
                $decisionSource = 'project_type';
                $decisionEvidence = $projectType;
            }
        }

        if ($family === null) {
            foreach ($moduleKeys as $moduleKey) {
                $candidate = $this->mapModuleKeyToFamily($moduleKey);
                if ($candidate === null) {
                    continue;
                }

                $family = $candidate;
                $decisionSource = 'module_signal';
                $decisionEvidence = $moduleKey;
                break;
            }
        }

        if ($family === null) {
            foreach ($this->promptKeywordMap() as $candidateFamily => $keywords) {
                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && str_contains($prompt, Str::lower($keyword))) {
                        $family = $candidateFamily;
                        $decisionSource = 'prompt_keyword';
                        $decisionEvidence = $keyword;
                        break 2;
                    }
                }
            }
        }

        if ($family === null && $templateSlug !== '') {
            $family = $this->inferFamilyFromTemplateSlug($templateSlug);
            if ($family !== null) {
                $decisionSource = 'template_slug';
                $decisionEvidence = $templateSlug;
            }
        }

        if ($family === null) {
            $family = 'business';
        }

        $builderComponentMapping = $this->augmentBuilderMappingForPromptRules(
            $this->builderComponentMappingForFamily($family),
            $family,
            $prompt
        );
        $builderComponentMapping = $this->augmentBuilderMappingWithSourceSpecAliases($builderComponentMapping);

        return [
            'ok' => true,
            'version' => self::VERSION,
            'industry_family' => $family,
            'decision_source' => $decisionSource,
            'decision_evidence' => $decisionEvidence,
            'signals' => [
                'project_type' => $projectType,
                'template_slug' => $templateSlug !== '' ? $templateSlug : null,
                'module_keys' => $moduleKeys,
            ],
            'builder_component_mapping' => $builderComponentMapping,
            'page_generation_catalog_supported' => in_array($family, ['ecommerce', 'blog', 'business'], true),
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     */
    private function normalizeProjectType(array $aiInput): ?string
    {
        foreach ([
            data_get($aiInput, 'platform_context.site.theme_settings.project_type'),
            data_get($aiInput, 'platform_context.site.project_type'),
            data_get($aiInput, 'platform_context.module_registry.project_type.key'),
        ] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return Str::lower($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<int, string>
     */
    private function moduleKeys(array $aiInput): array
    {
        $modules = data_get($aiInput, 'platform_context.module_registry.modules');
        if (! is_array($modules)) {
            return [];
        }

        $keys = [];
        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            $candidate = trim((string) ($module['key'] ?? $module['slug'] ?? ''));
            if ($candidate !== '') {
                $keys[] = Str::lower($candidate);
            }
        }

        return array_values(array_unique($keys));
    }

    private function mapProjectTypeToFamily(string $projectType): ?string
    {
        return match ($projectType) {
            'ecommerce', 'shop', 'store' => 'ecommerce',
            'service', 'booking', 'appointments' => 'booking',
            'portfolio' => 'portfolio',
            'real_estate', 'realestate', 'property' => 'real_estate',
            'restaurant', 'cafe' => 'restaurant',
            'hotel' => 'hotel',
            'blog', 'news' => 'blog',
            'business', 'company', 'corporate' => 'business',
            default => null,
        };
    }

    private function mapModuleKeyToFamily(string $moduleKey): ?string
    {
        return match ($moduleKey) {
            'ecommerce' => 'ecommerce',
            'booking', 'booking_team_scheduling', 'booking_finance' => 'booking',
            'portfolio' => 'portfolio',
            'real_estate' => 'real_estate',
            'restaurant' => 'restaurant',
            'hotel' => 'hotel',
            default => null,
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function promptKeywordMap(): array
    {
        return [
            'real_estate' => ['real estate', 'property listing', 'realtor', 'apartment', 'villa'],
            'restaurant' => ['restaurant', 'cafe', 'menu', 'table reservation', 'dining'],
            'hotel' => ['hotel', 'rooms', 'resort', 'hospitality', 'guest house'],
            'portfolio' => ['portfolio', 'case study', 'studio', 'agency showcase', 'photography'],
            'booking' => [
                'booking', 'appointment', 'calendar', 'schedule',
                'clinic', 'dentist', 'doctor',
                'salon', 'beauty', 'barber',
                'course', 'academy', 'enroll',
            ],
            'ecommerce' => ['ecommerce', 'e-commerce', 'online store', 'shop', 'store', 'buy', 'checkout', 'cart', 'product'],
            'blog' => ['blog', 'magazine', 'news', 'article'],
            'business' => ['business', 'corporate', 'company', 'landing page'],
        ];
    }

    private function inferFamilyFromTemplateSlug(string $templateSlug): ?string
    {
        return match (true) {
            str_contains($templateSlug, 'realestate') || str_contains($templateSlug, 'real-estate') || str_contains($templateSlug, 'property') => 'real_estate',
            str_contains($templateSlug, 'restaurant') || str_contains($templateSlug, 'cafe') || str_contains($templateSlug, 'menu') => 'restaurant',
            str_contains($templateSlug, 'hotel') || str_contains($templateSlug, 'resort') => 'hotel',
            str_contains($templateSlug, 'portfolio') || str_contains($templateSlug, 'studio') || str_contains($templateSlug, 'agency') => 'portfolio',
            str_contains($templateSlug, 'booking') || str_contains($templateSlug, 'appointment') || str_contains($templateSlug, 'service') => 'booking',
            str_contains($templateSlug, 'shop') || str_contains($templateSlug, 'store') || str_contains($templateSlug, 'ecom') => 'ecommerce',
            str_contains($templateSlug, 'blog') || str_contains($templateSlug, 'news') => 'blog',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function builderComponentMappingForFamily(string $family): array
    {
        return match ($family) {
            'ecommerce' => [
                'taxonomy_groups' => ['general', 'ecommerce', 'design'],
                'component_keys' => [
                    'webu_ecom_product_grid_01',
                    'webu_ecom_product_detail_01',
                    'webu_ecom_cart_page_01',
                    'webu_ecom_checkout_form_01',
                    'webu_ecom_orders_list_01',
                    'webu_ecom_order_detail_01',
                ],
                'requires_modules' => ['ecommerce'],
            ],
            'booking' => [
                'taxonomy_groups' => ['general', 'booking', 'design'],
                'component_keys' => [
                    'webu_svc_services_list_01',
                    'webu_svc_staff_grid_01',
                    'webu_book_slots_01',
                    'webu_book_booking_form_01',
                    'webu_book_calendar_01',
                ],
                'requires_modules' => ['booking'],
            ],
            'portfolio' => [
                'taxonomy_groups' => ['general', 'portfolio', 'design'],
                'component_keys' => [
                    'webu_portfolio_projects_grid_01',
                    'webu_portfolio_project_hero_01',
                    'webu_portfolio_gallery_01',
                    'webu_portfolio_metrics_01',
                ],
                'requires_modules' => ['portfolio'],
            ],
            'real_estate' => [
                'taxonomy_groups' => ['general', 'real_estate', 'design'],
                'component_keys' => [
                    'webu_realestate_property_grid_01',
                    'webu_realestate_property_hero_01',
                    'webu_realestate_search_filters_01',
                    'webu_realestate_map_01',
                ],
                'requires_modules' => ['real_estate'],
            ],
            'restaurant' => [
                'taxonomy_groups' => ['general', 'restaurant', 'booking', 'design'],
                'component_keys' => [
                    'webu_rest_menu_categories_01',
                    'webu_rest_menu_items_01',
                    'webu_rest_reservation_slots_01',
                    'webu_rest_reservation_form_01',
                ],
                'requires_modules' => ['restaurant', 'booking'],
            ],
            'hotel' => [
                'taxonomy_groups' => ['general', 'hotel', 'booking', 'design'],
                'component_keys' => [
                    'webu_hotel_room_grid_01',
                    'webu_hotel_room_detail_01',
                    'webu_hotel_room_availability_01',
                    'webu_hotel_reservation_form_01',
                ],
                'requires_modules' => ['hotel', 'booking'],
            ],
            'blog' => [
                'taxonomy_groups' => ['general', 'design'],
                'component_keys' => ['webu_general_container_01', 'webu_general_heading_01', 'webu_general_text_01'],
                'requires_modules' => [],
            ],
            default => [
                'taxonomy_groups' => ['general', 'design'],
                'component_keys' => ['webu_general_container_01', 'webu_general_heading_01', 'webu_general_text_01', 'webu_general_button_01'],
                'requires_modules' => [],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    private function augmentBuilderMappingForPromptRules(array $mapping, string $family, string $prompt): array
    {
        if ($family !== 'booking') {
            return $mapping;
        }

        $taxonomyGroups = is_array($mapping['taxonomy_groups'] ?? null)
            ? array_values(array_filter(array_map('strval', $mapping['taxonomy_groups'])))
            : [];
        $componentKeys = is_array($mapping['component_keys'] ?? null)
            ? array_values(array_filter(array_map('strval', $mapping['component_keys'])))
            : [];

        if ($this->promptContainsAnyKeyword($prompt, ['course', 'academy', 'enroll'])) {
            $taxonomyGroups[] = 'blog';
            $componentKeys[] = 'webu_blog_post_list_01';
            $componentKeys[] = 'webu_blog_category_list_01';
        }

        if ($this->promptContainsAnyKeyword($prompt, ['clinic', 'dentist', 'doctor'])) {
            $taxonomyGroups[] = 'forms';
            $componentKeys[] = 'webu_general_form_wrapper_01';
        }

        if ($this->promptContainsAnyKeyword($prompt, ['salon', 'beauty', 'barber'])) {
            $componentKeys[] = 'webu_general_image_01';
            $componentKeys[] = 'webu_general_grid_01';
        }

        $mapping['taxonomy_groups'] = array_values(array_unique($taxonomyGroups));
        $mapping['component_keys'] = array_values(array_unique($componentKeys));

        return $mapping;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function promptContainsAnyKeyword(string $prompt, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $needle = Str::lower(trim($keyword));
            if ($needle !== '' && str_contains($prompt, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Optional hardening: expose source-spec component aliases for current canonical `webu_*` keys.
     *
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    private function augmentBuilderMappingWithSourceSpecAliases(array $mapping): array
    {
        $componentKeys = is_array($mapping['component_keys'] ?? null)
            ? array_values(array_filter(array_map('strval', $mapping['component_keys'])))
            : [];

        try {
            $sourceSpecKeys = [];
            $unmappedCanonicalKeys = [];

            foreach ($componentKeys as $canonicalKey) {
                $aliases = $this->componentAliasMapService->findSourceComponentKeysForCanonicalBuilderKey($canonicalKey);
                if ($aliases === []) {
                    $unmappedCanonicalKeys[] = $canonicalKey;
                    continue;
                }

                foreach ($aliases as $alias) {
                    $sourceSpecKeys[] = $alias;
                }
            }

            $summary = $this->componentAliasMapService->summary();
            $mapping['source_spec_component_keys'] = array_values(array_unique($sourceSpecKeys));
            $mapping['source_spec_alias_coverage'] = [
                'ok' => true,
                'alias_map_version' => (string) ($summary['version'] ?? 'unknown'),
                'resolved_source_spec_key_count' => count($mapping['source_spec_component_keys']),
                'mapped_canonical_component_key_count' => count($componentKeys) - count(array_values(array_unique($unmappedCanonicalKeys))),
                'total_canonical_component_key_count' => count($componentKeys),
                'unmapped_canonical_component_keys' => array_values(array_unique($unmappedCanonicalKeys)),
            ];
        } catch (Throwable $e) {
            $mapping['source_spec_component_keys'] = [];
            $mapping['source_spec_alias_coverage'] = [
                'ok' => false,
                'error' => 'component_library_alias_map_unavailable',
                'message' => $e->getMessage(),
                'total_canonical_component_key_count' => count($componentKeys),
            ];
        }

        return $mapping;
    }
}
