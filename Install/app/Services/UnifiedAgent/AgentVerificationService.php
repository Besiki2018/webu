<?php

namespace App\Services\UnifiedAgent;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Arr;

/**
 * Mandatory verification before success.
 * Verifies that the requested visible result actually changed.
 * Never return success unless verification passes.
 */
class AgentVerificationService
{
    public function __construct()
    {
    }

    /**
     * Verify text edit: the requested path/value changed in the revision.
     *
     * @param  array{op: string, sectionId?: string, path?: string, value?: string, patch?: array}  $operation
     */
    public function verifyTextEdit(
        Page $page,
        PageRevision $revisionBefore,
        PageRevision $revisionAfter,
        array $operation
    ): array {
        return $this->verifyTextEditInContent(
            is_array($revisionBefore->content_json) ? $revisionBefore->content_json : [],
            is_array($revisionAfter->content_json) ? $revisionAfter->content_json : [],
            $operation
        );
    }

    /**
     * @param  array<string, mixed>  $contentBefore
     * @param  array<string, mixed>  $contentAfter
     * @param  array{op: string, sectionId?: string, path?: string, value?: string, patch?: array}  $operation
     */
    public function verifyTextEditInContent(array $contentBefore, array $contentAfter, array $operation): array
    {
        $path = $this->normalizeOperationPath($operation['path'] ?? $operation['parameter_path'] ?? '');
        $expectedValue = trim((string) ($operation['value'] ?? ''));
        $sectionId = trim((string) ($operation['sectionId'] ?? $operation['section_id'] ?? ''));

        if ($path === '' || $expectedValue === '' || $sectionId === '') {
            return ['verified' => false, 'reason' => 'Missing path, value, or sectionId'];
        }

        $beforeSection = $this->findSectionByLocalId($contentBefore['sections'] ?? [], $sectionId);
        $sections = $contentAfter['sections'] ?? [];
        $section = $this->findSectionByLocalId($sections, $sectionId);
        if ($section === null) {
            return ['verified' => false, 'reason' => "Section {$sectionId} not found"];
        }

        $beforeValue = Arr::get($beforeSection['props'] ?? [], $path);
        $actualValue = Arr::get($section['props'] ?? [], $path);

        $normalizedBefore = $this->normalizeValueForCompare($beforeValue);
        $normalizedExpected = $this->normalizeForCompare($expectedValue);
        $normalizedActual = $this->normalizeValueForCompare($actualValue);

        if ($normalizedActual === $normalizedExpected && $normalizedBefore !== $normalizedActual) {
            return ['verified' => true];
        }

        if ($normalizedActual === $normalizedExpected) {
            return [
                'verified' => false,
                'reason' => "Value at {$path} already matched '{$expectedValue}' before execution",
            ];
        }

        return [
            'verified' => false,
            'reason' => "Expected '{$expectedValue}' at {$path}, got '".($normalizedActual !== '' ? $normalizedActual : '(empty)')."'",
        ];
    }

    /**
     * Verify button edit: label and/or href changed.
     *
     * @param  array{op: string, sectionId?: string, patch?: array}  $operation
     */
    public function verifyButtonEdit(
        Page $page,
        PageRevision $revisionBefore,
        PageRevision $revisionAfter,
        array $operation
    ): array {
        return $this->verifyButtonEditInContent(
            is_array($revisionBefore->content_json) ? $revisionBefore->content_json : [],
            is_array($revisionAfter->content_json) ? $revisionAfter->content_json : [],
            $operation
        );
    }

    /**
     * @param  array<string, mixed>  $contentBefore
     * @param  array<string, mixed>  $contentAfter
     * @param  array{op: string, sectionId?: string, patch?: array}  $operation
     */
    public function verifyButtonEditInContent(array $contentBefore, array $contentAfter, array $operation): array
    {
        $sectionId = trim((string) ($operation['sectionId'] ?? $operation['section_id'] ?? ''));
        $patch = is_array($operation['patch'] ?? null) ? $operation['patch'] : [];

        if ($sectionId === '' || $patch === []) {
            return ['verified' => false, 'reason' => 'Missing sectionId or patch'];
        }

        $beforeSection = $this->findSectionByLocalId($contentBefore['sections'] ?? [], $sectionId);
        $sections = $contentAfter['sections'] ?? [];
        $section = $this->findSectionByLocalId($sections, $sectionId);
        if ($section === null) {
            return ['verified' => false, 'reason' => "Section {$sectionId} not found"];
        }

        $beforeProps = $beforeSection['props'] ?? [];
        $props = $section['props'] ?? [];
        $flatPatch = $this->flattenPatchPaths($patch);
        $anyVerified = false;
        foreach ($flatPatch as $key => $expectedVal) {
            $before = Arr::get($beforeProps, $key);
            $actual = Arr::get($props, $key);
            if ($this->normalizeValueForCompare($actual) !== $this->normalizeValueForCompare($expectedVal)) {
                continue;
            }

            if ($this->normalizeValueForCompare($before) === $this->normalizeValueForCompare($actual)) {
                continue;
            }

            $anyVerified = true;
            break;
        }

        if ($anyVerified) {
            return ['verified' => true];
        }

        return ['verified' => false, 'reason' => 'Button patch did not produce a new visible value'];
    }

