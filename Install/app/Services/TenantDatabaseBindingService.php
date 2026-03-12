<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TenantDatabaseBinding;
use Illuminate\Validation\ValidationException;

class TenantDatabaseBindingService
{
    public function dedicatedDbFeatureEnabled(): bool
    {
        return (bool) config('tenancy.dedicated_db_enabled', false);
    }

    public function isEligible(Project $project): bool
    {
        if (! $this->dedicatedDbFeatureEnabled()) {
            return false;
        }

        $planSlug = (string) ($project->user?->getCurrentPlan()?->slug ?? '');
        $allowed = config('tenancy.dedicated_db_allowed_plan_slugs', ['enterprise']);

        return in_array($planSlug, is_array($allowed) ? $allowed : ['enterprise'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function provision(Project $project, array $payload, ?int $actorId = null): TenantDatabaseBinding
    {
        if (! $this->dedicatedDbFeatureEnabled()) {
            throw ValidationException::withMessages([
                'feature' => 'Dedicated DB mode is disabled by feature flag.',
            ]);
        }

        if (! $this->isEligible($project)) {
            throw ValidationException::withMessages([
                'project' => 'Project is not eligible for dedicated DB mode (enterprise only).',
            ]);
        }

        $binding = TenantDatabaseBinding::query()->firstOrNew([
            'project_id' => $project->id,
        ]);

        $binding->fill([
            'status' => TenantDatabaseBinding::STATUS_PROVISIONING,
            'driver' => (string) ($payload['driver'] ?? 'mysql'),
            'host' => $payload['host'] ?? null,
            'port' => isset($payload['port']) ? (int) $payload['port'] : null,
            'database' => $payload['database'] ?? null,
            'username' => $payload['username'] ?? null,
            'password' => $payload['password'] ?? null,
            'options_json' => is_array($payload['options_json'] ?? null) ? $payload['options_json'] : [],
            'last_error' => null,
            'updated_by' => $actorId,
        ]);

        if (! $binding->exists) {
            $binding->created_by = $actorId;
        }

        $binding->save();

        // Skeleton provisioning step: mark as active immediately.
        $binding->update([
            'status' => TenantDatabaseBinding::STATUS_ACTIVE,
            'provisioned_at' => now(),
            'disabled_at' => null,
            'last_health_check_at' => now(),
        ]);

        return $binding->fresh();
    }

    public function deactivate(Project $project, ?int $actorId = null): ?TenantDatabaseBinding
    {
        $binding = TenantDatabaseBinding::query()
            ->where('project_id', $project->id)
            ->first();

        if (! $binding) {
            return null;
        }

        $binding->update([
            'status' => TenantDatabaseBinding::STATUS_DISABLED,
            'disabled_at' => now(),
            'updated_by' => $actorId,
            'last_health_check_at' => now(),
        ]);

        return $binding->fresh();
    }

    public function resolveConnection(Project $project): ?array
    {
        if (! $this->dedicatedDbFeatureEnabled()) {
            return null;
        }

        $binding = TenantDatabaseBinding::query()
            ->where('project_id', $project->id)
            ->first();

        if (! $binding || ! $binding->isActive()) {
            return null;
        }

        return [
            'driver' => $binding->driver ?: 'mysql',
            'host' => $binding->host,
            'port' => $binding->port ?: 3306,
            'database' => $binding->database,
            'username' => $binding->username,
            'password' => $binding->password,
            'options' => is_array($binding->options_json) ? $binding->options_json : [],
        ];
    }
}

