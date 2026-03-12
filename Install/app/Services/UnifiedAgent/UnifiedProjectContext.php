<?php

namespace App\Services\UnifiedAgent;

use App\Models\Project;

/**
 * Unified project context snapshot for the Webu Site Agent.
 * Merges CMS structure, workspace scan, selected target, and project mode.
 *
 * @phpstan-type ContextArray array{
 *   project_id: int,
 *   project_mode: 'builder'|'cms'|'code',
 *   page_slug: string|null,
 *   page_id: int|null,
 *   locale: string,
 *   cms_pages: array<int, array{id: int, slug: string, title: string, sections: array}>,
 *   global_components: array<int, array{id: string, label: string, editable_fields?: array}>,
 *   theme: array<string, mixed>,
 *   selected_target: array<string, mixed>|null,
 *   workspace_scan: array{pages: array, sections: array, component_parameters?: array}|null,
 *   recent_edits: string|null,
 *   available_component_types: array<int, string>,
 * }
 */
final class UnifiedProjectContext
{
    public function __construct(
        public readonly Project $project,
        public readonly string $projectMode,
        public readonly ?string $pageSlug,
        public readonly ?int $pageId,
        public readonly string $locale,
        public readonly array $cmsPages,
        public readonly array $globalComponents,
        public readonly array $theme,
        public readonly ?array $selectedTarget,
        public readonly ?array $workspaceScan,
        public readonly ?string $recentEdits,
        public readonly array $availableComponentTypes,
    ) {}

    /**
     * @return ContextArray
     */
    public function toArray(): array
    {
        return [
            'project_id' => $this->project->id,
            'project_mode' => $this->projectMode,
            'page_slug' => $this->pageSlug,
            'page_id' => $this->pageId,
            'locale' => $this->locale,
            'cms_pages' => $this->cmsPages,
            'global_components' => $this->globalComponents,
            'theme' => $this->theme,
            'selected_target' => $this->selectedTarget,
            'workspace_scan' => $this->workspaceScan,
            'recent_edits' => $this->recentEdits,
            'available_component_types' => $this->availableComponentTypes,
        ];
    }

    /**
     * Build page context shape expected by AiInterpretCommandService.
     *
     * @return array{page_slug?: string, page_id?: int|null, sections: array, component_types?: string[], global_components?: array, theme?: array, selected_section_id?: string|null, selected_parameter_path?: string|null, selected_element_id?: string|null, selected_target?: array|null, locale?: string, recent_edits?: string|null}
     */
    public function toPageContextForInterpret(): array
    {
        $currentPage = $this->resolveCurrentPage();
        $sections = [];
        if ($currentPage !== null) {
            foreach ($currentPage['sections'] ?? [] as $sec) {
                $sections[] = array_filter([
                    'id' => $sec['id'] ?? null,
                    'type' => $sec['type'] ?? null,
                    'label' => $sec['label'] ?? null,
                    'editable_fields' => $sec['editable_fields'] ?? null,
                    'props' => $sec['props'] ?? null,
                ]);
            }
        }

        return [
            'page_slug' => $this->pageSlug,
            'page_id' => $this->pageId,
            'sections' => $sections,
            'component_types' => $this->availableComponentTypes,
            'global_components' => $this->globalComponents,
            'theme' => $this->theme,
            'selected_section_id' => $this->selectedTarget['section_id'] ?? null,
            'selected_parameter_path' => $this->selectedTarget['parameter_path'] ?? $this->selectedTarget['component_path'] ?? null,
            'selected_element_id' => $this->selectedTarget['element_id'] ?? null,
            'selected_target' => $this->selectedTarget,
            'locale' => $this->locale,
            'recent_edits' => $this->recentEdits,
        ];
    }

    private function resolveCurrentPage(): ?array
    {
        foreach ($this->cmsPages as $page) {
            if ($this->pageSlug !== null && ($page['slug'] ?? '') === $this->pageSlug) {
                return $page;
            }
            if ($this->pageId !== null && (int) ($page['id'] ?? 0) === $this->pageId) {
                return $page;
            }
        }

        return $this->cmsPages[0] ?? null;
    }
}
