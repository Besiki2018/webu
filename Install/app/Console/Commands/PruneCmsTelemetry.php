<?php

namespace App\Console\Commands;

use App\Services\CmsTelemetryEventStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneCmsTelemetry extends Command
{
    protected $signature = 'cms:telemetry-prune';

    protected $description = 'Prune expired CMS telemetry events based on retention_expires_at';

    public function __construct(
        protected CmsTelemetryEventStorageService $storage
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->storage->pruneExpired();

        $message = sprintf(
            'Pruned %d expired CMS telemetry events (retention_days=%d, cutoff=%s).',
            (int) ($result['deleted'] ?? 0),
            (int) ($result['retention_days'] ?? 0),
            (string) ($result['cutoff'] ?? now()->toISOString())
        );

        $this->info($message);

        Log::info('cms.telemetry.prune', [
            'deleted' => (int) ($result['deleted'] ?? 0),
            'retention_days' => (int) ($result['retention_days'] ?? 0),
            'cutoff' => $result['cutoff'] ?? null,
        ]);

        return self::SUCCESS;
    }
}
