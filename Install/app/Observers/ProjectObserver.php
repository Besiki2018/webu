<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\FallbackSubdomainService;
use App\Services\SiteProvisioningService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     *
     * Auto-generate API token for storage access on project creation.
     */
    public function created(Project $project): void
    {
        // Auto-generate API token for storage access if not already set
        if (! $project->api_token) {
            $token = bin2hex(random_bytes(32));
            $project->api_token = $token;
            $project->saveQuietly(); // Use saveQuietly to avoid triggering observers again

            Log::debug('ProjectObserver: Generated API token for project', [
                'project_id' => $project->id,
                'has_token' => ! empty($project->api_token),
            ]);
        }

        // Reserve a fallback subdomain when possible so custom-domain flows always
        // have a platform-domain fallback target.
        try {
            app(FallbackSubdomainService::class)->reserve($project, true);
        } catch (\Throwable $e) {
            Log::warning('ProjectObserver: Failed to reserve fallback subdomain', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto-provision baseline CMS site/pages for every new project.
        if (Schema::hasTable('sites')) {
            try {
                app(SiteProvisioningService::class)->provisionForProject($project);
            } catch (\Throwable $e) {
                Log::error('ProjectObserver: Failed to provision CMS site for project', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Keep site metadata synced when project publishing/domain/theme fields change.
     */
    public function updated(Project $project): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        $watchedFields = [
            'name',
            'template_id',
            'subdomain',
            'custom_domain',
            'published_at',
            'theme_preset',
        ];

        $hasRelevantChanges = collect($watchedFields)
            ->contains(fn (string $field) => $project->wasChanged($field));

        if (! $hasRelevantChanges) {
            return;
        }

        try {
            app(SiteProvisioningService::class)->provisionForProject($project);
        } catch (\Throwable $e) {
            Log::error('ProjectObserver: Failed to sync CMS site after project update', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync soft-delete status to site status.
     */
    public function deleted(Project $project): void
    {
        $this->syncSiteStatus($project);
    }

    /**
     * Sync restore status to site status.
     */
    public function restored(Project $project): void
    {
        $this->syncSiteStatus($project);
    }

    private function syncSiteStatus(Project $project): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        try {
            app(SiteProvisioningService::class)->provisionForProject($project);
        } catch (\Throwable $e) {
            Log::error('ProjectObserver: Failed to sync CMS site status', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
