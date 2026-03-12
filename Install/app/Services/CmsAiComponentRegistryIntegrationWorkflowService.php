<?php

namespace App\Services;

class CmsAiComponentRegistryIntegrationWorkflowService
{
    public const VERSION = 1;

    public function __construct(
        protected CmsAiComponentFactoryGenerator $componentFactoryGenerator,
        protected CmsAiRendererTemplateGenerationService $rendererTemplateGenerationService,
        protected CmsAiGeneratedComponentSecurityValidationService $generatedComponentSecurityValidationService
    ) {}

    /**
     * @param  array<string, mixed>  $rawFeatureSpec
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function prepareActivationFromRawFeatureSpec(array $rawFeatureSpec, array $options = []): array
    {
        $factoryResult = $this->componentFactoryGenerator->generateFromRawSpec($rawFeatureSpec, $options);

        if (! ($factoryResult['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'component_factory_generation_failed',
                'errors' => is_array($factoryResult['errors'] ?? null) ? $factoryResult['errors'] : [],
                'warnings' => is_array($factoryResult['warnings'] ?? null) ? $factoryResult['warnings'] : [],
                'activation_plan' => null,
                'summary' => [
                    'workflow_version' => self::VERSION,
                    'component_count' => 0,
                    'ready_component_count' => 0,
                    'blocked_component_count' => 0,
                ],
            ];
        }

        return $this->prepareActivationFromGeneratedArtifacts($factoryResult, null, $options);
    }

    /**
     * @param  array<string, mixed>  $factoryResult
     * @param  array<string, mixed>|null  $rendererTemplateResult
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function prepareActivationFromGeneratedArtifacts(
        array $factoryResult,
        ?array $rendererTemplateResult = null,
        array $options = []
    ): array {
        $factoryGenerated = is_array($factoryResult['generated'] ?? null) ? $factoryResult['generated'] : null;
        if (! ($factoryResult['ok'] ?? false) || ! is_array($factoryGenerated)) {
            return [
                'ok' => false,
                'code' => 'invalid_component_factory_result',
                'errors' => [[
                    'code' => 'missing_generated_payload',
                    'path' => '$.generated',
                    'message' => 'Component factory result must be successful before registry integration.',
                ]],
                'warnings' => [],
                'activation_plan' => null,
                'summary' => [
                    'workflow_version' => self::VERSION,
                    'component_count' => 0,
                    'ready_component_count' => 0,
                    'blocked_component_count' => 0,
                ],
            ];
        }

        $rendererTemplateResult ??= $this->rendererTemplateGenerationService->generateFromComponentFactoryResult($factoryResult, $options);
        if (! ($rendererTemplateResult['ok'] ?? false) || ! is_array($rendererTemplateResult['generated'] ?? null)) {
            return [
                'ok' => false,
                'code' => 'renderer_template_generation_failed',
                'errors' => is_array($rendererTemplateResult['errors'] ?? null) ? $rendererTemplateResult['errors'] : [],
                'warnings' => is_array($rendererTemplateResult['warnings'] ?? null) ? $rendererTemplateResult['warnings'] : [],
                'activation_plan' => null,
                'summary' => [
                    'workflow_version' => self::VERSION,
                    'component_count' => count(is_array($factoryGenerated['components'] ?? null) ? $factoryGenerated['components'] : []),
                    'ready_component_count' => 0,
                    'blocked_component_count' => count(is_array($factoryGenerated['components'] ?? null) ? $factoryGenerated['components'] : []),
                ],
            ];
        }

        $rendererTemplates = is_array(data_get($rendererTemplateResult, 'generated.templates'))
            ? array_values(array_filter(data_get($rendererTemplateResult, 'generated.templates'), 'is_array'))
            : [];
        $rendererTemplatesByRegistryType = [];
        foreach ($rendererTemplates as $template) {
            $registryType = (string) ($template['registry_type'] ?? '');
            if ($registryType === '' || isset($rendererTemplatesByRegistryType[$registryType])) {
                continue;
            }
            $rendererTemplatesByRegistryType[$registryType] = $template;
        }

        $components = is_array($factoryGenerated['components'] ?? null)
            ? array_values(array_filter($factoryGenerated['components'], 'is_array'))
            : [];

        $bundles = [];
        $errors = [];
        $warnings = array_values(array_unique(array_merge(
            is_array($factoryResult['warnings'] ?? null) ? $factoryResult['warnings'] : [],
            is_array($rendererTemplateResult['warnings'] ?? null) ? $rendererTemplateResult['warnings'] : []
        )));
        $readyCount = 0;
        $blockedCount = 0;

        foreach ($components as $index => $componentArtifact) {
            $registryType = (string) ($componentArtifact['registry_type'] ?? '');
            $bundle = $this->validateComponentActivationBundle(
                componentArtifact: $componentArtifact,
                rendererTemplate: $rendererTemplatesByRegistryType[$registryType] ?? null,
                componentIndex: $index
            );

            $bundles[] = $bundle['bundle'];

            if ($bundle['valid']) {
                $readyCount++;
            } else {
                $blockedCount++;
                $errors = array_merge($errors, $bundle['errors']);
            }

            if ($bundle['warnings'] !== []) {
                $warnings = array_values(array_unique(array_merge($warnings, $bundle['warnings'])));
            }
        }

        return [
            'ok' => $errors === [],
            'code' => $errors === [] ? null : 'generated_component_validation_failed',
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'activation_plan' => [
                'schema_version' => 1,
                'feature_key' => (string) ($factoryGenerated['feature_key'] ?? ''),
                'domain' => (string) ($factoryGenerated['domain'] ?? 'universal'),
                'components' => $bundles,
                'meta' => [
                    'workflow_version' => self::VERSION,
                    'factory_generator_version' => (int) data_get($factoryGenerated, 'meta.generator_version', 1),
                    'renderer_template_generator_version' => (int) data_get($rendererTemplateResult, 'generated.meta.generator_version', 1),
                    'activation_mode' => 'preflight_only',
                ],
            ],
            'summary' => [
                'workflow_version' => self::VERSION,
                'component_count' => count($components),
                'ready_component_count' => $readyCount,
                'blocked_component_count' => $blockedCount,
                'error_count' => count($errors),
                'warning_count' => count($warnings),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $componentArtifact
     * @param  array<string, mixed>|null  $rendererTemplate
     * @return array{
     *   valid: bool,
     *   errors: array<int, array<string, mixed>>,
     *   warnings: array<int, string>,
     *   bundle: array<string, mixed>
     * }
     */
    private function validateComponentActivationBundle(array $componentArtifact, ?array $rendererTemplate, int $componentIndex): array
    {
        $errors = [];
        $warnings = [];

        $registryType = (string) ($componentArtifact['registry_type'] ?? '');
        $registryEntry = is_array($componentArtifact['registry_entry'] ?? null) ? $componentArtifact['registry_entry'] : [];
        $nodeScaffold = is_array($componentArtifact['node_scaffold'] ?? null) ? $componentArtifact['node_scaffold'] : [];
        $rendererScaffold = is_array($componentArtifact['renderer_scaffold'] ?? null) ? $componentArtifact['renderer_scaffold'] : [];

        foreach ([
            '$.registry_type' => $registryType,
            '$.registry_entry.type' => data_get($registryEntry, 'type'),
            '$.node_scaffold.type' => data_get($nodeScaffold, 'type'),
            '$.renderer_scaffold.registry_type' => data_get($rendererScaffold, 'registry_type'),
        ] as $path => $value) {
            if (! is_string($value) || trim($value) === '') {
                $errors[] = [
                    'code' => 'missing_registry_type_reference',
                    'path' => $path,
                    'message' => 'Generated component artifact is missing required registry type linkage.',
                    'component_index' => $componentIndex,
                ];
            }
        }

        if ($registryType !== '' && (string) data_get($registryEntry, 'type', '') !== '' && (string) data_get($registryEntry, 'type') !== $registryType) {
            $errors[] = [
                'code' => 'registry_entry_type_mismatch',
                'path' => '$.registry_entry.type',
                'message' => 'Registry entry type must match generated registry_type.',
                'component_index' => $componentIndex,
                'expected' => $registryType,
                'actual' => data_get($registryEntry, 'type'),
            ];
        }

        if ($registryType !== '' && (string) data_get($nodeScaffold, 'type', '') !== '' && (string) data_get($nodeScaffold, 'type') !== $registryType) {
            $errors[] = [
                'code' => 'node_scaffold_type_mismatch',
                'path' => '$.node_scaffold.type',
                'message' => 'Node scaffold type must match generated registry_type.',
                'component_index' => $componentIndex,
                'expected' => $registryType,
                'actual' => data_get($nodeScaffold, 'type'),
            ];
        }

        if ($registryType !== '' && (string) data_get($rendererScaffold, 'registry_type', '') !== '' && (string) data_get($rendererScaffold, 'registry_type') !== $registryType) {
            $errors[] = [
                'code' => 'renderer_scaffold_type_mismatch',
                'path' => '$.renderer_scaffold.registry_type',
                'message' => 'Renderer scaffold registry_type must match generated registry_type.',
                'component_index' => $componentIndex,
                'expected' => $registryType,
                'actual' => data_get($rendererScaffold, 'registry_type'),
            ];
        }

        foreach (['category', 'props_schema', 'default_props', 'renderer', 'controls_config'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $registryEntry)) {
                $errors[] = [
                    'code' => 'missing_registry_entry_key',
                    'path' => '$.registry_entry.'.$requiredKey,
                    'message' => 'Registry entry scaffold is missing required key.',
                    'component_index' => $componentIndex,
                ];
            }
        }

