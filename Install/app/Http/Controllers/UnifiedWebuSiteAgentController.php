<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\UnifiedAgent\UnifiedWebuSiteAgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Unified Webu Site Agent API.
 * Single entry point for AI site editing. Replaces split analyze/interpret/execute flow.
 */
class UnifiedWebuSiteAgentController extends Controller
{
    public function __construct(
        protected UnifiedWebuSiteAgentOrchestrator $orchestrator
    ) {}

    /**
     * Run the unified agent for an edit request.
     * POST /panel/projects/{project}/unified-agent/edit
     */
    public function edit(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'instruction' => ['required', 'string', 'max:5000'],
            'page_slug' => ['nullable', 'string', 'max:191'],
            'page_id' => ['nullable', 'integer', 'min:1'],
            'locale' => ['nullable', 'string', 'max:16'],
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
            'selected_target.allowed_updates' => ['nullable', 'array'],
            'recent_edits' => ['nullable', 'string', 'max:500'],
            'project_mode' => ['nullable', 'string', 'in:builder,cms,code'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $selectedTarget = $this->normalizeSelectedTarget($validated['selected_target'] ?? null);

        $result = $this->orchestrator->runEdit($project, [
            'instruction' => $validated['instruction'],
            'page_slug' => $validated['page_slug'] ?? null,
            'page_id' => $validated['page_id'] ?? null,
            'locale' => $validated['locale'] ?? null,
            'selected_target' => $selectedTarget,
            'recent_edits' => $validated['recent_edits'] ?? null,
            'project_mode' => $validated['project_mode'] ?? 'builder',
            'publish' => (bool) ($validated['publish'] ?? false),
            'actor_id' => $request->user()?->id,
        ]);

        if (! ($result['success'] ?? false)) {
            Log::info('unified_agent.edit.failed', [
                'project_id' => $project->id,
                'error' => $result['error'] ?? null,
                'error_code' => $result['error_code'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Request failed.',
                'error_code' => $result['error_code'] ?? null,
                'diagnostic_log' => $result['diagnostic_log'] ?? [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'change_set' => $result['change_set'] ?? null,
            'summary' => $result['summary'] ?? [],
            'page' => $result['page'] ?? null,
            'revision' => $result['revision'] ?? null,
            'action_log' => $result['action_log'] ?? [],
            'applied_changes' => $result['applied_changes'] ?? [],
            'highlight_section_ids' => $result['highlight_section_ids'] ?? [],
            'diagnostic_log' => $result['diagnostic_log'] ?? [],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param  mixed  $input
     * @return array<string, mixed>|null
     */
    private function normalizeSelectedTarget(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $allowedUpdates = $input['allowed_updates'] ?? [];
        $editableFields = array_values(array_filter(array_map(
            static fn ($f) => is_string($f) ? trim($f) : '',
            $input['editable_fields'] ?? []
        )));

        return [
            'section_id' => trim((string) ($input['section_id'] ?? '')) ?: null,
            'section_key' => trim((string) ($input['section_key'] ?? '')) ?: null,
            'component_type' => trim((string) ($input['component_type'] ?? '')) ?: null,
            'component_name' => trim((string) ($input['component_name'] ?? '')) ?: null,
            'parameter_path' => trim((string) ($input['parameter_path'] ?? '')) ?: null,
            'component_path' => trim((string) ($input['component_path'] ?? '')) ?: null,
            'element_id' => trim((string) ($input['element_id'] ?? '')) ?: null,
            'editable_fields' => $editableFields,
            'allowed_updates' => [
                'operation_types' => $allowedUpdates['operationTypes'] ?? $allowedUpdates['operation_types'] ?? [],
                'field_paths' => $allowedUpdates['fieldPaths'] ?? $allowedUpdates['field_paths'] ?? [],
                'section_operation_types' => $allowedUpdates['sectionOperationTypes'] ?? $allowedUpdates['section_operation_types'] ?? [],
                'section_field_paths' => $allowedUpdates['sectionFieldPaths'] ?? $allowedUpdates['section_field_paths'] ?? $editableFields,
            ],
        ];
    }
}
