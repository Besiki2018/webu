<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiRendererTemplateGenerationService
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $factoryResult
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generateFromComponentFactoryResult(array $factoryResult, array $options = []): array
    {
        $generated = is_array($factoryResult['generated'] ?? null) ? $factoryResult['generated'] : null;
        if (! ($factoryResult['ok'] ?? false) || ! is_array($generated)) {
            return [
                'ok' => false,
                'code' => 'invalid_component_factory_result',
                'errors' => [[
                    'code' => 'missing_generated_payload',
                    'path' => '$.generated',
                    'message' => 'Component factory result must be successful and include generated artifacts.',
                ]],
                'warnings' => [],
                'generated' => null,
                'summary' => [
                    'generator_version' => self::VERSION,
                    'template_count' => 0,
                    'validation_error_count' => 1,
                ],
            ];
        }

        $rendererScaffolds = is_array($generated['renderer_scaffolds'] ?? null)
            ? array_values(array_filter($generated['renderer_scaffolds'], 'is_array'))
            : [];

        $templates = [];
        $warnings = [];
        $errors = [];
        $validTemplates = 0;
        $invalidTemplates = 0;

        foreach ($rendererScaffolds as $index => $rendererScaffold) {
            $built = $this->generateTemplateFromRendererScaffold(
                $rendererScaffold,
                [
                    'feature_key' => (string) ($generated['feature_key'] ?? ''),
                    'domain' => (string) ($generated['domain'] ?? 'universal'),
                    'component_index' => $index,
                ]
            );

            if (! ($built['ok'] ?? false)) {
                $invalidTemplates++;
                $errors = array_merge($errors, is_array($built['errors'] ?? null) ? $built['errors'] : []);
                continue;
            }

            $templates[] = $built['template'];
            $warnings = array_merge($warnings, is_array($built['warnings'] ?? null) ? $built['warnings'] : []);

            if ((bool) data_get($built, 'template.validation.ok')) {
                $validTemplates++;
            } else {
                $invalidTemplates++;
                $errors = array_merge($errors, is_array(data_get($built, 'template.validation.errors')) ? data_get($built, 'template.validation.errors') : []);
            }
        }

        return [
            'ok' => $errors === [],
            'code' => $errors === [] ? null : 'renderer_template_validation_failed',
            'errors' => array_values($errors),
            'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
            'generated' => [
                'schema_version' => 1,
                'templates' => $templates,
                'manifest' => [
                    'feature_key' => (string) ($generated['feature_key'] ?? ''),
                    'domain' => (string) ($generated['domain'] ?? 'universal'),
                    'template_refs' => array_values(array_map(
                        static fn (array $template): string => (string) ($template['template_ref'] ?? ''),
                        $templates
                    )),
                ],
                'meta' => [
                    'generator_version' => self::VERSION,
                    'source' => 'component_factory_renderer_template_generation',
                    'factory_generator_version' => (int) data_get($generated, 'meta.generator_version', 1),
                ],
            ],
            'summary' => [
                'generator_version' => self::VERSION,
                'template_count' => count($templates),
                'valid_template_count' => $validTemplates,
                'invalid_template_count' => $invalidTemplates,
                'validation_error_count' => count($errors),
                'warning_count' => count(array_values(array_unique(array_filter(array_map('strval', $warnings))))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rendererScaffold
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generateTemplateFromRendererScaffold(array $rendererScaffold, array $context = []): array
    {
        $scaffoldValidation = $this->validateRendererScaffoldShape($rendererScaffold);
        if (! ($scaffoldValidation['valid'] ?? false)) {
            return [
                'ok' => false,
                'errors' => $scaffoldValidation['errors'],
                'warnings' => [],
                'template' => null,
            ];
        }

        $registryType = (string) ($rendererScaffold['registry_type'] ?? '');
        $componentKey = (string) ($rendererScaffold['component_key'] ?? '');
        $templateRef = (string) data_get($rendererScaffold, 'template.ref');
        $rootMarker = (string) data_get($rendererScaffold, 'template.markers.root');
        $contentMarkers = is_array(data_get($rendererScaffold, 'template.markers.content'))
            ? array_values(array_filter(data_get($rendererScaffold, 'template.markers.content'), 'is_string'))
            : [];
        $bindingKeys = is_array(data_get($rendererScaffold, 'adapter_contract.expects_bindings'))
            ? array_values(array_filter(data_get($rendererScaffold, 'adapter_contract.expects_bindings'), 'is_string'))
            : [];
        $queryResources = is_array(data_get($rendererScaffold, 'adapter_contract.queries'))
            ? array_values(array_filter(array_map('strval', data_get($rendererScaffold, 'adapter_contract.queries')), static fn (string $value): bool => trim($value) !== ''))
            : [];

        $html = $this->buildTemplateHtml(
            registryType: $registryType,
            componentKey: $componentKey,
            rootMarker: $rootMarker,
            contentMarkers: $contentMarkers,
            bindingKeys: $bindingKeys,
            queryResources: $queryResources,
            context: $context
        );

        $validation = $this->validateGeneratedTemplate(
            rendererScaffold: $rendererScaffold,
            html: $html
        );

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'template' => [
                'registry_type' => $registryType,
                'component_key' => $componentKey,
                'template_ref' => $templateRef,
                'html' => $html,
                'validation' => $validation,
                'meta' => [
                    'schema_version' => 1,
                    'feature_key' => (string) ($context['feature_key'] ?? ''),
                    'domain' => (string) ($context['domain'] ?? 'universal'),
                    'component_index' => (int) ($context['component_index'] ?? 0),
                    'generator_version' => self::VERSION,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rendererScaffold
     * @return array{valid: bool, errors: array<int, array<string, mixed>>}
     */
    private function validateRendererScaffoldShape(array $rendererScaffold): array
    {
        $errors = [];

        foreach ([
            ['path' => '$.registry_type', 'value' => $rendererScaffold['registry_type'] ?? null],
            ['path' => '$.component_key', 'value' => $rendererScaffold['component_key'] ?? null],
            ['path' => '$.template.ref', 'value' => data_get($rendererScaffold, 'template.ref')],
            ['path' => '$.template.markers.root', 'value' => data_get($rendererScaffold, 'template.markers.root')],
        ] as $required) {
            $value = $required['value'];
            if (! is_string($value) || trim($value) === '') {
                $errors[] = [
                    'code' => 'missing_required_string',
                    'path' => (string) $required['path'],
                    'message' => 'Renderer scaffold is missing required string field.',
                ];
            }
        }

        $templateRef = (string) data_get($rendererScaffold, 'template.ref', '');
        if ($templateRef !== '' && ! Str::endsWith($templateRef, '.html')) {
            $errors[] = [
                'code' => 'invalid_template_ref',
                'path' => '$.template.ref',
                'message' => 'Renderer template ref must end with .html.',
                'expected' => '.html suffix',
                'actual' => $templateRef,
            ];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $rendererScaffold
     * @return array<string, mixed>
     */
    public function validateGeneratedTemplate(array $rendererScaffold, string $html): array
    {
        $errors = [];
        $warnings = [];

        $rootMarker = (string) data_get($rendererScaffold, 'template.markers.root', '');
        $contentMarkers = is_array(data_get($rendererScaffold, 'template.markers.content'))
            ? array_values(array_filter(data_get($rendererScaffold, 'template.markers.content'), 'is_string'))
            : [];
        $bindingKeys = is_array(data_get($rendererScaffold, 'adapter_contract.expects_bindings'))
            ? array_values(array_filter(data_get($rendererScaffold, 'adapter_contract.expects_bindings'), 'is_string'))
            : [];
        $queryResources = is_array(data_get($rendererScaffold, 'adapter_contract.queries'))
            ? array_values(array_filter(array_map('strval', data_get($rendererScaffold, 'adapter_contract.queries')), static fn (string $value): bool => trim($value) !== ''))
            : [];

        if (trim($html) === '') {
            $errors[] = [
                'code' => 'empty_template_html',
                'path' => '$.html',
                'message' => 'Generated template HTML is empty.',
            ];
        }

        if ($rootMarker !== '' && ! str_contains($html, $rootMarker)) {
            $errors[] = [
                'code' => 'missing_root_marker',
                'path' => '$.template.markers.root',
                'message' => 'Generated HTML is missing required root marker.',
                'expected' => $rootMarker,
            ];
        }

        foreach ($contentMarkers as $index => $contentMarker) {
            if (! str_contains($html, $contentMarker)) {
                $errors[] = [
                    'code' => 'missing_content_marker',
                    'path' => '$.template.markers.content.'.$index,
                    'message' => 'Generated HTML is missing required content marker.',
                    'expected' => $contentMarker,
                ];
            }
        }

        foreach ($bindingKeys as $bindingKey) {
            $marker = 'data-bind-binding="'.$bindingKey.'"';
            if (! str_contains($html, $marker)) {
                $errors[] = [
                    'code' => 'missing_binding_marker',
                    'path' => '$.adapter_contract.expects_bindings',
                    'message' => 'Generated HTML is missing binding marker for adapter contract key.',
                    'expected' => $bindingKey,
                ];
            }
        }

        foreach ($queryResources as $queryResource) {
            $marker = 'data-bind-query-resource="'.$queryResource.'"';
            if (! str_contains($html, $marker)) {
                $errors[] = [
                    'code' => 'missing_query_marker',
                    'path' => '$.adapter_contract.queries',
                    'message' => 'Generated HTML is missing query resource marker for adapter contract.',
                    'expected' => $queryResource,
                ];
            }
        }

        if (! str_contains($html, 'data-webu-ai-template-generated="1"')) {
            $warnings[] = 'missing_generated_template_marker';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => [
                'root_marker_present' => $rootMarker === '' ? null : str_contains($html, $rootMarker),
                'content_marker_count' => count($contentMarkers),
                'binding_marker_count' => count($bindingKeys),
                'query_marker_count' => count($queryResources),
                'generated_template_marker_present' => str_contains($html, 'data-webu-ai-template-generated="1"'),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $contentMarkers
     * @param  array<int, string>  $bindingKeys
     * @param  array<int, string>  $queryResources
     * @param  array<string, mixed>  $context
     */
    private function buildTemplateHtml(
        string $registryType,
        string $componentKey,
        string $rootMarker,
        array $contentMarkers,
        array $bindingKeys,
        array $queryResources,
        array $context = []
    ): string {
        $featureKey = (string) ($context['feature_key'] ?? 'feature');

        $lines = [];
        $lines[] = '<section '.$rootMarker.' data-webu-ai-template-generated="1" data-webu-ai-feature="'.e($featureKey).'" data-webu-ai-component-key="'.e($componentKey).'">';
        $lines[] = '  <div data-webu-ai-slot="root" data-webu-ai-registry-type="'.e($registryType).'">';

        foreach ($contentMarkers as $index => $marker) {
            $contentPath = $this->extractQuotedMarkerValue($marker, 'data-bind-content');
            $token = $contentPath !== null && $contentPath !== '' ? '{{content.'.$contentPath.'}}' : '{{content.value_'.$index.'}}';
            $lines[] = '    <span '.$marker.'>'.e($token).'</span>';
        }

        if ($bindingKeys !== []) {
            $lines[] = '    <div data-webu-ai-slot="bindings">';
            foreach ($bindingKeys as $bindingKey) {
                $lines[] = '      <span data-bind-binding="'.e($bindingKey).'">{{binding.'.e($bindingKey).'}}</span>';
            }
            $lines[] = '    </div>';
        }

        if ($queryResources !== []) {
            $lines[] = '    <div data-webu-ai-slot="queries">';
            foreach ($queryResources as $queryResource) {
                $queryToken = '{{query.'.e($this->queryAliasFromResource($queryResource)).'.data}}';
                $lines[] = '      <template data-bind-query-resource="'.e($queryResource).'">'.$queryToken.'</template>';
            }
            $lines[] = '    </div>';
        }

        $lines[] = '  </div>';
        $lines[] = '</section>';

        return implode("\n", $lines);
    }

    private function extractQuotedMarkerValue(string $marker, string $attribute): ?string
    {
        $pattern = '/'.preg_quote($attribute, '/').'=\"([^\"]+)\"/';
        if (! preg_match($pattern, $marker, $matches)) {
            return null;
        }

        return isset($matches[1]) ? (string) $matches[1] : null;
    }

    private function queryAliasFromResource(string $resource): string
    {
        $alias = trim(Str::snake(str_replace(['.', '-', ' '], '_', $resource)));

        return $alias !== '' ? $alias : 'query';
    }
}

