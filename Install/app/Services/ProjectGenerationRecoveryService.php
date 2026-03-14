<?php

namespace App\Services;

use App\Models\ProjectGenerationRun;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Support\Carbon;

class ProjectGenerationRecoveryService
{
    public const STALE_TIMEOUT_MINUTES = 15;

    public function __construct(
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    public function recoverStaleActiveRunsForUser(int|string $userId): int
    {
        $runs = ProjectGenerationRun::query()
            ->with('project')
            ->where('user_id', $userId)
            ->whereIn('status', ProjectGenerationRun::activeStatuses())
            ->get();

        $recovered = 0;
        foreach ($runs as $run) {
            if (! $this->isStale($run)) {
                continue;
            }

            $this->markAsTimedOut($run);
            $recovered += 1;
        }

        return $recovered;
    }

    public function recoverStaleRun(?ProjectGenerationRun $run): ?ProjectGenerationRun
    {
        if (! $run || ! $run->isActive() || ! $this->isStale($run)) {
            return $run;
        }

        $this->markAsTimedOut($run);

        return $run->fresh(['project']);
    }

    public function isStale(ProjectGenerationRun $run): bool
    {
        if (! $run->isActive()) {
            return false;
        }

        $reference = $run->started_at
            ?? $run->created_at
            ?? $run->updated_at;

        if (! $reference instanceof Carbon) {
            return false;
        }

        return $reference->lte(now()->subMinutes(self::STALE_TIMEOUT_MINUTES));
    }

    public function staleFailureMessage(): string
    {
        return 'Website generation timed out before completion. Start a new generation or retry this project.';
    }

    protected function markAsTimedOut(ProjectGenerationRun $run): void
    {
        $message = $this->staleFailureMessage();

        $run->forceFill([
            'status' => ProjectGenerationRun::STATUS_FAILED,
            'progress_message' => null,
            'error_message' => $message,
            'failed_at' => now(),
            'completed_at' => null,
        ])->save();

        $project = $run->relationLoaded('project')
            ? $run->project
            : $run->project()->first();

        if ($project) {
            $this->projectWorkspace->syncInitialGenerationState($project, [
                'active_generation_run_id' => (string) $run->id,
                'phase' => ProjectGenerationRun::STATUS_FAILED,
                'error_message' => $message,
            ]);
        }
    }
}
