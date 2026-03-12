<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectSqlExport as ProjectSqlExportModel;
use App\Services\ProjectSqlExportService;
use Illuminate\Console\Command;

class ProjectSqlExport extends Command
{
    protected $signature = 'project:sql-export
        {project : Project UUID}
        {--requested-by= : Optional user id that initiated export}
        {--disk=local : Storage disk}
        {--path=project-sql-exports : Base storage path}';

    protected $description = 'Generate tenant-scoped SQL export package for a project.';

    public function __construct(
        protected ProjectSqlExportService $exports
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectId = (string) $this->argument('project');
        $project = Project::query()->find($projectId);

        if (! $project) {
            $this->error('Project not found: '.$projectId);

            return self::FAILURE;
        }

        $requestedBy = $this->option('requested-by');
        $requestedById = is_numeric((string) $requestedBy) ? (int) $requestedBy : null;

        $export = $this->exports->export(
            project: $project,
            requestedBy: $requestedById,
            disk: (string) $this->option('disk'),
            basePath: (string) $this->option('path')
        );

        if ($export->status !== ProjectSqlExportModel::STATUS_COMPLETED) {
            $this->error('SQL export failed: '.($export->error_message ?: 'unknown error'));

            return self::FAILURE;
        }

        $this->info('Project SQL export completed.');
        $this->line('Export ID: '.(string) $export->id);
        $this->line('SQL path: '.(string) $export->sql_path);
        $this->line('Manifest path: '.(string) $export->manifest_path);
        $this->line('Checksum: '.(string) $export->checksum);

        return self::SUCCESS;
    }
}

