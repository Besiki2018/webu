<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PublishedProjectCacheService
{
    /**
     * Flush transformed published cache for a project.
     * This prevents stale HTML/JS responses after re-publish.
     */
    public function flushProject(string $projectId): void
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return;
        }

        $cachePath = "published/{$projectId}";
        if (Storage::disk('local')->exists($cachePath)) {
            Storage::disk('local')->deleteDirectory($cachePath);
        }
    }
}
