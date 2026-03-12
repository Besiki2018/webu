<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TenancyBackfillCommand extends Command
{
    protected $signature = 'tenancy:backfill';

    protected $description = 'Backfill tenant_id (and website_id on page_sections) for existing CMS rows. Idempotent.';

    public function handle(): int
    {
        $script = base_path('scripts/tenancy/backfill-tenant-ids.php');
        if (! is_file($script)) {
            $this->error('Backfill script not found: '.$script);

            return self::FAILURE;
        }

        passthru('php '.escapeshellarg($script), $code);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
