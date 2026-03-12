<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\Project;
use App\Services\AiAgentExecutorService;
use App\Services\AiChangeSetToContentMergeService;
use App\Services\AiContentPatchProposalService;
use App\Services\AiContentPatchService;
use App\Services\AiInterpretCommandService;
use App\Services\AiRevisionService;
use App\Services\AiSiteEditorAnalyzeService;
use App\Services\AiSiteEditorSiteOpsService;
use App\Services\OperationLogService;
use App\Services\SiteProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProjectAiContentPatchController extends Controller
{
    public function __construct(
        protected AiContentPatchService $patcher,
        protected OperationLogService $operationLogs,
        protected AiContentPatchProposalService $proposalService,
        protected AiRevisionService $aiRevisions,
        protected AiInterpretCommandService $interpretCommandService,
        protected AiSiteEditorAnalyzeService $analyzeService,
        protected AiChangeSetToContentMergeService $changeSetToPatch,
        protected SiteProvisioningService $siteProvisioning,
        protected AiSiteEditorSiteOpsService $siteOpsService,
        protected AiAgentExecutorService $agentExecutor
    ) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'page_id' => ['nullable', 'integer'],
            'page_slug' => ['nullable', 'string', 'max:191'],
            'mode' => ['nullable', 'string', 'in:merge,replace'],
            'patch_format' => ['nullable', 'string', 'in:content_merge,rfc6902'],
            'patch' => ['required', 'array'],
            'publish' => ['nullable', 'boolean'],
            'instruction' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $this->patcher->apply(
            $project,
            $validated,
            $request->user()?->id
        );

        $status = $result['replay']
            ? OperationLog::STATUS_INFO
            : OperationLog::STATUS_SUCCESS;
        $event = $result['replay']
            ? 'ai_content_patch_replay'
            : 'ai_content_patch_applied';

        $this->operationLogs->logBuild(
            project: $project,
            event: $event,
            status: $status,
            message: $result['replay']
                ? 'AI content patch request replayed using idempotency key.'
                : 'AI content patch created a new CMS revision.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'identifier' => $validated['idempotency_key'] ?? null,
                'context' => [
                    'page_id' => $result['page']->id,
                    'page_slug' => $result['page']->slug,
                    'revision_id' => $result['revision']->id,
                    'revision_version' => $result['revision']->version,
                    'publish' => (bool) ($validated['publish'] ?? false),
                    'mode' => $validated['mode'] ?? 'merge',
                    'instruction' => $validated['instruction'] ?? null,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'replay' => $result['replay'],
            'page' => [
                'id' => $result['page']->id,
                'slug' => $result['page']->slug,
                'status' => $result['page']->status,
            ],
            'revision' => [
                'id' => $result['revision']->id,
                'version' => $result['revision']->version,
                'published_at' => $result['revision']->published_at?->toISOString(),
                'content_json' => $result['revision']->content_json,
            ],
        ]);
    }

    /**
     * Interpret a natural-language command and return a ChangeSet (no apply).
     * Frontend applies the ChangeSet via execute. All changes apply only to the project from the URL (open project).
     */
    public function interpretCommand(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'instruction' => ['required', 'string', 'max:3000'],
            'page_context' => ['nullable', 'array'],
            'page_context.page_slug' => ['nullable', 'string', 'max:191'],
            'page_context.page_id' => ['nullable', 'integer'],
            'page_context.sections' => ['nullable', 'array'],
            'page_context.sections.*.id' => ['nullable', 'string'],
            'page_context.sections.*.type' => ['nullable', 'string'],
            'page_context.sections.*.label' => ['nullable', 'string'],
            'page_context.sections.*.editable_fields' => ['nullable', 'array'],
            'page_context.sections.*.editable_fields.*' => ['string'],
            'page_context.sections.*.props' => ['nullable', 'array'],
            'page_context.component_types' => ['nullable', 'array'],
            'page_context.component_types.*' => ['string'],
            'page_context.global_components' => ['nullable', 'array'],
            'page_context.global_components.*.id' => ['required', 'string'],
            'page_context.global_components.*.label' => ['nullable', 'string'],
            'page_context.global_components.*.editable_fields' => ['nullable', 'array'],
            'page_context.global_components.*.editable_fields.*' => ['string'],
            'page_context.theme' => ['nullable', 'array'],
            'page_context.selected_section_id' => ['nullable', 'string'],
            'page_context.selected_parameter_path' => ['nullable', 'string', 'max:500'],
            'page_context.selected_element_id' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target' => ['nullable', 'array'],
            'page_context.selected_target.section_id' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target.section_key' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target.component_type' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target.component_name' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target.parameter_path' => ['nullable', 'string', 'max:500'],
            'page_context.selected_target.component_path' => ['nullable', 'string', 'max:500'],
            'page_context.selected_target.element_id' => ['nullable', 'string', 'max:191'],
            'page_context.selected_target.editable_fields' => ['nullable', 'array'],
            'page_context.selected_target.editable_fields.*' => ['string'],
            'page_context.selected_target.variants' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates' => ['nullable', 'array'],
            'page_context.selected_target.current_breakpoint' => ['nullable', 'string', 'in:desktop,tablet,mobile'],
            'page_context.selected_target.current_interaction_state' => ['nullable', 'string', 'in:normal,hover,focus,active'],
            'page_context.selected_target.responsive_context' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates.scope' => ['nullable', 'string', 'in:element,section'],
            'page_context.selected_target.allowed_updates.operationTypes' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates.operationTypes.*' => ['string'],
            'page_context.selected_target.allowed_updates.fieldPaths' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates.fieldPaths.*' => ['string'],
            'page_context.selected_target.allowed_updates.sectionOperationTypes' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates.sectionOperationTypes.*' => ['string'],
            'page_context.selected_target.allowed_updates.sectionFieldPaths' => ['nullable', 'array'],
            'page_context.selected_target.allowed_updates.sectionFieldPaths.*' => ['string'],
            'page_context.locale' => ['nullable', 'string', 'max:20'],
            'page_context.recent_edits' => ['nullable', 'string', 'max:500'],
        ]);

        $ctx = $validated['page_context'] ?? [];
        $selectedTarget = $this->normalizeSelectedTargetContext($ctx['selected_target'] ?? null);
        $site = $this->siteProvisioning->provisionForProject($project);
        $serverTheme = is_array($site->theme_settings ?? null) ? $site->theme_settings : [];
        $pageContext = [
            'page_slug' => $ctx['page_slug'] ?? null,
            'page_id' => $ctx['page_id'] ?? null,
            'sections' => $ctx['sections'] ?? [],
            'component_types' => $ctx['component_types'] ?? null,
            'global_components' => $ctx['global_components'] ?? null,
            'theme' => $serverTheme !== [] ? $serverTheme : ($ctx['theme'] ?? null),
            'selected_section_id' => $selectedTarget['section_id'] ?? ($ctx['selected_section_id'] ?? null),
            'selected_parameter_path' => $selectedTarget['parameter_path']
                ?? $selectedTarget['component_path']
                ?? (isset($ctx['selected_parameter_path']) && trim((string) $ctx['selected_parameter_path']) !== '' ? trim((string) $ctx['selected_parameter_path']) : null),
            'selected_element_id' => $selectedTarget['element_id'] ?? (isset($ctx['selected_element_id']) && trim((string) $ctx['selected_element_id']) !== '' ? trim((string) $ctx['selected_element_id']) : null),
            'selected_target' => $selectedTarget,
            'locale' => $ctx['locale'] ?? null,
            'recent_edits' => isset($ctx['recent_edits']) && trim((string) $ctx['recent_edits']) !== '' ? trim((string) $ctx['recent_edits']) : null,
        ];

        $result = $this->interpretCommandService->interpret(
            $validated['instruction'],
            $pageContext
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to interpret command.',
                'raw_response' => $result['raw_response'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'change_set' => $result['change_set'],
            'summary' => $result['change_set']['summary'] ?? [],
        ]);
    }

    /**
     * Propose a JSON patch from a natural-language instruction (no apply).
     */
    public function propose(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'page_id' => ['nullable', 'integer', 'min:1'],
            'instruction' => ['required', 'string', 'max:3000'],
        ]);

        $result = $this->proposalService->propose(
            $project,
            isset($validated['page_id']) ? (int) $validated['page_id'] : null,
            $validated['instruction']
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to generate patch.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'proposed_patch' => $result['proposed_patch'],
            'patch_format' => $result['patch_format'] ?? 'rfc6902',
        ]);
    }

    /**
     * List AI revisions for the project's site, optionally scoped to a page.
     */
    public function indexRevisions(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $site = $project->site()->first();
        if (! $site) {
            return response()->json(['revisions' => []], 200);
        }

        $pageId = $request->integer('page_id');
        $page = $pageId > 0
            ? $site->pages()->find($pageId)
            : null;

        $revisions = $this->aiRevisions->listRevisions($site, $page);

        return response()->json([
            'revisions' => $revisions->map(fn ($r) => [
                'id' => $r->id,
                'page_id' => $r->page_id,
                'page_slug' => $r->page?->slug,
                'prompt_text' => $r->prompt_text,
                'snapshot_after' => $r->snapshot_after,
                'created_at' => $r->created_at?->toIso8601String(),
                'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            ]),
        ]);
    }

    /**
     * Get a single AI revision for preview (returns snapshot_after).
     */
    public function showRevision(Project $project, int $aiRevision): JsonResponse
    {
        $this->authorize('update', $project);

        $site = $project->site()->first();
        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $revision = $this->aiRevisions->getRevision($aiRevision, $site);
        if (! $revision) {
            return response()->json(['error' => 'Revision not found'], 404);
        }

        return response()->json([
            'id' => $revision->id,
            'page_id' => $revision->page_id,
            'page_slug' => $revision->page?->slug,
            'prompt_text' => $revision->prompt_text,
            'snapshot_before' => $revision->snapshot_before,
            'snapshot_after' => $revision->snapshot_after,
            'applied_patch' => $revision->applied_patch,
            'created_at' => $revision->created_at?->toIso8601String(),
            'user' => $revision->user ? ['id' => $revision->user->id, 'name' => $revision->user->name] : null,
        ]);
    }

    /**
     * Rollback to an AI revision (restores snapshot as new page revision).
     */
    public function rollback(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ai_revision_id' => ['required', 'integer', 'min:1'],
        ]);

        $site = $project->site()->first();
        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $result = $this->aiRevisions->rollbackToRevision((int) $validated['ai_revision_id'], $site);

        $this->operationLogs->logBuild(
            project: $project,
            event: 'ai_revision_rollback',
            status: OperationLog::STATUS_SUCCESS,
            message: 'Rolled back to AI revision.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'context' => [
                    'page_id' => $result['page']->id,
                    'revision_id' => $result['revision']->id,
                    'diverged' => $result['diverged'],
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'diverged' => $result['diverged'],
            'page' => [
                'id' => $result['page']->id,
                'slug' => $result['page']->slug,
            ],
            'revision' => [
                'id' => $result['revision']->id,
                'version' => $result['revision']->version,
                'content_json' => $result['revision']->content_json,
            ],
        ]);
    }

    /**
     * Content analysis for AI Site Editor: returns page structure (pages, sections, editable fields).
     */
    public function analyze(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $locale = $request->query('locale');
        $result = $this->analyzeService->analyze($project, $locale ? (string) $locale : null);

        return response()->json([
            'success' => true,
            ...$result,
            'available_components' => config('builder-component-registry.component_ids', []),
        ]);
    }

    /**
     * Execute a ChangeSet from the AI interpreter (apply operations to page content).
     * All operations apply only to the project from the URL (panel/projects/{project}/...).
     * The request body must not contain project_id; the open project is determined by the route.
     */
    public function executeFromChangeSet(Request $request, Project $project): JsonResponse
    {
        try {
            $this->authorize('update', $project);
        } catch (\Throwable $e) {
            Log::warning('ai_site_editor.execute.auth_failed', ['project_id' => $project->id, 'message' => $e->getMessage()]);
            throw $e;
        }

        Log::info('ai_site_editor.execute.request', [
            'project_id' => $project->id,
            'has_change_set' => $request->has('change_set'),
            'change_set_keys' => is_array($request->input('change_set')) ? array_keys($request->input('change_set')) : [],
            'operations_count' => is_array($request->input('change_set.operations')) ? count($request->input('change_set.operations')) : 0,
            'page_id' => $request->input('page_id'),
            'page_slug' => $request->input('page_slug'),
        ]);

        try {
            $validated = $request->validate([
                'page_id' => ['nullable', 'integer', 'min:1'],
                'page_slug' => ['nullable', 'string', 'max:191'],
                'locale' => ['nullable', 'string', 'max:16'],
                'change_set' => ['required', 'array'],
                'change_set.operations' => ['nullable', 'array'],
                'change_set.summary' => ['nullable', 'array'],
                'instruction' => ['nullable', 'string', 'max:5000'],
                'publish' => ['nullable', 'boolean'],
                'selected_target' => ['nullable', 'array'],
                'selected_target.section_id' => ['nullable', 'string', 'max:191'],
                'selected_target.section_key' => ['nullable', 'string', 'max:191'],
                'selected_target.component_type' => ['nullable', 'string', 'max:191'],
                'selected_target.component_name' => ['nullable', 'string', 'max:191'],
                'selected_target.parameter_path' => ['nullable', 'string', 'max:500'],
                'selected_target.component_path' => ['nullable', 'string', 'max:500'],
                'selected_target.element_id' => ['nullable', 'string', 'max:191'],
                'selected_target.editable_fields' => ['nullable', 'array'],
                'selected_target.editable_fields.*' => ['string'],
                'selected_target.variants' => ['nullable', 'array'],
                'selected_target.allowed_updates' => ['nullable', 'array'],
                'selected_target.current_breakpoint' => ['nullable', 'string', 'in:desktop,tablet,mobile'],
                'selected_target.current_interaction_state' => ['nullable', 'string', 'in:normal,hover,focus,active'],
                'selected_target.responsive_context' => ['nullable', 'array'],
                'selected_target.allowed_updates.scope' => ['nullable', 'string', 'in:element,section'],
                'selected_target.allowed_updates.operationTypes' => ['nullable', 'array'],
                'selected_target.allowed_updates.operationTypes.*' => ['string'],
                'selected_target.allowed_updates.fieldPaths' => ['nullable', 'array'],
                'selected_target.allowed_updates.fieldPaths.*' => ['string'],
                'selected_target.allowed_updates.sectionOperationTypes' => ['nullable', 'array'],
                'selected_target.allowed_updates.sectionOperationTypes.*' => ['string'],
                'selected_target.allowed_updates.sectionFieldPaths' => ['nullable', 'array'],
                'selected_target.allowed_updates.sectionFieldPaths.*' => ['string'],
            ]);
        } catch (ValidationException $e) {
            $diagnosticLog = $this->buildValidationDiagnosticLog($e->errors());
            Log::warning('ai_site_editor.execute.validation_failed', [
                'project_id' => $project->id,
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
                'diagnostic_log' => $diagnosticLog,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Validation failed.',
                'error_code' => AiAgentExecutorService::ERROR_VALIDATION_FAILED,
                'errors' => $e->errors(),
                'diagnostic_log' => $diagnosticLog,
            ]);
        }

        // Ensure operations is a sequential array (JSON may send object or omit key)
        $changeSet = $validated['change_set'];
        $ops = isset($changeSet['operations']) && is_array($changeSet['operations']) ? $changeSet['operations'] : [];
        $changeSet['operations'] = array_is_list($ops) ? $ops : array_values($ops);

        try {
            $selectedTarget = $this->normalizeSelectedTargetContext($validated['selected_target'] ?? null);
            $execResult = $this->agentExecutor->execute($project, $changeSet, [
                'page_id' => $validated['page_id'] ?? null,
                'page_slug' => $validated['page_slug'] ?? null,
                'locale' => $validated['locale'] ?? null,
                'instruction' => $validated['instruction'] ?? null,
                'publish' => (bool) ($validated['publish'] ?? false),
                'actor_id' => $request->user()?->id,
                'selected_target' => $selectedTarget,
            ]);
        } catch (\Throwable $e) {
            Log::error('ai_site_editor.execute.throwable', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Execution failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => 'executor_exception',
                'errors' => ['executor' => [$e->getMessage()]],
            ], 422);
        }

        if (! ($execResult['success'] ?? false)) {
            Log::warning('ai_site_editor.execute.executor_failed', [
                'project_id' => $project->id,
                'error' => $execResult['error'] ?? null,
                'error_code' => $execResult['error_code'] ?? null,
                'errors' => $execResult['errors'] ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $execResult['error'],
                'error' => $execResult['error'],
                'error_code' => $execResult['error_code'] ?? null,
                'errors' => $execResult['errors'] ?? null,
                'diagnostic_log' => is_array($execResult['diagnostic_log'] ?? null) ? $execResult['diagnostic_log'] : [],
            ]);
        }

        $result = $execResult;
        if (
            ! isset($result['page'])
            || ! $result['page'] instanceof \App\Models\Page
        ) {
            Log::error('ai_site_editor.execute.invalid_result', [
                'project_id' => $project->id,
                'result_keys' => array_keys($result),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Execution produced an invalid result.',
                'error' => 'Execution produced an invalid result.',
                'error_code' => 'executor_invalid_result',
                'diagnostic_log' => ['Executor returned no page object.'],
            ]);
        }

        $page = $result['page'];
        $revision = $result['revision'];
        $appliedChanges = is_array($result['applied_changes'] ?? null) ? $result['applied_changes'] : [];
        $diagnosticLog = is_array($result['diagnostic_log'] ?? null) ? $result['diagnostic_log'] : [];

        Log::info('ai_site_editor.execute.result', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'page_slug' => $page->slug,
            'revision_id' => $revision?->id,
            'revision_version' => $revision?->version,
            'applied_changes_count' => count($appliedChanges),
            'diagnostic_log' => $diagnosticLog,
        ]);

        $this->operationLogs->logBuild(
            project: $project,
            event: 'ai_site_editor_execute',
            status: OperationLog::STATUS_SUCCESS,
            message: 'AI Site Editor applied change set.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'context' => [
                    'page_id' => $page->id,
                    'page_slug' => $page->slug,
                    'revision_id' => $revision?->id,
                    'instruction' => $validated['instruction'] ?? null,
                    'diagnostic_log' => $diagnosticLog,
                ],
                'applied_changes' => $appliedChanges,
            ]
        );

        return response()->json([
            'success' => true,
            'replay' => $result['replay'],
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'status' => $page->status,
            ],
            'revision' => $revision ? [
                'id' => $revision->id,
                'version' => $revision->version,
                'published_at' => $revision->published_at?->toISOString(),
                'content_json' => $revision->content_json,
            ] : null,
            'summary' => $validated['change_set']['summary'] ?? [],
            'action_log' => is_array($result['action_log'] ?? null) ? $result['action_log'] : [],
            'applied_changes' => $appliedChanges,
            'highlight_section_ids' => is_array($result['highlight_section_ids'] ?? null) ? $result['highlight_section_ids'] : [],
            'diagnostic_log' => $diagnosticLog,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param  mixed  $input
     * @return array<string, mixed>|null
     */
    private function normalizeSelectedTargetContext(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $editableFields = isset($input['editable_fields']) && is_array($input['editable_fields'])
            ? array_values(array_filter(array_map(static fn ($field) => is_string($field) ? trim($field) : '', $input['editable_fields'])))
            : [];
        $allowedUpdates = isset($input['allowed_updates']) && is_array($input['allowed_updates']) ? $input['allowed_updates'] : [];

        return [
            'section_id' => isset($input['section_id']) && trim((string) $input['section_id']) !== '' ? trim((string) $input['section_id']) : null,
            'section_key' => isset($input['section_key']) && trim((string) $input['section_key']) !== '' ? trim((string) $input['section_key']) : null,
            'component_type' => isset($input['component_type']) && trim((string) $input['component_type']) !== '' ? trim((string) $input['component_type']) : null,
            'component_name' => isset($input['component_name']) && trim((string) $input['component_name']) !== '' ? trim((string) $input['component_name']) : null,
            'parameter_path' => isset($input['parameter_path']) && trim((string) $input['parameter_path']) !== '' ? trim((string) $input['parameter_path']) : null,
            'component_path' => isset($input['component_path']) && trim((string) $input['component_path']) !== '' ? trim((string) $input['component_path']) : null,
            'element_id' => isset($input['element_id']) && trim((string) $input['element_id']) !== '' ? trim((string) $input['element_id']) : null,
            'editable_fields' => $editableFields,
            'variants' => isset($input['variants']) && is_array($input['variants']) ? $input['variants'] : null,
            'current_breakpoint' => isset($input['current_breakpoint']) && in_array($input['current_breakpoint'], ['desktop', 'tablet', 'mobile'], true)
                ? $input['current_breakpoint']
                : 'desktop',
            'current_interaction_state' => isset($input['current_interaction_state']) && in_array($input['current_interaction_state'], ['normal', 'hover', 'focus', 'active'], true)
                ? $input['current_interaction_state']
                : 'normal',
            'responsive_context' => isset($input['responsive_context']) && is_array($input['responsive_context']) ? $input['responsive_context'] : null,
            'allowed_updates' => [
                'scope' => isset($allowedUpdates['scope']) && in_array($allowedUpdates['scope'], ['element', 'section'], true) ? $allowedUpdates['scope'] : 'section',
                'operation_types' => isset($allowedUpdates['operationTypes']) && is_array($allowedUpdates['operationTypes'])
                    ? array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $allowedUpdates['operationTypes'])))
                    : [],
                'field_paths' => isset($allowedUpdates['fieldPaths']) && is_array($allowedUpdates['fieldPaths'])
                    ? array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $allowedUpdates['fieldPaths'])))
                    : [],
                'section_operation_types' => isset($allowedUpdates['sectionOperationTypes']) && is_array($allowedUpdates['sectionOperationTypes'])
                    ? array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $allowedUpdates['sectionOperationTypes'])))
                    : [],
                'section_field_paths' => isset($allowedUpdates['sectionFieldPaths']) && is_array($allowedUpdates['sectionFieldPaths'])
                    ? array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $allowedUpdates['sectionFieldPaths'])))
                    : $editableFields,
            ],
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, string>
     */
    private function buildValidationDiagnosticLog(array $errors): array
    {
        $lines = [];

        foreach ($errors as $path => $messages) {
            $filteredMessages = array_values(array_filter(array_map(
                static fn ($message): string => trim((string) $message),
                is_array($messages) ? $messages : []
            )));

            if ($filteredMessages === []) {
                continue;
            }

            $lines[] = sprintf('Validation failed at %s: %s', $path, implode(' | ', $filteredMessages));
        }

        return $lines === [] ? ['Validation failed with no field-level details.'] : $lines;
    }

}
