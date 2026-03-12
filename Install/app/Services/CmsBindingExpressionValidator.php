<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsBindingExpressionValidator
{
    public function __construct(
        protected CmsCanonicalBindingResolver $resolver
    ) {}

    /**
     * @param  array<string, mixed>  $contentJson
     * @return array<string, mixed>
     */
    public function validateContentJson(array $contentJson): array
    {
        $sections = is_array($contentJson['sections'] ?? null) ? array_values($contentJson['sections']) : [];
        $warnings = [];

        foreach ($sections as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }

            $sectionType = trim((string) ($section['type'] ?? ''));
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];

            $this->collectWarningsForValue(
                $props,
                $warnings,
                [
                    'section_index' => (int) $sectionIndex,
                    'section_type' => $sectionType,
                    'field_path' => 'props',
                ]
            );

            $this->collectRouteParamBindingWarnings(
                $sectionType,
                $props,
                (int) $sectionIndex,
                $warnings
            );
        }

        return [
            'valid' => $warnings === [],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @param  array{section_index:int,section_type:string,field_path:string}  $context
     */
    private function collectWarningsForValue(mixed $value, array &$warnings, array $context): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $nextPath = $this->appendPathSegment($context['field_path'], $key);
                $this->collectWarningsForValue($child, $warnings, [
                    ...$context,
                    'field_path' => $nextPath,
                ]);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        $candidate = trim($value);
        if (! $this->looksLikeBindingExpression($candidate)) {
            return;
        }

        $inspection = $this->resolver->inspect([], $candidate, null);
        $errorCode = (string) ($inspection['error'] ?? '');
        if (($inspection['ok'] ?? false) && (($inspection['error'] ?? null) === null || ($inspection['deferred'] ?? false))) {
            return;
        }

        // P1-B2-04 focuses on parser/syntax/namespace feedback first.
        // Payload availability is context-dependent and should not warn at save time yet.
        if (in_array($errorCode, ['unresolved_path', 'unmapped_path'], true)) {
            return;
        }

        $warnings[] = [
            'type' => 'binding_expression',
            'severity' => 'warning',
            'section_index' => $context['section_index'],
            'section_type' => $context['section_type'],
            'field_path' => $context['field_path'],
            'expression' => $candidate,
            'error' => $errorCode !== '' ? $errorCode : 'invalid_syntax',
            'normalized_expression' => is_string($inspection['expression'] ?? null) ? $inspection['expression'] : null,
        ];
    }

    private function looksLikeBindingExpression(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains($value, '{{') || str_contains($value, '}}')) {
            return true;
        }

        // Optional raw-path mode for advanced users (only when it clearly looks namespaced).
        return preg_match('/^(project|site|page|route|menu|global|customer|ecommerce|booking|content|system)\.[A-Za-z0-9_.\[\]-]+$/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function collectRouteParamBindingWarnings(string $sectionType, array $props, int $sectionIndex, array &$warnings): void
    {
        $normalizedType = Str::lower(trim($sectionType));
        foreach ($this->routeBindingRules() as $rule) {
            $sectionTypes = $rule['section_types'] ?? [];
            if (! is_array($sectionTypes) || ! in_array($normalizedType, $sectionTypes, true)) {
                continue;
            }

            $fieldKey = (string) ($rule['field_key'] ?? '');
            if ($fieldKey === '') {
                continue;
            }

            $rawValue = $props[$fieldKey] ?? null;
            $candidate = is_string($rawValue) ? trim($rawValue) : '';
            $fieldPath = 'props.'.$fieldKey;

            if ($candidate === '') {
                $warnings[] = [
                    'type' => 'route_param_binding',
                    'severity' => 'warning',
                    'section_index' => $sectionIndex,
                    'section_type' => $sectionType,
                    'field_path' => $fieldPath,
                    'expression' => '',
                    'error' => (string) ($rule['missing_error'] ?? 'missing_route_binding'),
                    'normalized_expression' => null,
                ];

                continue;
            }

            $normalizedExpression = $this->resolver->normalizeExpression($candidate);
            if ($normalizedExpression === null) {
                // Generic syntax warnings are handled by binding-expression collector.
                continue;
            }

            $allowed = $rule['allowed'] ?? [];
            if (! is_array($allowed) || ! in_array($normalizedExpression, $allowed, true)) {
                $warnings[] = [
                    'type' => 'route_param_binding',
                    'severity' => 'warning',
                    'section_index' => $sectionIndex,
                    'section_type' => $sectionType,
                    'field_path' => $fieldPath,
                    'expression' => $candidate,
                    'error' => (string) ($rule['invalid_error'] ?? 'invalid_route_binding'),
                    'normalized_expression' => $normalizedExpression,
                ];
            }
        }
    }

    /**
     * @return array<int, array{
     *   section_types: array<int, string>,
     *   field_key: string,
     *   allowed: array<int, string>,
     *   missing_error: string,
     *   invalid_error: string
     * }>
     */
    private function routeBindingRules(): array
    {
        return [
            [
                'section_types' => [
                    'webu_ecom_product_detail_01',
                    'webu_ecom_product_gallery_01',
                    'webu_ecom_product_tabs_01',
                ],
                'field_key' => 'product_slug',
                'allowed' => ['{{route.params.slug}}', '{{route.slug}}'], // route.slug kept for legacy compatibility
                'missing_error' => 'missing_route_product_slug_binding',
                'invalid_error' => 'invalid_route_product_slug_binding',
            ],
            [
                'section_types' => ['webu_ecom_order_detail_01'],
                'field_key' => 'order_id',
                'allowed' => ['{{route.params.id}}'],
                'missing_error' => 'missing_route_order_id_binding',
                'invalid_error' => 'invalid_route_order_id_binding',
            ],
            [
                'section_types' => ['webu_book_slots_01'],
                'field_key' => 'service_id',
                'allowed' => ['{{route.params.service_id}}'],
                'missing_error' => 'missing_route_service_id_binding',
                'invalid_error' => 'invalid_route_service_id_binding',
            ],
            [
                'section_types' => ['webu_portfolio_project_hero_01', 'webu_portfolio_gallery_01'],
                'field_key' => 'project_slug',
                'allowed' => ['{{route.params.slug}}'],
                'missing_error' => 'missing_route_project_slug_binding',
                'invalid_error' => 'invalid_route_project_slug_binding',
            ],
            [
                'section_types' => ['webu_realestate_property_hero_01', 'webu_realestate_map_01'],
                'field_key' => 'property_slug',
                'allowed' => ['{{route.params.slug}}'],
                'missing_error' => 'missing_route_property_slug_binding',
                'invalid_error' => 'invalid_route_property_slug_binding',
            ],
            [
                'section_types' => [
                    'webu_hotel_room_detail_01',
                    'webu_hotel_room_availability_01',
                    'webu_hotel_reservation_form_01',
                ],
                'field_key' => 'room_slug',
                'allowed' => ['{{route.params.slug}}'],
                'missing_error' => 'missing_route_room_slug_binding',
                'invalid_error' => 'invalid_route_room_slug_binding',
            ],
        ];
    }

    /**
     * @param  int|string  $segment
     */
    private function appendPathSegment(string $base, int|string $segment): string
    {
        if (is_int($segment) || ctype_digit((string) $segment)) {
            return $base.'['.(int) $segment.']';
        }

        $segment = Str::of((string) $segment)->trim()->toString();
        if ($segment === '') {
            return $base;
        }

        return $base.'.'.$segment;
    }
}
