<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiFeatureSpecParser
{
    public const VERSION = 1;

    public function __construct(
        protected CmsCanonicalBindingResolver $bindingResolver
    ) {}

    /**
     * Parse a flexible feature spec payload into a canonical v1 feature spec.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function parse(array $input): array
    {
        $errors = [];
        $warnings = [];
        $normalizedAliases = [];

        $featureKeyRaw = $this->firstValue($input, ['feature_key', 'feature', 'key', 'slug'], $normalizedAliases);
        $displayNameRaw = $this->firstValue($input, ['display_name', 'name', 'title'], $normalizedAliases);
        $summaryRaw = $this->firstValue($input, ['summary', 'description', 'intent'], $normalizedAliases);
        $domainRaw = $this->firstValue($input, ['domain', 'module', 'project_type', 'vertical'], $normalizedAliases);

        $featureKey = $this->normalizeKey($featureKeyRaw);
        if ($featureKey === null) {
            $errors[] = $this->error('missing_feature_key', '$.feature_key', 'Feature spec requires feature_key/feature/key/slug.');
        }

        $displayName = is_scalar($displayNameRaw) ? trim((string) $displayNameRaw) : '';
        if ($displayName === '' && $featureKey !== null) {
            $displayName = Str::headline(str_replace('-', ' ', $featureKey));
        }
        if ($displayName === '') {
            $errors[] = $this->error('missing_display_name', '$.display_name', 'Feature spec requires display_name/name/title.');
        }

        $domain = $this->normalizeDomain(is_scalar($domainRaw) ? (string) $domainRaw : null);
        if ($domain === null) {
            $domain = 'universal';
            if ($domainRaw !== null) {
                $warnings[] = $this->warning('unknown_domain', '$.domain', 'Unknown domain/module alias; defaulted to universal.', [
                    'raw' => $domainRaw,
                ]);
            }
        }

        $summary = is_scalar($summaryRaw) ? trim((string) $summaryRaw) : '';

        $entities = $this->parseEntities(
            $this->normalizeList($this->firstValue($input, ['entities', 'models', 'resources'], $normalizedAliases))
        );
        $warnings = array_merge($warnings, $entities['warnings']);

        $components = $this->parseComponents(
            $this->normalizeList($this->firstValue($input, ['components', 'widgets', 'blocks', 'elements'], $normalizedAliases)),
            $domain,
            $warnings
        );
        if ($components === []) {
            $errors[] = $this->error('missing_components', '$.components', 'Feature spec must define at least one component/widget/block.');
        }

        $states = $this->parseSimpleItems(
            $this->normalizeList($this->firstValue($input, ['states', 'ui_states'], $normalizedAliases)),
            defaultLabelPrefix: 'State',
            pathPrefix: '$.states'
        );
        if ($states === []) {
            $states = $this->defaultStates();
            $warnings[] = $this->warning('states_defaulted', '$.states', 'No states provided; applied default ready/loading/empty/error states.');
        }

        $events = $this->parseSimpleItems(
            $this->normalizeList($this->firstValue($input, ['events', 'actions', 'user_events'], $normalizedAliases)),
            defaultLabelPrefix: 'Event',
            pathPrefix: '$.events'
        );

        $endpointsInput = $this->normalizeList($this->firstValue($input, ['api_endpoints', 'endpoints'], $normalizedAliases));
        $apiContract = [
            'endpoints' => $this->parseEndpoints($endpointsInput, $warnings),
        ];

        $acceptanceChecks = $this->parseStringList(
            $this->normalizeList($this->firstValue($input, ['acceptance_checks', 'acceptance_criteria', 'tests'], $normalizedAliases))
        );
        $examples = $this->parseStringList(
            $this->normalizeList($this->firstValue($input, ['examples', 'example_prompts'], $normalizedAliases))
        );

        $metaInput = is_array($input['meta'] ?? null) ? $input['meta'] : [];
        $metaSource = strtolower(trim((string) ($metaInput['source'] ?? 'manual_spec')));
        if (! in_array($metaSource, ['manual_spec', 'ai_prompt', 'template_analysis', 'internal'], true)) {
            $warnings[] = $this->warning('meta_source_defaulted', '$.meta.source', 'Unsupported meta.source; defaulted to manual_spec.', [
                'raw' => $metaInput['source'] ?? null,
            ]);
            $metaSource = 'manual_spec';
        }

        $spec = [
            'schema_version' => 1,
            'feature_key' => $featureKey,
            'display_name' => $displayName,
            'domain' => $domain,
            'summary' => $summary !== '' ? $summary : null,
            'entities' => $entities['entities'],
            'components' => $components,
            'states' => $states,
            'events' => $events,
            'api_contract' => $apiContract,
            'builder_contract' => $this->deriveBuilderContract($domain, $components, $states, $events),
            'acceptance_checks' => $acceptanceChecks,
            'examples' => $examples,
            'meta' => [
                'source' => $metaSource,
                'parser_version' => self::VERSION,
                'normalized_aliases' => array_values(array_unique($normalizedAliases)),
                'warnings_count' => 0, // backfilled after warnings are finalized
            ],
        ];

        $spec = $this->pruneNulls($spec);

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'spec' => null,
                'summary' => [
                    'parser_version' => self::VERSION,
                    'error_count' => count($errors),
                    'warning_count' => count($warnings),
                ],
            ];
        }

        $spec['meta']['warnings_count'] = count($warnings);

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => $warnings,
            'spec' => $spec,
            'summary' => [
                'parser_version' => self::VERSION,
                'feature_key' => $featureKey,
                'domain' => $domain,
                'component_count' => count($components),
                'entity_count' => count($spec['entities'] ?? []),
                'endpoint_count' => count($spec['api_contract']['endpoints'] ?? []),
                'warning_count' => count($warnings),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{entities: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    private function parseEntities(array $items): array
    {
        $entities = [];
        $warnings = [];

        foreach ($items as $index => $item) {
            if (is_string($item) || is_numeric($item)) {
                $key = $this->normalizeKey((string) $item);
                if ($key === null) {
                    continue;
                }
                $entities[$key] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace('-', ' ', $key)),
                    'fields' => [],
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $key = $this->normalizeKey($this->firstValue($item, ['key', 'name', 'slug']));
            if ($key === null) {
                $warnings[] = $this->warning('entity_missing_key', '$.entities['.$index.']', 'Skipped entity without key/name.');
                continue;
            }

            $labelRaw = $this->firstValue($item, ['label', 'title', 'name']);
            $label = is_scalar($labelRaw) ? trim((string) $labelRaw) : Str::headline(str_replace('-', ' ', $key));
            if ($label === '') {
                $label = Str::headline(str_replace('-', ' ', $key));
            }

            $fieldItems = $this->normalizeList($this->firstValue($item, ['fields', 'attributes', 'columns']));
            $fields = [];
            $fieldSeen = [];
            foreach ($fieldItems as $fieldIndex => $field) {
                if (is_string($field) || is_numeric($field)) {
                    $fieldKey = $this->normalizeKey((string) $field);
                    if ($fieldKey === null || isset($fieldSeen[$fieldKey])) {
                        continue;
                    }
                    $fieldSeen[$fieldKey] = true;
                    $fields[] = [
                        'key' => $fieldKey,
                        'label' => Str::headline(str_replace('-', ' ', $fieldKey)),
                        'type' => $this->inferFieldTypeFromKey($fieldKey),
                        'required' => false,
                    ];

                    continue;
                }

                if (! is_array($field)) {
                    continue;
                }

                $fieldKey = $this->normalizeKey($this->firstValue($field, ['key', 'name', 'slug']));
                if ($fieldKey === null || isset($fieldSeen[$fieldKey])) {
                    continue;
                }
                $fieldSeen[$fieldKey] = true;

                $fieldLabelRaw = $this->firstValue($field, ['label', 'title', 'name']);
                $fieldLabel = is_scalar($fieldLabelRaw) ? trim((string) $fieldLabelRaw) : '';
                if ($fieldLabel === '') {
                    $fieldLabel = Str::headline(str_replace('-', ' ', $fieldKey));
                }

                $type = $this->normalizeFieldType($this->firstValue($field, ['type', 'field_type']));
                if ($type === null) {
                    $type = $this->inferFieldTypeFromKey($fieldKey);
                }

                $fields[] = [
                    'key' => $fieldKey,
                    'label' => $fieldLabel,
                    'type' => $type,
                    'required' => (bool) ($field['required'] ?? false),
                    'description' => $this->normalizeNullableString($field['description'] ?? null),
                ];
            }

            $entities[$key] = $this->pruneNulls([
                'key' => $key,
                'label' => $label,
                'description' => $this->normalizeNullableString($item['description'] ?? null),
                'fields' => $fields,
            ]);
        }

        return [
            'entities' => array_values($entities),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function parseComponents(array $items, string $domain, array &$warnings): array
    {
        $components = [];
        $seen = [];

        foreach ($items as $index => $item) {
            if (is_string($item) || is_numeric($item)) {
                $key = $this->normalizeKey((string) $item);
                if ($key === null || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $components[] = $this->defaultComponent($key, $domain);

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $key = $this->normalizeKey($this->firstValue($item, ['key', 'name', 'slug']));
            if ($key === null) {
                $warnings[] = $this->warning('component_missing_key', '$.components['.$index.']', 'Skipped component without key/name.');
                continue;
            }
            if (isset($seen[$key])) {
                $warnings[] = $this->warning('component_duplicate_key', '$.components['.$index.'].key', 'Duplicate component key ignored.', ['key' => $key]);
                continue;
            }
            $seen[$key] = true;

            $labelRaw = $this->firstValue($item, ['label', 'title', 'name']);
            $label = is_scalar($labelRaw) ? trim((string) $labelRaw) : Str::headline(str_replace('-', ' ', $key));
            if ($label === '') {
                $label = Str::headline(str_replace('-', ' ', $key));
            }

            $role = $this->normalizeRole($this->firstValue($item, ['role', 'kind', 'type']));
            $category = $this->normalizeCategory($this->firstValue($item, ['category']), $domain, $role);

            $bindingsInput = $this->firstValue($item, ['bindings', 'data_bindings']);
            $queriesInput = $this->firstValue($item, ['queries', 'data_queries']);
            $actionsInput = $this->firstValue($item, ['actions', 'events']);
            $propsInput = $this->firstValue($item, ['props_contract', 'props']);
            $controlsInput = $this->firstValue($item, ['controls', 'control_hints']);

            $component = [
                'key' => $key,
                'label' => $label,
                'role' => $role,
                'category' => $category,
                'summary' => $this->normalizeNullableString($this->firstValue($item, ['summary', 'description'])),
                'variants' => $this->parseStringList($this->normalizeList($item['variants'] ?? [])),
                'data_contract' => [
                    'bindings' => $this->normalizeBindingsMap(is_array($bindingsInput) ? $bindingsInput : [], $warnings, '$.components['.$index.'].bindings'),
                    'queries' => $this->parseQueries($this->normalizeList($queriesInput)),
                    'actions' => $this->parseStringList($this->normalizeList($actionsInput)),
                ],
                'props_contract' => [
                    'defaults' => $this->filterScalarTree(is_array($propsInput) ? $propsInput : []),
                    'controls' => $this->parseStringList($this->normalizeList($controlsInput)),
                ],
            ];

            $components[] = $this->pruneNulls($component);
        }

        return $components;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function parseQueries(array $items): array
    {
        $queries = [];
        $seen = [];

        foreach ($items as $item) {
            if (is_string($item) || is_numeric($item)) {
                $raw = trim((string) $item);
                if ($raw === '') {
                    continue;
                }
                $key = $this->normalizeKey($raw);
                if ($key === null || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $queries[] = [
                    'key' => $key,
                    'resource' => $raw,
                ];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $resource = $this->normalizeNullableString($this->firstValue($item, ['resource', 'name', 'query']));
            if ($resource === null) {
                continue;
            }

            $key = $this->normalizeKey($this->firstValue($item, ['key', 'name'])) ?? $this->normalizeKey($resource);
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $queries[] = $this->pruneNulls([
                'key' => $key,
                'resource' => $resource,
                'binding' => $this->normalizeBindingExpression($item['binding'] ?? null),
                'method' => $this->normalizeHttpMethod($item['method'] ?? null),
            ]);
        }

        return $queries;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function parseEndpoints(array $items, array &$warnings): array
    {
        $endpoints = [];
        $seen = [];

        foreach ($items as $index => $item) {
            if (is_string($item) || is_numeric($item)) {
                $parsed = $this->parseEndpointString((string) $item);
                if ($parsed === null) {
                    $warnings[] = $this->warning('endpoint_parse_failed', '$.api_contract.endpoints['.$index.']', 'Could not parse endpoint string.', [
                        'raw' => (string) $item,
                    ]);
                    continue;
                }
                $compoundKey = $parsed['method'].' '.$parsed['path'];
                if (isset($seen[$compoundKey])) {
                    continue;
                }
                $seen[$compoundKey] = true;
                $endpoints[] = $parsed;
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $method = $this->normalizeHttpMethod($this->firstValue($item, ['method', 'http_method'])) ?? 'GET';
            $path = $this->normalizeEndpointPath($this->firstValue($item, ['path', 'url', 'endpoint']));
            if ($path === null) {
                continue;
            }

            $key = $this->normalizeKey($this->firstValue($item, ['key', 'name'])) ?? $this->normalizeKey($method.' '.trim($path, '/'));
            if ($key === null) {
                continue;
            }

            $compoundKey = $method.' '.$path;
            if (isset($seen[$compoundKey])) {
                continue;
            }
            $seen[$compoundKey] = true;

            $endpoints[] = $this->pruneNulls([
                'key' => $key,
                'method' => $method,
                'path' => $path,
                'summary' => $this->normalizeNullableString($this->firstValue($item, ['summary', 'description'])),
            ]);
        }

        return $endpoints;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function parseSimpleItems(array $items, string $defaultLabelPrefix, string $pathPrefix): array
    {
        $result = [];
        $seen = [];

        foreach ($items as $index => $item) {
            if (is_string($item) || is_numeric($item)) {
                $key = $this->normalizeKey((string) $item);
                if ($key === null || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace('-', ' ', $key)),
                ];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $key = $this->normalizeKey($this->firstValue($item, ['key', 'name', 'slug']));
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $labelRaw = $this->firstValue($item, ['label', 'title', 'name']);
            $label = is_scalar($labelRaw) ? trim((string) $labelRaw) : '';
            if ($label === '') {
                $label = Str::headline(str_replace('-', ' ', $key));
            }

            $result[] = $this->pruneNulls([
                'key' => $key,
                'label' => $label,
                'description' => $this->normalizeNullableString($item['description'] ?? null),
            ]);
        }

        return array_values($result);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, array<string, mixed>>  $states
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function deriveBuilderContract(string $domain, array $components, array $states, array $events): array
    {
        $registryCategories = [$this->normalizeRegistryCategoryForDomain($domain)];
        $roles = [];
        foreach ($components as $component) {
            $role = (string) ($component['role'] ?? '');
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        $controls = ['content', 'style', 'advanced'];
        if ($events !== []) {
            $controls[] = 'interactions';
        }
        if ($states !== []) {
            $controls[] = 'states';
        }
        $controls[] = 'data';

        $rendererHints = [];
        foreach ($components as $component) {
            $rendererHints[] = [
                'component_key' => (string) ($component['key'] ?? ''),
                'preferred_renderer_kind' => 'adapter',
                'supports_dynamic_bindings' => ! empty(data_get($component, 'data_contract.bindings')),
            ];
        }

        return [
            'target_registry_categories' => array_values(array_unique(array_filter($registryCategories))),
            'control_groups' => array_values(array_unique($controls)),
            'component_roles' => array_values(array_unique(array_filter($roles))),
            'renderer_hints' => $rendererHints,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<string, string>
     */
    private function normalizeBindingsMap(array $bindings, array &$warnings, string $path): array
    {
        $result = [];

        foreach ($bindings as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $normalizedKey = $this->normalizeKey($key);
            if ($normalizedKey === null) {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }

            $resolverNormalized = $this->bindingResolver->normalizeExpression($raw);
            if ($resolverNormalized === null) {
                $warnings[] = $this->warning('binding_expression_invalid', $path.'.'.$normalizedKey, 'Binding expression could not be normalized; raw value preserved.', [
                    'raw' => $raw,
                ]);
                $result[$normalizedKey] = $raw;
                continue;
            }

            $result[$normalizedKey] = $resolverNormalized;
        }

        return $result;
    }

    private function normalizeBindingExpression(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return $this->bindingResolver->normalizeExpression($raw) ?? $raw;
    }

    /**
     * @param  mixed  $value
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values($value);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function parseStringList(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $result[] = $value;
        }

        return array_values(array_unique($result));
    }

    private function defaultComponent(string $key, string $domain): array
    {
        return [
            'key' => $key,
            'label' => Str::headline(str_replace('-', ' ', $key)),
            'role' => $this->inferRoleFromKey($key),
            'category' => $this->normalizeCategory(null, $domain, $this->inferRoleFromKey($key)),
            'data_contract' => [
                'bindings' => [],
                'queries' => [],
                'actions' => [],
            ],
            'props_contract' => [
                'defaults' => [],
                'controls' => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultStates(): array
    {
        return [
            ['key' => 'ready', 'label' => 'Ready'],
            ['key' => 'loading', 'label' => 'Loading'],
            ['key' => 'empty', 'label' => 'Empty'],
            ['key' => 'error', 'label' => 'Error'],
        ];
    }

    private function normalizeDomain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'shop', 'store', 'ecom', 'e-commerce' => 'ecommerce',
            'commerce' => 'ecommerce',
            'services', 'service-business' => 'services',
            'saas' => 'software',
            default => in_array($value, ['ecommerce', 'booking', 'blog', 'services', 'software', 'universal'], true) ? $value : null,
        };
    }

    private function normalizeRegistryCategoryForDomain(string $domain): string
    {
        return match ($domain) {
            'ecommerce' => 'ecommerce',
            'booking' => 'booking',
            'blog' => 'content',
            'services' => 'business',
            'software' => 'business',
            default => 'custom',
        };
    }

    private function normalizeRole(mixed $value): string
    {
        $raw = is_scalar($value) ? strtolower(trim((string) $value)) : '';
        if ($raw === '') {
            return 'panel';
        }

        return match ($raw) {
            'widget', 'panel', 'block' => 'panel',
            'button', 'trigger', 'toggle' => 'trigger',
            'list', 'grid', 'table' => 'list',
            'detail', 'item', 'card', 'row' => 'detail',
            'form' => 'form',
            'page' => 'page',
            'badge', 'chip' => 'indicator',
            default => $raw,
        };
    }

    private function inferRoleFromKey(string $key): string
    {
        $key = strtolower($key);

        return match (true) {
            str_contains($key, 'button'), str_contains($key, 'toggle'), str_contains($key, 'trigger') => 'trigger',
            str_contains($key, 'list'), str_contains($key, 'grid'), str_contains($key, 'table') => 'list',
            str_contains($key, 'detail'), str_contains($key, 'item'), str_contains($key, 'card') => 'detail',
            str_contains($key, 'form') => 'form',
            str_contains($key, 'page') => 'page',
            default => 'panel',
        };
    }

    private function normalizeCategory(mixed $value, string $domain, string $role): string
    {
        $raw = is_scalar($value) ? strtolower(trim((string) $value)) : '';
        if ($raw !== '') {
            return str_replace([' ', '_'], '-', $raw);
        }

        return match ($domain) {
            'ecommerce' => 'ecommerce',
            'booking' => 'booking',
            default => match ($role) {
                'form' => 'forms',
                'trigger' => 'actions',
                default => 'custom-feature',
            },
        };
    }

    private function normalizeFieldType(mixed $value): ?string
    {
        $raw = is_scalar($value) ? strtolower(trim((string) $value)) : '';
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            'int', 'integer' => 'number',
            'float', 'decimal', 'money', 'price' => 'money',
            'bool' => 'boolean',
            'datetime', 'timestamp' => 'date',
            'uuid', 'id' => 'id',
            'jsonb' => 'json',
            default => in_array($raw, ['string', 'number', 'boolean', 'object', 'array', 'id', 'date', 'money', 'url', 'html', 'json'], true) ? $raw : null,
        };
    }

    private function inferFieldTypeFromKey(string $key): string
    {
        return match (true) {
            str_ends_with($key, '-id'), $key === 'id' => 'id',
            str_contains($key, 'price'), str_contains($key, 'amount'), str_contains($key, 'total') => 'money',
            str_contains($key, 'url'), str_contains($key, 'link') => 'url',
            str_contains($key, 'html') => 'html',
            str_contains($key, 'json') => 'json',
            str_contains($key, 'date'), str_contains($key, 'time') => 'date',
            str_starts_with($key, 'is-'), str_starts_with($key, 'has-') => 'boolean',
            default => 'string',
        };
    }

    private function normalizeHttpMethod(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $method = strtoupper(trim((string) $value));

        return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : null;
    }

    private function normalizeEndpointPath(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $path = trim((string) $value);
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return preg_replace('#/+#', '/', $path) ?: $path;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseEndpointString(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(\S+)$/i', $value, $matches) !== 1) {
            return null;
        }

        $method = strtoupper($matches[1]);
        $path = $this->normalizeEndpointPath($matches[2]);
        if ($path === null) {
            return null;
        }

        $key = $this->normalizeKey($method.' '.trim($path, '/'));
        if ($key === null) {
            return null;
        }

        return [
            'key' => $key,
            'method' => $method,
            'path' => $path,
        ];
    }

    private function normalizeKey(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower($raw);
        $normalized = str_replace(['/', '_', ' '], '-', $normalized);
        $normalized = preg_replace('/[^a-z0-9{}.-]+/', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-.');

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $keys
     * @param  array<int, string>|null  $normalizedAliases
     */
    private function firstValue(array $input, array $keys, ?array &$normalizedAliases = null): mixed
    {
        $preferredKey = $keys[0] ?? null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                if (
                    $normalizedAliases !== null
                    && is_string($preferredKey)
                    && $key !== $preferredKey
                ) {
                    $normalizedAliases[] = $key.'->'.$preferredKey;
                }

                return $input[$key];
            }
        }

        $normalizedLookup = [];
        foreach ($input as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $normalizedLookup[$this->normalizeAliasKey($key)] = $key;
        }

        foreach ($keys as $key) {
            $normalized = $this->normalizeAliasKey($key);
            if (isset($normalizedLookup[$normalized])) {
                $original = $normalizedLookup[$normalized];
                if ($normalizedAliases !== null && $original !== $key) {
                    $normalizedAliases[] = $original.'->'.$key;
                }

                return $input[$original];
            }
        }

        return null;
    }

    private function normalizeAliasKey(string $key): string
    {
        return str_replace(['-', '_', ' '], '', strtolower(trim($key)));
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

        return array_filter(
            $payload,
            static fn ($value): bool => $value !== null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function error(string $code, string $path, string $message): array
    {
        return [
            'code' => $code,
            'path' => $path,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function warning(string $code, string $path, string $message, array $meta = []): array
    {
        return array_merge([
            'code' => $code,
            'path' => $path,
            'message' => $message,
        ], $meta);
    }
}
