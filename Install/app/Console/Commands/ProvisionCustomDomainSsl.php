<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\Project;
use App\Services\DomainSettingService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProvisionCustomDomainSsl extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'domain:provision-ssl
                            {--dry-run : Show what would be done without executing}
                            {--triggered-by=cron : Who triggered this}';

    /**
     * The console command description.
     */
    protected $description = 'Provision SSL certificates for verified custom domains';

    /**
     * Track if nginx needs reloading.
     */
    protected bool $needsNginxReload = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Provision Custom Domain SSL',
            self::class,
            $this->option('triggered-by')
        );

        try {
            // Skip if custom domains are globally disabled
            if (! app(DomainSettingService::class)->isCustomDomainsEnabled()) {
                $cronLog->markSuccess('Custom domains are disabled globally - skipping');
                $this->info('Custom domains are disabled globally - skipping SSL provisioning');

                return Command::SUCCESS;
            }

            // Find domains needing SSL
            $now = now();
            $projects = Project::whereNotNull('custom_domain')
                ->where('custom_domain_verified', true)
                ->where(function ($query) use ($now): void {
                    $query->where(function ($pending) use ($now): void {
                        $pending->where('custom_domain_ssl_status', 'pending')
                            ->where(function ($retryWindow) use ($now): void {
                                $retryWindow->whereNull('custom_domain_ssl_next_retry_at')
                                    ->orWhere('custom_domain_ssl_next_retry_at', '<=', $now);
                            });
                    })->orWhere(function ($failedRetry) use ($now): void {
                        $failedRetry->where('custom_domain_ssl_status', 'failed')
                            ->whereNotNull('custom_domain_ssl_next_retry_at')
                            ->where('custom_domain_ssl_next_retry_at', '<=', $now);
                    });
                })
                ->get();

            if ($projects->isEmpty()) {
                $cronLog->markSuccess('No domains pending SSL provisioning');
                $this->info('No domains pending SSL provisioning');

                return Command::SUCCESS;
            }

            $this->info("Found {$projects->count()} domain(s) pending SSL provisioning");

            $provisioned = 0;
            $failed = 0;
            $scheduledRetry = 0;

            foreach ($projects as $project) {
                $domain = $project->custom_domain;

                if ($this->option('dry-run')) {
                    $this->info("[DRY-RUN] Would provision SSL for: {$domain}");

                    continue;
                }

                $this->info("Provisioning SSL for: {$domain}");

                $project->update([
                    'custom_domain_ssl_next_retry_at' => null,
                ]);

                if ($this->provisionCertificate($project)) {
                    $provisioned++;
                    $this->info("  Success: SSL provisioned for {$domain}");
                } else {
                    $project->refresh();
                    if ($project->custom_domain_ssl_status === 'pending' && $project->custom_domain_ssl_next_retry_at) {
                        $scheduledRetry++;
                        $this->warn(
                            "  Retry scheduled: {$domain} at ".
                            $project->custom_domain_ssl_next_retry_at->toDateTimeString()
                        );
                    } else {
                        $failed++;
                        $this->error("  Failed: Could not provision SSL for {$domain}");
                    }
                }
            }

            // Reload nginx once at the end if any configs were added
            if ($this->needsNginxReload && ! $this->option('dry-run')) {
                $this->info('Reloading Nginx...');
                if (! $this->reloadNginx()) {
                    $this->error('Failed to reload Nginx - check configuration');
                }
            }

            $message = "Provisioned: {$provisioned}, Retry scheduled: {$scheduledRetry}, Failed: {$failed}";
            $cronLog->markSuccess($message);
            $this->info($message);

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            Log::error('SSL provisioning failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("SSL provisioning failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Provision SSL certificate for a project.
     */
    protected function provisionCertificate(Project $project): bool
    {
        $domain = $project->custom_domain;

        // Run certbot
        $result = Process::run([
            'sudo', 'certbot', 'certonly',
            '--nginx',
            '-d', $domain,
            '--non-interactive',
            '--agree-tos',
            '--register-unsafely-without-email',
        ]);

        if (! $result->successful()) {
            $rawError = trim((string) ($result->errorOutput() ?: $result->output()));
            $error = $rawError !== '' ? str($rawError)->limit(500)->toString() : 'Certbot command failed.';

            Log::error('Certbot failed for domain', [
                'domain' => $domain,
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
            $this->markSslFailure($project, $error);

            return false;
        }

        // Generate nginx config
        $configResult = $this->generateNginxConfig($project);
        if (! $configResult['success']) {
            $this->markSslFailure($project, $configResult['error'] ?? 'Nginx config generation failed.');

            return false;
        }

        $project->update([
            'custom_domain_ssl_status' => 'active',
            'custom_domain_ssl_attempts' => 0,
            'custom_domain_ssl_next_retry_at' => null,
            'custom_domain_ssl_last_error' => null,
        ]);

        // Notify user about SSL provisioning
        if ($project->user) {
            app(NotificationService::class)->notifySslProvisioned($project->user, $project);
        }

        return true;
    }

    /**
     * Generate Nginx config for the custom domain.
     */
    protected function generateNginxConfig(Project $project): array
    {
        $domain = $project->custom_domain;
        $configFilename = "webby-{$domain}.conf";
        $tempPath = "/tmp/{$configFilename}";
        $configPath = "/etc/nginx/sites-available/{$configFilename}";

        try {
            $config = view('nginx.custom-domain', [
                'domain' => $domain,
                'project_id' => $project->id,
            ])->render();

            // Write config to temp file
            file_put_contents($tempPath, $config);

            // Move to nginx sites-available
            $result = Process::run(['sudo', 'mv', $tempPath, $configPath]);
            if (! $result->successful()) {
                $error = trim((string) ($result->errorOutput() ?: $result->output()));
                Log::error('Failed to move nginx config', [
                    'domain' => $domain,
                    'error' => $result->errorOutput(),
                ]);

                return [
                    'success' => false,
                    'error' => $error !== '' ? str($error)->limit(500)->toString() : 'Failed to move nginx config.',
                ];
            }

            // Create symlink in sites-enabled
            $result = Process::run(['sudo', 'ln', '-sf', $configPath, '/etc/nginx/sites-enabled/']);
            if (! $result->successful()) {
                $error = trim((string) ($result->errorOutput() ?: $result->output()));
                Log::error('Failed to create nginx symlink', [
                    'domain' => $domain,
                    'error' => $result->errorOutput(),
                ]);

                return [
                    'success' => false,
                    'error' => $error !== '' ? str($error)->limit(500)->toString() : 'Failed to create nginx symlink.',
                ];
            }

            $this->needsNginxReload = true;

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate nginx config', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            // Clean up temp file if exists
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'success' => false,
                'error' => str($e->getMessage())->limit(500)->toString(),
            ];
        }
    }

    /**
     * Reload Nginx after config changes.
     */
    protected function reloadNginx(): bool
    {
        // Test nginx configuration first
        $result = Process::run(['sudo', 'nginx', '-t']);
        if (! $result->successful()) {
            Log::error('Nginx configuration test failed', [
                'error' => $result->errorOutput(),
            ]);

            return false;
        }

        // Reload nginx
        $result = Process::run(['sudo', 'nginx', '-s', 'reload']);
        if (! $result->successful()) {
            Log::error('Nginx reload failed', [
                'error' => $result->errorOutput(),
            ]);

            return false;
        }

        return true;
    }

    protected function markSslFailure(Project $project, string $error): void
    {
        $attempts = (int) ($project->custom_domain_ssl_attempts ?? 0) + 1;
        $maxAttempts = max(1, (int) config('domain.ssl.max_attempts', 5));
        $baseBackoff = max(1, (int) config('domain.ssl.retry_backoff_minutes', 15));
        $retryable = $attempts < $maxAttempts;
        $nextRetryAt = $retryable
            ? now()->addMinutes((int) ($baseBackoff * (2 ** max(0, $attempts - 1))))
            : null;

        $project->update([
            'custom_domain_ssl_status' => $retryable ? 'pending' : 'failed',
            'custom_domain_ssl_attempts' => $attempts,
            'custom_domain_ssl_next_retry_at' => $nextRetryAt,
            'custom_domain_ssl_last_error' => str($error)->limit(500)->toString(),
        ]);
    }
}
