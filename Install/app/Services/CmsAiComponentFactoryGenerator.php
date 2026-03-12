<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiComponentFactoryGenerator
{
    public const VERSION = 1;

    public function __construct(
        protected CmsAiFeatureSpecParser $featureSpecParser
    ) {}

    /**
     * Generate builder-compatible component scaffolds from a canonical feature spec v1.
     *
     * @param  array<string, mixed>  $featureSpec
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generateFromCanonicalSpec(array $featureSpec, array $options = []): array
    {
        $validation = $this->validateCanonicalFeatureSpec($featureSpec);
        if (! ($validation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_feature_spec',
                'errors' => $validation['errors'],
                'warnings' => [],
                'generated' => null,
                'summary' => [
                    'generator_version' => self::VERSION,
                    'error_count' => count($validation['errors']),
                ],
            ];
        }

        $featureKey = (string) $featureSpec['feature_key'];
        $domain = (string) ($featureSpec['domain'] ?? 'universal');
        $components = is_array($featureSpec['components'] ?? null) ? array_values($featureSpec['components']) : [];
        $states = is_array($featureSpec['states'] ?? null) ? array_values($featureSpec['states']) : [];
        $events = is_array($featureSpec['events'] ?? null) ? array_values($featureSpec['events']) : [];
        $builderContract = is_array($featureSpec['builder_contract'] ?? null) ? $featureSpec['builder_contract'] : [];

        $warnings = [];
        $generatedComponents = [];
        $registryEntries = [];
        $nodeScaffolds = [];
        $rendererScaffolds = [];
        $dynamicBindingComponents = 0;

        foreach ($components as $index => $component) {
            if (! is_array($component)) {
                continue;
            }

            $built = $this->buildGeneratedComponentArtifact(
                featureSpec: $featureSpec,
                component: $component,
                componentIndex: $index,
                states: $states,
                events: $events,
                builderContract: $builderContract,
                options: $options
            );

            $warnings = array_merge($warnings, $built['warnings']);
            $generatedComponents[] = $built['artifact'];
            $registryEntries[] = $built['artifact']['registry_entry'];
            $nodeScaffolds[] = $built['artifact']['node_scaffold'];
            $rendererScaffolds[] = $built['artifact']['renderer_scaffold'];

            if ((bool) data_get($built, 'artifact.registry_entry.meta.supports_dynamic_bindings')) {
                $dynamicBindingComponents++;
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => array_values(array_unique($warnings)),
            'generated' => [
                'schema_version' => 1,
                'feature_key' => $featureKey,
                'domain' => $domain,
                'components' => $generatedComponents,
                'registry_entries' => $registryEntries,
                'node_scaffolds' => $nodeScaffolds,
                'renderer_scaffolds' => $rendererScaffolds,
                'meta' => [
                    'generator_version' => self::VERSION,
                    'source' => 'feature_spec_factory',
                    'feature_spec_parser_version' => (int) data_get($featureSpec, 'meta.parser_version', 1),
                ],
            ],
            'summary' => [
                'generator_version' => self::VERSION,
                'feature_key' => $featureKey,
                'domain' => $domain,
                'component_count' => count($generatedComponents),
                'registry_entry_count' => count($registryEntries),
                'node_scaffold_count' => count($nodeScaffolds),
                'renderer_scaffold_count' => count($rendererScaffolds),
                'dynamic_binding_components' => $dynamicBindingComponents,
                'warning_count' => count(array_values(array_unique($warnings))),
            ],
        ];
    }

    /**
     * Parse flexible feature spec input and then generate scaffolds.
     *
     * @param  array<string, mixed>  $rawFeatureSpec
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generateFromRawSpec(array $rawFeatureSpec, array $options = []): array
    {
        $parsed = $this->featureSpecParser->parse($rawFeatureSpec);
        if (! ($parsed['ok'] ?? false) || ! is_array($parsed['spec'] ?? null)) {
            return [
                'ok' => false,
                'code' => 'feature_spec_parse_failed',
                'errors' => is_array($parsed['errors'] ?? null) ? $parsed['errors'] : [],
                'warnings' => is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [],
                'generated' => null,
                'summary' => [
                    'generator_version' => self::VERSION,
                    'parse_ok' => false,
                ],
            ];
        }

        $generated = $this->generateFromCanonicalSpec($parsed['spec'], $options);
        $generatedWarnings = is_array($generated['warnings'] ?? null) ? $generated['warnings'] : [];
        $parseWarnings = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

        $generated['warnings'] = array_values(array_unique(array_merge(
            $generatedWarnings,
            array_map(
                static fn ($warning): string => is_array($warning) ? ((string) ($warning['code'] ?? 'parse_warning')) : (string) $warning,
                $parseWarnings
            )
        )));

        if (is_array($generated['generated'] ?? null)) {
            $generated['generated']['feature_spec'] = $parsed['spec'];
        }

        return $generated;
    }

    /**
     * @param  array<string, mixed>  $featureSpec
     * @param  array<string, mixed>  $component
     * @param  array<int, array<string, mixed>>  $states
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $builderContract
     * @param  array<string, mixed>  $options
     * @return array{
     *   artifact: array<string, mixed>,
     *   warnings: array<int, string>
     * }
     */
    private function buildGeneratedComponentArtifact(
        array $featureSpec,
        array $component,
        int $componentIndex,
        array $states,
        array $events,
        array $builderContract,
        array $options = []
    ): array {
        $warnings = [];

        $featureKey = (string) ($featureSpec['feature_key'] ?? 'feature');
        $domain = (string) ($featureSpec['domain'] ?? 'universal');
        $componentKey = (string) ($component['key'] ?? 'component-'.$componentIndex);
        $componentLabel = (string) ($component['label'] ?? Str::headline(str_replace('-', ' ', $componentKey)));
        $componentRole = (string) ($component['role'] ?? 'panel');

        $registryType = $this->registryTypeForComponent($featureKey, $componentKey);

        $componentBindings = $this->normalizeBindingKeysForArtifacts(
            is_array(data_get($component, 'data_contract.bindings')) ? data_get($component, 'data_contract.bindings') : []
        );
        $componentQueries = is_array(data_get($component, 'data_contract.queries')) ? data_get($component, 'data_contract.queries') : [];
        $componentActions = is_array(data_get($component, 'data_contract.actions')) ? data_get($component, 'data_contract.actions') : [];
        $componentDefaults = is_array(data_get($component, 'props_contract.defaults')) ? data_get($component, 'props_contract.defaults') : [];
        $componentControls = is_array(data_get($component, 'props_contract.controls')) ? data_get($component, 'props_contract.controls') : [];
        $componentVariants = is_array($component['variants'] ?? null) ? array_values($component['variants']) : [];

        $defaultProps = $this->buildDefaultProps(
            componentDefaults: $componentDefaults,
            componentQueries: $componentQueries,
            componentActions: $componentActions,
            componentVariants: $componentVariants,
            states: $states,
            featureKey: $featureKey,
            componentKey: $componentKey,
            componentRole: $componentRole,
        );

        $controlsConfig = $this->buildControlsConfig(
            componentLabel: $componentLabel,
            componentRole: $componentRole,
            componentControls: $componentControls,
            componentDefaults: $componentDefaults,
            componentBindings: $componentBindings,
            componentQueries: $componentQueries,
            componentActions: $componentActions,
            builderContract: $builderContract,
            states: $states,
            events: $events,
            warnings: $warnings,
        );

        $propsSchema = $this->buildPropsSchema(
            componentDefaults: $componentDefaults,
            componentBindings: $componentBindings,
            componentQueries: $componentQueries,
            componentActions: $componentActions,
            states: $states,
            componentVariants: $componentVariants,
        );

        $supportsDynamicBindings = $componentBindings !== [] || $componentQueries !== [];

        $registryEntry = [
            'type' => $registryType,
            'category' => (string) ($component['category'] ?? $this->fallbackCategoryForDomain($domain)),
            'props_schema' => $propsSchema,
            'default_props' => $defaultProps,
            'renderer' => [
                'kind' => 'adapter',
                'runtime_key' => 'ai_feature_factory:'.$featureKey.':'.$componentKey,
                'html_template_ref' => 'generated/'.$featureKey.'/'.$componentKey.'.html',
            ],
            'controls_config' => $controlsConfig,
            'meta' => [
                'schema_version' => 1,
                'supports_dynamic_bindings' => $supportsDynamicBindings,
                'supports_responsive' => true,
                'supports_states' => $states !== [],
                'generated' => [
                    'feature_key' => $featureKey,
                    'component_key' => $componentKey,
                    'component_role' => $componentRole,
                    'generator_version' => self::VERSION,
                ],
            ],
        ];

        $nodeScaffold = [
            'id' => $this->nodeScaffoldId($featureKey, $componentKey, $componentIndex),
            'type' => $registryType,
            'props' => $defaultProps,
            'bindings' => $componentBindings,
            'meta' => [
                'schema_version' => 1,
                'source' => 'ai_component_factory',
                'label' => $componentLabel,
                'feature_key' => $featureKey,
                'component_key' => $componentKey,
            ],
        ];

        $rendererScaffold = [
            'component_key' => $componentKey,
            'registry_type' => $registryType,
            'template' => [
                'ref' => 'generated/'.$featureKey.'/'.$componentKey.'.html',
                'markers' => [
                    'root' => 'data-webu-ai-feature-component="'.$registryType.'"',
                    'content' => array_values(array_map(
                        static fn (string $field): string => 'data-bind-content="'.$field.'"',
                        array_keys($this->flattenScalarTree($componentDefaults))
                    )),
                ],
            ],
            'adapter_contract' => [
                'expects_bindings' => array_keys($componentBindings),
                'queries' => array_values(array_map(
                    static fn (array $query): string => (string) ($query['resource'] ?? ''),
                    array_filter($componentQueries, 'is_array')
                )),
            ],
        ];

        return [
            'artifact' => [
                'component_key' => $componentKey,
                'component_label' => $componentLabel,
                'registry_type' => $registryType,
                'registry_entry' => $registryEntry,
                'node_scaffold' => $nodeScaffold,
                'renderer_scaffold' => $rendererScaffold,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $featureSpec
     * @return array{valid: bool, errors: array<int, array<string, mixed>>}
     */
    private function validateCanonicalFeatureSpec(array $featureSpec): array
    {
        $errors = [];

        foreach (['schema_version', 'feature_key', 'display_name', 'domain', 'components', 'states', 'api_contract', 'builder_contract', 'meta'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $featureSpec)) {
                $errors[] = [
                    'code' => 'missing_required_key',
                    'path' => '$.'.$requiredKey,
                    'message' => 'Missing canonical feature spec key.',
                ];
            }
        }

        if (($featureSpec['schema_version'] ?? null) !== 1) {
            $errors[] = [
                'code' => 'unsupported_schema_version',
                'path' => '$.schema_version',
                'message' => 'Only canonical feature spec v1 is supported.',
                'expected' => 1,
                'actual' => $featureSpec['schema_version'] ?? null,
            ];
        }

        if (! is_array($featureSpec['components'] ?? null) || $featureSpec['components'] === []) {
            $errors[] = [
                'code' => 'invalid_components',
                'path' => '$.components',
                'message' => 'Canonical feature spec must contain at least one component.',
            ];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function registryTypeForComponent(string $featureKey, string $componentKey): string
    {
        return 'feature-'.trim(Str::slug($featureKey, '-').'-'.Str::slug($componentKey, '-'), '-');
    }

    private function nodeScaffoldId(string $featureKey, string $componentKey, int $index): string
    {
        return Str::snake($featureKey).'_'.Str::snake($componentKey).'_'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $componentDefaults
     * @param  array<int, array<string, mixed>>  $componentQueries
     * @param  array<int, string>  $componentActions
     * @param  array<int, mixed>  $componentVariants
     * @param  array<int, array<string, mixed>>  $states
     * @return array<string, mixed>
     */
    private function buildDefaultProps(
        array $componentDefaults,
        array $componentQueries,
        array $componentActions,
        array $componentVariants,
        array $states,
        string $featureKey,
        string $componentKey,
        string $componentRole
    ): array {
        $contentDefaults = $this->filterScalarTree($componentDefaults);

        $dataDefaults = [];
        if (count($componentQueries) === 1 && is_array($componentQueries[0])) {
            $firstQuery = $componentQueries[0];
            $dataDefaults['query'] = $this->pruneNulls([
                'resource' => (string) ($firstQuery['resource'] ?? ''),
                'binding' => is_string($firstQuery['binding'] ?? null) ? (string) $firstQuery['binding'] : null,
                'method' => is_string($firstQuery['method'] ?? null) ? (string) $firstQuery['method'] : null,
            ]);
        } elseif ($componentQueries !== []) {
            $dataDefaults['queries'] = array_values(array_map(
                fn (array $query): array => $this->pruneNulls([
                    'key' => (string) ($query['key'] ?? ''),
                    'resource' => (string) ($query['resource'] ?? ''),
                    'binding' => is_string($query['binding'] ?? null) ? (string) $query['binding'] : null,
                    'method' => is_string($query['method'] ?? null) ? (string) $query['method'] : null,
                ]),
                array_filter($componentQueries, 'is_array')
            ));
        }
        if ($componentActions !== []) {
            $dataDefaults['actions'] = array_values(array_filter(array_map(
                static fn ($value): ?string => is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null,
                $componentActions
            )));
        }

        $styleDefaults = [];
        if ($componentVariants !== []) {
            $firstVariant = is_scalar($componentVariants[0]) ? trim((string) $componentVariants[0]) : '';
            if ($firstVariant !== '') {
                $styleDefaults['variant'] = $firstVariant;
            }
        }

        $advancedDefaults = [
            'attributes' => [
                'data-webu-ai-feature' => $featureKey,
                'data-webu-ai-component' => $componentKey,
                'data-webu-ai-role' => $componentRole,
            ],
        ];

        $responsiveDefaults = [
            'desktop' => [],
            'tablet' => [],
            'mobile' => [],
        ];

        $stateDefaults = [];
        foreach ($states as $state) {
            if (! is_array($state)) {
                continue;
            }
            $stateKey = trim((string) ($state['key'] ?? ''));
            if ($stateKey === '') {
                continue;
            }
            $stateDefaults[$stateKey] = [];
        }

        return [
            'content' => $contentDefaults,
            'data' => $dataDefaults,
            'style' => $styleDefaults,
            'advanced' => $advancedDefaults,
            'responsive' => $responsiveDefaults,
            'states' => $stateDefaults,
        ];
    }

    /**
     * @param  array<int, mixed>  $componentControls
     * @param  array<string, mixed>  $componentDefaults
     * @param  array<string, string>  $componentBindings
     * @param  array<int, array<string, mixed>>  $componentQueries
     * @param  array<int, string>  $componentActions
     * @param  array<string, mixed>  $builderContract
     * @param  array<int, array<string, mixed>>  $states
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function buildControlsConfig(
        string $componentLabel,
        string $componentRole,
        array $componentControls,
        array $componentDefaults,
        array $componentBindings,
        array $componentQueries,
        array $componentActions,
        array $builderContract,
        array $states,
        array $events,
        array &$warnings
    ): array {
        $requestedGroups = array_values(array_unique(array_filter(array_map(
            static fn ($value): ?string => is_scalar($value) ? trim((string) $value) : null,
            is_array($builderContract['control_groups'] ?? null) ? $builderContract['control_groups'] : []
        ))));

        if ($requestedGroups === []) {
            $requestedGroups = ['content', 'data', 'style', 'advanced'];
        }

        $componentControlGroupHints = array_values(array_unique(array_filter(array_map(
            static fn ($value): ?string => is_scalar($value) ? trim((string) $value) : null,
            $componentControls
        ))));
        if ($componentControlGroupHints !== []) {
            foreach ($componentControlGroupHints as $groupHint) {
                if (! in_array($groupHint, $requestedGroups, true)) {
                    $requestedGroups[] = $groupHint;
                }
            }
        }

        $groups = [];
        $flatContentDefaults = $this->flattenScalarTree($componentDefaults);

        foreach ($requestedGroups as $groupId) {
            $normalizedGroupId = strtolower(trim($groupId));
            if ($normalizedGroupId === '') {
                continue;
            }

            $fields = [];

            if ($normalizedGroupId === 'content') {
                foreach ($flatContentDefaults as $path => $value) {
                    $fields[] = [
                        'key' => $path,
                        'label' => Str::headline(str_replace(['.', '_', '-'], ' ', $path)),
                        'group' => 'content',
                        'control' => $this->controlTypeForValue($value),
                        'path' => 'props.content.'.$path,
                        'dynamic_capable' => true,
                    ];
                }
            } elseif ($normalizedGroupId === 'data') {
                foreach ($componentBindings as $bindingKey => $bindingExpr) {
                    $fields[] = [
                        'key' => $bindingKey,
                        'label' => Str::headline(str_replace(['_', '-'], ' ', $bindingKey)),
                        'group' => 'data',
                        'control' => 'binding',
                        'path' => 'bindings.'.$bindingKey,
                        'default' => $bindingExpr,
                        'dynamic_capable' => true,
                    ];
                }
                foreach ($componentQueries as $queryIndex => $query) {
                    if (! is_array($query)) {
                        continue;
                    }
                    $resource = (string) ($query['resource'] ?? '');
                    if ($resource === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => 'query_'.$queryIndex,
                        'label' => Str::headline(str_replace('.', ' ', $resource)),
                        'group' => 'data',
                        'control' => 'query_resource',
                        'path' => (count($componentQueries) === 1 ? 'props.data.query.resource' : 'props.data.queries['.$queryIndex.'].resource'),
                        'default' => $resource,
                        'dynamic_capable' => true,
                    ];
                }
                foreach ($componentActions as $actionIndex => $action) {
                    if (! is_scalar($action) || trim((string) $action) === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => 'action_'.$actionIndex,
                        'label' => Str::headline(str_replace(['.', '_'], ' ', (string) $action)),
                        'group' => 'data',
                        'control' => 'event_action',
                        'path' => 'props.data.actions['.$actionIndex.']',
                        'default' => (string) $action,
                    ];
                }
            } elseif ($normalizedGroupId === 'style') {
                $fields[] = [
                    'key' => 'variant',
                    'label' => 'Variant',
                    'group' => 'style',
                    'control' => 'select',
                    'path' => 'props.style.variant',
                ];
                $fields[] = [
                    'key' => 'spacing',
                    'label' => 'Spacing',
                    'group' => 'style',
                    'control' => 'spacing',
                    'path' => 'props.style.spacing',
                ];
                $fields[] = [
                    'key' => 'typography',
                    'label' => 'Typography',
                    'group' => 'style',
                    'control' => 'typography',
                    'path' => 'props.style.typography',
                ];
            } elseif ($normalizedGroupId === 'advanced') {
                $fields[] = [
                    'key' => 'custom_css',
                    'label' => 'Custom CSS',
                    'group' => 'advanced',
                    'control' => 'code',
                    'path' => 'props.advanced.custom_css',
                ];
                $fields[] = [
                    'key' => 'visibility',
                    'label' => 'Visibility',
                    'group' => 'advanced',
                    'control' => 'visibility',
                    'path' => 'props.advanced.visibility',
                ];
                $fields[] = [
                    'key' => 'attributes',
                    'label' => 'Attributes',
                    'group' => 'advanced',
                    'control' => 'attributes',
                    'path' => 'props.advanced.attributes',
                ];
            } elseif ($normalizedGroupId === 'states') {
                foreach ($states as $state) {
                    if (! is_array($state)) {
                        continue;
                    }
                    $stateKey = trim((string) ($state['key'] ?? ''));
                    if ($stateKey === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => $stateKey,
                        'label' => (string) ($state['label'] ?? Str::headline($stateKey)),
                        'group' => 'states',
                        'control' => 'state_group',
                        'path' => 'props.states.'.$stateKey,
                    ];
                }
            } elseif ($normalizedGroupId === 'interactions') {
                foreach ($events as $event) {
                    if (! is_array($event)) {
                        continue;
                    }
                    $eventKey = trim((string) ($event['key'] ?? ''));
                    if ($eventKey === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => $eventKey,
                        'label' => (string) ($event['label'] ?? Str::headline($eventKey)),
                        'group' => 'interactions',
                        'control' => 'event_handler',
                        'path' => 'props.data.actions',
                    ];
                }
            } else {
                $warnings[] = 'component_factory: unsupported control group ['.$normalizedGroupId.'] mapped with empty fieldset.';
            }

            $groups[] = [
                'id' => $normalizedGroupId,
                'label' => Str::headline(str_replace(['_', '-'], ' ', $normalizedGroupId)),
                'meta' => [
                    'generator' => 'CmsAiComponentFactoryGenerator',
                    'component_role' => $componentRole,
                    'component_label' => $componentLabel,
                ],
                'fields' => $fields,
            ];
        }

        return [
            'version' => 1,
            'groups' => $groups,
        ];
    }

    /**
     * @param  array<string, mixed>  $componentDefaults
     * @param  array<string, string>  $componentBindings
     * @param  array<int, array<string, mixed>>  $componentQueries
     * @param  array<int, string>  $componentActions
     * @param  array<int, array<string, mixed>>  $states
     * @param  array<int, mixed>  $componentVariants
     * @return array<string, mixed>
     */
    private function buildPropsSchema(
        array $componentDefaults,
        array $componentBindings,
        array $componentQueries,
        array $componentActions,
        array $states,
        array $componentVariants
    ): array {
        $contentProperties = $this->schemaPropertiesFromScalarTree($componentDefaults);
        $dataProperties = [];

        if ($componentQueries !== []) {
            if (count($componentQueries) === 1) {
                $dataProperties['query'] = [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string'],
                        'binding' => ['type' => 'string'],
                        'method' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ];
            } else {
                $dataProperties['queries'] = [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                ];
            }
        }
        if ($componentActions !== []) {
            $dataProperties['actions'] = [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ];
        }
        if ($componentBindings !== []) {
            $dataProperties['bindings_available'] = [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'default' => array_keys($componentBindings),
            ];
        }

        $styleProperties = [
            'variant' => ['type' => 'string'],
            'spacing' => ['type' => 'object'],
            'typography' => ['type' => 'object'],
        ];
        if ($componentVariants !== []) {
            $enumVariants = array_values(array_filter(array_map(
                static fn ($v): ?string => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null,
                $componentVariants
            )));
            if ($enumVariants !== []) {
                $styleProperties['variant']['enum'] = $enumVariants;
            }
        }

        $statesProperties = [];
        foreach ($states as $state) {
            if (! is_array($state)) {
                continue;
            }
            $stateKey = trim((string) ($state['key'] ?? ''));
            if ($stateKey === '') {
                continue;
            }
            $statesProperties[$stateKey] = ['type' => 'object'];
        }

        return [
            'type' => 'object',
            'required' => ['content', 'data', 'style', 'advanced', 'responsive', 'states'],
            'properties' => [
                'content' => [
                    'type' => 'object',
                    'properties' => $contentProperties,
                    'additionalProperties' => true,
                ],
                'data' => [
                    'type' => 'object',
                    'properties' => $dataProperties,
                    'additionalProperties' => true,
                ],
                'style' => [
                    'type' => 'object',
                    'properties' => $styleProperties,
                    'additionalProperties' => true,
                ],
                'advanced' => [
                    'type' => 'object',
                    'properties' => [
                        'attributes' => ['type' => 'object'],
                        'visibility' => ['type' => 'object'],
                        'custom_css' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ],
                'responsive' => [
                    'type' => 'object',
                    'properties' => [
                        'desktop' => ['type' => 'object'],
                        'tablet' => ['type' => 'object'],
                        'mobile' => ['type' => 'object'],
                    ],
                    'additionalProperties' => true,
                ],
                'states' => [
                    'type' => 'object',
                    'properties' => $statesProperties,
                    'additionalProperties' => true,
                ],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, string>
     */
    private function flattenScalarTree(array $tree, string $prefix = ''): array
    {
        $flat = [];
        foreach ($tree as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenScalarTree($value, $path));
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $flat[$path] = $value === null ? '' : (string) $value;
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    private function schemaPropertiesFromScalarTree(array $tree): array
    {
        $properties = [];

        foreach ($tree as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $properties[$key] = [
                    'type' => 'object',
                    'properties' => $this->schemaPropertiesFromScalarTree($value),
                    'additionalProperties' => true,
                ];
                continue;
            }

            $properties[$key] = [
                'type' => $this->jsonSchemaTypeForScalar($value),
            ];
        }

        return $properties;
    }

    private function jsonSchemaTypeForScalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            default => 'string',
        };
    }

    private function controlTypeForValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'switch',
            is_int($value), is_float($value) => 'number',
            is_string($value) && str_contains($value, '{{') => 'binding',
            is_string($value) && mb_strlen($value) > 120 => 'textarea',
            default => 'text',
        };
    }

    private function fallbackCategoryForDomain(string $domain): string
    {
        return match ($domain) {
            'ecommerce' => 'ecommerce',
            'booking' => 'booking',
            'blog' => 'content',
            'services', 'software' => 'business',
            default => 'custom-feature',
        };
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array<string, string>
     */
    private function normalizeBindingKeysForArtifacts(array $bindings): array
    {
        $normalized = [];

        foreach ($bindings as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $artifactKey = trim(Str::snake(str_replace(['.', '-', ' '], '_', $key)));
            if ($artifactKey === '') {
                continue;
            }

            $normalized[$artifactKey] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function filterScalarTree(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->filterScalarTree($value);
                if ($nested !== []) {
                    $result[$key] = $nested;
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pruneNulls(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->pruneNulls($value);
            }
        }

        return array_filter($payload, static fn ($value): bool => $value !== null);
    }
}
