<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiGeneratedComponentSecurityValidationService
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $componentArtifact
     * @param  array<string, mixed>|null  $rendererTemplate
     * @return array<string, mixed>
     */
    public function validateComponentBundle(array $componentArtifact, ?array $rendererTemplate): array
    {
        $errors = [];
        $warnings = [];

        $registryType = (string) ($componentArtifact['registry_type'] ?? '');
        $registryEntry = is_array($componentArtifact['registry_entry'] ?? null) ? $componentArtifact['registry_entry'] : [];
        $rendererScaffold = is_array($componentArtifact['renderer_scaffold'] ?? null) ? $componentArtifact['renderer_scaffold'] : [];
        $nodeScaffold = is_array($componentArtifact['node_scaffold'] ?? null) ? $componentArtifact['node_scaffold'] : [];

        $this->validateRegistryType($registryType, '$.registry_type', $errors);

        $this->validateTemplateRef(
            (string) data_get($registryEntry, 'renderer.html_template_ref', ''),
            '$.registry_entry.renderer.html_template_ref',
            $errors
        );
        $this->validateTemplateRef(
            (string) data_get($rendererScaffold, 'template.ref', ''),
            '$.renderer_scaffold.template.ref',
            $errors
        );

        $this->validateBindingKeys(
            is_array(data_get($nodeScaffold, 'bindings')) ? data_get($nodeScaffold, 'bindings') : [],
            '$.node_scaffold.bindings',
            $errors
        );

        $this->validateCustomCss(
            data_get($nodeScaffold, 'props.advanced.custom_css'),
            '$.node_scaffold.props.advanced.custom_css',
            $errors,
            $warnings
        );

        if (! is_array($rendererTemplate)) {
            return [
                'ok' => $errors === [],
                'errors' => $errors,
                'warnings' => $warnings,
                'checks' => [
                    'registry_type_safe' => $registryType !== '' && $this->isSafeRegistryType($registryType),
                    'template_refs_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_template_ref'),
                    'renderer_html_safe' => null,
                    'bindings_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_binding'),
                    'custom_css_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_custom_css'),
                ],
            ];
        }

        $this->validateTemplateRef(
            (string) ($rendererTemplate['template_ref'] ?? ''),
            '$.renderer_template.template_ref',
            $errors
        );

        $rendererHtml = (string) ($rendererTemplate['html'] ?? '');
        $rendererHtmlSafety = $this->validateRendererHtml($rendererHtml, '$.renderer_template.html', $errors);

        return [
            'ok' => $errors === [],
            'errors' => array_values($errors),
            'warnings' => array_values(array_unique($warnings)),
            'checks' => [
                'registry_type_safe' => $registryType !== '' && $this->isSafeRegistryType($registryType),
                'template_refs_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_template_ref'),
                'renderer_html_safe' => $rendererHtmlSafety,
                'bindings_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_binding'),
                'custom_css_safe' => ! $this->hasErrorWithPrefix($errors, 'unsafe_custom_css'),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validateRegistryType(string $registryType, string $path, array &$errors): void
    {
        if ($registryType === '') {
            return;
        }

        if (! $this->isSafeRegistryType($registryType)) {
            $errors[] = [
                'code' => 'unsafe_registry_type',
                'path' => $path,
                'message' => 'Registry type contains unsafe characters.',
                'actual' => $registryType,
            ];
        }
    }

    private function isSafeRegistryType(string $registryType): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', $registryType);
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validateTemplateRef(string $templateRef, string $path, array &$errors): void
    {
        if ($templateRef === '') {
            return;
        }

        if (str_contains($templateRef, '..') || str_contains($templateRef, '\\')) {
            $errors[] = [
                'code' => 'unsafe_template_ref_path_traversal',
                'path' => $path,
                'message' => 'Template ref contains path traversal tokens.',
                'actual' => $templateRef,
            ];
        }

        if (! Str::startsWith($templateRef, 'generated/')) {
            $errors[] = [
                'code' => 'unsafe_template_ref_prefix',
                'path' => $path,
                'message' => 'Template ref must stay under generated/ namespace.',
                'expected' => 'generated/*',
                'actual' => $templateRef,
            ];
        }

        if (! Str::endsWith($templateRef, '.html')) {
            $errors[] = [
                'code' => 'unsafe_template_ref_suffix',
                'path' => $path,
                'message' => 'Template ref must end with .html.',
                'expected' => '*.html',
                'actual' => $templateRef,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validateBindingKeys(array $bindings, string $path, array &$errors): void
    {
        foreach ($bindings as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! preg_match('/^[a-z0-9_]+$/', $key)) {
                $errors[] = [
                    'code' => 'unsafe_binding_key',
                    'path' => $path.'.'.$key,
                    'message' => 'Binding key must be snake_case alphanumeric.',
                    'actual' => $key,
                ];
            }

            if (is_scalar($value) && preg_match('/<\s*script\b|javascript\s*:|on[a-z0-9_-]+\s*=/i', (string) $value)) {
                $errors[] = [
                    'code' => 'unsafe_binding_value',
                    'path' => $path.'.'.$key,
                    'message' => 'Binding value contains unsafe token.',
                    'actual' => (string) $value,
                ];
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, string>  $warnings
     */
    private function validateCustomCss(mixed $value, string $path, array &$errors, array &$warnings): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $css = $value;

        if (preg_match('/@import\b/i', $css)) {
            $errors[] = [
                'code' => 'unsafe_custom_css_import',
                'path' => $path,
                'message' => 'Generated custom CSS cannot include @import.',
            ];
        }

        if (preg_match('/expression\s*\(/i', $css)) {
            $errors[] = [
                'code' => 'unsafe_custom_css_expression',
                'path' => $path,
                'message' => 'Generated custom CSS cannot include CSS expression().',
            ];
        }

        if (preg_match('/javascript\s*:/i', $css)) {
            $errors[] = [
                'code' => 'unsafe_custom_css_javascript_url',
                'path' => $path,
                'message' => 'Generated custom CSS cannot include javascript: URLs.',
            ];
        }

        if (preg_match('/url\s*\(\s*[\'"]?\s*data:/i', $css)) {
            $warnings[] = 'generated_custom_css_contains_data_url';
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validateRendererHtml(string $html, string $path, array &$errors): bool
    {
        if (trim($html) === '') {
            $errors[] = [
                'code' => 'unsafe_renderer_html_empty',
                'path' => $path,
                'message' => 'Renderer template HTML is empty.',
            ];

            return false;
        }

        $forbiddenPatterns = [
            'unsafe_renderer_html_script_tag' => '/<\s*script\b/i',
            'unsafe_renderer_html_iframe_tag' => '/<\s*iframe\b/i',
            'unsafe_renderer_html_inline_event_handler' => '/\son[a-z0-9_-]+\s*=/i',
            'unsafe_renderer_html_javascript_url' => '/javascript\s*:/i',
            'unsafe_renderer_html_srcdoc' => '/\bsrcdoc\s*=/i',
            'unsafe_renderer_html_php_tag' => '/<\?(php|=)?/i',
            'unsafe_renderer_html_raw_blade_output' => '/\{!!/i',
        ];

        $safe = true;
        foreach ($forbiddenPatterns as $code => $pattern) {
            if (! preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $safe = false;
            $errors[] = [
                'code' => $code,
                'path' => $path,
                'message' => 'Generated renderer HTML contains forbidden token/pattern.',
                'match' => is_array($match[0] ?? null) ? (string) $match[0][0] : null,
            ];
        }

        return $safe;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function hasErrorWithPrefix(array $errors, string $prefix): bool
    {
        foreach ($errors as $error) {
            $code = (string) ($error['code'] ?? '');
            if (Str::startsWith($code, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
