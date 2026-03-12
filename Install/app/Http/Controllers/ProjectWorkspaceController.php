<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectCodeParserService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Services\WebuCodex\PathRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real project codebase: initialize workspace, parse code, read/write files.
 * Enables AI-editable code per project (Lovable/Codex-style).
 */
class ProjectWorkspaceController extends Controller
{
    public function __construct(
        protected ProjectWorkspaceService $workspace,
        protected ProjectCodeParserService $parser,
        protected CodebaseScanner $codebaseScanner
    ) {}

    /**
     * Initialize or regenerate project codebase from CMS.
     * Creates workspace + template + generates src/pages/* from current CMS pages.
     */
    public function initialize(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $root = $this->workspace->initializeProjectCodebase($project);
            $scan = $this->codebaseScanner->scan($project);
            $this->codebaseScanner->writeIndex($project, $scan);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'workspace_root' => $root,
            'message' => 'Project codebase initialized. Code is in src/pages, src/sections, src/layouts.',
        ]);
    }

    /**
     * Regenerate workspace code from current CMS (pages + sections).
     * Updates src/pages/* and ensures all builder section components exist. Does not overwrite package.json or config.
     */
    public function regenerate(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $this->workspace->generateFromCms($project);
            $this->codebaseScanner->invalidateIndex($project);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code regenerated from site. All pages and section components are in sync with the visual builder.',
        ]);
    }

    /**
     * Get full project structure for AI context (pages, sections, components, layouts, styles, page_structure).
     * Uses cached scan when valid; otherwise runs full scan and caches.
     */
    public function structure(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $this->workspace->ensureProjectCodebaseReady($project);
            $scan = $this->codebaseScanner->getScanFromIndex($project);
            if ($scan === null) {
                $scan = $this->codebaseScanner->scan($project);
                $this->codebaseScanner->writeIndex($project, $scan);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Could not scan project. Ensure workspace is initialized.',
                'structure' => [
                    'pages' => [],
                    'sections' => [],
                    'components' => [],
                    'layouts' => [],
                    'styles' => [],
                    'public' => [],
                    'page_structure' => [],
                    'imports_sample' => [],
                    'file_contents' => [],
                    'component_parameters' => [
                        'sections' => [],
                        'components' => [],
                        'layouts' => [],
                    ],
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'structure' => $scan,
        ]);
    }

    /**
     * List files in the project workspace (allowed dirs only: pages, components, sections, layouts, styles, public).
     * For Code tab: full project with content and design, same scope as Webu AI edits.
     */
    public function listFiles(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $this->workspace->ensureProjectCodebaseReady($project);
        $files = $this->workspace->listFiles($project);

        return response()->json([
            'success' => true,
            'files' => $files,
        ]);
    }

    /**
     * Get parsed page structure from real code (for builder UI).
     * Returns list of pages with their section/component tags from Page.tsx.
     */
    public function parsedPages(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $pages = $this->parser->parseAllPages($project);

        return response()->json([
            'success' => true,
            'pages' => $pages,
        ]);
    }

    /**
     * Read a file from the project workspace (for AI / code editor fallback).
     */
    public function readFile(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $path = $request->query('path');
        if (! is_string($path) || $path === '') {
            return response()->json(['success' => false, 'error' => 'path required'], 422);
        }
        $path = PathRules::normalizePath($path);
        if (! PathRules::isAllowed($path)) {
            return response()->json(['success' => false, 'error' => 'Path not allowed. Use only src/pages, src/components, src/sections, src/layouts, src/styles, public.'], 422);
        }

        $this->workspace->ensureProjectCodebaseReady($project);
        $content = $this->workspace->readEditableFile($project, $path);
        if ($content === null) {
            return response()->json(['success' => false, 'error' => 'File not found'], 404);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'content' => $content,
        ]);
    }

    /**
     * Write a file to the project workspace (create or update).
     */
    public function writeFile(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'path' => 'required|string|max:500',
            'content' => 'required|string|max:1048576',
        ]);
        $path = PathRules::normalizePath($validated['path']);
        if (! PathRules::isAllowed($path)) {
            return response()->json([
                'success' => false,
                'error' => 'Path not allowed. Use only src/pages, src/components, src/sections, src/layouts, src/styles, public.',
            ], 422);
        }

        try {
            $this->workspace->writeFile($project, $path, $validated['content']);
            $this->codebaseScanner->invalidateIndex($project);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }

    /**
     * Delete a file from the project workspace.
     */
    public function deleteFile(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $path = $request->query('path');
        if (! is_string($path) || $path === '') {
            return response()->json(['success' => false, 'error' => 'path required'], 422);
        }
        $path = PathRules::normalizePath($path);
        if (! PathRules::isAllowed($path)) {
            return response()->json(['success' => false, 'error' => 'Path not allowed. Use only allowed project directories.'], 422);
        }

        $deleted = $this->workspace->deleteFile($project, $path);
        if (! $deleted) {
            return response()->json(['success' => false, 'error' => 'File not found or could not delete'], 404);
        }

        $this->codebaseScanner->invalidateIndex($project);

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }
}
