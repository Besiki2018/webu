<?php

namespace App\Services\WebuCodex;

use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;

/**
 * Safe file operations for Webu AI project editing. Only allowed paths (PathRules) can be read/written/deleted.
 */
class FileEditor
{
    public function __construct(
        protected ProjectWorkspaceService $workspace
    ) {}

    /**
     * Read file. Returns null if path not allowed or file not found.
     */
    public function readFile(Project $project, string $relativePath): ?string
    {
        if (! PathRules::isAllowed($relativePath)) {
            return null;
        }

        return $this->workspace->readFile($project, $relativePath);
    }

    /**
     * Create or update file. Returns false if path not allowed.
     */
    public function writeFile(Project $project, string $relativePath, string $content): bool
    {
        if (! PathRules::isAllowed($relativePath)) {
            return false;
        }

        $existing = $this->workspace->readFile($project, $relativePath);
        $this->workspace->writeFile($project, $relativePath, $content, [
            'actor' => 'ai',
            'source' => 'ai_project_edit',
            'operation_kind' => $existing === null ? 'create_file' : 'update_file',
            'preview_refresh_requested' => true,
        ]);

        return true;
    }

    /**
     * Delete file. Returns false if path not allowed or file not found.
     */
    public function deleteFile(Project $project, string $relativePath): bool
    {
        if (! PathRules::isAllowed($relativePath)) {
            return false;
        }

        return $this->workspace->deleteFile($project, $relativePath, [
            'actor' => 'ai',
            'source' => 'ai_project_edit',
            'operation_kind' => 'delete_file',
            'preview_refresh_requested' => true,
        ]);
    }
}
