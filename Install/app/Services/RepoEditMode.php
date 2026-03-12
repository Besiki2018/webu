<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Repo-edit mode: backend-safe file operations separate from workspace-only mode.
 *
 * Contract:
 * - Workspace mode: project workspace files only, existing protections.
 * - Repo mode: configurable roots, whitelist, blacklist, audit log, dry-run, rollback.
 *
 * This service defines the domain contract and validation. Minimal UI/API stub.
 */
class RepoEditMode
{
    public function __construct(
        protected bool $enabled = false,
        /** @var array<int, string> Allowed root paths (e.g. ['/var/www/projects']) */
        protected array $allowedRoots = [],
        /** @var array<int, string> Forbidden paths/patterns (e.g. ['.env', '.git/', 'secrets/']) */
        protected array $forbiddenPatterns = [],
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAllowedRoots(): array
    {
        return $this->allowedRoots;
    }

    public function getForbiddenPatterns(): array
    {
        return $this->forbiddenPatterns;
    }

    /**
     * Validate that a path is within allowed roots and not in blacklist.
     */
    public function validatePath(string $path): bool
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            return false;
        }

        $withinRoot = false;
        foreach ($this->allowedRoots as $root) {
            $rootResolved = realpath($root);
            if ($rootResolved !== false && str_starts_with($resolved, $rootResolved)) {
                $withinRoot = true;
                break;
            }
        }
        if (! $withinRoot) {
            return false;
        }

        foreach ($this->forbiddenPatterns as $pattern) {
            if (str_contains($path, $pattern) || str_contains($resolved, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log an audit entry for a file operation.
     */
    public function auditLog(string $operation, string $path, array $context = []): void
    {
        Log::channel('stack')->info('repo_edit_audit', array_merge([
            'operation' => $operation,
            'path' => $path,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Create a rollback snapshot path for the given file.
     */
    public function getRollbackSnapshotPath(string $path): string
    {
        return $path.'.webu_rollback_'.date('Y-m-d_His');
    }

    /**
     * Distinguish workspace mode from repo mode for callers.
     */
    /** @return 'workspace'|'repo' */
    public function getMode(): string
    {
        return $this->enabled ? 'repo' : 'workspace';
    }
}
