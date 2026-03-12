<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiProjectFileEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webu AI project file editing. Extends existing chat; no separate product.
 * POST body: { message: string }. Returns { success, summary, changes, error?, no_change_reason?, files_changed?: boolean }.
 */
class ProjectAiProjectEditController extends Controller
{
    public function __construct(
        protected AiProjectFileEditService $aiProjectEdit
    ) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'selected_element' => ['sometimes', 'nullable', 'array'],
            'selected_element.section_id' => ['required_with:selected_element', 'string', 'max:255'],
            'selected_element.parameter_path' => ['required_with:selected_element', 'string', 'max:255'],
            'selected_element.element_id' => ['required_with:selected_element', 'string', 'max:255'],
            'selected_element.page_id' => ['sometimes', 'nullable'],
            'selected_element.page_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selected_element.component_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'selected_element.component_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selected_element.component_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selected_element.editable_fields' => ['sometimes', 'nullable', 'array'],
            'selected_element.editable_fields.*' => ['string', 'max:255'],
            'selected_element.variants' => ['sometimes', 'nullable', 'array'],
            'selected_element.allowed_updates' => ['sometimes', 'nullable', 'array'],
            'design_pattern_hints' => ['sometimes', 'nullable', 'array'],
            'design_pattern_hints.*' => ['string', 'max:500'],
        ]);

        $selectedElement = isset($validated['selected_element'])
            ? [
                'section_id' => $validated['selected_element']['section_id'] ?? '',
                'parameter_path' => $validated['selected_element']['parameter_path'] ?? '',
                'element_id' => $validated['selected_element']['element_id'] ?? '',
                'page_id' => $validated['selected_element']['page_id'] ?? null,
                'page_slug' => $validated['selected_element']['page_slug'] ?? null,
                'component_path' => $validated['selected_element']['component_path'] ?? null,
                'component_type' => $validated['selected_element']['component_type'] ?? null,
                'component_name' => $validated['selected_element']['component_name'] ?? null,
                'editable_fields' => $validated['selected_element']['editable_fields'] ?? [],
                'variants' => $validated['selected_element']['variants'] ?? null,
                'allowed_updates' => $validated['selected_element']['allowed_updates'] ?? null,
            ]
            : null;

        $designPatternHints = isset($validated['design_pattern_hints']) && is_array($validated['design_pattern_hints'])
            ? array_values(array_filter(array_map('trim', $validated['design_pattern_hints'])))
            : null;

        $result = $this->aiProjectEdit->run($project, $validated['message'], $selectedElement, $designPatternHints);

        return response()->json([
            'success' => $result['success'],
            'summary' => $result['summary'],
            'changes' => $result['changes'],
            'files_changed' => ! empty($result['changes']),
            'diagnostic_log' => is_array($result['diagnostic_log'] ?? null) ? $result['diagnostic_log'] : [],
            ...(isset($result['created']) ? ['created' => $result['created']] : []),
            ...(isset($result['updated']) ? ['updated' => $result['updated']] : []),
            ...(isset($result['deleted']) ? ['deleted' => $result['deleted']] : []),
            ...(isset($result['error']) ? ['error' => $result['error']] : []),
            ...(isset($result['no_change_reason']) ? ['no_change_reason' => $result['no_change_reason']] : []),
        ]);
    }
}
