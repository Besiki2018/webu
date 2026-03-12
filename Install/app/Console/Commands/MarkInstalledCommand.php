<?php

namespace App\Console\Commands;

use App\Http\Middleware\Installed;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MarkInstalledCommand extends Command
{
    protected $signature = 'app:mark-installed';

    protected $description = 'Mark the application as installed (creates marker file and DB flag). Use when the app keeps redirecting to /install.';

    public function handle(): int
    {
        $path = Installed::installedMarkerPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true)) {
                $this->error("Could not create directory: {$dir}");

                return self::FAILURE;
            }
        }
        if (@file_put_contents($path, (string) time()) === false) {
            $this->error("Could not write marker file: {$path}");

            return self::FAILURE;
        }
        $this->info("Marker file created: {$path}");

        try {
            if (Schema::hasTable('system_settings')) {
                SystemSetting::set('installation_completed', true, 'boolean', 'system');
                $this->info('Database flag installation_completed set to true.');
            } else {
                $this->warn('Table system_settings does not exist; run migrations if needed. The marker file alone will stop redirects to /install.');
            }
        } catch (\Throwable $e) {
            $this->warn('Could not set DB flag: '.$e->getMessage().'. The marker file alone will stop redirects to /install.');
        }

        $this->info('Done. Reload the site; it should no longer redirect to /install.');

        return self::SUCCESS;
    }
}
