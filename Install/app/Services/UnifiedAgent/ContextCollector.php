<?php

namespace App\Services\UnifiedAgent;

use App\Models\Project;
use App\Services\AiSiteEditorAnalyzeService;
use App\Services\SiteProvisioningService;
use App\Services\WebuCodex\CodebaseScanner;

/**
 * Collects unified project context: CMS + workspace + selected target.
 */
class ContextCollector
{
    public function __construct(
        protected AiSiteEditorAnalyzeService $analyzeService,
        protected SiteProvisioningService $siteProvisioning,
        protected CodebaseScanner $codebaseScanner
    ) {}

    /**
     * @param  array{
     *   page_slug?: string|null,
     *   page_id?: int|null,
     *   locale?: string|null,
     *   selected_target?: array|null,
     *   recent_edits?: string|null,
     *   project_mode?: 'builder'|'cms'|'code'
     * }  $options
     */
    public function collect(Project $project, array $options = []): UnifiedProjectContext
    {
        $analyzeResult = $this->analyzeService->analyze(
            $project,
            isset($options['locale']) && $options['locale'] !== '' ? (string) $options['locale'] : null
        );

        $pages = $analyzeResult['pages'] ?? [];
        $globalComponents = $analyzeResult['global_components'] ?? [];
        $availableComponentTypes = config('builder-component-registry.component_ids', []);

        $site = $this->siteProvisioning->provisionForProject($project);
        $theme = is_array($site->theme_settings) ? $site->theme_settings : [];

        $workspaceScan = null;
        try {
            $scan = $this->codebaseScanner->getScanFromIndex($project);
            if ($scan === null) {
                $scan = $this->codebaseScanner->scan($project);
                $this->codebaseScanner->writeIndex($project, $scan);
            }
            $workspaceScan = [
                'pages' => $scan['pages'] ?? [],
                'sections' => $scan['sections'] ?? [],
                'component_parameters' => $scan['component_parameters'] ?? [],
            ];
        } catch (\Throwable $e) {
            $workspaceScan = ['pages' => [], 'sections' => [], 'component_parameters' => []];
        }

        $cmsPages = [];
        foreach ($pages as $p) {
            $cmsPages[] = [
                'id' => $p['id'] ?? 0,
                'slug' => $p['slug'] ?? '',
                'title' => $p['title'] ?? '',
                'sections' => $p['sections'] ?? [],
            ];
        }

        return new UnifiedProjectContext(
            project: $project,
            projectMode: $options['project_mode'] ?? 'builder',
            pageSlug: $options['page_slug'] ?? null,
            pageId: isset($options['page_id']) ? (int) $options['page_id'] : null,
            locale: $options['locale'] ?? 'ka',
            cmsPages: $cmsPages,
            globalComponents: $globalComponents,
            theme: $theme,
            selectedTarget: $options['selected_target'] ?? null,
            workspaceScan: $workspaceScan,
            recentEdits: $options['recent_edits'] ?? null,
            availableComponentTypes: is_array($availableComponentTypes) ? array_values($availableComponentTypes) : [],
        );
    }
}