        foreach (['id', 'type', 'props', 'bindings', 'meta'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $nodeScaffold)) {
                $errors[] = [
                    'code' => 'missing_node_scaffold_key',
                    'path' => '$.node_scaffold.'.$requiredKey,
                    'message' => 'Node scaffold is missing required key.',
                    'component_index' => $componentIndex,
                ];
            }
        }

        if ((int) data_get($nodeScaffold, 'meta.schema_version', 0) !== 1) {
            $errors[] = [
                'code' => 'invalid_node_schema_version',
                'path' => '$.node_scaffold.meta.schema_version',
                'message' => 'Node scaffold must be schema_version=1 before activation.',
                'component_index' => $componentIndex,
                'expected' => 1,
                'actual' => data_get($nodeScaffold, 'meta.schema_version'),
            ];
        }

        $registryTemplateRef = (string) data_get($registryEntry, 'renderer.html_template_ref', '');
        $rendererScaffoldRef = (string) data_get($rendererScaffold, 'template.ref', '');
        if ($registryTemplateRef === '' || $rendererScaffoldRef === '' || $registryTemplateRef !== $rendererScaffoldRef) {
            $errors[] = [
                'code' => 'renderer_template_ref_mismatch',
                'path' => '$.registry_entry.renderer.html_template_ref',
                'message' => 'Registry entry renderer template ref must match renderer scaffold template ref.',
                'component_index' => $componentIndex,
                'expected' => $rendererScaffoldRef,
                'actual' => $registryTemplateRef,
            ];
        }

        if (! is_array($rendererTemplate)) {
            $errors[] = [
                'code' => 'missing_renderer_template',
                'path' => '$.renderer_templates',
                'message' => 'Renderer template generation result is missing template for registry_type.',
                'component_index' => $componentIndex,
                'expected' => $registryType,
            ];
        } else {
            if ((string) ($rendererTemplate['registry_type'] ?? '') !== $registryType) {
                $errors[] = [
                    'code' => 'renderer_template_type_mismatch',
                    'path' => '$.renderer_template.registry_type',
                    'message' => 'Renderer template registry type must match generated registry_type.',
                    'component_index' => $componentIndex,
                    'expected' => $registryType,
                    'actual' => $rendererTemplate['registry_type'] ?? null,
                ];
            }

            if ((string) ($rendererTemplate['template_ref'] ?? '') !== $rendererScaffoldRef) {
                $errors[] = [
                    'code' => 'renderer_template_ref_output_mismatch',
                    'path' => '$.renderer_template.template_ref',
                    'message' => 'Renderer template output ref must match renderer scaffold template ref.',
                    'component_index' => $componentIndex,
                    'expected' => $rendererScaffoldRef,
                    'actual' => $rendererTemplate['template_ref'] ?? null,
                ];
            }

            if (! (bool) data_get($rendererTemplate, 'validation.ok')) {
                $errors[] = [
                    'code' => 'renderer_template_validation_failed',
                    'path' => '$.renderer_template.validation',
                    'message' => 'Generated renderer template failed validation and cannot be activated.',
                    'component_index' => $componentIndex,
                ];
            }
        }

        if ((bool) data_get($registryEntry, 'meta.supports_dynamic_bindings') && (array) data_get($nodeScaffold, 'bindings', []) === []) {
            $warnings[] = 'dynamic_binding_component_without_node_bindings';
        }

        $securityValidation = $this->generatedComponentSecurityValidationService->validateComponentBundle(
            $componentArtifact,
            $rendererTemplate
        );

        if (! (bool) ($securityValidation['ok'] ?? false)) {
            foreach (is_array($securityValidation['errors'] ?? null) ? $securityValidation['errors'] : [] as $securityError) {
                if (! is_array($securityError)) {
                    continue;
                }

                $securityError['component_index'] = $componentIndex;
                $errors[] = $securityError;
            }
        }

        if (is_array($securityValidation['warnings'] ?? null) && $securityValidation['warnings'] !== []) {
            $warnings = array_values(array_unique(array_merge(
                $warnings,
                array_values(array_filter(array_map('strval', $securityValidation['warnings'])))
            )));
        }

        $valid = $errors === [];

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'bundle' => [
                'component_key' => (string) ($componentArtifact['component_key'] ?? 'component-'.$componentIndex),
                'registry_type' => $registryType,
                'status' => $valid ? 'ready_for_activation' : 'blocked',
                'checks' => [
                    'registry_entry_present' => $registryEntry !== [],
                    'node_scaffold_present' => $nodeScaffold !== [],
                    'renderer_scaffold_present' => $rendererScaffold !== [],
                    'renderer_template_present' => is_array($rendererTemplate),
                    'renderer_template_validation_ok' => (bool) data_get($rendererTemplate, 'validation.ok'),
                    'security_validation_ok' => (bool) ($securityValidation['ok'] ?? false),
                ],
                'registry_entry' => $registryEntry,
                'node_scaffold' => $nodeScaffold,
                'renderer_scaffold' => $rendererScaffold,
                'renderer_template' => is_array($rendererTemplate) ? [
                    'template_ref' => (string) ($rendererTemplate['template_ref'] ?? ''),
                    'html' => (string) ($rendererTemplate['html'] ?? ''),
                    'validation' => is_array($rendererTemplate['validation'] ?? null) ? $rendererTemplate['validation'] : null,
                ] : null,
                'errors' => $errors,
                'warnings' => $warnings,
                'security_validation' => $securityValidation,
            ],
        ];
    }
}
