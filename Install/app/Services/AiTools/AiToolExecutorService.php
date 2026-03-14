<?php

namespace App\Services\AiTools;

use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Services\WebuCodex\PathRules;
use Illuminate\Support\Facades\Log;

/**
 * Executes AI agent tools within the project workspace.
 * Extends Webu AI pipeline: tools operate only inside allowed project scope.
 */
class AiToolExecutorService
{
    public const TOOL_READ_FILE = 'readFile';

    public const TOOL_WRITE_FILE = 'writeFile';

    public const TOOL_CREATE_FILE = 'createFile';

    public const TOOL_UPDATE_FILE = 'updateFile';

    public const TOOL_DELETE_FILE = 'deleteFile';

    public const TOOL_LIST_FILES = 'listFiles';

    public const TOOL_SEARCH_FILES = 'searchFiles';

    public const TOOL_RELOAD_PREVIEW = 'reloadPreview';

    /** @var array<string, string> */
    protected const TOOL_ALIASES = [
        self::TOOL_CREATE_FILE => self::TOOL_WRITE_FILE,
        self::TOOL_UPDATE_FILE => self::TOOL_WRITE_FILE,
    ];

    public function __construct(
        protected ProjectWorkspaceService $workspace,
        protected CodebaseScanner $scanner
    ) {}

