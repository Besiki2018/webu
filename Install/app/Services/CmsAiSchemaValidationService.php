<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class CmsAiSchemaValidationService
{
    private const INPUT_SCHEMA_RELATIVE_PATH = 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json';

    private const OUTPUT_SCHEMA_RELATIVE_PATH = 'docs/architecture/schemas/cms-ai-generation-output.v1.schema.json';

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    public function validateInputPayload(mixed $payload): array
    {
        $schema = $this->readSchema(self::INPUT_SCHEMA_RELATIVE_PATH);
        $errors = [];

        if (! $schema['ok']) {
            return $this->buildSchemaReadFailureResponse(self::INPUT_SCHEMA_RELATIVE_PATH, $schema['errors']);
        }

        if (! $this->isAssoc($payload)) {
            $this->pushError($errors, 'invalid_type', '$', 'Expected object payload.', 'object', $this->describeType($payload));

            return $this->finalize(self::INPUT_SCHEMA_RELATIVE_PATH, $errors);
        }

        /** @var array<string, mixed> $data */
        $data = $payload;
        $this->assertStrictTopLevelRequiredKeys($data, ['schema_version', 'request', 'platform_context', 'meta'], $errors);

        $this->assertConstInt($data['schema_version'] ?? null, 1, '$.schema_version', $errors);

        $request = $this->expectAssoc($data, 'request', '$.request', $errors);
        if ($request !== null) {
            $this->assertRequiredKeys($request, ['mode', 'prompt', 'locale', 'target'], '$.request', $errors);
            $this->assertEnumString($request['mode'] ?? null, ['generate_site', 'generate_pages', 'generate_theme', 'edit_page', 'edit_site'], '$.request.mode', $errors);
            $this->assertNonEmptyString($request['prompt'] ?? null, '$.request.prompt', $errors);
            $this->assertNonEmptyString($request['locale'] ?? null, '$.request.locale', $errors);
            $this->expectAssoc($request, 'target', '$.request.target', $errors);
        }

        $platformContext = $this->expectAssoc($data, 'platform_context', '$.platform_context', $errors);
        if ($platformContext !== null) {
            $this->assertRequiredKeys(
                $platformContext,
                ['project', 'site', 'template_blueprint', 'site_settings_snapshot', 'section_library', 'module_registry', 'module_entitlements'],
                '$.platform_context',
                $errors
            );

            $this->expectAssoc($platformContext, 'project', '$.platform_context.project', $errors);
            $site = $this->expectAssoc($platformContext, 'site', '$.platform_context.site', $errors);
            if ($site !== null) {
                $this->assertRequiredKeys($site, ['id', 'name', 'status', 'locale', 'theme_settings'], '$.platform_context.site', $errors);
            }

            $templateBlueprint = $this->expectAssoc($platformContext, 'template_blueprint', '$.platform_context.template_blueprint', $errors);
            if ($templateBlueprint !== null) {
                $this->assertRequiredKeys($templateBlueprint, ['template_id', 'template_slug', 'default_pages', 'default_sections'], '$.platform_context.template_blueprint', $errors);
            }

            $siteSettings = $this->expectAssoc($platformContext, 'site_settings_snapshot', '$.platform_context.site_settings_snapshot', $errors);
            if ($siteSettings !== null) {
                $this->assertRequiredKeys($siteSettings, ['site', 'typography', 'global_settings'], '$.platform_context.site_settings_snapshot', $errors);
                $globalSettings = $this->expectAssoc($siteSettings, 'global_settings', '$.platform_context.site_settings_snapshot.global_settings', $errors);
                if ($globalSettings !== null) {
                    $this->assertRequiredKeys(
                        $globalSettings,
                        ['logo_media_id', 'logo_asset_url', 'contact_json', 'social_links_json', 'analytics_ids_json'],
                        '$.platform_context.site_settings_snapshot.global_settings',
                        $errors
                    );
                }
            }

            $this->expectList($platformContext, 'section_library', '$.platform_context.section_library', $errors);
            $this->expectAssoc($platformContext, 'module_registry', '$.platform_context.module_registry', $errors);
            $this->expectAssoc($platformContext, 'module_entitlements', '$.platform_context.module_entitlements', $errors);
        }

        $meta = $this->expectAssoc($data, 'meta', '$.meta', $errors);
        if ($meta !== null) {
            $this->assertRequiredKeys($meta, ['request_id', 'created_at', 'source'], '$.meta', $errors);
            $this->assertNonEmptyString($meta['request_id'] ?? null, '$.meta.request_id', $errors);
            $this->assertEnumString($meta['source'] ?? null, ['builder_chat', 'builder_action', 'internal_tool', 'api'], '$.meta.source', $errors);
        }

        return $this->finalize(self::INPUT_SCHEMA_RELATIVE_PATH, $errors);
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    public function validateOutputPayload(mixed $payload): array
    {
        $schema = $this->readSchema(self::OUTPUT_SCHEMA_RELATIVE_PATH);
        $errors = [];

        if (! $schema['ok']) {
            return $this->buildSchemaReadFailureResponse(self::OUTPUT_SCHEMA_RELATIVE_PATH, $schema['errors']);
        }

        if (! $this->isAssoc($payload)) {
            $this->pushError($errors, 'invalid_type', '$', 'Expected object payload.', 'object', $this->describeType($payload));

            return $this->finalize(self::OUTPUT_SCHEMA_RELATIVE_PATH, $errors);
        }

        /** @var array<string, mixed> $data */
        $data = $payload;
        $this->assertStrictTopLevelRequiredKeys($data, ['schema_version', 'theme', 'pages', 'header', 'footer', 'meta'], $errors);
        $this->assertConstInt($data['schema_version'] ?? null, 1, '$.schema_version', $errors);

        $theme = $this->expectAssoc($data, 'theme', '$.theme', $errors);
        if ($theme !== null) {
            $this->assertRequiredKeys($theme, ['theme_settings_patch'], '$.theme', $errors);
        }

        $pages = $this->expectList($data, 'pages', '$.pages', $errors);
        if ($pages !== null) {
            foreach ($pages as $index => $page) {
                $path = '$.pages['.$index.']';
                if (! $this->isAssoc($page)) {
                    $this->pushError($errors, 'invalid_type', $path, 'Expected page object.', 'object', $this->describeType($page));
                    continue;
                }
                /** @var array<string, mixed> $page */
                $this->assertRequiredKeys($page, ['slug', 'title', 'status', 'builder_nodes'], $path, $errors);
                $this->assertEnumString($page['status'] ?? null, ['draft', 'published'], $path.'.status', $errors);
                if (array_key_exists('builder_nodes', $page) && ! is_array($page['builder_nodes'])) {
                    $this->pushError($errors, 'invalid_type', $path.'.builder_nodes', 'Expected array of canonical page nodes.', 'array', $this->describeType($page['builder_nodes']));
                }
            }
        }

        foreach (['header', 'footer'] as $fixedKey) {
            $fixed = $this->expectAssoc($data, $fixedKey, '$.'.$fixedKey, $errors);
            if ($fixed !== null) {
                $this->assertRequiredKeys($fixed, ['enabled', 'section_type', 'props'], '$.'.$fixedKey, $errors);
            }
        }

        $meta = $this->expectAssoc($data, 'meta', '$.meta', $errors);
        if ($meta !== null) {
            $this->assertRequiredKeys($meta, ['generator', 'created_at', 'contracts', 'validation_expectations'], '$.meta', $errors);

            $contracts = $this->expectAssoc($meta, 'contracts', '$.meta.contracts', $errors);
            if ($contracts !== null) {
                $this->assertRequiredKeys($contracts, ['ai_input_schema', 'canonical_page_node_schema', 'canonical_component_registry_schema'], '$.meta.contracts', $errors);
                $this->assertExactString(
                    $contracts['ai_input_schema'] ?? null,
                    'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    '$.meta.contracts.ai_input_schema',
                    $errors
                );
                $this->assertExactString(
                    $contracts['canonical_page_node_schema'] ?? null,
                    'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    '$.meta.contracts.canonical_page_node_schema',
                    $errors
                );
                $this->assertExactString(
                    $contracts['canonical_component_registry_schema'] ?? null,
                    'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                    '$.meta.contracts.canonical_component_registry_schema',
                    $errors
                );
            }

            $expectations = $this->expectAssoc($meta, 'validation_expectations', '$.meta.validation_expectations', $errors);
            if ($expectations !== null) {
                foreach ([
                    'strict_top_level',
                    'no_parallel_storage',
                    'builder_native_pages',
                    'component_availability_check_required',
                    'binding_validation_required',
                ] as $boolKey) {
                    $this->assertRequiredKeys($expectations, [$boolKey], '$.meta.validation_expectations', $errors);
                    $this->assertConstBool($expectations[$boolKey] ?? null, true, '$.meta.validation_expectations.'.$boolKey, $errors);
                }
            }
        }

        return $this->finalize(self::OUTPUT_SCHEMA_RELATIVE_PATH, $errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateInputJsonString(string $json): array
    {
        return $this->validateJsonString($json, fn (mixed $payload) => $this->validateInputPayload($payload), self::INPUT_SCHEMA_RELATIVE_PATH);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateOutputJsonString(string $json): array
    {
        return $this->validateJsonString($json, fn (mixed $payload) => $this->validateOutputPayload($payload), self::OUTPUT_SCHEMA_RELATIVE_PATH);
    }

    /**
     * @param  callable(mixed): array<string, mixed>  $validator
     * @return array<string, mixed>
     */
    private function validateJsonString(string $json, callable $validator, string $schemaPath): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'schema' => $schemaPath,
                'error_count' => 1,
                'errors' => [[
                    'code' => 'invalid_json',
                    'path' => '$',
                    'message' => json_last_error_msg(),
                    'expected' => 'valid json string',
                    'actual' => 'invalid json',
                ]],
            ];
        }

        return $validator($decoded);
    }

    /**
     * @return array{ok: bool, schema?: array<string, mixed>, errors?: array<int, array<string, mixed>>}
     */
    private function readSchema(string $relativePath): array
    {
        $path = base_path($relativePath);
        $errors = [];

        if (! File::exists($path)) {
            $this->pushError($errors, 'schema_missing', '$', 'Schema file not found.', $relativePath, 'missing');

            return ['ok' => false, 'errors' => $errors];
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->pushError($errors, 'schema_invalid_json', '$', 'Schema file is not valid JSON.', 'json object', 'invalid json');

            return ['ok' => false, 'errors' => $errors];
        }

        return ['ok' => true, 'schema' => $decoded];
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function buildSchemaReadFailureResponse(string $schemaPath, array $errors): array
    {
        return [
            'valid' => false,
            'schema' => $schemaPath,
            'error_count' => count($errors),
            'errors' => array_values($errors),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<string, mixed>
     */
    private function finalize(string $schemaPath, array $errors): array
    {
        return [
            'valid' => $errors === [],
            'schema' => $schemaPath,
            'error_count' => count($errors),
            'errors' => array_values($errors),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $requiredKeys
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertStrictTopLevelRequiredKeys(array $data, array $requiredKeys, array &$errors): void
    {
        $this->assertRequiredKeys($data, $requiredKeys, '$', $errors);

        $extra = array_values(array_diff(array_keys($data), $requiredKeys));
        foreach ($extra as $extraKey) {
            $this->pushError($errors, 'unexpected_key', '$.'.$extraKey, 'Unexpected top-level key.', implode(', ', $requiredKeys), $extraKey);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $requiredKeys
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertRequiredKeys(array $data, array $requiredKeys, string $path, array &$errors): void
    {
        foreach ($requiredKeys as $requiredKey) {
            if (! array_key_exists($requiredKey, $data)) {
                $this->pushError($errors, 'missing_required_key', $path === '$' ? '$.'.$requiredKey : $path.'.'.$requiredKey, 'Missing required key.', $requiredKey, 'missing');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $container
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<string, mixed>|null
     */
    private function expectAssoc(array $container, string $key, string $path, array &$errors): ?array
    {
        $value = $container[$key] ?? null;
        if (! $this->isAssoc($value)) {
            $this->pushError($errors, 'invalid_type', $path, 'Expected object.', 'object', $this->describeType($value));

            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $container
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, mixed>|null
     */
    private function expectList(array $container, string $key, string $path, array &$errors): ?array
    {
        $value = $container[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            $this->pushError($errors, 'invalid_type', $path, 'Expected array.', 'array', $this->describeType($value));

            return null;
        }

        /** @var array<int, mixed> $value */
        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertConstInt(mixed $value, int $expected, string $path, array &$errors): void
    {
        if (! is_int($value) || $value !== $expected) {
            $this->pushError($errors, 'const_mismatch', $path, 'Value must match schema constant.', $expected, $value);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertConstBool(mixed $value, bool $expected, string $path, array &$errors): void
    {
        if (! is_bool($value) || $value !== $expected) {
            $this->pushError($errors, 'const_mismatch', $path, 'Value must match schema constant.', $expected, $value);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertExactString(mixed $value, string $expected, string $path, array &$errors): void
    {
        if (! is_string($value) || $value !== $expected) {
            $this->pushError($errors, 'const_mismatch', $path, 'Value must match expected string.', $expected, $value);
        }
    }

    /**
     * @param  array<int, string>  $allowed
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertEnumString(mixed $value, array $allowed, string $path, array &$errors): void
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            $this->pushError($errors, 'enum_mismatch', $path, 'Value is not in allowed enum.', $allowed, $value);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function assertNonEmptyString(mixed $value, string $path, array &$errors): void
    {
        if (! is_string($value) || trim($value) === '') {
            $this->pushError($errors, 'invalid_string', $path, 'Expected non-empty string.', 'non-empty string', $value);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function pushError(array &$errors, string $code, string $path, string $message, mixed $expected, mixed $actual): void
    {
        $errors[] = [
            'code' => $code,
            'path' => $path,
            'message' => $message,
            'expected' => $expected,
            'actual' => is_scalar($actual) || $actual === null ? $actual : $this->describeType($actual),
        ];
    }

    private function isAssoc(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    private function describeType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        if ($value === null) {
            return 'null';
        }

        return gettype($value);
    }
}
