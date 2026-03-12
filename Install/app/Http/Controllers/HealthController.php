<?php

namespace App\Http\Controllers;

use App\Models\Builder;
use App\Models\Project;
use App\Services\DomainSettingService;
use App\Services\PlatformRequirementService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class HealthController extends Controller
{
    public function __construct(
        protected DomainSettingService $domainSettings,
        protected PlatformRequirementService $platformRequirements
    ) {}

    /**
     * Simple "application up" endpoint (replaces Laravel's default closure route for Ziggy compatibility).
     * Used by load balancers / k8s. Does not require DB.
     */
    public function up(): Response
    {
        $exception = null;
        try {
            Event::dispatch(new DiagnosingHealth);
        } catch (\Throwable $e) {
            if (app()->hasDebugModeEnabled()) {
                throw $e;
            }
            report($e);
            $exception = $e->getMessage();
        }
        $path = base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php');

        return response(View::file($path, ['exception' => $exception]), $exception ? 500 : 200);
    }

    /**
     * Basic health endpoint for monitoring.
     */
    public function index(): JsonResponse
    {
        $checks = $this->runChecks();
        $overall = $this->resolveOverallStatus($checks);

        return response()->json([
            'status' => $overall,
            'service' => 'webby',
            'timestamp' => now()->toIso8601String(),
            'checks' => collect($checks)->mapWithKeys(
                fn (array $check, string $name) => [$name => $check['status']]
            ),
        ], $overall === 'down' ? 503 : 200);
    }

    /**
     * Detailed health endpoint for diagnostics.
     */
    public function details(): JsonResponse
    {
        $checks = $this->runChecks();
        $overall = $this->resolveOverallStatus($checks);

        return response()->json([
            'status' => $overall,
            'service' => 'webby',
            'app' => [
                'environment' => app()->environment(),
                'version' => app()->version(),
                'php_version' => PHP_VERSION,
                'queue_driver' => config('queue.default'),
            ],
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $overall === 'down' ? 503 : 200);
    }

    /**
     * Readiness probe: DB reachable + critical paths writable.
     * Use for load balancer / k8s readiness (traffic only when ready).
     */
    public function ready(): JsonResponse
    {
        $db = $this->checkDatabase();
        $storage = $this->checkStorage();
        $ready = ($db['status'] ?? 'fail') === 'pass' && ($storage['status'] ?? 'fail') === 'pass';

        return response()->json([
            'ready' => $ready,
            'checks' => [
                'database' => $db['status'],
                'storage' => $storage['status'],
            ],
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    /**
     * Lightweight metrics endpoint for alerting integrations.
     */
    public function metrics(): JsonResponse
    {
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;
        $pendingJobs = Schema::hasTable('jobs')
            ? DB::table('jobs')->count()
            : 0;
        $sslPending = Project::query()
            ->whereNotNull('custom_domain')
            ->where('custom_domain_verified', true)
            ->where('custom_domain_ssl_status', 'pending')
            ->count();
        $sslFailed = Project::query()
            ->whereNotNull('custom_domain')
            ->where('custom_domain_ssl_status', 'failed')
            ->count();

        $operationFailsLastHour = Schema::hasTable('operation_logs')
            ? DB::table('operation_logs')
                ->where('status', 'error')
                ->where('created_at', '>=', now()->subHour())
                ->count()
            : 0;

        return response()->json([
            'service' => 'webby',
            'timestamp' => now()->toIso8601String(),
            'metrics' => [
                'queue.pending_jobs' => (int) $pendingJobs,
                'queue.failed_jobs' => (int) $failedJobs,
                'domains.ssl_pending' => (int) $sslPending,
                'domains.ssl_failed' => (int) $sslFailed,
                'ops.failures_last_hour' => (int) $operationFailsLastHour,
            ],
        ]);
    }

    /**
     * Run all health checks and return normalized results.
     *
     * @return array<string, array{status: string, message: string, details: array<mixed>}>
     */
    protected function runChecks(): array
    {
        return [
            'platform' => $this->checkPlatformRequirements(),
            'database' => $this->checkDatabase(),
            'queue_worker' => $this->checkQueueWorker(),
            'storage' => $this->checkStorage(),
            'builder' => $this->checkBuilder(),
            'domain_ssl' => $this->checkDomainSsl(),
        ];
    }

    /**
     * Database connectivity check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'pass',
                'message' => 'Database connection is healthy.',
                'details' => [
                    'connection' => config('database.default'),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'Database connection failed.',
                'details' => [
                    'connection' => config('database.default'),
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Runtime platform requirements check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkPlatformRequirements(): array
    {
        $phpVersion = $this->platformRequirements->phpVersionStatus();
        $grpc = $this->platformRequirements->extensionStatus('ext-grpc');
        $pdoLoaded = extension_loaded('pdo');

        $details = [
            'php' => [
                'minimum' => $phpVersion['minimum'],
                'current' => $phpVersion['current'],
                'ok' => $phpVersion['ok'],
            ],
            'extensions' => [
                'pdo' => [
                    'loaded' => $pdoLoaded,
                    'required' => true,
                ],
                'grpc' => [
                    'loaded' => $grpc['loaded'],
                    'required' => $grpc['required'],
                    'required_by' => $grpc['required_by'],
                ],
            ],
        ];

        if (! $phpVersion['ok']) {
            return [
                'status' => 'fail',
                'message' => 'Runtime PHP version does not satisfy minimum requirement.',
                'details' => $details,
            ];
        }

        if (! $pdoLoaded) {
            return [
                'status' => 'fail',
                'message' => 'PDO extension is missing.',
                'details' => $details,
            ];
        }

        if ($grpc['required'] && ! $grpc['loaded']) {
            return [
                'status' => 'fail',
                'message' => 'ext-grpc is required by installed dependencies but not loaded.',
                'details' => $details,
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Runtime platform requirements are satisfied.',
            'details' => $details,
        ];
    }

    /**
     * Queue/worker baseline check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkQueueWorker(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $connectionConfig = config("queue.connections.{$driver}");

        if (! is_array($connectionConfig)) {
            return [
                'status' => 'fail',
                'message' => 'Queue driver is configured but connection settings are missing.',
                'details' => [
                    'driver' => $driver,
                ],
            ];
        }

        if ($driver === 'sync') {
            return [
                'status' => 'warn',
                'message' => 'Queue is running in sync mode. No background worker is used.',
                'details' => [
                    'driver' => $driver,
                    'worker_required' => false,
                ],
            ];
        }

        if ($driver === 'database' && ! Schema::hasTable('jobs')) {
            return [
                'status' => 'fail',
                'message' => 'Queue driver is database, but jobs table is missing.',
                'details' => [
                    'driver' => $driver,
                    'jobs_table_exists' => false,
                ],
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Queue connection is configured.',
            'details' => [
                'driver' => $driver,
                'worker_required' => true,
            ],
        ];
    }

    /**
     * Storage writability check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkStorage(): array
    {
        $paths = [
            storage_path(),
            storage_path('app/private'),
            storage_path('framework/cache'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $failed = [];

        foreach ($paths as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $failed[] = $path;
            }
        }

        if (! empty($failed)) {
            return [
                'status' => 'fail',
                'message' => 'One or more storage paths are not writable.',
                'details' => [
                    'failed_paths' => $failed,
                ],
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Storage paths are writable.',
            'details' => [
                'checked_paths' => $paths,
            ],
        ];
    }

    /**
     * Builder availability check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkBuilder(): array
    {
        $builders = Builder::active()->get();

        if ($builders->isEmpty()) {
            return [
                'status' => 'warn',
                'message' => 'No active builder servers configured.',
                'details' => [
                    'active_builders' => 0,
                    'online_builders' => 0,
                ],
            ];
        }

        $builderStatuses = [];
        $onlineCount = 0;

        foreach ($builders->take(3) as $builder) {
            $details = $builder->getDetails();
            $online = (bool) ($details['online'] ?? false);
            if ($online) {
                $onlineCount++;
            }

            $builderStatuses[] = [
                'id' => $builder->id,
                'name' => $builder->name,
                'online' => $online,
                'version' => $details['version'] ?? '-',
                'sessions' => $details['sessions'] ?? 0,
            ];
        }

        if ($onlineCount === 0) {
            return [
                'status' => 'fail',
                'message' => 'No active builder server is reachable.',
                'details' => [
                    'active_builders' => $builders->count(),
                    'online_builders' => 0,
                    'builders_checked' => $builderStatuses,
                ],
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'At least one active builder server is online.',
            'details' => [
                'active_builders' => $builders->count(),
                'online_builders' => $onlineCount,
                'builders_checked' => $builderStatuses,
            ],
        ];
    }

    /**
     * Domain and SSL basic status check.
     *
     * @return array{status: string, message: string, details: array<mixed>}
     */
    protected function checkDomainSsl(): array
    {
        $subdomainsEnabled = $this->domainSettings->isSubdomainsEnabled();
        $customDomainsEnabled = $this->domainSettings->isCustomDomainsEnabled();

        $pendingSsl = Project::whereNotNull('custom_domain')
            ->where('custom_domain_verified', true)
            ->where('custom_domain_ssl_status', 'pending')
            ->count();

        $activeSsl = Project::whereNotNull('custom_domain')
            ->where('custom_domain_ssl_status', 'active')
            ->count();

        $failedSsl = Project::whereNotNull('custom_domain')
            ->where('custom_domain_ssl_status', 'failed')
            ->count();

        $certbotInstalled = Process::run(['which', 'certbot'])->successful();

        $details = [
            'subdomains_enabled' => $subdomainsEnabled,
            'custom_domains_enabled' => $customDomainsEnabled,
            'certbot_installed' => $certbotInstalled,
            'ssl_pending_count' => $pendingSsl,
            'ssl_active_count' => $activeSsl,
            'ssl_failed_count' => $failedSsl,
        ];

        if (! $customDomainsEnabled && ! $subdomainsEnabled) {
            return [
                'status' => 'warn',
                'message' => 'Domain publishing is currently disabled in settings.',
                'details' => $details,
            ];
        }

        if ($customDomainsEnabled && ! $certbotInstalled) {
            return [
                'status' => 'warn',
                'message' => 'Custom domains are enabled, but certbot is not available on this host.',
                'details' => $details,
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Domain and SSL services are reachable at basic level.',
            'details' => $details,
        ];
    }

    /**
     * Resolve overall health status from check results.
     */
    protected function resolveOverallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? 'fail') === 'fail') {
                return 'down';
            }
        }

        foreach ($checks as $check) {
            if (($check['status'] ?? 'warn') === 'warn') {
                return 'degraded';
            }
        }

        return 'ok';
    }
}
