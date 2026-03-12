<?php

namespace App\Services;

use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\UnifiedAgent\AgentVerificationService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Support\BuilderComponentAliasResolver;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lovable-style AI Agent Executor.
 *
 * Applies AI change sets to the builder/CMS only. This is the single place where
 * "tools" (updateComponentParameter, createSection, deleteSection, updateGlobalHeader, etc.)
 * are executed via the change set operations.
 *
 * Pipeline: Page Structure Analysis (AiSiteEditorAnalyzeService) → Interpret (AiInterpretCommandService)
 * → Execute (this service: site ops + section ops) → Builder state update → Preview refresh (client).
 *
 * AI may only modify: component parameters, page content, CMS entries, theme_settings (header/footer).
 * AI must never modify: component source code, builder core logic, system configuration.
 */
class AiAgentExecutorService
{
    public const ERROR_PAGE_NOT_FOUND = 'page_not_found';

    public const ERROR_SITE_OPERATIONS_FAILED = 'site_operations_failed';

    public const ERROR_CHANGE_SET_FAILED = 'change_set_failed';

    public const ERROR_VALIDATION_FAILED = 'validation_failed';

    public const ERROR_PATCHER_FAILED = 'patcher_failed';

    public const ERROR_SELECTED_TARGET_SCOPE_VIOLATION = 'selected_target_scope_violation';

    public const ERROR_SELECTED_TARGET_UNMAPPABLE = 'selected_target_unmappable';

    public const ERROR_NO_EFFECT = 'no_effect';

    public function __construct(
        protected SiteProvisioningService $siteProvisioning,
        protected AiChangeSetToContentMergeService $changeSetToPatch,
        protected AiContentPatchService $patcher,
        protected AiSiteEditorSiteOpsService $siteOpsService,
        protected LocalizedCmsPayload $localizedPayload,
        protected AiButtonOperationPatchResolver $buttonPatchResolver,
        protected CmsSectionBindingService $sectionBindings,
        protected ProjectWorkspaceService $workspace,
        protected CodebaseScanner $scanner,
        protected AgentVerificationService $verification,
    ) {}