    /**
     * Execute a tool by name with validated arguments.
     *
     * @return array{success: bool, error?: string, data?: mixed}
     */
    public function execute(Project $project, string $toolName, array $args, ?string $userPrompt = null): array
    {
        $toolName = self::TOOL_ALIASES[$toolName] ?? $toolName;
        $timestamp = now()->toIso8601String();

        try {
            $result = match ($toolName) {
                self::TOOL_READ_FILE => $this->readFile($project, $args),
                self::TOOL_WRITE_FILE => $this->writeFile($project, $args),
                self::TOOL_DELETE_FILE => $this->deleteFile($project, $args),
                self::TOOL_LIST_FILES => $this->listFiles($project, $args),
                self::TOOL_SEARCH_FILES => $this->searchFiles($project, $args),
                self::TOOL_RELOAD_PREVIEW => $this->reloadPreview($args),
                default => ['success' => false, 'error' => "Unknown tool: {$toolName}"],
            };
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        $this->logExecution($project->id, $toolName, $args, $userPrompt, $timestamp, $result);

        return $result;
    }

    /**
     * @param  array{path: string}  $args
     * @return array{success: bool, error?: string, data?: array{path: string, content: string}}
     */
    protected function readFile(Project $project, array $args): array
    {
        $path = $this->pathArg($args);
        if ($path === null) {
            return ['success' => false, 'error' => 'Missing or invalid path'];
        }
        if (! PathRules::isAllowed($path)) {
            return ['success' => false, 'error' => 'Path not allowed'];
        }

        $content = $this->workspace->readFile($project, $path);
        if ($content === null) {
            return ['success' => false, 'error' => 'File not found'];
        }

        return [
            'success' => true,
            'data' => ['path' => $path, 'content' => $content],
        ];
    }

    /**
     * @param  array{path: string, content: string}  $args
     * @return array{success: bool, error?: string, data?: array{path: string}}
     */
    protected function writeFile(Project $project, array $args): array
    {
        $path = $this->pathArg($args);
        $content = isset($args['content']) && is_string($args['content']) ? $args['content'] : null;
        if ($path === null) {
            return ['success' => false, 'error' => 'Missing or invalid path'];
        }
        if ($content === null) {
            return ['success' => false, 'error' => 'Missing or invalid content'];
        }
        if (! PathRules::isAllowed($path)) {
            return ['success' => false, 'error' => 'Path not allowed'];
        }

        $existing = $this->workspace->readFile($project, $path);
        $this->workspace->writeFile($project, $path, $content, [
            'actor' => 'ai',
            'source' => 'ai_tool_executor',
            'operation_kind' => $existing === null ? 'create_file' : 'update_file',
            'preview_refresh_requested' => true,
        ]);
        $this->scanner->invalidateIndex($project);

        return ['success' => true, 'data' => ['path' => $path]];
    }

    /**
     * @param  array{path: string}  $args
     * @return array{success: bool, error?: string, data?: array{path: string}}
     */
    protected function deleteFile(Project $project, array $args): array
    {
        $path = $this->pathArg($args);
        if ($path === null) {
            return ['success' => false, 'error' => 'Missing or invalid path'];
        }
        if (! PathRules::isAllowed($path)) {
            return ['success' => false, 'error' => 'Path not allowed'];
        }

        $deleted = $this->workspace->deleteFile($project, $path, [
            'actor' => 'ai',
            'source' => 'ai_tool_executor',
            'operation_kind' => 'delete_file',
            'preview_refresh_requested' => true,
        ]);
        if (! $deleted) {
            return ['success' => false, 'error' => 'File not found or could not delete'];
        }
        $this->scanner->invalidateIndex($project);

        return ['success' => true, 'data' => ['path' => $path]];
    }

    /**
     * @param  array{max_files?: int}  $args
     * @return array{success: bool, error?: string, data?: array{files: array}}
     */
    protected function listFiles(Project $project, array $args): array
    {
        $maxFiles = isset($args['max_files']) && is_numeric($args['max_files'])
            ? (int) $args['max_files']
            : 500;
        $maxFiles = min(max(1, $maxFiles), 1000);

        $files = $this->workspace->listFiles($project, $maxFiles);

        return [
            'success' => true,
            'data' => ['files' => $files],
        ];
    }

    /**
     * @param  array{keyword: string, max_results?: int}  $args
     * @return array{success: bool, error?: string, data?: array{matches: array}}
     */
    protected function searchFiles(Project $project, array $args): array
    {
        $keyword = isset($args['keyword']) && is_string($args['keyword']) ? trim($args['keyword']) : null;
        if ($keyword === null || $keyword === '') {
            return ['success' => false, 'error' => 'Missing or invalid keyword'];
        }

        $maxResults = isset($args['max_results']) && is_numeric($args['max_results'])
            ? (int) $args['max_results']
            : 50;
        $maxResults = min(max(1, $maxResults), 200);

        $all = $this->workspace->listFiles($project, 500);
        $lower = strtolower($keyword);
        $matches = [];
        foreach ($all as $entry) {
            $matchType = null;
            $snippet = null;

            if (str_contains(strtolower($entry['path']), $lower)) {
                $matchType = 'path';
            } elseif (str_contains(strtolower($entry['name']), $lower)) {
                $matchType = 'name';
            } elseif (! ($entry['is_dir'] ?? false)) {
                $content = $this->workspace->readFile($project, $entry['path']);
                if (is_string($content) && stripos($content, $keyword) !== false) {
                    $matchType = 'content';
                    $snippet = $this->extractContentSnippet($content, $keyword);
                }
            }

            if ($matchType !== null) {
                $matches[] = [
                    ...$entry,
                    'match_type' => $matchType,
                    'snippet' => $snippet,
                ];
            }

            if (count($matches) >= $maxResults) {
                break;
            }
        }

        return [
            'success' => true,
            'data' => ['matches' => $matches],
        ];
    }

    private function extractContentSnippet(string $content, string $keyword, int $radius = 80): ?string
    {
        $position = stripos($content, $keyword);
        if ($position === false) {
            return null;
        }

        $start = max(0, $position - $radius);
        $length = min(strlen($content) - $start, strlen($keyword) + ($radius * 2));
        $snippet = substr($content, $start, $length);
        $snippet = preg_replace('/\s+/', ' ', $snippet) ?? $snippet;
        $snippet = trim($snippet);

        if ($start > 0) {
            $snippet = '...'.$snippet;
        }
        if (($start + $length) < strlen($content)) {
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{success: bool, data?: array{reload_requested: true}}
     */
    protected function reloadPreview(array $args): array
    {
        return [
            'success' => true,
            'data' => ['reload_requested' => true],
        ];
    }

    protected function pathArg(array $args): ?string
    {
        $path = $args['path'] ?? $args['file_path'] ?? null;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return PathRules::normalizePath(trim($path));
    }

    protected function logExecution(string $projectId, string $toolName, array $args, ?string $userPrompt, string $timestamp, array $result): void
    {
        $log = [
            'project_id' => $projectId,
            'tool' => $toolName,
            'timestamp' => $timestamp,
            'success' => $result['success'] ?? false,
            'user_prompt' => $userPrompt,
            'path' => $args['path'] ?? $args['file_path'] ?? null,
        ];
        Log::channel('single')->info('AI tool execution', $log);
    }
}
