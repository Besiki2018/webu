<?php

namespace App\Jobs;

use App\Models\ProjectGenerationRun;
use App\Services\ProjectGenerationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunProjectGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $generationRunId
    ) {}

    public function handle(ProjectGenerationRunner $runner): void
    {
        $run = ProjectGenerationRun::query()
            ->with('project')
            ->find($this->generationRunId);

        if (! $run) {
            return;
        }

        $runner->run($run);
    }
}
