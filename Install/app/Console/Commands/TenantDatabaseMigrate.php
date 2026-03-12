<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\TenantDatabaseBinding;
use App\Services\TenantDatabaseBindingService;
use Illuminate\Console\Command;

class TenantDatabaseMigrate extends Command
{
    protected $signature = 'tenant:database:migrate
        {project? : Optional project UUID}
        {--all : Run against all active dedicated DB bindings}';

    protected $description = 'Enterprise dedicated-DB migration lifecycle command (skeleton).';

    public function __construct(
        protected TenantDatabaseBindingService $bindings
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->bindings->dedicatedDbFeatureEnabled()) {
            $this->warn('Dedicated DB feature is disabled. Nothing to migrate.');

            return self::SUCCESS;
        }

        $targets = collect();
        $projectArg = (string) ($this->argument('project') ?? '');

        if ($projectArg !== '') {
            $project = Project::query()->find($projectArg);
            if (! $project) {
                $this->error('Project not found: '.$projectArg);

                return self::FAILURE;
            }

            $targets->push($project);
        } elseif ($this->option('all')) {
            $targets = Project::query()
                ->whereIn('id', function ($query): void {
                    $query->select('project_id')
                        ->from((new TenantDatabaseBinding)->getTable())
                        ->where('status', TenantDatabaseBinding::STATUS_ACTIVE)
                        ->whereNull('disabled_at');
                })
                ->get();
        } else {
            $this->error('Provide a project ID or use --all.');

            return self::FAILURE;
        }

        if ($targets->isEmpty()) {
            $this->info('No dedicated DB bindings found for migration.');

            return self::SUCCESS;
        }

        foreach ($targets as $project) {
            $connection = $this->bindings->resolveConnection($project);
            if (! $connection) {
                $this->warn("Skipping project {$project->id}: no active dedicated binding.");
                continue;
            }

            // Skeleton migration step:
            // At this stage we only verify binding routing metadata.
            $this->line(sprintf(
                '[%s] dedicated DB binding verified: %s@%s/%s',
                (string) $project->id,
                (string) ($connection['username'] ?? 'user'),
                (string) ($connection['host'] ?? 'host'),
                (string) ($connection['database'] ?? 'database')
            ));
        }

        $this->info('Dedicated DB migration skeleton completed.');

        return self::SUCCESS;
    }
}

