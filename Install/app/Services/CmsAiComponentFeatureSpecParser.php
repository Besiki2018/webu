<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiComponentFeatureSpecParser
{
    public const PARSER_VERSION = 1;

    public const SPEC_SCHEMA_RELATIVE_PATH = 'docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json';

    public const CANONICAL_COMPONENT_REGISTRY_SCHEMA_RELATIVE_PATH = 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json';

    public const CANONICAL_PAGE_NODE_SCHEMA_RELATIVE_PATH = 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json';

    /**
     * Parse a canonical feature spec JSON string.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function parseJsonString(string $json, array $options = []): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->failure([
                $this->error(
                    code: 'invalid_json',
                    path: '$',
                    message: json_last_error_msg(),
                    expected: 'valid json string',
                    actual: 'invalid json'
                ),
            ]);
        }

        return $this->parse($decoded, $options);
    }

    /**
     * Normalize raw FeatureSpec payload variants into canonical component auto-generator input.
     *
     * Supported input aliases:
     * - ui_intent.primary_component / ui_intent.secondary_components
     * - ui.primary / ui.secondary
     * - permissions.required fallback for context/auth defaults
     *
     * @param  mixed  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function parse(mixed $payload, array $options = []): array
    {
        $warnings = [];
        $errors = [];
        $aliasesApplied = [];

        if (! $this->isAssoc($payload)) {
            return $this->failure([
                $this->error(
                    code: 'invalid_type',
                    path: '$',
                    message: 'Feature spec payload must be an object.',
                    expected: 'object',
                    actual: $this->describeType($payload)
                ),
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $payload;

        $rawFeatureKey = trim((string) ($data['feature_key'] ?? ''));
        if ($rawFeatureKey === '') {
            $errors[] = $this->error(
                code: 'missing_required_key',
                path: '$.feature_key',
                message: 'Missing required feature_key.',
                expected: 'non-empty string',
                actual: 'missing'
            );
        }

        $featureKey = $this->canonicalizeFeatureKey($rawFeatureKey);
        if ($rawFeatureKey !== '' && $featureKey === '') {
            $errors[] = $this->error(
                code: 'invalid_feature_key',
                path: '$.feature_key',
                message: 'feature_key could not be normalized to canonical snake_case.',
                expected: 'letters/numbers/underscore',
                actual: $rawFeatureKey
            );
        }
        if ($rawFeatureKey !== '' && $featureKey !== '' && $rawFeatureKey !== $featureKey) {
            $aliasesApplied[] = 'feature_key.normalized';
            $warnings[] = "Normalized feature_key [{$rawFeatureKey}] -> [{$featureKey}].";
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' && $featureKey !== '') {
            $title = Str::headline($featureKey);
            $warnings[] = "Missing title; defaulted to [{$title}] from feature_key.";
        }
        if ($title === '') {
            $errors[] = $this->error(
                code: 'missing_required_key',
                path: '$.title',
                message: 'Missing title (or feature_key to derive one).',
                expected: 'non-empty string',
                actual: 'missing'
            );
        }

        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            $category = 'Basic';
            $warnings[] = 'Missing category; defaulted to [Basic].';
        }

        $description = array_key_exists('description', $data)
            ? $this->normalizeNullableString($data['description'])
            : null;

        $permissions = $this->normalizePermissions($data, $errors, $warnings, $aliasesApplied);
        $context = $this->normalizeContext($data, $permissions, $warnings, $aliasesApplied);

        $entities = $this->normalizeEntities($data['entities'] ?? [], $errors, $warnings);
        $endpoints = $this->normalizeEndpoints($data['endpoints'] ?? null, $context, $permissions, $errors, $warnings, $aliasesApplied);
        $events = $this->normalizeEvents($data['events'] ?? [], $errors, $warnings);

        $uiIntent = $this->normalizeUiIntent($data, $featureKey, $category, $errors, $warnings, $aliasesApplied, $options);
        $generatorHints = $this->buildGeneratorHints($featureKey, $category, $uiIntent['component_set'], $options);

        if ($errors !== []) {
            return $this->failure($errors, $warnings);
        }

        $featureSpec = [
            'schema_version' => 1,
            'feature_key' => $featureKey,
            'title' => $title,
            'category' => $this->normalizeCategoryLabel($category),
            'description' => $description,
            'context' => $context,
            'permissions' => $permissions,
            'entities' => $entities,
            'endpoints' => $endpoints,
            'events' => $events,
            'ui_intent' => [
                'primary_component' => $uiIntent['primary_component'],
                'secondary_components' => $uiIntent['secondary_components'],
                'component_set' => $uiIntent['component_set'],
            ],
            'generator_hints' => $generatorHints,
            'meta' => [
                'parser' => class_basename(self::class),
                'parser_version' => self::PARSER_VERSION,
                'source_variant' => $uiIntent['source_variant'],
                'aliases_applied' => array_values(array_unique($aliasesApplied)),
                'contracts' => [
                    'feature_spec_schema' => self::SPEC_SCHEMA_RELATIVE_PATH,
                    'canonical_component_registry_schema' => self::CANONICAL_COMPONENT_REGISTRY_SCHEMA_RELATIVE_PATH,
                    'canonical_page_node_schema' => self::CANONICAL_PAGE_NODE_SCHEMA_RELATIVE_PATH,
                ],
            ],
        ];

        return [
            'ok' => true,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => [],
            'feature_spec' => $featureSpec,
            'diagnostics' => [
                'endpoint_count' => count($endpoints),
                'entity_count' => count($entities),
                'event_count' => count($events),
                'component_count' => count($uiIntent['component_set']),
                'component_types' => array_values(array_map(
                    fn (array $component): string => (string) ($component['type'] ?? ''),
                    $uiIntent['component_set']
                )),
            ],
            'meta' => [
                'parser' => class_basename(self::class),
                'schema' => self::SPEC_SCHEMA_RELATIVE_PATH,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $aliasesApplied
     * @return array<string, string>
     */
    private function normalizePermissions(array $data, array &$errors, array &$warnings, array &$aliasesApplied): array
    {
        $required = null;

        if ($this->isAssoc($data['permissions'] ?? null)) {
            /** @var array<string, mixed> $permissions */
            $permissions = $data['permissions'];
            $candidate = $this->normalizeAccessScope($permissions['required'] ?? null);
            if ($candidate !== null) {
                $required = $candidate;
            } elseif (array_key_exists('required', $permissions)) {
                $errors[] = $this->error(
                    code: 'invalid_enum',
                    path: '$.permissions.required',
                    message: 'Unsupported permissions.required value.',
                    expected: 'public|customer|admin',
                    actual: $this->describeScalar($permissions['required'])
                );
            }
        } elseif (array_key_exists('permissions', $data)) {
            $errors[] = $this->error(
                code: 'invalid_type',
                path: '$.permissions',
                message: 'permissions must be an object.',
                expected: 'object',
                actual: $this->describeType($data['permissions'])
            );
        }

        if ($required === null) {
            $fromContext = $this->normalizeAccessScope($data['context'] ?? null);
            if ($fromContext !== null) {
                $required = $fromContext;
                if (! $this->isAssoc($data['permissions'] ?? null)) {
                    $aliasesApplied[] = 'context->permissions.required';
                }
            }
        }

        if ($required === null) {
            $required = 'public';
            $warnings[] = 'Missing permissions/context; defaulted required access to [public].';
        }

        return ['required' => $required];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $permissions
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $aliasesApplied
     */
    private function normalizeContext(array $data, array $permissions, array &$warnings, array &$aliasesApplied): string
    {
        $context = $this->normalizeAccessScope($data['context'] ?? null);
        if ($context !== null) {
            return $context;
        }

        $aliasesApplied[] = 'permissions.required->context';
        $warnings[] = "Missing/invalid context; derived from permissions.required [{$permissions['required']}].";

        return $permissions['required'];
    }

    /**
     * @param  mixed  $rawEntities
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEntities(mixed $rawEntities, array &$errors, array &$warnings): array
    {
        if ($rawEntities === null) {
            return [];
        }

        if (! is_array($rawEntities) || ! array_is_list($rawEntities)) {
            $errors[] = $this->error(
                code: 'invalid_type',
                path: '$.entities',
                message: 'entities must be a list.',
                expected: 'array(list)',
                actual: $this->describeType($rawEntities)
            );

            return [];
        }

        $entities = [];
        $seen = [];
        foreach ($rawEntities as $index => $rawEntity) {
            $path = '$.entities['.$index.']';
            if (! $this->isAssoc($rawEntity)) {
                $errors[] = $this->error(
                    code: 'invalid_type',
                    path: $path,
                    message: 'Entity entry must be an object.',
                    expected: 'object',
                    actual: $this->describeType($rawEntity)
                );
                continue;
            }

            /** @var array<string, mixed> $rawEntity */
            $name = trim((string) ($rawEntity['name'] ?? ''));
            if ($name === '') {
                $errors[] = $this->error(
                    code: 'missing_required_key',
                    path: $path.'.name',
                    message: 'Entity name is required.',
                    expected: 'non-empty string',
                    actual: 'missing'
                );
                continue;
            }

            $entityKey = $this->canonicalizeKey($name);
            if ($entityKey === '') {
                $entityKey = 'entity_'.($index + 1);
            }

            if (isset($seen[$entityKey])) {
                $warnings[] = "Duplicate entity key [{$entityKey}] deduplicated.";
                $suffix = 2;
                while (isset($seen[$entityKey.'_'.$suffix])) {
                    $suffix++;
                }
                $entityKey .= '_'.$suffix;
            }
            $seen[$entityKey] = true;

            $fields = $rawEntity['fields'] ?? [];
            if (! $this->isAssoc($fields)) {
                $errors[] = $this->error(
                    code: 'invalid_type',
                    path: $path.'.fields',
                    message: 'Entity fields must be an object map.',
                    expected: 'object',
                    actual: $this->describeType($fields)
                );
                continue;
            }

            /** @var array<string, mixed> $fields */
            $normalizedFields = [];
            foreach ($fields as $fieldName => $fieldType) {
                $canonicalFieldName = $this->canonicalizeKey((string) $fieldName);
                if ($canonicalFieldName === '') {
                    $warnings[] = "Skipped invalid entity field name on entity [{$name}].";
                    continue;
                }

                $normalizedFields[$canonicalFieldName] = $this->normalizeFieldShape($fieldType);
            }

            $entities[] = [
                'name' => $name,
                'key' => $entityKey,
                'fields' => $normalizedFields,
            ];
        }

        return $entities;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeFieldShape(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value) !== '' ? trim($value) : 'string';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_values(array_map(fn (mixed $item) => $this->normalizeFieldShape($item), $value));
            }

            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalizeFieldShape($item);
            }

            return $normalized;
        }

        return get_debug_type($value);
    }

    /**
     * @param  mixed  $rawEndpoints
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $aliasesApplied
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEndpoints(
        mixed $rawEndpoints,
        string $context,
        array $permissions,
        array &$errors,
        array &$warnings,
        array &$aliasesApplied
    ): array {
        if (! is_array($rawEndpoints) || ! array_is_list($rawEndpoints)) {
            $errors[] = $this->error(
                code: 'invalid_type',
                path: '$.endpoints',
                message: 'endpoints must be a list.',
                expected: 'array(list)',
                actual: $this->describeType($rawEndpoints)
            );

            return [];
        }

        if ($rawEndpoints === []) {
            $errors[] = $this->error(
                code: 'min_items',
                path: '$.endpoints',
                message: 'At least one endpoint is required.',
                expected: '>= 1',
                actual: '0'
            );

            return [];
        }

        $normalized = [];
        $seenOperationKeys = [];

        foreach ($rawEndpoints as $index => $rawEndpoint) {
            $pathBase = '$.endpoints['.$index.']';
            if (! $this->isAssoc($rawEndpoint)) {
                $errors[] = $this->error(
                    code: 'invalid_type',
                    path: $pathBase,
                    message: 'Endpoint entry must be an object.',
                    expected: 'object',
                    actual: $this->describeType($rawEndpoint)
                );
                continue;
            }

            /** @var array<string, mixed> $rawEndpoint */
            $methodRaw = trim((string) ($rawEndpoint['method'] ?? ''));
            $method = strtoupper($methodRaw);
            if ($method === '') {
                $errors[] = $this->error(
                    code: 'missing_required_key',
                    path: $pathBase.'.method',
                    message: 'Endpoint method is required.',
                    expected: 'HTTP method',
                    actual: 'missing'
                );
                continue;
            }
            if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $errors[] = $this->error(
                    code: 'invalid_enum',
                    path: $pathBase.'.method',
                    message: 'Unsupported endpoint method.',
                    expected: 'GET|POST|PUT|PATCH|DELETE',
                    actual: $methodRaw
                );
                continue;
            }

            $rawPath = trim((string) ($rawEndpoint['path'] ?? ''));
            if ($rawPath === '') {
                $errors[] = $this->error(
                    code: 'missing_required_key',
                    path: $pathBase.'.path',
                    message: 'Endpoint path is required.',
                    expected: '/path',
                    actual: 'missing'
                );
                continue;
            }

            $endpointPath = $rawPath;
            if (! str_starts_with($endpointPath, '/')) {
                $endpointPath = '/'.$endpointPath;
                $warnings[] = "Endpoint [{$method}] path normalized with leading slash: [{$rawPath}] -> [{$endpointPath}].";
                $aliasesApplied[] = 'endpoints.path.leading_slash';
            }

            $auth = $this->normalizeAccessScope($rawEndpoint['auth'] ?? null);
            if ($auth === null) {
                $auth = $permissions['required'] ?? $context;
                $warnings[] = "Endpoint [{$method} {$endpointPath}] missing/invalid auth; defaulted to [{$auth}].";
                $aliasesApplied[] = 'endpoint.auth.defaulted';
            }

            $name = trim((string) ($rawEndpoint['name'] ?? ''));
            if ($name === '') {
                $name = $this->deriveEndpointName($method, $endpointPath);
                $warnings[] = "Endpoint [{$method} {$endpointPath}] missing name; derived [{$name}].";
                $aliasesApplied[] = 'endpoints.name.derived';
            }

            $operationKey = $this->canonicalizeKey($name);
            if ($operationKey === '') {
                $operationKey = strtolower($method).'_endpoint_'.($index + 1);
                $warnings[] = "Endpoint [{$method} {$endpointPath}] had non-canonical name; fallback operation_key [{$operationKey}] used.";
            }
            if (isset($seenOperationKeys[$operationKey])) {
                $suffix = 2;
                while (isset($seenOperationKeys[$operationKey.'_'.$suffix])) {
                    $suffix++;
                }
                $warnings[] = "Duplicate endpoint operation_key [{$operationKey}] deduplicated.";
                $operationKey .= '_'.$suffix;
            }
            $seenOperationKeys[$operationKey] = true;

            $query = $this->normalizeEndpointOptionalPayload(
                $rawEndpoint,
                key: 'query',
                default: [],
                path: $pathBase.'.query',
                errors: $errors
            );
            $body = $this->normalizeEndpointOptionalPayload(
                $rawEndpoint,
                key: 'body',
                default: null,
                path: $pathBase.'.body',
                errors: $errors
            );
            $responseShape = $this->normalizeEndpointOptionalPayload(
                $rawEndpoint,
                key: 'response_shape',
                default: null,
                path: $pathBase.'.response_shape',
                errors: $errors
            );

            $normalized[] = [
                'name' => $name,
                'operation_key' => $operationKey,
                'method' => $method,
                'path' => $endpointPath,
                'auth' => $auth,
                'query' => $query,
                'body' => $body,
                'response_shape' => $responseShape,
                'route_params' => $this->extractRouteParams($endpointPath),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function normalizeEndpointOptionalPayload(
        array $endpoint,
        string $key,
        mixed $default,
        string $path,
        array &$errors
    ): mixed {
        if (! array_key_exists($key, $endpoint)) {
            return $default;
        }

        $value = $endpoint[$key];
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            $errors[] = $this->error(
                code: 'invalid_type',
                path: $path,
                message: "{$key} must be object, list, or null.",
                expected: 'array|object|null',
                actual: $this->describeType($value)
            );

            return $default;
        }

        return $value;
    }

    /**
     * @param  mixed  $rawEvents
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEvents(mixed $rawEvents, array &$errors, array &$warnings): array
    {
        if ($rawEvents === null) {
            return [];
        }

        if (! is_array($rawEvents) || ! array_is_list($rawEvents)) {
            $errors[] = $this->error(
                code: 'invalid_type',
                path: '$.events',
                message: 'events must be a list.',
                expected: 'array(list)',
                actual: $this->describeType($rawEvents)
            );

            return [];
        }

        $events = [];
        foreach ($rawEvents as $index => $rawEvent) {
            $path = '$.events['.$index.']';
            if (! $this->isAssoc($rawEvent)) {
                $errors[] = $this->error(
                    code: 'invalid_type',
                    path: $path,
                    message: 'Event entry must be an object.',
                    expected: 'object',
                    actual: $this->describeType($rawEvent)
                );
                continue;
            }

            /** @var array<string, mixed> $rawEvent */
            $name = trim((string) ($rawEvent['name'] ?? ''));
            if ($name === '') {
                $warnings[] = 'Skipped event with missing name.';
                continue;
            }

            $payload = $rawEvent['payload'] ?? [];
            if (! $this->isAssoc($payload)) {
                $warnings[] = "Event [{$name}] payload normalized to empty object.";
                $payload = [];
            }

            /** @var array<string, mixed> $payload */
            $events[] = [
                'name' => $name,
                'payload' => $payload,
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $aliasesApplied
     * @param  array<string, mixed>  $options
     * @return array{
     *   primary_component: string,
     *   secondary_components: array<int, string>,
     *   component_set: array<int, array<string, mixed>>,
     *   source_variant: string
     * }
     */
    private function normalizeUiIntent(
        array $data,
        string $featureKey,
        string $category,
        array &$errors,
        array &$warnings,
        array &$aliasesApplied,
        array $options
    ): array {
        $uiIntentRaw = $this->isAssoc($data['ui_intent'] ?? null) ? $data['ui_intent'] : null;
        $uiRaw = $this->isAssoc($data['ui'] ?? null) ? $data['ui'] : null;

        $sourceVariant = match (true) {
            $uiIntentRaw !== null && $uiRaw !== null => 'mixed',
            $uiIntentRaw !== null => 'ui_intent',
            $uiRaw !== null => 'ui',
            default => 'missing',
        };

        $primaryRaw = '';
        $secondaryRaw = [];

        if ($uiIntentRaw !== null) {
            /** @var array<string, mixed> $uiIntentRaw */
            $primaryRaw = trim((string) ($uiIntentRaw['primary_component'] ?? ''));
            $secondaryRaw = is_array($uiIntentRaw['secondary_components'] ?? null)
                ? array_values($uiIntentRaw['secondary_components'])
                : [];
        }

        if ($primaryRaw === '' && $uiRaw !== null) {
            /** @var array<string, mixed> $uiRaw */
            $primaryRaw = trim((string) ($uiRaw['primary'] ?? ''));
            $aliasesApplied[] = 'ui.primary->ui_intent.primary_component';
        }

        if ($secondaryRaw === [] && $uiRaw !== null && is_array($uiRaw['secondary'] ?? null)) {
            /** @var array<string, mixed> $uiRaw */
            $secondaryRaw = array_values((array) $uiRaw['secondary']);
            $aliasesApplied[] = 'ui.secondary->ui_intent.secondary_components';
        }

        if ($primaryRaw === '') {
            $errors[] = $this->error(
                code: 'missing_required_key',
                path: '$.ui_intent.primary_component',
                message: 'Primary component intent is required (ui_intent.primary_component or ui.primary).',
                expected: 'non-empty string',
                actual: 'missing'
            );

            return [
                'primary_component' => '',
                'secondary_components' => [],
                'component_set' => [],
                'source_variant' => $sourceVariant,
            ];
        }

        $primary = $this->canonicalizeComponentKey($primaryRaw, $featureKey);
        if ($primary === '') {
            $errors[] = $this->error(
                code: 'invalid_ui_component',
                path: '$.ui_intent.primary_component',
                message: 'Primary component could not be normalized.',
                expected: 'component role string',
                actual: $primaryRaw
            );
        }

        $secondary = [];
        foreach ($secondaryRaw as $index => $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                $warnings[] = 'Skipped non-string secondary component intent entry.';
                continue;
            }

            $normalized = $this->canonicalizeComponentKey((string) $item, $featureKey);
            if ($normalized === '') {
                $warnings[] = "Skipped invalid secondary component intent [".(string) $item.'].';
                continue;
            }
            $secondary[] = $normalized;
        }

        $secondary = array_values(array_unique(array_filter($secondary, fn (string $value): bool => $value !== '' && $value !== $primary)));

        $namespace = $this->resolveNamespaceForCategory($category, $options);
        $componentSet = [];
        $componentSet[] = $this->buildComponentDescriptor('primary', $primary, $namespace, $featureKey, $category, $primaryRaw);
        foreach ($secondary as $rawKey) {
            $componentSet[] = $this->buildComponentDescriptor('secondary', $rawKey, $namespace, $featureKey, $category, $rawKey);
        }

        return [
            'primary_component' => $primary,
            'secondary_components' => $secondary,
            'component_set' => $componentSet,
            'source_variant' => $sourceVariant,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $componentSet
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildGeneratorHints(string $featureKey, string $category, array $componentSet, array $options): array
    {
        $namespace = $this->resolveNamespaceForCategory($category, $options);

        return [
            'namespace' => $namespace,
            'builder_sidebar_category' => $this->mapBuilderSidebarCategory($category),
            'component_types' => array_values(array_map(
                fn (array $component): string => (string) ($component['type'] ?? ''),
                $componentSet
            )),
            'feature_dir' => $featureKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComponentDescriptor(
        string $role,
        string $componentKey,
        string $namespace,
        string $featureKey,
        string $category,
        string $rawName
    ): array {
        $typeSegment = str_replace('_', '-', $componentKey);

        return [
            'role' => $role,
            'component_key' => $componentKey,
            'type' => "{$namespace}.{$featureKey}.{$typeSegment}",
            'raw_name' => $rawName,
            'category' => $this->mapBuilderSidebarCategory($category),
        ];
    }

    private function resolveNamespaceForCategory(string $category, array $options): string
    {
        $override = trim((string) ($options['component_namespace'] ?? ''));
        if ($override !== '') {
            return $this->canonicalizeDotSegment($override) ?: 'ecom';
        }

        $normalized = strtolower(trim($category));

        return match ($normalized) {
            'e-commerce', 'ecommerce', 'account' => 'ecom',
            'forms', 'form' => 'forms',
            'media' => 'media',
            'layout' => 'layout',
            'basic' => 'basic',
            default => 'ext',
        };
    }

    private function mapBuilderSidebarCategory(string $category): string
    {
        $normalized = strtolower(trim($category));

        return match ($normalized) {
            'e-commerce', 'ecommerce' => 'E-commerce',
            'account' => 'Account',
            'forms', 'form' => 'Forms',
            'media' => 'Media',
            'layout' => 'Layout',
            'basic' => 'Basic',
            default => Str::headline($category !== '' ? $category : 'Basic'),
        };
    }

    private function normalizeCategoryLabel(string $category): string
    {
        return $this->mapBuilderSidebarCategory($category);
    }

    private function canonicalizeFeatureKey(string $value): string
    {
        return $this->canonicalizeKey($value);
    }

    private function canonicalizeComponentKey(string $value, string $featureKey): string
    {
        $candidate = $this->canonicalizeKey($value);
        if ($candidate === '') {
            return '';
        }

        if ($featureKey !== '') {
            $featurePrefix = $featureKey.'_';
            if ($candidate === $featureKey) {
                return 'main';
            }
            if (str_starts_with($candidate, $featurePrefix)) {
                $candidate = substr($candidate, strlen($featurePrefix)) ?: $candidate;
            }
        }

        return $candidate;
    }

    private function canonicalizeDotSegment(string $value): string
    {
        $segment = strtolower(trim($value));
        $segment = preg_replace('/[^a-z0-9_.-]+/', '-', $segment) ?? '';
        $segment = trim($segment, '.-');
        $segment = preg_replace('/-{2,}/', '-', $segment) ?? '';

        return $segment;
    }

    private function canonicalizeKey(string $value): string
    {
        $normalized = Str::snake(trim($value));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($normalized)) ?? '';
        $normalized = preg_replace('/_{2,}/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private function deriveEndpointName(string $method, string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return Str::studly(strtolower($method)).'Root';
        }

        $parts = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $segment, $matches) === 1) {
                $parts[] = 'By'.Str::studly($matches[1]);
                continue;
            }

            $parts[] = Str::studly(str_replace(['-', '.'], ' ', $segment));
        }

        return Str::studly(strtolower($method)).implode('', $parts);
    }

    /**
     * @return array<int, string>
     */
    private function extractRouteParams(string $path): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);

        return array_values(array_unique(array_map(
            fn (string $value): string => $this->canonicalizeKey($value),
            $matches[1] ?? []
        )));
    }

    private function normalizeAccessScope(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, ['public', 'customer', 'admin'], true) ? $normalized : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            $normalized = trim((string) $value);

            return $normalized === '' ? null : $normalized;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(array $errors, array $warnings = []): array
    {
        return [
            'ok' => false,
            'code' => 'invalid_feature_spec',
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values($errors),
            'feature_spec' => null,
            'meta' => [
                'parser' => class_basename(self::class),
                'schema' => self::SPEC_SCHEMA_RELATIVE_PATH,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(string $code, string $path, string $message, string $expected, string $actual): array
    {
        return [
            'code' => $code,
            'path' => $path,
            'message' => $message,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    private function isAssoc(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    private function describeType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'array(list)' : 'object';
        }

        return get_debug_type($value);
    }

    private function describeScalar(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) json_encode($value);
        }

        return $this->describeType($value);
    }
}

