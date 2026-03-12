<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\EcommerceDemoSeederService;
use Illuminate\Console\Command;

class EcommerceSeedDemoCommand extends Command
{
    protected $signature = 'ecommerce:seed-demo
        {--site_id= : Site UUID to seed (default: first site)}
        {--force : Re-run even if already seeded}';

    protected $description = 'Seed demo e-commerce data (categories, products, images) for a site. Idempotent.';

    public function handle(EcommerceDemoSeederService $seeder): int
    {
        $siteId = $this->option('site_id');
        $force = (bool) $this->option('force');

        $site = $siteId
            ? Site::query()->find($siteId)
            : Site::query()->first();

        if (! $site) {
            $this->error($siteId ? "Site not found: {$siteId}" : 'No site found. Create a project first.');

            return self::FAILURE;
        }

        if ($seeder->isSeeded($site) && ! $force) {
            $this->info('Site already has demo e-commerce data. Use --force to re-run.');

            return self::SUCCESS;
        }

        $seeder->run($site, $force);
        $this->info('E-commerce demo data seeded for site: '.$site->id);

        return self::SUCCESS;
    }
}
