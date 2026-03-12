<?php

namespace App\Services;

use App\Models\SectionLibrary;
use App\Models\Site;
use Illuminate\Support\Str;

class CmsAiOutputValidationEngine
{
    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator,
        protected CmsCanonicalBindingResolver $bindingResolver,
        protected ?AiOutputSchemaValidator $aiOutputSchemaValidator = null
    ) {}

    /**
     * Validate schema + component availability + binding expressions for AI output v1
     * before save/render steps.
     *
     * @param  array<string, mixed>  $aiOutput
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function validateOutputForSite(Site $site, array $aiOutput, array $options = []): array
    {
        if ($this->aiOutputSchemaValidator !== null) {
            $securityErrors = $this->aiOutputSchemaValidator->validate($aiOutput);
            if ($securityErrors !== []) {
                return [
                    'ok' => false,
                    'code' => 'invalid_ai_output',
                    'errors' => array_map(fn (string $msg): array => ['type' => 'security', 'message' => $msg], $securityErrors),
                    'warnings' => [],
                    'summary' => [
                        'site_id' => (string) $site->id,
                        'gate_passed' => false,
                        'blocking_checks' => ['no_raw_html_css'],
                    ],
                    'validation' => [
                        'schema' => ['valid' => false, 'error_count' => count($securityErrors)],
                        'component_availability' => ['valid' => false, 'error_count' => 0, 'warning_count' => 0, 'checked_components' => 0, 'errors' => [], 'warnings' => []],
                        'bindings' => ['valid' => false, 'error_count' => 0, 'warning_count' => 0, 'checked_candidates' => 0, 'errors' => [], 'warnings' => []],
                    ],
                ];
            }
        }

        $schemaValidation = $this->schemaValidator->validateOutputPayload($aiOutput);
        if (! ($schemaValidation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_ai_output',
                'errors' => is_array($schemaValidation['errors'] ?? null) ? $schemaValidation['errors'] : [],
                'warnings' => [],
                'summary' => [
                    'site_id' => (string) $site->id,
                    'gate_passed' => false,
                    'blocking_checks' => ['schema'],
                ],
                'validation' => [
                    'schema' => $this->compactValidationReport($schemaValidation),
                    'component_availability' => [
                        'valid' => false,
                        'error_count' => 0,
                        'warning_count' => 0,
                        'checked_components' => 0,
                        'errors' => [],
                        'warnings' => [],
                    ],
                    'bindings' => [
                        'valid' => false,
                        'error_count' => 0,
                        'warning_count' => 0,
                        'checked_candidates' => 0,
                        'errors' => [],
                        'warnings' => [],
                    ],
                ],
            ];
        }

        $availability = $this->validateComponentAvailability($aiOutput);
        $bindings = $this->validateBindings($aiOutput, $options);

        $errors = array_merge($availability['errors'], $bindings['errors']);
        $warnings = array_merge($availability['warnings'], $bindings['warnings']);
        $ok = $errors === [];

        return [
            'ok' => $ok,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'site_id' => (string) $site->id,
                'gate_passed' => $ok,
                'blocking_checks' => [
                    'schema',
                    'component_availability',
                    'bindings',
                ],
                'checked_components' => (int) $availability['checked_components'],
                'checked_binding_candidates' => (int) $bindings['checked_candidates'],
            ],
            'validation' => [
                'schema' => $this->compactValidationReport($schemaValidation),
                'component_availability' => [
                    'valid' => $availability['errors'] === [],
                    'error_count' => count($availability['errors']),
                    'warning_count' => count($availability['warnings']),
                    'checked_components' => $availability['checked_components'],
                    'errors' => $availability['errors'],
                    'warnings' => $availability['warnings'],
                ],
                'bindings' => [
                    'valid' => $bindings['errors'] === [],
                    'error_count' => count($bindings['errors']),
                    'warning_count' => count($bindings['warnings']),
                    'checked_candidates' => $bindings['checked_candidates'],
                    'errors' => $bindings['errors'],
                    'warnings' => $bindings['warnings'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aiOutput
     * @return array{
     *   checked_components:int,
     *   errors: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, mixed>>
     * }
     */
    private function validateComponentAvailability(array $aiOutput): array
    {
        $library = SectionLibrary::query()
            ->get(['key', 'category', 'enabled'])
            ->mapWithKeys(static fn (SectionLibrary $section): array => [
                Str::lower(trim((string) $section->key)) => [
                    'key' => (string) $section->key,
                    'category' => (string) $section->category,
                    'enabled' => (bool) $section->enabled,
                ],
            ])
            ->all();

        $checkedComponents = 0;
        $errors = [];
        $warnings = [];

        $pages = is_array($aiOutput['pages'] ?? null) ? array_values($aiOutput['pages']) : [];
        foreach ($pages as $pageIndex => $page) {
            if (! is_array($page)) {
                continue;
            }

            $nodes = is_array($page['builder_nodes'] ?? null) ? array_values($page['builder_nodes']) : [];
            foreach ($nodes as $nodeIndex => $node) {
                if (! is_array($node)) {
                    continue;
                }

                $this->validateComponentNodeTypeAvailability(
                    node: $node,
                    path: '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.']',
                    scope: 'pages',
                    library: $library,
                    checkedComponents: $checkedComponents,
                    errors: $errors,
                    warnings: $warnings
                );
            }
        }

        foreach (['header', 'footer'] as $fixedKey) {
            $fixed = is_array($aiOutput[$fixedKey] ?? null) ? $aiOutput[$fixedKey] : [];
            if ($fixed === []) {
                continue;
            }

            $enabled = (bool) ($fixed['enabled'] ?? false);
            $sectionType = is_string($fixed['section_type'] ?? null)
                ? trim((string) $fixed['section_type'])
                : '';

            if (! $enabled) {
                continue;
            }

            if ($sectionType === '') {
                $errors[] = [
                    'type' => 'component_availability',
                    'code' => 'missing_enabled_fixed_section_type',
                    'scope' => $fixedKey,
                    'path' => '$.'.$fixedKey.'.section_type',
                    'requested_type' => null,
                ];
                continue;
            }

            $checkedComponents++;
            $mapping = $this->mapToLibrary($sectionType, $library);
            $this->appendComponentAvailabilityFinding(
                scope: $fixedKey,
                path: '$.'.$fixedKey.'.section_type',
                requestedType: $sectionType,
                mapping: $mapping,
                errors: $errors,
                warnings: $warnings
            );
        }

        return [
            'checked_components' => $checkedComponents,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{key:string,category:string,enabled:bool}>  $library
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function validateComponentNodeTypeAvailability(
        array $node,
        string $path,
        string $scope,
        array $library,
        int &$checkedComponents,
        array &$errors,
        array &$warnings
    ): void {
        $type = is_string($node['type'] ?? null) ? trim((string) $node['type']) : '';
        $resolvedType = null;
        if ($type !== '') {
            $checkedComponents++;
            $mapping = $this->mapToLibrary($type, $library);
            $resolvedType = is_string($mapping['mapped_key'] ?? null) ? (string) $mapping['mapped_key'] : null;
            $this->appendComponentAvailabilityFinding(
                scope: $scope,
                path: $path.'.type',
                requestedType: $type,
                mapping: $mapping,
                errors: $errors,
                warnings: $warnings
            );
        }

        $parentLayoutRole = $this->resolveLayoutNestingRole($type, $resolvedType);

        $children = is_array($node['children'] ?? null) ? array_values($node['children']) : [];
        foreach ($children as $childIndex => $child) {
            if (! is_array($child)) {
                continue;
            }

            $childType = is_string($child['type'] ?? null) ? trim((string) $child['type']) : '';
            $childResolvedType = null;
            if ($childType !== '') {
                $childMapping = $this->mapToLibrary($childType, $library);
                $childResolvedType = is_string($childMapping['mapped_key'] ?? null) ? (string) $childMapping['mapped_key'] : null;
            }
            $childLayoutRole = $this->resolveLayoutNestingRole($childType, $childResolvedType);
            $this->appendLayoutNestingFinding(
                scope: $scope,
                path: $path.'.children['.$childIndex.'].type',
                parentType: $type,
                parentResolvedType: $resolvedType,
                parentLayoutRole: $parentLayoutRole,
                childType: $childType,
                childResolvedType: $childResolvedType,
                childLayoutRole: $childLayoutRole,
                errors: $errors
            );

            $this->validateComponentNodeTypeAvailability(
                node: $child,
                path: $path.'.children['.$childIndex.']',
                scope: $scope,
                library: $library,
                checkedComponents: $checkedComponents,
                errors: $errors,
                warnings: $warnings
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function appendLayoutNestingFinding(
        string $scope,
        string $path,
        string $parentType,
        ?string $parentResolvedType,
        ?string $parentLayoutRole,
        string $childType,
        ?string $childResolvedType,
        ?string $childLayoutRole,
        array &$errors
    ): void {
        if ($parentLayoutRole === null) {
            return;
        }

        $allowedRoles = null;
        $invalidChild = false;

        if ($parentLayoutRole === 'section') {
            // Section nesting is constrained for foundation layout children.
            $allowedRoles = ['container', 'grid', 'columns'];
            if ($childLayoutRole !== null && ! in_array($childLayoutRole, $allowedRoles, true)) {
                $invalidChild = true;
            }
        } elseif (in_array($parentLayoutRole, ['spacer', 'divider'], true)) {
            // Spacer/divider are leaf layout nodes.
            $allowedRoles = [];
            $invalidChild = true;
        }

        if (! $invalidChild) {
            return;
        }

        $errors[] = [
            'type' => 'component_availability',
            'code' => 'invalid_layout_nesting_child',
            'scope' => $scope,
            'path' => $path,
            'parent_type' => $parentType !== '' ? $parentType : null,
            'parent_resolved_type' => $parentResolvedType,
            'parent_layout_role' => $parentLayoutRole,
            'child_type' => $childType !== '' ? $childType : null,
            'child_resolved_type' => $childResolvedType,
            'child_layout_role' => $childLayoutRole,
            'allowed_child_layout_roles' => $allowedRoles,
        ];
    }

    private function resolveLayoutNestingRole(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if ($this->containsAllSignals($candidate, ['layout', 'section'])
                || $this->containsAllSignals($candidate, ['general', 'section'])) {
                return 'section';
            }
            if ($this->containsAllSignals($candidate, ['layout', 'container'])
                || $this->containsAllSignals($candidate, ['general', 'container'])) {
                return 'container';
            }
            if ($this->containsAllSignals($candidate, ['layout', 'grid'])
                || $this->containsAllSignals($candidate, ['general', 'grid'])) {
                return 'grid';
            }
            if ($this->containsAllSignals($candidate, ['layout', 'columns'])
                || $this->containsAllSignals($candidate, ['general', 'columns'])) {
                return 'columns';
            }
            if ($this->containsAllSignals($candidate, ['layout', 'spacer'])
                || $this->containsAllSignals($candidate, ['general', 'spacer'])) {
                return 'spacer';
            }
            if ($this->containsAllSignals($candidate, ['layout', 'divider'])
                || $this->containsAllSignals($candidate, ['general', 'divider'])) {
                return 'divider';
            }
        }

        return null;
    }

    /**
     * @param  array{mapped_key:?string,mapped_category:?string,confidence:string,enabled:?bool}  $mapping
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function appendComponentAvailabilityFinding(
        string $scope,
        string $path,
        string $requestedType,
        array $mapping,
        array &$errors,
        array &$warnings
    ): void {
        if ($mapping['mapped_key'] === null) {
            $errors[] = [
                'type' => 'component_availability',
                'code' => 'unavailable_component',
                'scope' => $scope,
                'path' => $path,
                'requested_type' => $requestedType,
                'resolved_type' => null,
                'match_confidence' => 'none',
            ];

            return;
        }

        if (($mapping['enabled'] ?? null) === false) {
            $errors[] = [
                'type' => 'component_availability',
                'code' => 'disabled_component',
                'scope' => $scope,
                'path' => $path,
                'requested_type' => $requestedType,
                'resolved_type' => $mapping['mapped_key'],
                'match_confidence' => $mapping['confidence'],
            ];

            return;
        }

        if (($mapping['confidence'] ?? 'exact') === 'fuzzy') {
            $warnings[] = [
                'type' => 'component_availability',
                'code' => 'fuzzy_component_match',
                'scope' => $scope,
                'path' => $path,
                'requested_type' => $requestedType,
                'resolved_type' => $mapping['mapped_key'],
                'match_confidence' => 'fuzzy',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $aiOutput
     * @param  array<string, mixed>  $options
     * @return array{
     *   checked_candidates:int,
     *   errors: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, mixed>>
     * }
     */
    private function validateBindings(array $aiOutput, array $options): array
    {
        $checkedCandidates = 0;
        $errors = [];
        $warnings = [];
        $reportUnresolved = (bool) ($options['report_unresolved_bindings'] ?? false);

        $pages = is_array($aiOutput['pages'] ?? null) ? array_values($aiOutput['pages']) : [];
        foreach ($pages as $pageIndex => $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageSlug = is_string($page['slug'] ?? null) ? trim((string) $page['slug']) : '';
            $routePattern = is_string($page['route_pattern'] ?? null) ? trim((string) $page['route_pattern']) : '';
            $nodes = is_array($page['builder_nodes'] ?? null) ? array_values($page['builder_nodes']) : [];

            foreach ($nodes as $nodeIndex => $node) {
                if (! is_array($node)) {
                    continue;
                }

                $nodePath = '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.']';
                $this->collectBindingCandidatesFromNode(
                    node: $node,
                    nodePath: $nodePath,
                    checkedCandidates: $checkedCandidates,
                    errors: $errors,
                    warnings: $warnings,
                    reportUnresolved: $reportUnresolved
                );
                $this->collectRouteBindingExpectationFindings(
                    pageSlug: $pageSlug,
                    routePattern: $routePattern,
                    node: $node,
                    nodePath: $nodePath,
                    errors: $errors
                );
            }
        }

        foreach (['header', 'footer'] as $fixedKey) {
            $fixed = is_array($aiOutput[$fixedKey] ?? null) ? $aiOutput[$fixedKey] : [];
            if ($fixed === []) {
                continue;
            }

            $props = is_array($fixed['props'] ?? null) ? $fixed['props'] : [];
            $bindings = is_array($fixed['bindings'] ?? null) ? $fixed['bindings'] : [];

            $this->collectBindingCandidatesFromValue(
                value: $props,
                basePath: '$.'.$fixedKey.'.props',
                checkedCandidates: $checkedCandidates,
                errors: $errors,
                warnings: $warnings,
                reportUnresolved: $reportUnresolved,
                scope: $fixedKey,
                sectionType: is_string($fixed['section_type'] ?? null) ? (string) $fixed['section_type'] : null
            );
            $this->collectBindingCandidatesFromValue(
                value: $bindings,
                basePath: '$.'.$fixedKey.'.bindings',
                checkedCandidates: $checkedCandidates,
                errors: $errors,
                warnings: $warnings,
                reportUnresolved: $reportUnresolved,
                scope: $fixedKey,
                sectionType: is_string($fixed['section_type'] ?? null) ? (string) $fixed['section_type'] : null
            );
        }

        return [
            'checked_candidates' => $checkedCandidates,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function collectBindingCandidatesFromNode(
        array $node,
        string $nodePath,
        int &$checkedCandidates,
        array &$errors,
        array &$warnings,
        bool $reportUnresolved
    ): void {
        $nodeType = is_string($node['type'] ?? null) ? (string) $node['type'] : null;
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $bindings = is_array($node['bindings'] ?? null) ? $node['bindings'] : [];

        $this->collectBindingCandidatesFromValue(
            value: $props,
            basePath: $nodePath.'.props',
            checkedCandidates: $checkedCandidates,
            errors: $errors,
            warnings: $warnings,
            reportUnresolved: $reportUnresolved,
            scope: 'pages',
            sectionType: $nodeType
        );
        $this->collectBindingCandidatesFromValue(
            value: $bindings,
            basePath: $nodePath.'.bindings',
            checkedCandidates: $checkedCandidates,
            errors: $errors,
            warnings: $warnings,
            reportUnresolved: $reportUnresolved,
            scope: 'pages',
            sectionType: $nodeType
        );

        $children = is_array($node['children'] ?? null) ? array_values($node['children']) : [];
        foreach ($children as $childIndex => $child) {
            if (! is_array($child)) {
                continue;
            }

            $this->collectBindingCandidatesFromNode(
                node: $child,
                nodePath: $nodePath.'.children['.$childIndex.']',
                checkedCandidates: $checkedCandidates,
                errors: $errors,
                warnings: $warnings,
                reportUnresolved: $reportUnresolved
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function collectBindingCandidatesFromValue(
        mixed $value,
        string $basePath,
        int &$checkedCandidates,
        array &$errors,
        array &$warnings,
        bool $reportUnresolved,
        string $scope,
        ?string $sectionType = null
    ): void {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $this->collectBindingCandidatesFromValue(
                    value: $child,
                    basePath: $this->appendPathSegment($basePath, $key),
                    checkedCandidates: $checkedCandidates,
                    errors: $errors,
                    warnings: $warnings,
                    reportUnresolved: $reportUnresolved,
                    scope: $scope,
                    sectionType: $sectionType
                );
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

        $checkedCandidates++;
        $inspection = $this->bindingResolver->inspect([], $candidate, null);
        $errorCode = is_string($inspection['error'] ?? null) ? (string) $inspection['error'] : '';

        if (($inspection['ok'] ?? false) && (($inspection['error'] ?? null) === null || ($inspection['deferred'] ?? false))) {
            return;
        }

        if (in_array($errorCode, ['unresolved_path', 'unmapped_path'], true)) {
            if (! $reportUnresolved) {
                return;
            }

            $warnings[] = [
                'type' => 'binding_validation',
                'code' => $errorCode,
                'scope' => $scope,
                'section_type' => $sectionType,
                'path' => $basePath,
                'expression' => $candidate,
                'normalized_expression' => is_string($inspection['expression'] ?? null) ? $inspection['expression'] : null,
            ];

            return;
        }

        $errors[] = [
            'type' => 'binding_validation',
            'code' => $errorCode !== '' ? $errorCode : 'invalid_syntax',
            'scope' => $scope,
            'section_type' => $sectionType,
            'path' => $basePath,
            'expression' => $candidate,
            'normalized_expression' => is_string($inspection['expression'] ?? null) ? $inspection['expression'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function collectRouteBindingExpectationFindings(
        string $pageSlug,
        string $routePattern,
        array $node,
        string $nodePath,
        array &$errors
    ): void {
        $nodeType = $this->normalizeLookupKey(is_string($node['type'] ?? null) ? (string) $node['type'] : '');
        $bindings = is_array($node['bindings'] ?? null) ? $node['bindings'] : [];
        $content = is_array(data_get($node, 'props.content')) ? data_get($node, 'props.content') : [];

        $isProductDetailNode = $this->containsAllSignals($nodeType, ['product', 'detail']);
        $isOrderDetailNode = $this->containsAllSignals($nodeType, ['order', 'detail']);

        if ($isProductDetailNode && $this->looksLikeProductDetailPage($pageSlug, $routePattern)) {
            $this->validateRequiredRouteBinding(
                field: 'product_slug',
                allowed: ['{{route.params.slug}}', '{{route.slug}}'],
                bindings: $bindings,
                content: $content,
                path: $nodePath,
                sectionType: is_string($node['type'] ?? null) ? (string) $node['type'] : null,
                errors: $errors
            );
        }

        if ($isOrderDetailNode && $this->looksLikeOrderDetailPage($pageSlug, $routePattern)) {
            $this->validateRequiredRouteBinding(
                field: 'order_id',
                allowed: ['{{route.params.id}}', '{{route.id}}'],
                bindings: $bindings,
                content: $content,
                path: $nodePath,
                sectionType: is_string($node['type'] ?? null) ? (string) $node['type'] : null,
                errors: $errors
            );
        }
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @param  array<string, mixed>  $content
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $allowed
     */
    private function validateRequiredRouteBinding(
        string $field,
        array $allowed,
        array $bindings,
        array $content,
        string $path,
        ?string $sectionType,
        array &$errors
    ): void {
        $bindingValue = $bindings[$field] ?? null;
        $bindingPath = $path.'.bindings.'.$field;

        if (! is_string($bindingValue) || trim($bindingValue) === '') {
            $fallbackContentValue = $content[$field] ?? null;
            if (is_string($fallbackContentValue) && trim($fallbackContentValue) !== '') {
                $bindingValue = $fallbackContentValue;
                $bindingPath = $path.'.props.content.'.$field;
            }
        }

        $raw = is_string($bindingValue) ? trim($bindingValue) : '';
        if ($raw === '') {
            $errors[] = [
                'type' => 'binding_validation',
                'code' => 'missing_required_route_binding',
                'section_type' => $sectionType,
                'path' => $bindingPath,
                'field' => $field,
                'expected_any_of' => $allowed,
            ];

            return;
        }

        $normalized = $this->bindingResolver->normalizeExpression($raw);
        if ($normalized === null) {
            // Syntax error is reported by generic binding validation collector.
            return;
        }

        if (! in_array($normalized, $allowed, true)) {
            $errors[] = [
                'type' => 'binding_validation',
                'code' => 'invalid_required_route_binding',
                'section_type' => $sectionType,
                'path' => $bindingPath,
                'field' => $field,
                'expression' => $raw,
                'normalized_expression' => $normalized,
                'expected_any_of' => $allowed,
            ];
        }
    }

    /**
     * @param  array<string, array{key:string,category:string,enabled:bool}>  $library
     * @return array{mapped_key:?string,mapped_category:?string,confidence:string,enabled:?bool}
     */
    private function mapToLibrary(string $key, array $library): array
    {
        $normalized = $this->normalizeLookupKey($key);
        if ($normalized === '') {
            return [
                'mapped_key' => null,
                'mapped_category' => null,
                'confidence' => 'none',
                'enabled' => null,
            ];
        }

        if (isset($library[$normalized])) {
            return [
                'mapped_key' => $library[$normalized]['key'],
                'mapped_category' => $library[$normalized]['category'],
                'confidence' => 'exact',
                'enabled' => $library[$normalized]['enabled'],
            ];
        }

        $compact = $this->compactLookupKey($normalized);
        foreach ($library as $libNormalized => $mapped) {
            if ($this->compactLookupKey($libNormalized) === $compact) {
                return [
                    'mapped_key' => $mapped['key'],
                    'mapped_category' => $mapped['category'],
                    'confidence' => 'alias',
                    'enabled' => $mapped['enabled'],
                ];
            }
        }

        foreach ($library as $libNormalized => $mapped) {
            if (str_contains($libNormalized, $normalized) || str_contains($normalized, $libNormalized)) {
                return [
                    'mapped_key' => $mapped['key'],
                    'mapped_category' => $mapped['category'],
                    'confidence' => 'fuzzy',
                    'enabled' => $mapped['enabled'],
                ];
            }
        }

        return [
            'mapped_key' => null,
            'mapped_category' => null,
            'confidence' => 'none',
            'enabled' => null,
        ];
    }

    private function normalizeLookupKey(string $key): string
    {
        return Str::lower(trim($key));
    }

    private function compactLookupKey(string $key): string
    {
        return str_replace(['-', '_', ' '], '', Str::lower(trim($key)));
    }

    private function looksLikeBindingExpression(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains($value, '{{') || str_contains($value, '}}')) {
            return true;
        }

        return preg_match('/^(project|site|page|route|menu|global|customer|ecommerce|booking|content|system)\.[A-Za-z0-9_.\[\]-]+$/', $value) === 1;
    }

    /**
     * @param  int|string  $segment
     */
    private function appendPathSegment(string $base, int|string $segment): string
    {
        if (is_int($segment) || ctype_digit((string) $segment)) {
            return $base.'['.(int) $segment.']';
        }

        $segment = trim((string) $segment);
        if ($segment === '') {
            return $base;
        }

        return $base.'.'.$segment;
    }

    private function looksLikeProductDetailPage(string $pageSlug, string $routePattern): bool
    {
        $slug = Str::lower(trim($pageSlug));
        $route = Str::lower(trim($routePattern));

        return $slug === 'product'
            || $slug === 'product-detail'
            || str_contains($slug, 'product')
            || str_contains($route, '{slug}');
    }

    private function looksLikeOrderDetailPage(string $pageSlug, string $routePattern): bool
    {
        $slug = Str::lower(trim($pageSlug));
        $route = Str::lower(trim($routePattern));

        return $slug === 'order-detail'
            || str_contains($slug, 'order')
            || str_contains($route, '{id}');
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function containsAllSignals(string $value, array $signals): bool
    {
        $compact = $this->compactLookupKey($value);
        if ($compact === '') {
            return false;
        }

        foreach ($signals as $signal) {
            if (! str_contains($compact, $this->compactLookupKey($signal))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function compactValidationReport(array $report): array
    {
        return [
            'valid' => (bool) ($report['valid'] ?? false),
            'schema' => $report['schema'] ?? null,
            'error_count' => (int) ($report['error_count'] ?? 0),
        ];
    }
}