    /**
     * Execute a change set: apply site-level ops (theme, header/footer) then section ops (page content).
     *
     * @param  array{operations: array, summary?: array}  $changeSet
     * @param  array{page_id?: int, page_slug?: string, instruction?: string, publish?: bool, locale?: string, actor_id?: int, selected_target?: array<string, mixed>|null}  $options
     * @return array{success: true, page: Page, revision: PageRevision|null, action_log: array, applied_changes: array, highlight_section_ids: array, diagnostic_log: array<int, string>}|array{success: false, error: string, error_code: string, diagnostic_log?: array<int, string>}
     */
    public function execute(Project $project, array $changeSet, array $options = []): array
    {
        $site = $this->siteProvisioning->provisionForProject($project);
        $initialThemeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $pageId = isset($options['page_id']) ? (int) $options['page_id'] : null;
        $pageSlug = isset($options['page_slug']) ? trim((string) ($options['page_slug'] ?? '')) : null;

        $page = $this->resolvePage($site->id, $pageId, $pageSlug);
        if (! $page) {
            Log::warning('ai_site_editor.executor.page_not_found', [
                'project_id' => $project->id,
                'site_id' => $site->id,
                'page_id' => $pageId,
                'page_slug' => $pageSlug,
            ]);
            return [
                'success' => false,
                'error' => 'Page not found. Provide page_id or page_slug.',
                'error_code' => self::ERROR_PAGE_NOT_FOUND,
            ];
        }

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $currentContent = is_array($latestRevision?->content_json) ? $latestRevision->content_json : ['sections' => []];
        $requestedLocale = isset($options['locale']) ? trim((string) $options['locale']) : null;
        $resolvedCurrentPayload = $this->localizedPayload->resolve($currentContent, $requestedLocale, $site->locale);
        $resolvedCurrentContent = is_array($resolvedCurrentPayload['content'] ?? null)
            ? $resolvedCurrentPayload['content']
            : ['sections' => []];
        $diagnosticLog = [];

        $allOps = $changeSet['operations'] ?? [];
        if (is_array($allOps) && $allOps !== []) {
            $diagnosticLog[] = 'Requested operations: '.$this->summarizeOperationTypes($allOps);
        } else {
            $diagnosticLog[] = 'Requested operations: none';
        }
        $scopeValidation = $this->validateOperationsAgainstSelectedTarget(
            is_array($allOps) ? $allOps : [],
            is_array($options['selected_target'] ?? null) ? $options['selected_target'] : null,
            is_array($resolvedCurrentContent['sections'] ?? null) ? $resolvedCurrentContent['sections'] : [],
            $options['instruction'] ?? null
        );
        if ($scopeValidation !== null) {
            return [
                'success' => false,
                'error' => $scopeValidation['error'],
                'error_code' => $scopeValidation['error_code'],
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        $sectionOps = array_values(array_filter($allOps, static function ($op) {
            $o = is_array($op) ? ($op['op'] ?? '') : '';
            return in_array($o, ['updateSection', 'insertSection', 'deleteSection', 'reorderSection', 'replaceImage', 'updateButton', 'updateText'], true);
        }));
        $siteOps = array_values(array_filter($allOps, static function ($op) {
            $o = is_array($op) ? ($op['op'] ?? '') : '';
            return in_array($o, ['updateTheme', 'updateGlobalComponent'], true);
        }));

        if ($siteOps !== []) {
            try {
                $this->siteOpsService->apply($site->fresh(), $siteOps, [
                    'instruction' => $options['instruction'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ai_site_editor.executor.site_ops_failed', [
                    'project_id' => $project->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to apply site operations (theme or global header/footer). '.$e->getMessage(),
                    'error_code' => self::ERROR_SITE_OPERATIONS_FAILED,
                    'diagnostic_log' => $diagnosticLog,
                ];
            }
        }

        if ($this->shouldIgnoreSectionOpsForGlobalInstruction($siteOps, $sectionOps, $options['instruction'] ?? null)) {
            $sectionOps = [];
        }

        $sections = is_array($resolvedCurrentContent['sections'] ?? null) ? $resolvedCurrentContent['sections'] : [];
        $sectionOps = $this->normalizeSectionOperations($sectionOps, $sections);
        $diagnosticLog = [
            ...$diagnosticLog,
            ...$this->describeNormalizedOperations($siteOps, 'site'),
            ...$this->describeNormalizedOperations($sectionOps, 'section'),
        ];

        $result = null;
        if ($sectionOps !== []) {
            $changeSetForContent = [
                'operations' => $sectionOps,
                'summary' => $changeSet['summary'] ?? [],
            ];
            try {
                $fullContent = $this->changeSetToPatch->toPatch(
                    $changeSetForContent,
                    $currentContent,
                    $requestedLocale,
                    $site->locale
                );
            } catch (\Throwable $e) {
                Log::warning('ai_site_editor.executor.change_set_failed', [
                    'project_id' => $project->id,
                    'page_id' => $page->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to apply change set: '.$e->getMessage(),
                    'error_code' => self::ERROR_CHANGE_SET_FAILED,
                    'diagnostic_log' => $diagnosticLog,
                ];
            }

            $payload = [
                'page_id' => $page->id,
                'mode' => 'replace',
                'patch' => $fullContent,
                'instruction' => $options['instruction'] ?? null,
                'publish' => (bool) ($options['publish'] ?? false),
            ];

            try {
                $result = $this->patcher->apply($project, $payload, $options['actor_id'] ?? null);
            } catch (RuntimeException $e) {
                Log::warning('ai_site_editor.executor.patcher_failed', [
                    'project_id' => $project->id,
                    'page_id' => $page->id,
                    'message' => $e->getMessage(),
                ]);
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => self::ERROR_PATCHER_FAILED,
                    'diagnostic_log' => $diagnosticLog,
                ];
            } catch (\Illuminate\Validation\ValidationException $e) {
                Log::warning('ai_site_editor.executor.validation_failed', [
                    'project_id' => $project->id,
                    'errors' => $e->errors(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Validation failed.',
                    'error_code' => self::ERROR_VALIDATION_FAILED,
                    'errors' => $e->errors(),
                    'diagnostic_log' => $diagnosticLog,
                ];
            }
        } else {
            $result = [
                'replay' => false,
                'page' => $page,
                'revision' => $latestRevision,
            ];
        }

        $appliedChanges = [];
        $actionLogLabels = [];
        foreach ($siteOps as $op) {
            $o = is_array($op) ? $op : [];
            $appliedChanges[] = [
                'op' => $o['op'] ?? null,
                'component' => $o['op'] === 'updateGlobalComponent' ? ($o['component'] ?? null) : ($o['op'] === 'updateTheme' ? 'theme' : null),
                'summary' => isset($o['patch']) && is_array($o['patch']) ? array_keys($o['patch']) : [],
            ];
            $actionLogLabels[] = $this->formatActionLogEntry($o);
        }
        foreach ($sectionOps as $op) {
            $o = is_array($op) ? $op : [];
            $sectionId = $o['sectionId'] ?? $o['section_id'] ?? '';
            $currentSection = $this->findSectionByLocalId($sections, is_string($sectionId) ? trim($sectionId) : '');
            $changeEntry = [
                'op' => $o['op'] ?? null,
                'section_id' => $sectionId,
                'summary' => isset($o['patch']) && is_array($o['patch']) ? array_keys($o['patch']) : [],
            ];
            $oldNew = $this->buildOldNewForLog($o, $currentSection);
            if ($oldNew !== []) {
                $changeEntry['old_value'] = $oldNew['old_value'];
                $changeEntry['new_value'] = $oldNew['new_value'];
            }
            $appliedChanges[] = $changeEntry;
            $actionLogLabels[] = $this->formatActionLogEntry($o, $currentSection);
        }
        $actionLog = array_values(array_filter($actionLogLabels));
        $highlightSectionIds = array_values(array_unique(array_filter(array_map(static function ($c) {
            $id = $c['section_id'] ?? null;
            return is_string($id) && $id !== '' ? $id : null;
        }, $appliedChanges))));

        $latestPageRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();
        $finalContent = is_array($latestPageRevision?->content_json) ? $latestPageRevision->content_json : $currentContent;
        $resolvedFinalPayload = $this->localizedPayload->resolve($finalContent, $requestedLocale, $site->locale);
        $resolvedFinalContent = is_array($resolvedFinalPayload['content'] ?? null)
            ? $resolvedFinalPayload['content']
            : ['sections' => []];
        $finalThemeSettings = is_array($site->fresh()?->theme_settings) ? $site->fresh()->theme_settings : [];
        $pageChanged = $this->valueFingerprint($currentContent) !== $this->valueFingerprint($finalContent);
        $siteChanged = $this->valueFingerprint($initialThemeSettings) !== $this->valueFingerprint($finalThemeSettings);
        $diagnosticLog[] = 'Page content changed: '.($pageChanged ? 'yes' : 'no');
        $diagnosticLog[] = 'Site settings changed: '.($siteChanged ? 'yes' : 'no');
        if ($latestRevision !== null || $latestPageRevision !== null) {
            $diagnosticLog[] = 'Revision: v'.($latestRevision?->version ?? 0).' -> v'.($latestPageRevision?->version ?? 0);
        }
        $verificationFailures = $this->collectSectionVerificationFailures($resolvedCurrentContent, $resolvedFinalContent, $sectionOps);
        foreach ($verificationFailures as $failure) {
            $diagnosticLog[] = $failure;
        }

        if ($verificationFailures !== []) {
            Log::warning('ai_site_editor.executor.verification_failed', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'operations' => $allOps,
                'diagnostic_log' => $diagnosticLog,
            ]);

            return [
                'success' => false,
                'error' => 'The requested visible content change could not be verified after execution.',
                'error_code' => self::ERROR_NO_EFFECT,
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        if (! $pageChanged && ! $siteChanged) {
            Log::warning('ai_site_editor.executor.no_effect', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'operations' => $allOps,
                'diagnostic_log' => $diagnosticLog,
            ]);

            return [
                'success' => false,
                'error' => 'No visible change was applied. The interpreted operations did not change page content or site settings.',
                'error_code' => self::ERROR_NO_EFFECT,
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        if ($pageChanged || $siteChanged) {
            $this->workspace->invalidateWorkspaceProjection($project);
            $this->scanner->invalidateIndex($project);
        }

        return [
            'success' => true,
            'page' => $result['page'],
            'revision' => $result['revision'] ?? null,
            'replay' => $result['replay'] ?? false,
            'action_log' => $actionLog,
            'applied_changes' => $appliedChanges,
            'highlight_section_ids' => $highlightSectionIds,
            'diagnostic_log' => $diagnosticLog,
        ];
    }

    /**
     * @param  array<int, mixed>  $sectionOps
     * @return array<int, string>
     */
    private function collectSectionVerificationFailures(
        array $contentBefore,
        array $contentAfter,
        array $sectionOps
    ): array {
        $failures = [];

        foreach (array_values($sectionOps) as $index => $operation) {
            if (! is_array($operation)) {
                continue;
            }

            $opType = trim((string) ($operation['op'] ?? ''));
            if ($opType === 'updateText') {
                $verification = $this->verification->verifyTextEditInContent($contentBefore, $contentAfter, $operation);
            } elseif ($opType === 'updateButton') {
                $verification = $this->verification->verifyButtonEditInContent($contentBefore, $contentAfter, $operation);
            } else {
                continue;
            }

            if (($verification['verified'] ?? false) === true) {
                continue;
            }

            $failures[] = sprintf(
                'Verification failed for section op #%d (%s): %s',
                $index + 1,
                $opType,
                $verification['reason'] ?? 'unknown'
            );
        }

        return $failures;
    }

    private function resolvePage(string $siteId, ?int $pageId, ?string $pageSlug): ?Page
    {
        if ($pageId > 0) {
            $page = Page::query()
                ->where('site_id', $siteId)
                ->where('id', $pageId)
                ->first();
            if ($page) {
                return $page;
            }
        }
        if ($pageSlug !== null && $pageSlug !== '') {
            $page = Page::query()
                ->where('site_id', $siteId)
                ->where('slug', $pageSlug)
                ->first();
            if ($page) {
                return $page;
            }
        }
        return Page::query()
            ->where('site_id', $siteId)
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Ignore destructive section ops when the prompt clearly targets only the site-wide header/footer.
     *
     * @param  array<int, array<string, mixed>>  $siteOps
     * @param  array<int, array<string, mixed>>  $sectionOps
     */
    private function shouldIgnoreSectionOpsForGlobalInstruction(array $siteOps, array $sectionOps, ?string $instruction): bool
    {
        if ($siteOps === [] || $sectionOps === []) {
            return false;
        }

        $needle = Str::lower(trim((string) $instruction));
        if ($needle === '') {
            return false;
        }

        $components = array_values(array_unique(array_filter(array_map(static function ($op): string {
            return is_array($op) ? Str::lower(trim((string) ($op['component'] ?? ''))) : '';
        }, $siteOps))));

        if ($components === []) {
            return false;
        }

        $mentionsHeader = Str::contains($needle, ['header', 'top bar', 'announcement', 'navbar', 'ჰედერ', 'ჰედერში', 'შაპკ']);
        $mentionsFooter = Str::contains($needle, ['footer', 'ფუტერ', 'ფუტერში']);
        $mentionsLocalSection = Str::contains($needle, ['section', 'hero', 'pricing', 'testimonial', 'cta', 'card', 'სექცი', 'ჰირო']);

        if ($mentionsLocalSection) {
            return false;
        }

        return (in_array('header', $components, true) && $mentionsHeader)
            || (in_array('footer', $components, true) && $mentionsFooter);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function findSectionByLocalId(array $sections, string $localId): ?array
    {
        if ($localId === '') {
            return null;
        }
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

    /**
     * @param  array<int, mixed>  $operations
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSectionOperations(array $operations, array $sections): array
    {
        $normalized = [];

        foreach ($operations as $operation) {
            $op = is_array($operation) ? $operation : [];
            $opType = trim((string) ($op['op'] ?? ''));
            if ($opType === 'updateButton') {
                $sectionId = trim((string) ($op['sectionId'] ?? ''));
                $currentSection = $this->findSectionByLocalId($sections, $sectionId);
                $currentProps = is_array($currentSection['props'] ?? null) ? $currentSection['props'] : [];
                $patch = $this->buttonPatchResolver->resolvePatch($op, $currentProps);
                if ($patch !== []) {
                    $op['patch'] = $patch;
                }
            } elseif ($opType === 'updateText') {
                $sectionId = trim((string) ($op['sectionId'] ?? $op['section_id'] ?? ''));
                $currentSection = $this->findSectionByLocalId($sections, $sectionId);
                $currentProps = is_array($currentSection['props'] ?? null) ? $currentSection['props'] : [];
                $sectionType = trim((string) ($currentSection['type'] ?? $currentSection['key'] ?? ''));
                $binding = $sectionType !== '' ? $this->sectionBindings->resolveBinding($sectionType) : [];
                $editableFields = array_values(array_filter(array_map(
                    static fn ($value): string => is_string($value) ? trim($value) : '',
                    is_array($binding['editable_fields'] ?? null) ? $binding['editable_fields'] : []
                )));
                $path = $this->normalizeOperationPath($op['path'] ?? $op['parameter_path'] ?? 'headline');
                $resolvedPath = $this->resolvePreferredTextPath($path, $editableFields, $currentProps);
                if ($resolvedPath !== $path) {
                    $op['original_path'] = $path;
                    $op['path'] = $resolvedPath;
                }
            } elseif ($opType === 'insertSection') {
                $sectionType = trim((string) ($op['sectionType'] ?? $op['section_type'] ?? ''));
                $normalizedSectionType = BuilderComponentAliasResolver::normalize($sectionType);
                if ($normalizedSectionType !== '' && $normalizedSectionType !== $sectionType) {
                    $op['original_section_type'] = $sectionType;
                    $op['sectionType'] = $normalizedSectionType;
                }
            }

            $normalized[] = $op;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $editableFields
     * @param  array<string, mixed>  $currentProps
     */
    private function resolvePreferredTextPath(string $path, array $editableFields, array $currentProps): string
    {
        if ($editableFields === []) {
            return $this->resolveHeuristicTextPath($path, $currentProps);
        }

        if ($this->pathMatchesAllowedScope($path, $editableFields)) {
            return $path;
        }

        $textFields = array_values(array_filter($editableFields, fn (string $field): bool => $this->looksLikeTextEditableField($field)));
        if ($textFields === []) {
            return $path;
        }

        $aliases = [
            'title' => ['headline', 'title', 'heading', 'eyebrow', 'subtitle', 'description', 'body'],
            'headline' => ['headline', 'title', 'heading'],
            'heading' => ['headline', 'title', 'heading'],
            'eyebrow' => ['eyebrow', 'title', 'headline'],
            'subtitle' => ['subtitle', 'description', 'body'],
            'description' => ['description', 'subtitle', 'body'],
            'body' => ['body', 'description', 'subtitle'],
            'text' => ['body', 'description', 'subtitle', 'headline', 'title'],
        ];

        foreach ($aliases[$path] ?? [$path] as $candidate) {
            if ($this->pathMatchesAllowedScope($candidate, $textFields)) {
                return $candidate;
            }
        }

        if (count($textFields) === 1) {
            return $textFields[0];
        }

        foreach (['headline', 'title', 'eyebrow', 'subtitle', 'description', 'body'] as $candidate) {
            if ($this->pathMatchesAllowedScope($candidate, $textFields)) {
                return $candidate;
            }
        }

        foreach ($textFields as $field) {
            if (array_key_exists($field, $currentProps)) {
                return $field;
            }
        }

        return $textFields[0] ?? $path;
    }

    /**
     * @param  array<string, mixed>  $currentProps
     */
    private function resolveHeuristicTextPath(string $path, array $currentProps): string
    {
        $aliases = [
            'title' => ['headline', 'title', 'heading', 'eyebrow', 'subtitle', 'description', 'body'],
            'headline' => ['headline', 'title', 'heading'],
            'heading' => ['headline', 'title', 'heading'],
            'eyebrow' => ['eyebrow', 'title', 'headline'],
            'subtitle' => ['subtitle', 'description', 'body'],
            'description' => ['description', 'subtitle', 'body'],
            'body' => ['body', 'description', 'subtitle'],
            'text' => ['body', 'description', 'subtitle', 'headline', 'title'],
        ];

        foreach ($aliases[$path] ?? [$path] as $candidate) {
            if (array_key_exists($candidate, $currentProps)) {
                return $candidate;
            }
        }

        return $path;
    }

    private function looksLikeTextEditableField(string $field): bool
    {
        $normalizedField = Str::lower(trim($field));
        if ($normalizedField === '') {
            return false;
        }

        return preg_match('/(^|\.|_)(title|headline|heading|eyebrow|subtitle|description|body|text|label|caption|content|copy)$/', $normalizedField) === 1;
    }

    /**
     * @param  array<int, mixed>  $operations
     * @return array<int, string>
     */
    private function describeNormalizedOperations(array $operations, string $group): array
    {
        $lines = [];

        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                continue;
            }

            $opType = trim((string) ($operation['op'] ?? ''));
            if ($opType === '') {
                continue;
            }

            $target = trim((string) ($operation['component'] ?? $operation['sectionId'] ?? $operation['section_id'] ?? ''));
            $paths = $this->extractOperationPaths($operation);
            $originalPath = trim((string) ($operation['original_path'] ?? ''));
            if ($originalPath !== '' && $paths !== []) {
                $paths[0] = $originalPath.' => '.$paths[0];
            }
            $lines[] = sprintf(
                '%s op #%d: %s%s%s',
                ucfirst($group),
                $index + 1,
                $opType,
                $target !== '' ? ' -> '.$target : '',
                $paths !== [] ? ' ['.implode(', ', array_slice($paths, 0, 8)).']' : ''
            );
        }

        return $lines;
    }

    /**
     * @param  array<int, mixed>  $operations
     */
    private function summarizeOperationTypes(array $operations): string
    {
        $counts = [];

        foreach ($operations as $operation) {
            if (! is_array($operation)) {
                continue;
            }
            $opType = trim((string) ($operation['op'] ?? ''));
            if ($opType === '') {
                continue;
            }
            $counts[$opType] = ($counts[$opType] ?? 0) + 1;
        }

        if ($counts === []) {
            return 'none';
        }

        $parts = [];
        foreach ($counts as $opType => $count) {
            $parts[] = $opType.' ×'.$count;
        }

        return implode(', ', $parts);
    }

    private function valueFingerprint(mixed $value): string
    {
        return md5((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * Build old_value and new_value for activity log (structured for OperationLog).
     *
     * @param  array<string, mixed>  $op
     * @param  array<string, mixed>|null  $currentSection
     * @return array{old_value?: mixed, new_value?: mixed}
     */
    private function buildOldNewForLog(array $op, ?array $currentSection): array
    {
        $opType = $op['op'] ?? '';
        $props = ($currentSection !== null && is_array($currentSection['props'] ?? null)) ? $currentSection['props'] : [];

        if ($opType === 'updateSection' || $opType === 'updateButton') {
            $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
            if ($patch === []) {
                return [];
            }
            $oldVals = [];
            $newVals = [];
            foreach ($patch as $key => $newVal) {
                $oldVals[$key] = array_key_exists($key, $props) ? $props[$key] : null;
                $newVals[$key] = $newVal;
            }
            return ['old_value' => $oldVals, 'new_value' => $newVals];
        }

        if ($opType === 'replaceImage') {
            $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
            $newUrl = $patch['image'] ?? $patch['image_url'] ?? $op['image_url'] ?? null;
            $oldUrl = $props['image'] ?? $props['image_url'] ?? $props['src'] ?? null;
            return ['old_value' => $oldUrl, 'new_value' => $newUrl];
        }

        if ($opType === 'updateText') {
            $path = $this->normalizeOperationPath($op['path'] ?? $op['parameter_path'] ?? 'headline');
            return [
                'old_value' => Arr::get($props, $path),
                'new_value' => $op['value'] ?? null,
            ];
        }

        return [];
    }

    /**
     * Format a value for action log (short string).
     */
    private function formatLogValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_scalar($value)) {
            $s = (string) $value;
            return Str::length($s) > 50 ? Str::substr($s, 0, 47).'…' : $s;
        }
        if (is_array($value)) {
            return '[…]';
        }
        return '…';
    }

    /**
     * @param  array<string, mixed>|null  $currentSection  Section from content_json (props, localId, type, ...)
     */
    private function formatActionLogEntry(array $op, ?array $currentSection = null): string
    {
        $opType = $op['op'] ?? '';
        if ($opType === 'updateTheme') {
            $keys = is_array($op['patch'] ?? null) ? array_keys($op['patch']) : [];
            return 'Theme updated'.($keys !== [] ? ' ('.implode(', ', $keys).')' : '');
        }
        if ($opType === 'updateGlobalComponent') {
            $comp = $op['component'] ?? 'component';
            $keys = is_array($op['patch'] ?? null) ? array_keys($op['patch']) : [];
            return ucfirst((string) $comp).' updated'.($keys !== [] ? ' ('.implode(', ', $keys).')' : '');
        }
        if ($opType === 'updateSection' || $opType === 'updateButton') {
            $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
            if ($patch === []) {
                return 'Section updated';
            }
            $props = ($currentSection !== null && is_array($currentSection['props'] ?? null)) ? $currentSection['props'] : [];
            $parts = [];
            foreach ($patch as $key => $newVal) {
                $oldVal = array_key_exists($key, $props) ? $props[$key] : null;
                $parts[] = $key.': '.$this->formatLogValue($oldVal).' → '.$this->formatLogValue($newVal);
            }
            return 'Section updated: '.implode('; ', $parts);
        }
        if ($opType === 'replaceImage') {
            $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
            $newUrl = $patch['image'] ?? $patch['image_url'] ?? $op['image_url'] ?? null;
            $props = ($currentSection !== null && is_array($currentSection['props'] ?? null)) ? $currentSection['props'] : [];
            $oldUrl = $props['image'] ?? $props['image_url'] ?? $props['src'] ?? null;
            if ($oldUrl !== null || $newUrl !== null) {
                return 'Image: '.$this->formatLogValue($oldUrl).' → '.$this->formatLogValue($newUrl);
            }
            return 'Image replaced';
        }
        if ($opType === 'insertSection') {
            $type = BuilderComponentAliasResolver::normalize((string) ($op['sectionType'] ?? 'section'));
            return 'Section added: '.$type;
        }
        if ($opType === 'deleteSection') {
            return 'Section removed';
        }
        if ($opType === 'reorderSection') {
            return 'Sections reordered';
        }
        if ($opType === 'updateText') {
            $path = $this->normalizeOperationPath($op['path'] ?? $op['parameter_path'] ?? 'headline');
            $val = $this->formatLogValue($op['value'] ?? null);
            return 'Text updated ('.$path.'): '.$val;
        }
        return '';
    }

    private function normalizeOperationPath(mixed $pathValue): string
    {
        if (is_array($pathValue)) {
            $segments = array_values(array_filter(array_map(static function ($segment): string {
                return trim((string) $segment);
            }, $pathValue), static fn (string $segment): bool => $segment !== ''));

            return $segments !== [] ? implode('.', $segments) : 'headline';
        }

        $path = trim((string) $pathValue);

        return $path !== '' ? $path : 'headline';
    }

    /**
     * @param  array<int, mixed>  $operations
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{error: string, error_code: string}|null
     */
    private function validateOperationsAgainstSelectedTarget(array $operations, ?array $selectedTarget, array $sections, ?string $instruction): ?array
    {
        if ($selectedTarget === null || $operations === []) {
            return null;
        }

        $targetSectionId = trim((string) ($selectedTarget['section_id'] ?? ''));
        $targetSectionKey = trim((string) ($selectedTarget['section_key'] ?? $selectedTarget['component_type'] ?? ''));
        $targetPath = trim((string) ($selectedTarget['parameter_path'] ?? $selectedTarget['component_path'] ?? ''));
        $editableFields = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', is_array($selectedTarget['editable_fields'] ?? null) ? $selectedTarget['editable_fields'] : [])));
        $allowedUpdates = is_array($selectedTarget['allowed_updates'] ?? null) ? $selectedTarget['allowed_updates'] : [];
        $allowedOps = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', is_array($allowedUpdates['operation_types'] ?? null) ? $allowedUpdates['operation_types'] : [])));
        $allowedPaths = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', is_array($allowedUpdates['field_paths'] ?? null) ? $allowedUpdates['field_paths'] : [])));
        $sectionAllowedOps = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', is_array($allowedUpdates['section_operation_types'] ?? null) ? $allowedUpdates['section_operation_types'] : [])));
        $sectionAllowedPaths = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', is_array($allowedUpdates['section_field_paths'] ?? null) ? $allowedUpdates['section_field_paths'] : $editableFields)));

        $isGlobalTarget = $targetSectionId === '' && $this->resolveGlobalComponentNameFromTarget($targetSectionKey) !== null;
        if (! $isGlobalTarget && $targetSectionId === '') {
            return [
                'error' => 'The selected target does not resolve to a page section instance.',
                'error_code' => self::ERROR_SELECTED_TARGET_UNMAPPABLE,
            ];
        }
        if (! $isGlobalTarget && $targetSectionId !== '' && $this->findSectionByLocalId($sections, $targetSectionId) === null) {
            return [
                'error' => 'Could not find the selected target in the current page.',
                'error_code' => 'target_not_found',
            ];
        }

        if ($targetPath !== '' && $allowedPaths === [] && ! $this->instructionRequestsBroaderScope($instruction)) {
            return [
                'error' => 'The selected element is not mapped to any safe editable field paths.',
                'error_code' => self::ERROR_SELECTED_TARGET_UNMAPPABLE,
            ];
        }

        $allowGlobalScope = $this->instructionRequestsGlobalScope($instruction);
        $allowBroaderSameSection = ! $allowGlobalScope && $this->instructionRequestsBroaderSameSection($instruction);
        $effectiveAllowedOps = $allowBroaderSameSection && $sectionAllowedOps !== [] ? $sectionAllowedOps : $allowedOps;
        $effectiveAllowedPaths = $allowBroaderSameSection && $sectionAllowedPaths !== [] ? $sectionAllowedPaths : $allowedPaths;

        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                continue;
            }

            $opType = trim((string) ($operation['op'] ?? ''));
            if ($opType === '') {
                continue;
            }

            if ($allowGlobalScope) {
                continue;
            }

            if ($isGlobalTarget) {
                $globalValidation = $this->validateGlobalTargetOperation(
                    $operation,
                    $targetSectionKey,
                    $effectiveAllowedOps,
                    $effectiveAllowedPaths
                );
                if ($globalValidation !== null) {
                    return $globalValidation;
                }
                continue;
            }

            if ($effectiveAllowedOps !== [] && ! in_array($opType, $effectiveAllowedOps, true)) {
                return [
                    'error' => "Operation {$opType} is outside the selected target scope.",
                    'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
                ];
            }

            $sectionId = trim((string) ($operation['sectionId'] ?? $operation['section_id'] ?? ''));
            if (in_array($opType, ['updateSection', 'updateText', 'replaceImage', 'updateButton', 'deleteSection', 'reorderSection'], true) && $sectionId === '') {
                return [
                    'error' => 'The selected target update is missing a sectionId.',
                    'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
                ];
            }
            if ($targetSectionId !== '' && $sectionId !== '' && $sectionId !== $targetSectionId) {
                return [
                    'error' => 'The AI attempted to modify a different section than the selected target.',
                    'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
                ];
            }

            $candidatePaths = $this->extractOperationPaths($operation);
            if ($effectiveAllowedPaths === [] || $candidatePaths === []) {
                continue;
            }

            foreach ($candidatePaths as $candidatePath) {
                if ($this->pathMatchesAllowedScope($candidatePath, $effectiveAllowedPaths)) {
                    continue;
                }

                return [
                    'error' => "The AI attempted to update '{$candidatePath}' which is outside the selected target scope.",
                    'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<int, string>  $allowedOps
     * @param  array<int, string>  $allowedPaths
     * @return array{error: string, error_code: string}|null
     */
    private function validateGlobalTargetOperation(array $operation, string $targetSectionKey, array $allowedOps, array $allowedPaths): ?array
    {
        $opType = trim((string) ($operation['op'] ?? ''));
        $expectedComponent = $this->resolveGlobalComponentNameFromTarget($targetSectionKey);
        if ($expectedComponent === null) {
            return [
                'error' => 'The selected global target could not be resolved.',
                'error_code' => self::ERROR_SELECTED_TARGET_UNMAPPABLE,
            ];
        }

        if ($allowedOps !== [] && ! in_array($opType, $allowedOps, true)) {
            return [
                'error' => "Operation {$opType} is outside the selected global target scope.",
                'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
            ];
        }

        if ($opType !== 'updateGlobalComponent') {
            return [
                'error' => 'Only global component updates are allowed for the selected header/footer target.',
                'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
            ];
        }

        $component = Str::lower(trim((string) ($operation['component'] ?? '')));
        if ($component !== $expectedComponent) {
            return [
                'error' => 'The AI attempted to update a different global component than the selected target.',
                'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
            ];
        }

        if ($allowedPaths === []) {
            return null;
        }

        foreach ($this->extractOperationPaths($operation) as $candidatePath) {
            if ($this->pathMatchesAllowedScope($candidatePath, $allowedPaths)) {
                continue;
            }

            return [
                'error' => "The AI attempted to update '{$candidatePath}' which is outside the selected global target scope.",
                'error_code' => self::ERROR_SELECTED_TARGET_SCOPE_VIOLATION,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<int, string>
     */
    private function extractOperationPaths(array $operation): array
    {
        $opType = trim((string) ($operation['op'] ?? ''));

        if ($opType === 'updateText') {
            return [$this->normalizeOperationPath($operation['path'] ?? $operation['parameter_path'] ?? 'headline')];
        }

        if (in_array($opType, ['updateSection', 'updateButton', 'updateGlobalComponent', 'updateTheme'], true)) {
            $patch = is_array($operation['patch'] ?? null) ? $operation['patch'] : [];
            return $this->flattenPatchPaths($patch);
        }

        if ($opType === 'replaceImage') {
            $patch = is_array($operation['patch'] ?? null) ? $operation['patch'] : [];
            $paths = $this->flattenPatchPaths($patch);
            if ($paths !== []) {
                return $paths;
            }

            if (isset($operation['image_url']) && trim((string) $operation['image_url']) !== '') {
                return ['image_url'];
            }

            return ['image'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<int, string>
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
                $paths = [...$paths, ...$this->flattenPatchPaths($value, $path)];
                continue;
            }

            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<int, string>  $allowedPaths
     */
    private function pathMatchesAllowedScope(string $candidatePath, array $allowedPaths): bool
    {
        $candidate = trim($candidatePath);
        if ($candidate === '') {
            return false;
        }

        foreach ($allowedPaths as $allowedPath) {
            $allowed = trim($allowedPath);
            if ($allowed === '') {
                continue;
            }

            if (
                $candidate === $allowed
                || Str::startsWith($candidate, $allowed.'.')
                || Str::startsWith($allowed, $candidate.'.')
            ) {
                return true;
            }

            $candidateRoot = Str::before($candidate, '.');
            $allowedRoot = Str::before($allowed, '.');
            if ($candidateRoot !== '' && $candidateRoot === $allowedRoot && ! Str::contains($allowed, '.')) {
                return true;
            }
        }

        return false;
    }

    private function resolveGlobalComponentNameFromTarget(string $targetSectionKey): ?string
    {
        $normalized = Str::lower(trim($targetSectionKey));
        if ($normalized === '') {
            return null;
        }

        if (Str::contains($normalized, 'header')) {
            return 'header';
        }

        if (Str::contains($normalized, 'footer')) {
            return 'footer';
        }

        return null;
    }

    private function instructionRequestsBroaderSameSection(?string $instruction): bool
    {
        $needle = Str::lower(trim((string) $instruction));
        if ($needle === '') {
            return false;
        }

        return Str::contains($needle, [
            'this section',
            'this component',
            'this block',
            'this header',
            'this footer',
            'entire section',
            'whole section',
            'whole component',
            'ამ სექცი',
            'ამ კომპონენტ',
            'ამ ბლოკ',
            'ამ ჰედერ',
            'ამ ფუტერ',
            'მთელი სექცი',
            'მთელი კომპონენტ',
        ]);
    }

    private function instructionRequestsGlobalScope(?string $instruction): bool
    {
        $needle = Str::lower(trim((string) $instruction));
        if ($needle === '') {
            return false;
        }

        return Str::contains($needle, [
            'entire page',
            'whole page',
            'all sections',
            'entire site',
            'whole site',
            'site-wide',
            'global',
            'every page',
            'add section',
            'remove section',
            'delete section',
            'reorder section',
            'move section',
            'მთელი გვერდ',
            'მთელ საიტ',
            'ყველა სექცი',
            'გლობალ',
            'დაამატე სექცი',
            'წაშალე სექცი',
            'გადაადგილე სექცი',
        ]);
    }

    private function instructionRequestsBroaderScope(?string $instruction): bool
    {
        return $this->instructionRequestsBroaderSameSection($instruction) || $this->instructionRequestsGlobalScope($instruction);
    }
}
