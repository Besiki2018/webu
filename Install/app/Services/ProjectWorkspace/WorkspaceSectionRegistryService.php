<?php

namespace App\Services\ProjectWorkspace;

use App\Models\Project;
use App\Services\AiTools\SectionNameNormalizer;

class WorkspaceSectionRegistryService
{
    public function __construct(
        protected ProjectWorkspaceService $workspace,
        protected SectionNameNormalizer $sectionNameNormalizer,
        protected WorkspaceComponentMetadataService $componentMetadata
    ) {}

    /**
     * @return array<int, array{component: string, path: string, key: string, label: string, category: string, category_label: string, schema_json?: array<string, mixed>, fields?: array<int, array<string, mixed>>}>
     */
    public function builderItems(Project $project): array
    {
        $items = [];
        $metadata = $this->componentMetadata->scan($project)['sections'] ?? [];
        foreach ($this->listSectionComponents($project) as $component) {
            $meta = is_array($metadata[$component] ?? null) ? $metadata[$component] : [];
            $items[] = [
                'component' => $component,
                'path' => is_string($meta['path'] ?? null) ? $meta['path'] : 'src/sections/'.$component.'.tsx',
                'key' => $component,
                'label' => is_string($meta['label'] ?? null) && trim((string) $meta['label']) !== ''
                    ? trim((string) $meta['label'])
                    : ($this->sectionNameNormalizer->humanize($component) ?: $component),
                'category' => 'workspace',
                'category_label' => 'Workspace sections',
                'schema_json' => is_array($meta['schema_json'] ?? null) ? $meta['schema_json'] : null,
                'fields' => is_array($meta['fields'] ?? null) ? $meta['fields'] : [],
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    public function listSectionComponents(Project $project): array
    {
        $components = [];
        foreach (array_keys($this->componentMetadata->scan($project)['sections'] ?? []) as $component) {
            if (! is_string($component) || trim($component) === '') {
                continue;
            }

            $components[$component] = trim($component);
        }

        ksort($components, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($components);
    }
}
