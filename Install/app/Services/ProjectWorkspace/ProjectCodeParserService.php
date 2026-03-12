<?php

namespace App\Services\ProjectWorkspace;

use App\Models\Project;

/**
 * Parses project code files so the builder can display structure from real code.
 * Reads Page.tsx (and other files) and extracts section/component list.
 */
class ProjectCodeParserService
{
    public function __construct(
        protected ProjectWorkspaceService $workspaceService
    ) {}

    /**
     * Parse a Page.tsx file and return the list of section/component tags used inside it.
     * e.g. ["HeroSection", "ProductGridSection", "NewsletterSection"]
     *
     * @return array<int, string>
     */
    public function parsePageSections(Project $project, string $pagePath): array
    {
        $content = $this->workspaceService->readFile($project, $pagePath);
        if ($content === null || $content === '') {
            return [];
        }

        return $this->extractComponentTagsFromJsx($content);
    }

    /**
     * Get parsed structure for all page entry files under src/pages/{slug}/Page.tsx.
     *
     * @return array<int, array{slug: string, path: string, sections: array<int, string>}>
     */
    public function parseAllPages(Project $project): array
    {
        $root = $this->workspaceService->ensureWorkspaceRoot($project);
        $pagesDir = $root.'/src/pages';
        if (! is_dir($pagesDir)) {
            return [];
        }

        $result = [];
        $dirs = array_filter((array) scandir($pagesDir), static fn (string $d): bool => $d !== '.' && $d !== '..' && is_dir($pagesDir.'/'.$d));
        foreach ($dirs as $slug) {
            $pageFile = 'src/pages/'.$slug.'/Page.tsx';
            $sections = $this->parsePageSections($project, $pageFile);
            $result[] = [
                'slug' => $slug,
                'path' => $pageFile,
                'sections' => $sections,
            ];
        }

        return $result;
    }

    /**
     * Extract JSX component tag names from source (e.g. <HeroSection /> or <ProductGrid />).
     *
     * @return array<int, string>
     */
    private function extractComponentTagsFromJsx(string $source): array
    {
        $tags = [];
        // Match <ComponentName ... /> or <ComponentName> (PascalCase)
        if (preg_match_all('/<([A-Z][a-zA-Z0-9]*)(?:\s|>|\/)/', $source, $m)) {
            foreach ($m[1] as $tag) {
                if (! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }

        return $tags;
    }
}
