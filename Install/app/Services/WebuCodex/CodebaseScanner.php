<?php

namespace App\Services\WebuCodex;

use App\Models\Project;
use App\Services\ProjectWorkspace\WorkspaceComponentMetadataService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Support\Facades\File;

/**
 * Scans the project workspace for Webu AI. Returns structure for AI context.
 * Collects: pages, sections, components, layouts, styles, public files.
 * Supports a project index (.webu/index.json) for faster repeated reads; cached scans are
 * reused only when the workspace manifest still matches, so stale CMS/code projections are rejected.
 */
class CodebaseScanner
{
    private const INDEX_FILE = '.webu/index.json';

    private const INDEX_MAX_AGE_SECONDS = 300;

    public function __construct(
        protected ProjectWorkspaceService $workspace,
        protected WorkspaceComponentMetadataService $componentMetadata
    ) {}

    /**
     * Get project structure from index if valid and fresh. Returns null if index missing, stale,
     * or the current workspace manifest differs from the cached one.
     *
     * @return array{pages: array, sections: array, components: array, layouts: array, styles: array, public: array, imports_sample: array, file_contents: array, page_structure: array, component_parameters: array, projection_metadata?: array<string, mixed>, workspace_files?: array<int, array<string, mixed>>, manifest?: array<string, array{mtime: int|null, size: int|null}>, last_updated?: string}|null
     */
    public function getScanFromIndex(Project $project): ?array
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::INDEX_FILE;
        if (! is_file($path)) {
            return null;
        }
        $content = File::get($path);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }
        $updated = $data['last_updated'] ?? null;
        if ($updated === null) {
            return null;
        }
        $then = strtotime($updated);
        if ($then === false || (time() - $then) > self::INDEX_MAX_AGE_SECONDS) {
            return null;
        }
        $manifest = $data['manifest'] ?? null;
        if (! is_array($manifest) || $manifest !== $this->buildWorkspaceManifest($root)) {
            return null;
        }
        unset($data['last_updated']);
        unset($data['manifest']);

        return $data;
    }

    /**
     * Write current scan result to project index. Call after a full scan to speed up next request.
     *
     * @param  array{pages: array, sections: array, components: array, layouts: array, styles: array, public: array, imports_sample: array, file_contents: array, page_structure: array, component_parameters: array, projection_metadata?: array<string, mixed>, workspace_files?: array<int, array<string, mixed>>}  $scan
     */
    public function writeIndex(Project $project, array $scan): void
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);
        $dir = $root.'/.webu';
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0775, true);
        }
        $scan['last_updated'] = now()->toIso8601String();
        $scan['manifest'] = $this->buildWorkspaceManifest($root);
        File::put($dir.'/index.json', json_encode($scan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        File::put($dir.'/page-structure.json', json_encode($scan['page_structure'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        File::put($dir.'/component-parameters.json', json_encode($scan['component_parameters'] ?? [
            'sections' => [],
            'components' => [],
            'layouts' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Invalidate the project index so the next request performs a full scan. Call after any file edit.
     */
    public function invalidateIndex(Project $project): void
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::INDEX_FILE;
        if (is_file($path)) {
            File::delete($path);
        }
    }

    /**
     * Full codebase scan for AI context.
     *
     * @return array{pages: array<int, string>, sections: array<int, string>, components: array<int, string>, layouts: array<int, string>, styles: array<int, string>, public: array<int, string>, imports_sample: array<string, string>, file_contents: array<string, string>, page_structure: array<string, array<int, string>>, component_parameters: array<string, mixed>, projection_metadata: array<string, mixed>, workspace_files: array<int, array<string, mixed>>}
     */
    public function scan(Project $project): array
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);

        $pages = $this->listFilesIn($root, 'src/pages', '.tsx');
        $sections = $this->listFilesIn($root, 'src/sections', '.tsx');
        $components = $this->listFilesIn($root, 'src/components', '.tsx');
        $layouts = $this->listFilesIn($root, 'src/layouts', '.tsx');
        $styles = array_values(array_unique(array_merge(
            $this->listFilesIn($root, 'src/styles', '.css'),
            $this->listFilesIn($root, 'src/theme', '', 50)
        )));
        $public = $this->listFilesIn($root, 'public', '', 50);

        $importsSample = [];
        foreach (array_merge($pages, $sections, $components, $layouts) as $path) {
            $content = $this->workspace->readEditableFile($project, $path);
            if ($content !== null) {
                $importsSample[$path] = $this->extractImportsBlock($content);
            }
        }

        $fileContents = $this->gatherFileContents(
            $project,
            $pages,
            $sections,
            $components,
            $layouts,
            $styles,
            ['src/App.tsx', 'src/main.tsx']
        );
        $componentParameters = $this->componentMetadata->scan($project);
        $projectionMetadata = $this->workspace->readWorkspaceProjection($project);
        $workspaceFiles = array_values(array_filter(
            $this->workspace->listFiles($project),
            static fn (array $entry): bool => (($entry['is_dir'] ?? false) === false)
        ));
        $pageStructure = $this->buildPageStructure($pages, $importsSample, $projectionMetadata);

        return [
            'pages' => $pages,
            'sections' => $sections,
            'components' => $components,
            'layouts' => $layouts,
            'styles' => $styles,
            'public' => $public,
            'imports_sample' => $importsSample,
            'file_contents' => $fileContents,
            'page_structure' => $pageStructure,
            'component_parameters' => $componentParameters,
            'projection_metadata' => $projectionMetadata,
            'workspace_files' => $workspaceFiles,
        ];
    }

    /**
     * Select request-specific file context without rescanning the full workspace on every prompt.
     *
     * @param  array{pages?: array<int, string>, sections?: array<int, string>, components?: array<int, string>, layouts?: array<int, string>, styles?: array<int, string>, public?: array<int, string>, file_contents?: array<string, string>, page_structure?: array<string, array<int, string>>, component_parameters?: array<string, mixed>, workspace_files?: array<int, array<string, mixed>>}  $scan
     * @param  array{section_id?: string, parameter_path?: string, element_id?: string, page_id?: mixed, page_slug?: string|null, component_path?: string|null, component_type?: string|null, component_name?: string|null}|null  $selectedElement
     * @return array<string, string>
     */
    public function selectRelevantFileContents(Project $project, array $scan, string $userMessage, ?array $selectedElement = null, int $limit = 12, int $maxPerFile = 12000): array
    {
        $candidatePaths = array_values(array_unique(array_filter(array_merge(
            is_array($scan['pages'] ?? null) ? $scan['pages'] : [],
            is_array($scan['sections'] ?? null) ? $scan['sections'] : [],
            is_array($scan['components'] ?? null) ? $scan['components'] : [],
            is_array($scan['layouts'] ?? null) ? $scan['layouts'] : [],
            is_array($scan['styles'] ?? null) ? $scan['styles'] : [],
            is_array($scan['public'] ?? null) ? $scan['public'] : [],
            array_keys(is_array($scan['file_contents'] ?? null) ? $scan['file_contents'] : []),
            ['src/App.tsx', 'src/main.tsx']
        ), static fn ($path): bool => is_string($path) && trim($path) !== '')));

        if ($candidatePaths === []) {
            return [];
        }

        $keywords = $this->tokenizeSearchTerms($userMessage);
        $selectedElementNeedles = $this->extractSelectedElementNeedles($selectedElement);
        $selectedPagePath = $this->resolveSelectedElementPagePath($scan, $selectedElement);
        $selectedComponentPaths = $this->resolveSelectedComponentPaths($scan, $selectedElement, $candidatePaths);
        $selectedPageImports = $this->resolveSelectedPageImports($scan, $selectedPagePath);
        $projectionRelatedPaths = $this->resolveProjectionRelatedPaths($scan, $selectedElement, $selectedPagePath, $candidatePaths, $keywords);
        $workspaceFileIndex = $this->buildWorkspaceFileIndex($scan);
        $selectedPageSlug = isset($selectedElement['page_slug']) && is_string($selectedElement['page_slug']) ? trim((string) $selectedElement['page_slug']) : '';
        $pinnedPaths = $this->resolvePinnedContextPaths(
            $scan,
            $candidatePaths,
            $keywords,
            $selectedElement,
            $selectedPagePath,
            $selectedComponentPaths,
            $projectionRelatedPaths
        );

        $pathScores = [];
        foreach ($candidatePaths as $path) {
            $pathScores[$path] = $this->scoreRelevantPath(
                $path,
                $keywords,
                $selectedElementNeedles,
                $selectedElement,
                $selectedPagePath,
                $selectedComponentPaths,
                $selectedPageImports,
                $selectedPageSlug,
                is_array($workspaceFileIndex[$path] ?? null) ? $workspaceFileIndex[$path] : []
            );
        }

        arsort($pathScores);
        $initialCandidates = array_slice(array_keys($pathScores), 0, max($limit * 2, 24));
        $orderedCandidates = array_values(array_unique(array_filter([
            ...$pinnedPaths,
            $selectedPagePath,
            ...$selectedComponentPaths,
            ...$projectionRelatedPaths,
            ...$initialCandidates,
        ])));

        $rankedContents = [];
        $contentScores = [];
        foreach ($orderedCandidates as $path) {
            $content = $this->loadContextSnippet($project, $scan, $path, $maxPerFile);
            if ($content === null) {
                continue;
            }

            $rankedContents[$path] = $content;
            $contentScores[$path] = ($pathScores[$path] ?? 0) + $this->scoreContentSnippet($content, $keywords, $selectedElementNeedles);
        }

        uksort($rankedContents, static function (string $left, string $right) use ($contentScores): int {
            return ($contentScores[$right] ?? 0) <=> ($contentScores[$left] ?? 0) ?: strcmp($left, $right);
        });

        $selectedContents = [];
        foreach ($pinnedPaths as $path) {
            if (! isset($rankedContents[$path])) {
                continue;
            }

            $selectedContents[$path] = $rankedContents[$path];
            if (count($selectedContents) >= $limit) {
                return $selectedContents;
            }
        }

        foreach ($rankedContents as $path => $content) {
            if (isset($selectedContents[$path])) {
                continue;
            }

            $selectedContents[$path] = $content;
            if (count($selectedContents) >= $limit) {
                break;
            }
        }

        return $selectedContents;
    }

    /**
     * Build page slug -> list of imported section/component names for AI context.
     *
     * @param  array<int, string>  $pages
     * @param  array<string, string>  $importsSample
     * @return array<string, array<int, string>>
     */
    private function buildPageStructure(array $pages, array $importsSample, array $projectionMetadata = []): array
    {
        $out = [];
        foreach ($pages as $path) {
            $slug = $this->pagePathToSlug($path);
            $block = $importsSample[$path] ?? '';
            $imports = $this->parseImportedNames($block);
            $out[$slug] = array_values(array_unique($imports));
        }

        foreach ((is_array($projectionMetadata['pages'] ?? null) ? $projectionMetadata['pages'] : []) as $pageEntry) {
            if (! is_array($pageEntry)) {
                continue;
            }

            $slug = is_string($pageEntry['slug'] ?? null) && trim((string) $pageEntry['slug']) !== ''
                ? trim((string) $pageEntry['slug'])
                : null;
            if ($slug === null) {
                continue;
            }

            $projectionImports = [];
            foreach ((is_array($pageEntry['sections'] ?? null) ? $pageEntry['sections'] : []) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $componentName = trim((string) ($section['component_name'] ?? ''));
                if ($componentName !== '') {
                    $projectionImports[] = $componentName;
                }
            }
            foreach ((is_array($pageEntry['layout_files'] ?? null) ? $pageEntry['layout_files'] : []) as $layoutFile) {
                if (! is_string($layoutFile)) {
                    continue;
                }
                $projectionImports[] = pathinfo($layoutFile, PATHINFO_FILENAME);
            }

            $out[$slug] = array_values(array_unique(array_filter([
                ...(is_array($out[$slug] ?? null) ? $out[$slug] : []),
                ...$projectionImports,
            ])));
        }

        return $out;
    }

    /**
     * @param  array{projection_metadata?: array<string, mixed>}  $scan
     * @param  array<int, string>  $candidatePaths
     * @param  array<int, string>  $keywords
     * @param  array<string, mixed>|null  $selectedElement
     * @param  array<int, string>  $selectedComponentPaths
     * @param  array<int, string>  $projectionRelatedPaths
     * @return array<int, string>
     */
    private function resolvePinnedContextPaths(
        array $scan,
        array $candidatePaths,
        array $keywords,
        ?array $selectedElement,
        ?string $selectedPagePath,
        array $selectedComponentPaths,
        array $projectionRelatedPaths
    ): array {
        $candidateSet = array_fill_keys($candidatePaths, true);
        $paths = array_values(array_unique(array_filter([
            $selectedPagePath,
            ...$selectedComponentPaths,
            ...$projectionRelatedPaths,
        ], static fn ($path): bool => is_string($path) && trim($path) !== '')));

        foreach ($this->resolveSelectedPageProjectionPaths($scan, $candidateSet, $selectedPagePath) as $path) {
            $paths[] = $path;
        }

        if ($this->shouldIncludeDefaultWorkspaceContext($keywords, $selectedElement)) {
            foreach ($this->resolveDefaultWorkspaceContextPaths($scan, $candidateSet) as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique(array_filter($paths, static fn ($path): bool => is_string($path) && isset($candidateSet[$path]))));
    }

    /**
     * @param  array{projection_metadata?: array<string, mixed>}  $scan
     * @param  array<string, bool>  $candidateSet
     * @return array<int, string>
     */
    private function resolveSelectedPageProjectionPaths(array $scan, array $candidateSet, ?string $selectedPagePath): array
    {
        if ($selectedPagePath === null) {
            return [];
        }

        $resolved = [$selectedPagePath];
        foreach ((is_array($scan['projection_metadata']['pages'] ?? null) ? $scan['projection_metadata']['pages'] : []) as $pageEntry) {
            if (! is_array($pageEntry)) {
                continue;
            }

            $pagePath = trim((string) ($pageEntry['path'] ?? ''));
            if ($pagePath === '' || $pagePath !== $selectedPagePath) {
                continue;
            }

            foreach ([
                ...(is_array($pageEntry['layout_files'] ?? null) ? $pageEntry['layout_files'] : []),
                ...(is_array($pageEntry['section_files'] ?? null) ? $pageEntry['section_files'] : []),
            ] as $path) {
                if (is_string($path) && isset($candidateSet[$path])) {
                    $resolved[] = $path;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<string, mixed>|null  $selectedElement
     */
    private function shouldIncludeDefaultWorkspaceContext(array $keywords, ?array $selectedElement): bool
    {
        if (! is_array($selectedElement) || $selectedElement === []) {
            return true;
        }

        return array_intersect($keywords, [
            'project',
            'workspace',
            'site',
            'code',
            'context',
            'component',
            'components',
            'layout',
            'layouts',
            'page',
            'pages',
            'header',
            'footer',
        ]) !== [];
    }

    /**
     * Pin a truthful baseline context so broad AI prompts always include real
     * pages, shared layouts, and the actual section/component files in use.
     *
     * @param  array{projection_metadata?: array<string, mixed>, pages?: array<int, string>, sections?: array<int, string>, layouts?: array<int, string>, components?: array<int, string>}  $scan
     * @param  array<string, bool>  $candidateSet
     * @return array<int, string>
     */
    private function resolveDefaultWorkspaceContextPaths(array $scan, array $candidateSet): array
    {
        $resolved = [];
        $layoutPaths = [];
        $sectionPaths = [];

        foreach (array_slice(
            array_values(array_filter(
                is_array($scan['projection_metadata']['pages'] ?? null) ? $scan['projection_metadata']['pages'] : [],
                static fn ($entry): bool => is_array($entry)
            )),
            0,
            3
        ) as $pageEntry) {
            $pagePath = trim((string) ($pageEntry['path'] ?? ''));
            if ($pagePath !== '' && isset($candidateSet[$pagePath])) {
                $resolved[] = $pagePath;
            }

            foreach ((is_array($pageEntry['layout_files'] ?? null) ? $pageEntry['layout_files'] : []) as $path) {
                if (is_string($path) && isset($candidateSet[$path])) {
                    $layoutPaths[] = $path;
                }
            }

            foreach ((is_array($pageEntry['section_files'] ?? null) ? $pageEntry['section_files'] : []) as $path) {
                if (is_string($path) && isset($candidateSet[$path])) {
                    $sectionPaths[] = $path;
                }
            }
        }

        foreach ((is_array($scan['projection_metadata']['layouts'] ?? null) ? $scan['projection_metadata']['layouts'] : []) as $layoutEntry) {
            if (! is_array($layoutEntry)) {
                continue;
            }

            $path = trim((string) ($layoutEntry['path'] ?? ''));
            if ($path !== '' && isset($candidateSet[$path])) {
                $layoutPaths[] = $path;
            }
        }

        $resolved = [
            ...$resolved,
            ...array_slice(array_values(array_unique($layoutPaths)), 0, 4),
            ...array_slice(array_values(array_unique($sectionPaths)), 0, 6),
        ];

        if ($resolved !== []) {
            return array_values(array_unique($resolved));
        }

        foreach (array_slice(is_array($scan['pages'] ?? null) ? $scan['pages'] : [], 0, 3) as $path) {
            if (is_string($path) && isset($candidateSet[$path])) {
                $resolved[] = $path;
            }
        }
        foreach (array_slice(is_array($scan['layouts'] ?? null) ? $scan['layouts'] : [], 0, 3) as $path) {
            if (is_string($path) && isset($candidateSet[$path])) {
                $resolved[] = $path;
            }
        }
        foreach (array_slice(
            array_values(array_unique(array_merge(
                is_array($scan['sections'] ?? null) ? $scan['sections'] : [],
                is_array($scan['components'] ?? null) ? $scan['components'] : []
            ))),
            0,
            6
        ) as $path) {
            if (is_string($path) && isset($candidateSet[$path])) {
                $resolved[] = $path;
            }
        }

        return array_values(array_unique($resolved));
    }

    private function pagePathToSlug(string $path): string
    {
        if (preg_match('#src/pages/([^/]+)/#', str_replace('\\', '/', $path), $m)) {
            return $m[1];
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Extract imported component/section names from import block text.
     *
     * @return array<int, string>
     */
    private function parseImportedNames(string $importBlock): array
    {
        $names = [];
        foreach (explode("\n", $importBlock) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'import ')) {
                continue;
            }
            if (preg_match('/import\s+(\w+)\s+from\s+[\'"][^\'"]+[\'"]/', $line, $m)) {
                $names[] = $m[1];
            } elseif (preg_match('/import\s*\{\s*([^}]+)\s*\}\s+from/', $line, $m)) {
                foreach (array_map('trim', explode(',', $m[1])) as $spec) {
                    $name = preg_replace('/\s+as\s+\w+$/', '', $spec);
                    $name = trim(explode(' ', $name)[0]);
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Full content of key files for AI context. Truncated per file to fit prompt.
     *
     * @param  array<int, string>  $pages
     * @param  array<int, string>  $sections
     * @param  array<int, string>  $components
     * @param  array<int, string>  $layouts
     * @param  array<int, string>  $styles
     * @param  array<int, string>  $extraFiles
     * @return array<string, string>
     */
    private function gatherFileContents(
        Project $project,
        array $pages,
        array $sections,
        array $components,
        array $layouts,
        array $styles,
        array $extraFiles,
        int $maxPerFile = 12000
    ): array
    {
        $out = [];
        $groups = [
            [$this->prioritizePaths($pages, ['src/pages/home/Page.tsx']), 6],
            [$this->prioritizePaths($sections, ['HeroSection', 'PricingSection', 'FeaturesSection']), 8],
            [$this->prioritizePaths($components, ['Header.tsx', 'Footer.tsx']), 6],
            [$this->prioritizePaths($layouts, ['SiteLayout.tsx']), 4],
            [$styles, 4],
            [$extraFiles, 4],
        ];

        foreach ($groups as [$paths, $limit]) {
            foreach (array_slice($paths, 0, $limit) as $path) {
                if (isset($out[$path])) {
                    continue;
                }

                $content = $this->workspace->readEditableFile($project, $path);
                if ($content !== null) {
                    $out[$path] = strlen($content) > $maxPerFile ? substr($content, 0, $maxPerFile)."\n// ... (truncated)" : $content;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function tokenizeSearchTerms(string $userMessage): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9_\\/-]+/i', strtolower($userMessage)) ?: [],
            static fn ($value): bool => is_string($value) && strlen($value) >= 3
        )));
    }

    /**
     * @param  array{page_slug?: string|null, page_id?: mixed}|null  $selectedElement
     * @param  array{pages?: array<int, string>}  $scan
     */
    private function resolveSelectedElementPagePath(array $scan, ?array $selectedElement): ?string
    {
        $pages = is_array($scan['pages'] ?? null) ? $scan['pages'] : [];
        if ($pages === []) {
            return null;
        }

        $pageSlug = isset($selectedElement['page_slug']) && is_string($selectedElement['page_slug'])
            ? trim((string) $selectedElement['page_slug'])
            : '';
        if ($pageSlug !== '') {
            foreach ($pages as $pagePath) {
                if ($this->pagePathToSlug($pagePath) === $pageSlug) {
                    return $pagePath;
                }
            }
        }

        $pageId = trim((string) ($selectedElement['page_id'] ?? ''));
        if ($pageId !== '') {
            foreach ($pages as $pagePath) {
                if (str_contains(strtolower($pagePath), strtolower($pageId))) {
                    return $pagePath;
                }
            }
        }

        return null;
    }

    /**
     * @param  array{component_parameters?: array<string, mixed>}  $scan
     * @param  array{component_path?: string|null, component_type?: string|null, component_name?: string|null}|null  $selectedElement
     * @param  array<int, string>  $candidatePaths
     * @return array<int, string>
     */
    private function resolveSelectedComponentPaths(array $scan, ?array $selectedElement, array $candidatePaths): array
    {
        $resolved = [];
        $candidateSet = array_fill_keys($candidatePaths, true);

        $componentPath = isset($selectedElement['component_path']) && is_string($selectedElement['component_path'])
            ? trim((string) $selectedElement['component_path'])
            : '';
        if ($componentPath !== '' && isset($candidateSet[$componentPath])) {
            $resolved[] = $componentPath;
        }

        $componentParameters = is_array($scan['component_parameters'] ?? null) ? $scan['component_parameters'] : [];
        $componentNeedles = array_values(array_filter([
            isset($selectedElement['component_name']) && is_string($selectedElement['component_name']) ? trim((string) $selectedElement['component_name']) : '',
            isset($selectedElement['component_type']) && is_string($selectedElement['component_type']) ? trim((string) $selectedElement['component_type']) : '',
        ]));

        foreach (['sections', 'components', 'layouts'] as $bucket) {
            $entries = is_array($componentParameters[$bucket] ?? null) ? $componentParameters[$bucket] : [];
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $component = trim((string) ($entry['component'] ?? ''));
                $path = trim((string) ($entry['path'] ?? ''));
                if ($component === '' || $path === '' || ! isset($candidateSet[$path])) {
                    continue;
                }

                foreach ($componentNeedles as $needle) {
                    if ($needle !== '' && strcasecmp($component, $needle) === 0) {
                        $resolved[] = $path;
                    }
                }
            }
        }

        $projectionComponents = is_array($scan['projection_metadata']['components'] ?? null) ? $scan['projection_metadata']['components'] : [];
        $selectedEditableFields = $this->extractSelectedElementFields($selectedElement);
        $selectedPageSlug = isset($selectedElement['page_slug']) && is_string($selectedElement['page_slug']) ? trim((string) $selectedElement['page_slug']) : '';
        foreach ($projectionComponents as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = trim((string) ($entry['path'] ?? ''));
            $component = trim((string) ($entry['component_name'] ?? $entry['component'] ?? ''));
            if ($path === '' || $component === '' || ! isset($candidateSet[$path])) {
                continue;
            }

            $matchesComponent = false;
            foreach ($componentNeedles as $needle) {
                if ($needle !== '' && strcasecmp($component, $needle) === 0) {
                    $matchesComponent = true;
                }
            }

            $propKeys = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                [
                    ...(is_array($entry['prop_keys'] ?? null) ? $entry['prop_keys'] : []),
                    ...(is_array($entry['prop_paths'] ?? null) ? $entry['prop_paths'] : []),
                ]
            )));
            $matchesField = $selectedEditableFields !== [] && array_intersect(
                array_map(static fn (string $value): string => strtolower($value), $selectedEditableFields),
                array_map(static fn (string $value): string => strtolower($value), $propKeys)
            ) !== [];
            $matchesPage = $selectedPageSlug !== '' && in_array($selectedPageSlug, is_array($entry['pages'] ?? null) ? $entry['pages'] : [], true);

            if ($matchesComponent || $matchesField || $matchesPage) {
                $resolved[] = $path;
            }
        }

        if ($resolved !== []) {
            return array_values(array_unique($resolved));
        }

        foreach ($candidatePaths as $path) {
            foreach ($componentNeedles as $needle) {
                if ($needle !== '' && str_contains(strtolower($path), strtolower($needle))) {
                    $resolved[] = $path;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array{projection_metadata?: array<string, mixed>}  $scan
     * @param  array<string, mixed>|null  $selectedElement
     * @param  array<int, string>  $candidatePaths
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function resolveProjectionRelatedPaths(array $scan, ?array $selectedElement, ?string $selectedPagePath, array $candidatePaths, array $keywords): array
    {
        $candidateSet = array_fill_keys($candidatePaths, true);
        $resolved = [];
        $projectionMetadata = is_array($scan['projection_metadata'] ?? null) ? $scan['projection_metadata'] : [];
        $selectedPageSlug = isset($selectedElement['page_slug']) && is_string($selectedElement['page_slug']) ? trim((string) $selectedElement['page_slug']) : '';
        $selectedEditableFields = $this->extractSelectedElementFields($selectedElement);
        $componentNeedles = array_values(array_filter([
            isset($selectedElement['component_name']) && is_string($selectedElement['component_name']) ? trim((string) $selectedElement['component_name']) : '',
            isset($selectedElement['component_type']) && is_string($selectedElement['component_type']) ? trim((string) $selectedElement['component_type']) : '',
        ]));

        foreach ((is_array($projectionMetadata['pages'] ?? null) ? $projectionMetadata['pages'] : []) as $pageEntry) {
            if (! is_array($pageEntry)) {
                continue;
            }

            $pagePath = trim((string) ($pageEntry['path'] ?? ''));
            $pageSlug = trim((string) ($pageEntry['slug'] ?? ''));
            $pageMatches = ($selectedPagePath !== null && $pagePath === $selectedPagePath)
                || ($selectedPageSlug !== '' && $pageSlug === $selectedPageSlug);

            if (! $pageMatches) {
                continue;
            }

            foreach ([
                ...(is_array($pageEntry['layout_files'] ?? null) ? $pageEntry['layout_files'] : []),
                ...(is_array($pageEntry['section_files'] ?? null) ? $pageEntry['section_files'] : []),
            ] as $path) {
                if (is_string($path) && isset($candidateSet[$path])) {
                    $resolved[] = $path;
                }
            }
        }

        $layoutMentioned = array_intersect($keywords, ['layout', 'header', 'footer', 'menu', 'theme', 'navigation']) !== [];
        foreach ((is_array($projectionMetadata['layouts'] ?? null) ? $projectionMetadata['layouts'] : []) as $layoutEntry) {
            if (! is_array($layoutEntry)) {
                continue;
            }

            $path = trim((string) ($layoutEntry['path'] ?? ''));
            if ($path === '' || ! isset($candidateSet[$path])) {
                continue;
            }

            $componentName = trim((string) ($layoutEntry['component_name'] ?? ''));
            $propPaths = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($layoutEntry['prop_paths'] ?? null) ? $layoutEntry['prop_paths'] : []
            )));
            $matchesField = $selectedEditableFields !== [] && array_intersect(
                array_map(static fn (string $value): string => strtolower($value), $selectedEditableFields),
                array_map(static fn (string $value): string => strtolower($value), $propPaths)
            ) !== [];
            $matchesComponent = array_filter($componentNeedles, static fn (string $needle): bool => $needle !== '' && strcasecmp($needle, $componentName) === 0) !== [];

            if ($layoutMentioned || $matchesField || $matchesComponent) {
                $resolved[] = $path;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array{page_structure?: array<string, array<int, string>>}  $scan
     * @return array<int, string>
     */
    private function resolveSelectedPageImports(array $scan, ?string $selectedPagePath): array
    {
        if ($selectedPagePath === null) {
            return [];
        }

        $pageStructure = is_array($scan['page_structure'] ?? null) ? $scan['page_structure'] : [];
        $pageSlug = $this->pagePathToSlug($selectedPagePath);
        $imports = is_array($pageStructure[$pageSlug] ?? null) ? $pageStructure[$pageSlug] : [];

        return array_values(array_filter(array_map(static fn ($value): string => is_string($value) ? trim($value) : '', $imports)));
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array{section_id?: string, parameter_path?: string, element_id?: string, component_type?: string|null, component_name?: string|null}|null  $selectedElement
     * @param  array<int, string>  $selectedComponentPaths
     * @param  array<int, string>  $selectedPageImports
     */
    private function scoreRelevantPath(
        string $path,
        array $keywords,
        array $selectedElementNeedles,
        ?array $selectedElement,
        ?string $selectedPagePath,
        array $selectedComponentPaths,
        array $selectedPageImports,
        string $selectedPageSlug,
        array $workspaceFileMeta = []
    ): int {
        $score = 0;
        $lowerPath = strtolower($path);
        $baseName = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $componentName = isset($selectedElement['component_name']) && is_string($selectedElement['component_name']) ? trim((string) $selectedElement['component_name']) : '';
        $componentType = isset($selectedElement['component_type']) && is_string($selectedElement['component_type']) ? trim((string) $selectedElement['component_type']) : '';

        if ($selectedPagePath !== null && $path === $selectedPagePath) {
            $score += 1600;
        }

        if (in_array($path, $selectedComponentPaths, true)) {
            $score += 1500;
        }

        if ($selectedPageSlug !== '' && str_contains($lowerPath, strtolower($selectedPageSlug))) {
            $score += 400;
        }

        foreach ($selectedPageImports as $importName) {
            if ($importName !== '' && $baseName === strtolower($importName)) {
                $score += 900;
            }
        }

        if ($componentName !== '' && str_contains($lowerPath, strtolower($componentName))) {
            $score += 700;
        }

        if ($componentType !== '' && str_contains($lowerPath, strtolower($componentType))) {
            $score += 550;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($lowerPath, $keyword)) {
                $score += 90;
            }
        }
        foreach ($selectedElementNeedles as $needle) {
            if ($needle !== '' && str_contains($lowerPath, strtolower($needle))) {
                $score += 80;
            }
        }

        $mentionsLayout = array_intersect($keywords, ['layout', 'header', 'footer', 'menu', 'theme', 'style']) !== [];
        if ($mentionsLayout && (str_contains($lowerPath, 'layout') || str_contains($lowerPath, 'header') || str_contains($lowerPath, 'footer') || str_contains($lowerPath, 'theme') || str_contains($lowerPath, 'style'))) {
            $score += 180;
        }

        $projectionRole = is_string($workspaceFileMeta['projection_role'] ?? null) ? trim((string) $workspaceFileMeta['projection_role']) : '';
        $projectionSource = is_string($workspaceFileMeta['projection_source'] ?? null) ? trim((string) $workspaceFileMeta['projection_source']) : '';
        if ($projectionRole === 'page' && $selectedPagePath !== null && $path === $selectedPagePath) {
            $score += 260;
        }
        if ($projectionRole === 'layout' || $projectionRole === 'layout-component') {
            $score += $mentionsLayout ? 210 : 40;
        }
        if ($projectionRole === 'section' && $componentName !== '' && str_contains($lowerPath, strtolower($componentName))) {
            $score += 180;
        }
        if ($projectionSource === 'detached-projection') {
            $score += 140;
        } elseif ($projectionSource === 'custom') {
            $score += 80;
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array{section_id?: string, parameter_path?: string, element_id?: string, component_type?: string|null, component_name?: string|null}|null  $selectedElement
     */
    private function scoreContentSnippet(string $content, array $keywords, array $selectedElementNeedles): int
    {
        $score = 0;
        $lowerContent = strtolower($content);

        foreach ($selectedElementNeedles as $needle) {
            if ($needle !== '' && str_contains($lowerContent, strtolower($needle))) {
                $score += 220;
            }
        }

        foreach ($keywords as $keyword) {
            if (str_contains($lowerContent, $keyword)) {
                $score += 18;
            }
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>|null  $selectedElement
     * @return array<int, string>
     */
    private function extractSelectedElementNeedles(?array $selectedElement): array
    {
        if (! is_array($selectedElement)) {
            return [];
        }

        $needles = array_filter([
            isset($selectedElement['section_id']) ? trim((string) $selectedElement['section_id']) : '',
            isset($selectedElement['parameter_path']) ? trim((string) $selectedElement['parameter_path']) : '',
            isset($selectedElement['element_id']) ? trim((string) $selectedElement['element_id']) : '',
            isset($selectedElement['page_slug']) ? trim((string) $selectedElement['page_slug']) : '',
            isset($selectedElement['component_path']) ? trim((string) $selectedElement['component_path']) : '',
            isset($selectedElement['component_name']) ? trim((string) $selectedElement['component_name']) : '',
            isset($selectedElement['component_type']) ? trim((string) $selectedElement['component_type']) : '',
            isset($selectedElement['current_breakpoint']) ? trim((string) $selectedElement['current_breakpoint']) : '',
            isset($selectedElement['current_interaction_state']) ? trim((string) $selectedElement['current_interaction_state']) : '',
        ], static fn ($value): bool => is_string($value) && $value !== '');

        foreach ($this->extractSelectedElementFields($selectedElement) as $field) {
            $needles[] = $field;
        }

        $variants = is_array($selectedElement['variants'] ?? null) ? $selectedElement['variants'] : [];
        foreach (['layout', 'style'] as $variantKey) {
            $variantEntry = is_array($variants[$variantKey] ?? null) ? $variants[$variantKey] : [];
            $active = isset($variantEntry['active']) ? trim((string) $variantEntry['active']) : '';
            if ($active !== '') {
                $needles[] = $active;
            }
            foreach ((is_array($variantEntry['options'] ?? null) ? $variantEntry['options'] : []) as $option) {
                if (is_string($option) && trim($option) !== '') {
                    $needles[] = trim($option);
                }
            }
        }

        $responsiveContext = is_array($selectedElement['responsive_context'] ?? null) ? $selectedElement['responsive_context'] : [];
        foreach (['responsiveFieldPaths', 'stateFieldPaths'] as $fieldBucket) {
            foreach ((is_array($responsiveContext[$fieldBucket] ?? null) ? $responsiveContext[$fieldBucket] : []) as $fieldPath) {
                if (is_string($fieldPath) && trim($fieldPath) !== '') {
                    $needles[] = trim($fieldPath);
                }
            }
        }

        return array_values(array_unique(array_filter($needles, static fn ($value): bool => is_string($value) && $value !== '')));
    }

    /**
     * @param  array<string, mixed>|null  $selectedElement
     * @return array<int, string>
     */
    private function extractSelectedElementFields(?array $selectedElement): array
    {
        if (! is_array($selectedElement)) {
            return [];
        }

        $fields = [];
        foreach ((is_array($selectedElement['editable_fields'] ?? null) ? $selectedElement['editable_fields'] : []) as $field) {
            if (is_string($field) && trim($field) !== '') {
                $fields[] = trim($field);
            }
        }

        $allowedUpdates = is_array($selectedElement['allowed_updates'] ?? null) ? $selectedElement['allowed_updates'] : [];
        foreach (['fieldPaths', 'sectionFieldPaths'] as $fieldKey) {
            foreach ((is_array($allowedUpdates[$fieldKey] ?? null) ? $allowedUpdates[$fieldKey] : []) as $field) {
                if (is_string($field) && trim($field) !== '') {
                    $fields[] = trim($field);
                }
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @param  array{file_contents?: array<string, string>}  $scan
     */
    private function loadContextSnippet(Project $project, array $scan, string $path, int $maxPerFile): ?string
    {
        $cached = is_array($scan['file_contents'] ?? null) ? ($scan['file_contents'][$path] ?? null) : null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->workspace->readEditableFile($project, $path);
        if ($content === null || $content === '') {
            return null;
        }

        return strlen($content) > $maxPerFile
            ? substr($content, 0, $maxPerFile)."\n// ... (truncated)"
            : $content;
    }

    /**
     * @param  array{workspace_files?: array<int, array<string, mixed>>}  $scan
     * @return array<string, array<string, mixed>>
     */
    private function buildWorkspaceFileIndex(array $scan): array
    {
        $index = [];

        foreach ((is_array($scan['workspace_files'] ?? null) ? $scan['workspace_files'] : []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = trim((string) ($entry['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $index[$path] = $entry;
        }

        return $index;
    }

    /**
     * @return array<string, array{mtime: int|null, size: int|null}>
     */
    private function buildWorkspaceManifest(string $root): array
    {
        $paths = array_values(array_unique(array_merge(
            $this->listFilesIn($root, 'src/pages', '.tsx'),
            $this->listFilesIn($root, 'src/sections', '.tsx'),
            $this->listFilesIn($root, 'src/components', '.tsx'),
            $this->listFilesIn($root, 'src/layouts', '.tsx'),
            $this->listFilesIn($root, 'src/styles', '.css', 100),
            $this->listFilesIn($root, 'src/theme', '', 100),
            $this->listFilesIn($root, 'public', '', 100),
            ['src/App.tsx', 'src/main.tsx']
        )));

        $manifest = [];
        foreach ($paths as $path) {
            $absolutePath = $root.'/'.ltrim($path, '/');
            if (! is_file($absolutePath)) {
                continue;
            }

            $manifest[$path] = [
                'mtime' => File::lastModified($absolutePath),
                'size' => File::size($absolutePath),
            ];
        }

        ksort($manifest);

        return $manifest;
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $priorityNeedles
     * @return array<int, string>
     */
    private function prioritizePaths(array $paths, array $priorityNeedles): array
    {
        usort($paths, static function (string $left, string $right) use ($priorityNeedles): int {
            $score = static function (string $path) use ($priorityNeedles): int {
                $value = strtolower($path);
                $total = 0;
                foreach ($priorityNeedles as $needle) {
                    if ($needle !== '' && str_contains($value, strtolower($needle))) {
                        $total += 10;
                    }
                }

                return $total;
            };

            return $score($right) <=> $score($left) ?: strcmp($left, $right);
        });

        return $paths;
    }

    /**
     * List relative paths under a directory (e.g. src/pages/home/Page.tsx).
     *
     * @return array<int, string>
     */
    private function listFilesIn(string $root, string $subDir, string $extension, int $max = 500): array
    {
        $dir = $root.'/'.ltrim($subDir, '/');
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $file) {
            if (count($out) >= $max) {
                break;
            }
            if (! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace($root.'/', '', str_replace('\\', '/', $path));
            if (! PathRules::isAllowed($relative)) {
                continue;
            }
            if ($extension !== '' && ! str_ends_with($relative, $extension)) {
                continue;
            }
            $out[] = $relative;
        }

        sort($out);

        return array_values($out);
    }

    private function extractImportsBlock(string $content): string
    {
        $lines = explode("\n", $content);
        $block = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'import ') || str_starts_with($trimmed, 'export ')) {
                $block[] = $line;
            } elseif ($block !== [] && ($trimmed === '' || str_starts_with($trimmed, 'function ') || str_starts_with($trimmed, 'const ') || preg_match('/^export default/', $trimmed))) {
                break;
            }
        }

        return implode("\n", array_slice($block, 0, 25));
    }
}