    /**
     * Verify header/footer (site-wide) change: theme_settings changed.
     */
    public function verifyGlobalComponentEdit(
        Site $siteBefore,
        Site $siteAfter,
        string $component,
        array $patch
    ): array {
        $themeBefore = is_array($siteBefore->theme_settings) ? $siteBefore->theme_settings : [];
        $themeAfter = is_array($siteAfter->theme_settings) ? $siteAfter->theme_settings : [];

        $layoutKey = $component.'_props';
        $layoutBefore = $themeBefore['layout'][$layoutKey] ?? [];
        $layoutAfter = $themeAfter['layout'][$layoutKey] ?? [];

        foreach ($patch as $path => $expectedVal) {
            $actual = Arr::get($layoutAfter, $path);
            if ($this->normalizeForCompare((string) $actual) !== $this->normalizeForCompare((string) $expectedVal)) {
                return [
                    'verified' => false,
                    'reason' => "Global {$component} patch at {$path} did not match",
                ];
            }
        }

        return ['verified' => true];
    }

    /**
     * Verify that some visible change occurred (fingerprint diff).
     */
    public function verifyVisibleChange(
        ?array $contentBefore,
        ?array $contentAfter,
        ?array $themeBefore,
        ?array $themeAfter
    ): array {
        $contentFingerprintBefore = md5(json_encode($contentBefore ?? [], JSON_UNESCAPED_UNICODE));
        $contentFingerprintAfter = md5(json_encode($contentAfter ?? [], JSON_UNESCAPED_UNICODE));
        $themeFingerprintBefore = md5(json_encode($themeBefore ?? [], JSON_UNESCAPED_UNICODE));
        $themeFingerprintAfter = md5(json_encode($themeAfter ?? [], JSON_UNESCAPED_UNICODE));

        $contentChanged = $contentFingerprintBefore !== $contentFingerprintAfter;
        $themeChanged = $themeFingerprintBefore !== $themeFingerprintAfter;

        if ($contentChanged || $themeChanged) {
            return ['verified' => true];
        }

        return [
            'verified' => false,
            'reason' => 'No visible change in page content or theme',
        ];
    }

    /**
     * @param  array<int, array{localId?: string}>  $sections
     */
    private function findSectionByLocalId(array $sections, string $localId): ?array
    {
        foreach (array_values($sections) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $id = CmsSectionLocalId::resolve($section, $index);
            if ($id === $localId) {
                $section['localId'] = $id;
                return $section;
            }
        }

        return null;
    }

    private function normalizeForCompare(string $value): string
    {
        $v = trim($value);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return $v;
    }

    private function normalizeOperationPath(mixed $pathValue): string
    {
        if (is_array($pathValue)) {
            $segments = array_values(array_filter(array_map(static function ($segment): string {
                return trim((string) $segment);
            }, $pathValue), static fn (string $segment): bool => $segment !== ''));

            return $segments !== [] ? implode('.', $segments) : '';
        }

        return trim((string) $pathValue);
    }

    private function normalizeValueForCompare(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $this->normalizeForCompare(is_string($encoded) ? $encoded : '');
        }

        if (is_scalar($value) || $value === null) {
            return $this->normalizeForCompare((string) ($value ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function flattenPatchPaths(array $patch, string $prefix = ''): array
    {
        $paths = [];

        foreach ($patch as $key => $value) {
            $segment = trim((string) $key);
            if ($segment === '') {
                continue;
            }

            $path = $prefix !== '' ? $prefix.'.'.$segment : $segment;
            if (is_array($value) && ! array_is_list($value)) {
                $paths += $this->flattenPatchPaths($value, $path);
                continue;
            }

            $paths[$path] = $value;
        }

        return $paths;
    }
}
