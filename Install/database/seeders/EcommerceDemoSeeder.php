<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Services\EcommerceDemoSeederService;
use Illuminate\Database\Seeder;

class EcommerceDemoSeeder extends Seeder
{
    /**
     * Run the e-commerce demo seed for the first site (e.g. from deploy/cron).
     * For a specific site, use: php artisan ecommerce:seed-demo --site_id=<uuid>
     * Idempotent: run twice = no duplicate data.
     */
    public function run(): void
    {
        $site = Site::query()->first();
        if (! $site) {
            return;
        }

        app(EcommerceDemoSeederService::class)->run($site, false);
    }
}
