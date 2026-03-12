<?php

namespace App\Services\BuilderV2;

use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use App\Services\SiteProvisioningService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuilderDocumentService
{
    public function __construct(
        protected SiteProvisioningService $siteProvisioningService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function loadDraft(Project $project): array
    {
        $path = $this->draftPath($project);
        if (Storage::disk('local')->exists($path)) {
            return $this->normalizeDocument(
                $project,
                $this->decodeDocument(Storage::disk('local')->get($path))
            );
        }

        $document = $this->seedFromCms($project);
        $this->write($path, $document);

        return $document;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadPublished(Project $project): ?array
    {
        $path = $this->publishedPath($project);
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return $this->normalizeDocument(
            $project,
            $this->decodeDocument(Storage::disk('local')->get($path))
        );
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function saveDraft(Project $project, array $document): array
    {
        $current = $this->loadDraft($project);
        $next = $this->normalizeDocument($project, $document, $current);
        $next['version'] = max((int) ($current['version'] ?? 0) + 1, (int) ($next['version'] ?? 1));
        $next['updatedAt'] = now()->toIso8601String();
        $this->write($this->draftPath($project), $next);

        return $next;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mutations
     * @return array<string, mixed>
     */
    public function applyMutations(Project $project, array $mutations): array
    {
        $document = $this->loadDraft($project);

        foreach ($mutations as $mutation) {
            if (! is_array($mutation)) {
                continue;
            }

            $document = $this->applyMutation($document, $mutation);
        }

        return $this->saveDraft($project, $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(Project $project): array
    {
        $draft = $this->loadDraft($project);
        $published = $draft;
        $published['publishedAt'] = now()->toIso8601String();
        $this->write($this->publishedPath($project), $published);

        return $published;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    private function applyMutation(array $document, array $mutation): array
    {
        $type = trim((string) ($mutation['type'] ?? ''));
        $payload = is_array($mutation['payload'] ?? null) ? $mutation['payload'] : [];

        return match ($type) {
            'PATCH_NODE_PROPS' => $this->applyPatchNodeProps($document, $payload),
            'PATCH_NODE_STYLES' => $this->applyPatchNodeStyles($document, $payload),
            'INSERT_NODE' => $this->applyInsertNode($document, $payload),
            'DELETE_NODE' => $this->applyDeleteNode($document, $payload),
            'MOVE_NODE' => $this->applyMoveNode($document, $payload),
            'DUPLICATE_NODE' => $this->applyDuplicateNode($document, $payload),
            default => $document,
        };
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyPatchNodeProps(array $document, array $payload): array
    {
        $nodeId = (string) ($payload['nodeId'] ?? '');
        if ($nodeId === '' || ! isset($document['nodes'][$nodeId])) {
            return $document;
        }

        $patch = is_array($payload['patch'] ?? null) ? $payload['patch'] : [];
        $existingProps = is_array($document['nodes'][$nodeId]['props'] ?? null) ? $document['nodes'][$nodeId]['props'] : [];
        $document['nodes'][$nodeId]['props'] = array_replace_recursive($existingProps, $patch);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyPatchNodeStyles(array $document, array $payload): array
    {
        $nodeId = (string) ($payload['nodeId'] ?? '');
        if ($nodeId === '' || ! isset($document['nodes'][$nodeId])) {
            return $document;
        }

        $patch = is_array($payload['patch'] ?? null) ? $payload['patch'] : [];
        $existingStyles = is_array($document['nodes'][$nodeId]['styles'] ?? null) ? $document['nodes'][$nodeId]['styles'] : [];
        $document['nodes'][$nodeId]['styles'] = array_replace_recursive($existingStyles, $patch);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyInsertNode(array $document, array $payload): array
    {
        $node = is_array($payload['node'] ?? null) ? $payload['node'] : [];
        $nodeId = trim((string) ($node['id'] ?? ''));
        $parentId = trim((string) ($payload['parentId'] ?? $node['parentId'] ?? ''));

        if ($nodeId === '' || $parentId === '' || ! isset($document['nodes'][$parentId])) {
            return $document;
        }

        $node['parentId'] = $parentId;
        $node['children'] = array_values(array_filter(
            is_array($node['children'] ?? null) ? $node['children'] : [],
            static fn ($childId): bool => is_string($childId) && trim($childId) !== ''
        ));
        $document['nodes'][$nodeId] = $node;

        $children = array_values(is_array($document['nodes'][$parentId]['children'] ?? null) ? $document['nodes'][$parentId]['children'] : []);
        $index = isset($payload['index']) ? (int) $payload['index'] : count($children);
        $index = max(0, min($index, count($children)));
        array_splice($children, $index, 0, [$nodeId]);
        $document['nodes'][$parentId]['children'] = $children;

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDeleteNode(array $document, array $payload): array
    {
        $nodeId = trim((string) ($payload['nodeId'] ?? ''));
        if ($nodeId === '' || ! isset($document['nodes'][$nodeId])) {
            return $document;
        }

        $parentId = $document['nodes'][$nodeId]['parentId'] ?? null;
        if (is_string($parentId) && isset($document['nodes'][$parentId])) {
            $document['nodes'][$parentId]['children'] = array_values(array_filter(
                is_array($document['nodes'][$parentId]['children'] ?? null) ? $document['nodes'][$parentId]['children'] : [],
                static fn ($childId): bool => $childId !== $nodeId
            ));
        }

        foreach ($this->collectSubtreeIds($document, $nodeId) as $id) {
            unset($document['nodes'][$id]);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyMoveNode(array $document, array $payload): array
    {
        $nodeId = trim((string) ($payload['nodeId'] ?? ''));
        $targetParentId = trim((string) ($payload['targetParentId'] ?? ''));

        if ($nodeId === '' || $targetParentId === '' || ! isset($document['nodes'][$nodeId], $document['nodes'][$targetParentId])) {
            return $document;
        }

        $currentParentId = $document['nodes'][$nodeId]['parentId'] ?? null;
        if (is_string($currentParentId) && isset($document['nodes'][$currentParentId])) {
            $document['nodes'][$currentParentId]['children'] = array_values(array_filter(
                is_array($document['nodes'][$currentParentId]['children'] ?? null) ? $document['nodes'][$currentParentId]['children'] : [],
                static fn ($childId): bool => $childId !== $nodeId
            ));
        }

        $targetChildren = array_values(is_array($document['nodes'][$targetParentId]['children'] ?? null) ? $document['nodes'][$targetParentId]['children'] : []);
        $index = isset($payload['index']) ? (int) $payload['index'] : count($targetChildren);
        $index = max(0, min($index, count($targetChildren)));
        array_splice($targetChildren, $index, 0, [$nodeId]);
        $document['nodes'][$targetParentId]['children'] = $targetChildren;
        $document['nodes'][$nodeId]['parentId'] = $targetParentId;

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDuplicateNode(array $document, array $payload): array
    {
        $nodeId = trim((string) ($payload['nodeId'] ?? ''));
        if ($nodeId === '' || ! isset($document['nodes'][$nodeId])) {
            return $document;
        }

        $cloneMap = [];
        foreach ($this->collectSubtreeIds($document, $nodeId) as $id) {
            $cloneMap[$id] = (string) Str::uuid();
        }

        foreach ($cloneMap as $sourceId => $cloneId) {
            $source = $document['nodes'][$sourceId];
            $source['id'] = $cloneId;
            $source['parentId'] = ($sourceId === $nodeId)
                ? ($payload['targetParentId'] ?? $source['parentId'] ?? null)
                : ($cloneMap[$source['parentId']] ?? null);
            $source['children'] = array_values(array_map(
                static fn ($childId) => $cloneMap[$childId] ?? $childId,
                is_array($source['children'] ?? null) ? $source['children'] : []
            ));
            $document['nodes'][$cloneId] = $source;
        }

        $sourceParentId = $payload['targetParentId'] ?? ($document['nodes'][$nodeId]['parentId'] ?? null);
        if (! is_string($sourceParentId) || ! isset($document['nodes'][$sourceParentId])) {
            return $document;
        }

        $children = array_values(is_array($document['nodes'][$sourceParentId]['children'] ?? null) ? $document['nodes'][$sourceParentId]['children'] : []);
        $sourceIndex = array_search($nodeId, $children, true);
        $insertIndex = isset($payload['index'])
            ? (int) $payload['index']
            : ($sourceIndex === false ? count($children) : $sourceIndex + 1);
        $insertIndex = max(0, min($insertIndex, count($children)));
        array_splice($children, $insertIndex, 0, [$cloneMap[$nodeId]]);
        $document['nodes'][$sourceParentId]['children'] = $children;

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, string>
     */
    private function collectSubtreeIds(array $document, string $rootId): array
    {
        $stack = [$rootId];
        $visited = [];

        while ($stack !== []) {
            $currentId = array_pop($stack);
            if (! is_string($currentId) || isset($visited[$currentId]) || ! isset($document['nodes'][$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $children = is_array($document['nodes'][$currentId]['children'] ?? null) ? $document['nodes'][$currentId]['children'] : [];
            foreach ($children as $childId) {
                if (is_string($childId)) {
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedFromCms(Project $project): array
    {
        $site = $this->siteProvisioningService->provisionForProject($project);
        $pages = $site->pages()
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        if ($pages->isEmpty()) {
            return $this->buildDefaultDocument($project);
        }

        $document = [
            'projectId' => (string) $project->id,
            'pages' => [],
            'nodes' => [],
            'rootPageId' => null,
            'version' => 1,
            'updatedAt' => now()->toIso8601String(),
            'publishedAt' => null,
        ];

        foreach ($pages as $index => $page) {
            $pageId = 'page-'.$page->id;
            $rootNodeId = 'page-root-'.$page->id;
            $document['pages'][$pageId] = [
                'id' => $pageId,
                'title' => (string) $page->title,
                'slug' => (string) $page->slug,
                'rootNodeId' => $rootNodeId,
                'status' => $page->status === 'published' ? 'published' : 'draft',
            ];
            if ($index === 0) {
                $document['rootPageId'] = $pageId;
            }

            $document['nodes'][$rootNodeId] = [
                'id' => $rootNodeId,
                'type' => 'page',
                'parentId' => null,
                'children' => [],
                'props' => [
                    'title' => (string) $page->title,
                    'slug' => (string) $page->slug,
                ],
                'styles' => [],
                'bindings' => [],
                'meta' => [
                    'label' => (string) $page->title,
                ],
            ];

            $sections = $this->resolvePageSections($site, $page);
            foreach ($sections as $sectionIndex => $section) {
                $localId = trim((string) ($section['localId'] ?? ''));
                $nodeId = $localId !== '' ? $localId : sprintf('page-%d-node-%d', $page->id, $sectionIndex + 1);
                $legacyType = trim((string) ($section['type'] ?? 'legacy-section'));
                $componentKey = $this->mapLegacySectionToComponentKey($legacyType);
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $props['legacyType'] = $legacyType;

                $document['nodes'][$rootNodeId]['children'][] = $nodeId;
                $document['nodes'][$nodeId] = [
                    'id' => $nodeId,
                    'type' => 'component',
                    'componentKey' => $componentKey,
                    'parentId' => $rootNodeId,
                    'children' => [],
                    'props' => $props,
                    'styles' => [],
                    'bindings' => [],
                    'meta' => [
                        'label' => (string) ($props['title'] ?? $props['headline'] ?? Str::headline(str_replace(['_', '-'], ' ', $legacyType))),
                    ],
                ];
            }
        }

        return $this->normalizeDocument($project, $document);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultDocument(Project $project): array
    {
        $pageId = 'page-home';
        $rootNodeId = 'page-root-home';
        $heroId = 'node-hero';
        $bannerId = 'node-banner';
        $footerId = 'node-footer';

        return [
            'projectId' => (string) $project->id,
            'pages' => [
                $pageId => [
                    'id' => $pageId,
                    'title' => 'Home',
                    'slug' => 'home',
                    'rootNodeId' => $rootNodeId,
                    'status' => 'draft',
                ],
            ],
            'nodes' => [
                $rootNodeId => [
                    'id' => $rootNodeId,
                    'type' => 'page',
                    'parentId' => null,
                    'children' => [$heroId, $bannerId, $footerId],
                    'props' => ['title' => 'Home', 'slug' => 'home'],
                    'styles' => [],
                    'bindings' => [],
                    'meta' => ['label' => 'Home'],
                ],
                $heroId => [
                    'id' => $heroId,
                    'type' => 'component',
                    'componentKey' => 'hero',
                    'parentId' => $rootNodeId,
                    'children' => [],
                    'props' => [
                        'title' => $project->name,
                        'subtitle' => 'Builder V2 draft',
                        'description' => 'This draft was created automatically because the project had no seedable CMS sections.',
                        'buttonText' => 'Get started',
                        'buttonLink' => '#',
                    ],
                    'styles' => [],
                    'bindings' => [],
                    'meta' => ['label' => 'Hero'],
                ],
                $bannerId => [
                    'id' => $bannerId,
                    'type' => 'component',
                    'componentKey' => 'banner',
                    'parentId' => $rootNodeId,
                    'children' => [],
                    'props' => [
                        'title' => 'Edit this draft visually',
                        'subtitle' => 'Canvas, layers, inspector, assets, AI, and history now run in one React runtime.',
                        'ctaLabel' => 'Continue',
                        'ctaUrl' => '#',
                    ],
                    'styles' => [],
                    'bindings' => [],
                    'meta' => ['label' => 'Banner'],
                ],
                $footerId => [
                    'id' => $footerId,
                    'type' => 'component',
                    'componentKey' => 'footer',
                    'parentId' => $rootNodeId,
                    'children' => [],
                    'props' => [
                        'copyright' => sprintf('%s. All rights reserved.', $project->name),
                    ],
                    'styles' => [],
                    'bindings' => [],
                    'meta' => ['label' => 'Footer'],
                ],
            ],
            'rootPageId' => $pageId,
            'version' => 1,
            'updatedAt' => now()->toIso8601String(),
            'publishedAt' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvePageSections(Site $site, Page $page): array
    {
        $revision = $page->revisions()
            ->latest('version')
            ->first() ?? $page->publishedRevision();

        $content = is_array($revision?->content_json) ? $revision->content_json : [];
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];

        return array_values(array_filter($sections, static fn ($section): bool => is_array($section)));
    }

    private function mapLegacySectionToComponentKey(string $legacyType): string
    {
        $normalized = Str::lower(trim($legacyType));

        return match (true) {
            str_contains($normalized, 'hero') => 'hero',
            str_contains($normalized, 'newsletter') => 'newsletter',
            str_contains($normalized, 'footer') => 'footer',
            str_contains($normalized, 'cta') => 'banner',
            str_contains($normalized, 'banner') => 'banner',
            str_contains($normalized, 'button') => 'button',
            str_contains($normalized, 'image') => 'image',
            str_contains($normalized, 'text') => 'text',
            default => 'legacy-section',
        };
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    private function normalizeDocument(Project $project, array $document, ?array $current = null): array
    {
        $pages = is_array($document['pages'] ?? null) ? $document['pages'] : [];
        $nodes = is_array($document['nodes'] ?? null) ? $document['nodes'] : [];
        $rootPageId = is_string($document['rootPageId'] ?? null) ? $document['rootPageId'] : array_key_first($pages);

        return [
            'projectId' => (string) ($document['projectId'] ?? $project->id),
            'pages' => $pages,
            'nodes' => $nodes,
            'rootPageId' => $rootPageId,
            'version' => max(1, (int) ($document['version'] ?? $current['version'] ?? 1)),
            'updatedAt' => (string) ($document['updatedAt'] ?? $current['updatedAt'] ?? now()->toIso8601String()),
            'publishedAt' => $document['publishedAt'] ?? ($current['publishedAt'] ?? null),
        ];
    }

    private function write(string $path, array $document): void
    {
        Storage::disk('local')->put(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDocument(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function draftPath(Project $project): string
    {
        return sprintf('builder-v2/%s/draft.json', $project->id);
    }

    private function publishedPath(Project $project): string
    {
        return sprintf('builder-v2/%s/published.json', $project->id);
    }
}
