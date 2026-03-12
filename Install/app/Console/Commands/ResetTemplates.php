<?php

namespace App\Console\Commands;

use App\Services\TemplateResetService;
use Illuminate\Console\Command;

class ResetTemplates extends Command
{
    protected $signature = 'templates:reset
        {--force : Confirm destructive reset}
        {--skip-backup : Skip pre-reset backup snapshot generation}
        {--backup-path=backups : Storage path for backup artifacts}
        {--without-sites : Do not wipe site-scoped CMS/Ecommerce/Booking data}';

    protected $description = 'Reset template library and CMS seed/demo content with optional backup snapshot.';

    public function __construct(
        protected TemplateResetService $templateResetService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        if (! $force && ! app()->environment('testing')) {
            $this->error('This operation is destructive. Re-run with --force.');

            return self::FAILURE;
        }

        $skipBackup = (bool) $this->option('skip-backup');
        $backupPath = trim((string) $this->option('backup-path'));
        $wipeSites = ! (bool) $this->option('without-sites');

        try {
            $summary = $this->templateResetService->reset(
                skipBackup: $skipBackup,
                backupDirectory: $backupPath,
                wipeSites: $wipeSites,
            );

            $this->info('Template reset completed.');

            $rows = [
                ['Wipe Sites', $wipeSites ? 'yes' : 'no'],
                ['Templates (before -> after)', ($summary['before']['templates'] ?? 0).' -> '.($summary['after']['templates'] ?? 0)],
                ['Sections (before -> after)', ($summary['before']['sections_library'] ?? 0).' -> '.($summary['after']['sections_library'] ?? 0)],
                ['Sites (before -> after)', ($summary['before']['sites'] ?? 0).' -> '.($summary['after']['sites'] ?? 0)],
                ['Pages (before -> after)', ($summary['before']['pages'] ?? 0).' -> '.($summary['after']['pages'] ?? 0)],
                ['Backup', (string) ($summary['backup'] ?? 'skipped')],
            ];

            $this->table(['Metric', 'Value'], $rows);

            $restoreCommand = $summary['restore_command'] ?? null;
            if (is_string($restoreCommand) && $restoreCommand !== '') {
                $this->newLine();
                $this->line('Restore command:');
                $this->line($restoreCommand);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Template reset failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
