<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Support\SubdomainHelper;

class FallbackSubdomainService
{
    /**
     * Reserve a deterministic fallback subdomain for a project when possible.
     * Returns the existing/reserved subdomain, or null if reservation is not allowed.
     */
    public function reserve(Project $project, bool $respectQuota = true): ?string
    {
        if (! $this->isSubdomainLayerEnabled()) {
            return null;
        }

        if (! empty($project->subdomain)) {
            return SubdomainHelper::normalize((string) $project->subdomain);
        }

        $owner = $project->user;
        if (! $owner || ! $owner->canUseSubdomains()) {
            return null;
        }

        if ($respectQuota && ! $owner->canCreateMoreSubdomains()) {
            return null;
        }

        $fallback = $this->suggest($project);

        $project->forceFill([
            'subdomain' => $fallback,
        ])->saveQuietly();

        return $fallback;
    }

    /**
     * Build a fallback suggestion without persisting it.
     */
    public function suggest(Project $project): string
    {
        $seed = trim((string) $project->name);
        if ($seed === '') {
            $seed = 'project-'.substr((string) $project->id, 0, 8);
        }

        return SubdomainHelper::generateFromString($seed);
    }

    private function isSubdomainLayerEnabled(): bool
    {
        return (bool) SystemSetting::get('domain_enable_subdomains', false);
    }
}

