<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceRsConnectorContract;
use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Jobs\ProcessEcommerceRsSync;
use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;
use App\Models\EcommerceRsSyncAttempt;
use App\Models\OperationLog;
use App\Models\Site;
use App\Models\User;
use App\Services\OperationLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class EcommerceRsSyncService implements EcommerceRsSyncServiceContract
{
    public function __construct(
        protected EcommerceRsConnectorContract $connector,
        protected OperationLogService $operationLogs
    ) {}

    public function queueExportSync(
        Site $site,
        EcommerceRsExport $export,
        ?User $actor = null,
        array $meta = []
    ): array {
        $targetExport = $this->assertExportInSite($site, $export, requireValid: true);
        $connector = $this->defaultConnector();

        $existing = EcommerceRsSync::query()
            ->where('site_id', $site->id)
            ->where('export_id', $targetExport->id)
            ->where('connector', $connector)
            ->first();

        if ($existing) {
            if ($existing->status === EcommerceRsSync::STATUS_FAILED) {
                return $this->retrySync(
                    site: $site,
                    sync: $existing,
                    actor: $actor,
                    meta: [
                        ...$meta,
                        'retry_source' => 'queue_existing_failed',
                    ]
                );
            }

            $existing->loadMissing(['site.project:id,name,user_id', 'export', 'order', 'requestedBy:id,name']);
            $this->logSync(
                sync: $existing,
                event: 'ecommerce_rs_sync_duplicate_ignored',
                status: OperationLog::STATUS_INFO,
                message: sprintf('RS export sync duplicate ignored (status=%s).', $existing->status),
                context: [
                    'reason' => 'existing_non_failed',
                ]
            );

            return [
                'site_id' => $site->id,
                'queued' => false,
                'message' => 'RS export sync already queued or completed.',
                'sync' => $this->serializeSync($existing, includeAttempts: true),
            ];
        }

        try {
            /** @var EcommerceRsSync $sync */
            $sync = EcommerceRsSync::query()->create([
                'site_id' => $site->id,
                'export_id' => $targetExport->id,
                'order_id' => $targetExport->order_id,
                'connector' => $connector,
                'idempotency_key' => $this->buildIdempotencyKey($site->id, $targetExport->id, $connector),
                'status' => EcommerceRsSync::STATUS_QUEUED,
                'attempts_count' => 0,
                'max_attempts' => max(1, $this->configuredMaxAttempts()),
                'meta_json' => $meta,
                'requested_by' => $actor?->id,
                'next_retry_at' => now(),
            ]);
        } catch (QueryException $queryException) {
            if ((int) $queryException->getCode() === 23000) {
                $raceSync = EcommerceRsSync::query()
                    ->where('site_id', $site->id)
                    ->where('export_id', $targetExport->id)
                    ->where('connector', $connector)
                    ->first();

                if ($raceSync) {
                    $raceSync->loadMissing(['site.project:id,name,user_id', 'export', 'order', 'requestedBy:id,name']);

                    return [
                        'site_id' => $site->id,
                        'queued' => false,
                        'message' => 'RS export sync already queued.',
                        'sync' => $this->serializeSync($raceSync, includeAttempts: true),
                    ];
                }
            }

            throw $queryException;
        }

        $sync->loadMissing(['site.project:id,name,user_id', 'export', 'order', 'requestedBy:id,name']);
        $this->dispatchSync($sync->id, runAt: null);

        $this->logSync(
            sync: $sync,
            event: 'ecommerce_rs_sync_queued',
            status: OperationLog::STATUS_INFO,
            message: 'RS export sync queued.',
            context: [
                'meta' => $meta,
            ]
        );

        return [
            'site_id' => $site->id,
            'queued' => true,
            'message' => 'RS export sync queued successfully.',
            'sync' => $this->serializeSync($sync, includeAttempts: true),
        ];
    }

    public function listSyncs(Site $site, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 25), 100));
        $status = $this->normalizeSyncStatus($filters['status'] ?? null);
        $orderId = $this->parsePositiveInt($filters['order_id'] ?? null);
        $exportId = $this->parsePositiveInt($filters['export_id'] ?? null);

        $query = EcommerceRsSync::query()
            ->where('site_id', $site->id)
            ->with([
                'export:id,site_id,order_id,status,schema_version,export_hash,generated_at',
                'order:id,site_id,order_number,status,payment_status,currency',
                'requestedBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        if ($exportId !== null) {
            $query->where('export_id', $exportId);
        }

        $syncs = $query->limit($limit)->get();

        $summaryBase = EcommerceRsSync::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId))
            ->when($exportId !== null, fn ($q) => $q->where('export_id', $exportId));

        return [
            'site_id' => $site->id,
            'filters' => [
                'status' => $status,
                'order_id' => $orderId,
                'export_id' => $exportId,
                'limit' => $limit,
            ],
            'summary' => [
                'total_syncs' => (clone $summaryBase)->count(),
                'queued_syncs' => (clone $summaryBase)->where('status', EcommerceRsSync::STATUS_QUEUED)->count(),
                'processing_syncs' => (clone $summaryBase)->where('status', EcommerceRsSync::STATUS_PROCESSING)->count(),
                'succeeded_syncs' => (clone $summaryBase)->where('status', EcommerceRsSync::STATUS_SUCCEEDED)->count(),
                'failed_syncs' => (clone $summaryBase)->where('status', EcommerceRsSync::STATUS_FAILED)->count(),
            ],
            'syncs' => $syncs
                ->map(fn (EcommerceRsSync $sync): array => $this->serializeSync($sync, includeAttempts: false))
                ->values()
                ->all(),
        ];
    }

    public function showSync(Site $site, EcommerceRsSync $sync): array
    {
        $targetSync = $this->assertSyncInSite($site, $sync, withAttempts: true);

        return [
            'site_id' => $site->id,
            'sync' => $this->serializeSync($targetSync, includeAttempts: true),
        ];
    }

    public function retrySync(
        Site $site,
        EcommerceRsSync $sync,
        ?User $actor = null,
        array $meta = []
    ): array {
        $targetSync = $this->assertSyncInSite($site, $sync, withAttempts: true);
        $targetSync->loadMissing(['export']);

        if ($targetSync->export && $targetSync->export->status !== EcommerceRsExport::STATUS_VALID) {
            throw new EcommerceDomainException(
                'RS sync retry requires a valid export payload.',
                422,
                [
                    'export_id' => $targetSync->export_id,
                    'export_status' => $targetSync->export->status,
                ]
            );
        }

        if ($targetSync->status === EcommerceRsSync::STATUS_PROCESSING) {
            return [
                'site_id' => $site->id,
                'queued' => false,
                'message' => 'RS sync is already processing.',
                'sync' => $this->serializeSync($targetSync, includeAttempts: true),
            ];
        }

        if ($targetSync->status === EcommerceRsSync::STATUS_SUCCEEDED) {
            return [
                'site_id' => $site->id,
                'queued' => false,
                'message' => 'RS sync already completed successfully.',
                'sync' => $this->serializeSync($targetSync, includeAttempts: true),
            ];
        }

        if ($targetSync->status === EcommerceRsSync::STATUS_QUEUED && ! $targetSync->next_retry_at?->isPast()) {
            return [
                'site_id' => $site->id,
                'queued' => false,
                'message' => 'RS sync is already queued.',
                'sync' => $this->serializeSync($targetSync, includeAttempts: true),
            ];
        }

        $nextMaxAttempts = max(
            (int) $targetSync->max_attempts,
            (int) $targetSync->attempts_count + 1
        );

        $targetSync->fill([
            'status' => EcommerceRsSync::STATUS_QUEUED,
            'max_attempts' => $nextMaxAttempts,
            'next_retry_at' => now(),
            'completed_at' => null,
            'last_error' => null,
            'requested_by' => $actor?->id ?? $targetSync->requested_by,
            'meta_json' => [
                ...(is_array($targetSync->meta_json) ? $targetSync->meta_json : []),
                ...$meta,
                'manual_retry_at' => now()->toISOString(),
            ],
        ])->save();

        $this->dispatchSync($targetSync->id, runAt: null);

        $targetSync->refresh()->loadMissing([
            'site.project:id,name,user_id',
            'export:id,site_id,order_id,status,schema_version,export_hash,generated_at',
            'order:id,site_id,order_number,status,payment_status,currency',
            'requestedBy:id,name',
            'attempts' => function ($query): void {
                $query->orderByDesc('attempt_no')->orderByDesc('id');
            },
        ]);

        $this->logSync(
            sync: $targetSync,
            event: 'ecommerce_rs_sync_retry_requested',
            status: OperationLog::STATUS_WARNING,
            message: 'RS sync retry queued manually.',
            context: [
                'meta' => $meta,
            ]
        );

        return [
            'site_id' => $site->id,
            'queued' => true,
            'message' => 'RS sync retry queued successfully.',
            'sync' => $this->serializeSync($targetSync, includeAttempts: true),
        ];
    }

    public function processSyncById(int $syncId): void
    {
        $claim = $this->claimNextAttempt($syncId);
        if (! $claim || isset($claim['skip_reason'])) {
            return;
        }

        $sync = EcommerceRsSync::query()
            ->with([
                'site.project:id,name,user_id',
                'export',
                'order:id,site_id,order_number,status,payment_status,currency',
                'requestedBy:id,name',
            ])
            ->find($claim['sync_id']);
        $attempt = EcommerceRsSyncAttempt::query()->find($claim['attempt_id']);

        if (! $sync || ! $attempt || ! $sync->export) {
            return;
        }

        $startedAt = microtime(true);

        $this->logSync(
            sync: $sync,
            event: 'ecommerce_rs_sync_attempt_started',
            status: OperationLog::STATUS_INFO,
            message: sprintf('RS sync attempt #%d started.', $attempt->attempt_no),
            context: [
                'attempt_id' => $attempt->id,
                'attempt_no' => $attempt->attempt_no,
            ]
        );

        try {
            $responsePayload = $this->connector->submitExport($sync, $sync->export);
            $durationMs = $this->durationMs($startedAt);

            $this->markAttemptSuccess($sync->id, $attempt->id, $durationMs, $responsePayload);

            $synced = EcommerceRsSync::query()
                ->with(['site.project:id,name,user_id', 'export', 'order:id,site_id,order_number,status,payment_status,currency'])
                ->find($sync->id);

            if ($synced) {
                $this->logSync(
                    sync: $synced,
                    event: 'ecommerce_rs_sync_succeeded',
                    status: OperationLog::STATUS_SUCCESS,
                    message: sprintf('RS sync succeeded on attempt #%d.', $attempt->attempt_no),
                    context: [
                        'attempt_id' => $attempt->id,
                        'attempt_no' => $attempt->attempt_no,
                        'duration_ms' => $durationMs,
                    ]
                );
            }
        } catch (Throwable $exception) {
            $durationMs = $this->durationMs($startedAt);
            $responsePayload = $exception instanceof EcommerceDomainException
                ? $exception->context()
                : [];

            $failureState = $this->markAttemptFailure(
                syncId: $sync->id,
                attemptId: $attempt->id,
                durationMs: $durationMs,
                responsePayload: $responsePayload,
                exception: $exception
            );

            $failedSync = EcommerceRsSync::query()
                ->with(['site.project:id,name,user_id', 'export', 'order:id,site_id,order_number,status,payment_status,currency'])
                ->find($sync->id);

            if (! $failedSync) {
                return;
            }

            if (($failureState['retry_scheduled'] ?? false) === true) {
                $nextRetryAt = $failureState['next_retry_at'] ?? null;
                $this->dispatchSync($failedSync->id, $nextRetryAt);

                $this->logSync(
                    sync: $failedSync,
                    event: 'ecommerce_rs_sync_retry_scheduled',
                    status: OperationLog::STATUS_WARNING,
                    message: sprintf('RS sync attempt #%d failed. Retry scheduled.', $attempt->attempt_no),
                    context: [
                        'attempt_id' => $attempt->id,
                        'attempt_no' => $attempt->attempt_no,
                        'duration_ms' => $durationMs,
                        'next_retry_at' => $nextRetryAt?->toISOString(),
                        'error' => $exception->getMessage(),
                    ]
                );

                return;
            }

            $this->logSync(
                sync: $failedSync,
                event: 'ecommerce_rs_sync_failed',
                status: OperationLog::STATUS_ERROR,
                message: sprintf('RS sync failed after attempt #%d.', $attempt->attempt_no),
                context: [
                    'attempt_id' => $attempt->id,
                    'attempt_no' => $attempt->attempt_no,
                    'duration_ms' => $durationMs,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function claimNextAttempt(int $syncId): ?array
    {
        return DB::transaction(function () use ($syncId): ?array {
            /** @var EcommerceRsSync|null $sync */
            $sync = EcommerceRsSync::query()
                ->whereKey($syncId)
                ->lockForUpdate()
                ->first();

            if (! $sync) {
                return null;
            }

            if ($sync->status === EcommerceRsSync::STATUS_SUCCEEDED) {
                return [
                    'skip_reason' => 'already_succeeded',
                    'sync_id' => $sync->id,
                ];
            }

            if (
                $sync->status === EcommerceRsSync::STATUS_PROCESSING
                && $sync->last_attempt_at
                && $sync->last_attempt_at->gt(now()->subMinutes(5))
            ) {
                return [
                    'skip_reason' => 'already_processing',
                    'sync_id' => $sync->id,
                ];
            }

            if (
                $sync->status === EcommerceRsSync::STATUS_QUEUED
                && $sync->next_retry_at
                && $sync->next_retry_at->isFuture()
            ) {
                return [
                    'skip_reason' => 'retry_not_due',
                    'sync_id' => $sync->id,
                ];
            }

            if ((int) $sync->attempts_count >= (int) $sync->max_attempts) {
                $sync->fill([
                    'status' => EcommerceRsSync::STATUS_FAILED,
                    'completed_at' => $sync->completed_at ?? now(),
                ])->save();

                return [
                    'skip_reason' => 'max_attempts_exhausted',
                    'sync_id' => $sync->id,
                ];
            }

            $attemptNo = (int) $sync->attempts_count + 1;
            $now = now();

            $sync->fill([
                'status' => EcommerceRsSync::STATUS_PROCESSING,
                'attempts_count' => $attemptNo,
                'last_attempt_at' => $now,
                'next_retry_at' => null,
                'started_at' => $sync->started_at ?? $now,
            ])->save();

            /** @var EcommerceRsSyncAttempt $attempt */
            $attempt = EcommerceRsSyncAttempt::query()->create([
                'site_id' => $sync->site_id,
                'sync_id' => $sync->id,
                'export_id' => $sync->export_id,
                'order_id' => $sync->order_id,
                'attempt_no' => $attemptNo,
                'status' => EcommerceRsSyncAttempt::STATUS_PROCESSING,
                'request_payload_json' => [
                    'connector' => $sync->connector,
                    'idempotency_key' => $sync->idempotency_key,
                ],
                'started_at' => $now,
            ]);

            return [
                'sync_id' => $sync->id,
                'attempt_id' => $attempt->id,
                'attempt_no' => $attemptNo,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function markAttemptSuccess(int $syncId, int $attemptId, int $durationMs, array $responsePayload): void
    {
        DB::transaction(function () use ($syncId, $attemptId, $durationMs, $responsePayload): void {
            /** @var EcommerceRsSync|null $sync */
            $sync = EcommerceRsSync::query()->whereKey($syncId)->lockForUpdate()->first();
            /** @var EcommerceRsSyncAttempt|null $attempt */
            $attempt = EcommerceRsSyncAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

            if (! $sync || ! $attempt) {
                return;
            }

            $attempt->fill([
                'status' => EcommerceRsSyncAttempt::STATUS_SUCCEEDED,
                'response_payload_json' => $responsePayload,
                'error_message' => null,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
            ])->save();

            $remoteReference = $this->nullableString($responsePayload['remote_reference'] ?? null);

            $sync->fill([
                'status' => EcommerceRsSync::STATUS_SUCCEEDED,
                'remote_reference' => $remoteReference,
                'last_error' => null,
                'response_snapshot_json' => $responsePayload,
                'next_retry_at' => null,
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     * @return array<string, mixed>
     */
    private function markAttemptFailure(
        int $syncId,
        int $attemptId,
        int $durationMs,
        array $responsePayload,
        Throwable $exception
    ): array {
        return DB::transaction(function () use ($syncId, $attemptId, $durationMs, $responsePayload, $exception): array {
            /** @var EcommerceRsSync|null $sync */
            $sync = EcommerceRsSync::query()->whereKey($syncId)->lockForUpdate()->first();
            /** @var EcommerceRsSyncAttempt|null $attempt */
            $attempt = EcommerceRsSyncAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

            if (! $sync || ! $attempt) {
                return [
                    'retry_scheduled' => false,
                    'next_retry_at' => null,
                ];
            }

            $attempt->fill([
                'status' => EcommerceRsSyncAttempt::STATUS_FAILED,
                'response_payload_json' => $responsePayload,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => $durationMs,
            ])->save();

            $retryable = $this->isRetryable($exception);
            $attemptNo = (int) $attempt->attempt_no;
            $attemptsRemaining = (int) $sync->max_attempts - (int) $sync->attempts_count;

            if ($retryable && $attemptsRemaining > 0) {
                $delaySeconds = $this->retryDelaySeconds($attemptNo);
                $nextRetryAt = now()->addSeconds($delaySeconds);

                $sync->fill([
                    'status' => EcommerceRsSync::STATUS_QUEUED,
                    'next_retry_at' => $nextRetryAt,
                    'last_error' => $exception->getMessage(),
                    'completed_at' => null,
                ])->save();

                return [
                    'retry_scheduled' => true,
                    'next_retry_at' => $nextRetryAt,
                ];
            }

            $sync->fill([
                'status' => EcommerceRsSync::STATUS_FAILED,
                'next_retry_at' => null,
                'last_error' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            return [
                'retry_scheduled' => false,
                'next_retry_at' => null,
            ];
        });
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof EcommerceDomainException) {
            return $exception->status() >= 500;
        }

        return true;
    }

    private function retryDelaySeconds(int $attemptNo): int
    {
        $configured = config('ecommerce.rs_sync.backoff_seconds', [60, 180, 600, 1800, 3600]);
        $sequence = is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, fn ($value): bool => is_numeric($value) && (int) $value > 0))
            : [60, 180, 600, 1800, 3600];

        if ($sequence === []) {
            return 60;
        }

        $index = max(0, min($attemptNo - 1, count($sequence) - 1));

        return (int) $sequence[$index];
    }

    private function configuredMaxAttempts(): int
    {
        return (int) config('ecommerce.rs_sync.max_attempts', 5);
    }

    private function defaultConnector(): string
    {
        $configured = $this->nullableString(config('ecommerce.rs_sync.default_connector'));

        return $configured ?? 'rs-v2-skeleton';
    }

    private function dispatchSync(int $syncId, mixed $runAt): void
    {
        $queue = $this->nullableString(config('ecommerce.rs_sync.queue')) ?? 'default';

        $job = ProcessEcommerceRsSync::dispatch($syncId)->onQueue($queue);
        if ($runAt !== null) {
            $job->delay($runAt);
        }
    }

    private function assertExportInSite(Site $site, EcommerceRsExport $export, bool $requireValid): EcommerceRsExport
    {
        if ((string) $export->site_id !== (string) $site->id) {
            throw new EcommerceDomainException('RS export not found for this site.', 404);
        }

        /** @var EcommerceRsExport|null $target */
        $target = EcommerceRsExport::query()
            ->where('site_id', $site->id)
            ->whereKey($export->id)
            ->with(['site.project:id,name,user_id', 'order:id,site_id,order_number,status,payment_status,currency', 'generatedBy:id,name'])
            ->first();

        if (! $target) {
            throw new EcommerceDomainException('RS export not found for this site.', 404);
        }

        if ($requireValid && $target->status !== EcommerceRsExport::STATUS_VALID) {
            throw new EcommerceDomainException(
                'Only valid RS exports can be synced.',
                422,
                [
                    'export_id' => $target->id,
                    'export_status' => $target->status,
                ]
            );
        }

        return $target;
    }

    private function assertSyncInSite(Site $site, EcommerceRsSync $sync, bool $withAttempts): EcommerceRsSync
    {
        if ((string) $sync->site_id !== (string) $site->id) {
            throw new EcommerceDomainException('RS sync not found for this site.', 404);
        }

        $query = EcommerceRsSync::query()
            ->where('site_id', $site->id)
            ->whereKey($sync->id)
            ->with([
                'site.project:id,name,user_id',
                'export:id,site_id,order_id,status,schema_version,export_hash,generated_at',
                'order:id,site_id,order_number,status,payment_status,currency',
                'requestedBy:id,name',
            ]);

        if ($withAttempts) {
            $query->with(['attempts' => function ($attemptQuery): void {
                $attemptQuery->orderByDesc('attempt_no')->orderByDesc('id');
            }]);
        }

        /** @var EcommerceRsSync|null $target */
        $target = $query->first();

        if (! $target) {
            throw new EcommerceDomainException('RS sync not found for this site.', 404);
        }

        return $target;
    }

    private function buildIdempotencyKey(string $siteId, int $exportId, string $connector): string
    {
        return sprintf('rs-sync:%s:%d:%s', $siteId, $exportId, $connector);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSync(EcommerceRsSync $sync, bool $includeAttempts): array
    {
        return [
            'id' => $sync->id,
            'site_id' => $sync->site_id,
            'export_id' => $sync->export_id,
            'order_id' => $sync->order_id,
            'order_number' => $sync->order?->order_number,
            'connector' => $sync->connector,
            'idempotency_key' => $sync->idempotency_key,
            'status' => $sync->status,
            'attempts_count' => (int) $sync->attempts_count,
            'max_attempts' => (int) $sync->max_attempts,
            'attempts_remaining' => max(0, (int) $sync->max_attempts - (int) $sync->attempts_count),
            'next_retry_at' => $sync->next_retry_at?->toISOString(),
            'last_attempt_at' => $sync->last_attempt_at?->toISOString(),
            'remote_reference' => $sync->remote_reference,
            'last_error' => $sync->last_error,
            'response_snapshot_json' => $sync->response_snapshot_json ?? [],
            'meta_json' => $sync->meta_json ?? [],
            'requested_by' => $sync->requested_by,
            'requested_by_name' => $sync->requestedBy?->name,
            'export' => $sync->export
                ? [
                    'id' => $sync->export->id,
                    'status' => $sync->export->status,
                    'schema_version' => $sync->export->schema_version,
                    'export_hash' => $sync->export->export_hash,
                    'generated_at' => $sync->export->generated_at?->toISOString(),
                ]
                : null,
            'started_at' => $sync->started_at?->toISOString(),
            'completed_at' => $sync->completed_at?->toISOString(),
            'created_at' => $sync->created_at?->toISOString(),
            'updated_at' => $sync->updated_at?->toISOString(),
            'attempts' => $includeAttempts
                ? $sync->attempts
                    ->map(fn (EcommerceRsSyncAttempt $attempt): array => [
                        'id' => $attempt->id,
                        'attempt_no' => (int) $attempt->attempt_no,
                        'status' => $attempt->status,
                        'request_payload_json' => $attempt->request_payload_json ?? [],
                        'response_payload_json' => $attempt->response_payload_json ?? [],
                        'error_message' => $attempt->error_message,
                        'duration_ms' => $attempt->duration_ms,
                        'started_at' => $attempt->started_at?->toISOString(),
                        'finished_at' => $attempt->finished_at?->toISOString(),
                        'created_at' => $attempt->created_at?->toISOString(),
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSync(
        EcommerceRsSync $sync,
        string $event,
        string $status,
        string $message,
        array $context = []
    ): void {
        $project = $sync->site?->project;

        $attributes = [
            'source' => 'ecommerce-rs-sync',
            'identifier' => (string) $sync->id,
            'context' => [
                'sync_id' => $sync->id,
                'site_id' => $sync->site_id,
                'export_id' => $sync->export_id,
                'order_id' => $sync->order_id,
                'connector' => $sync->connector,
                ...$context,
            ],
            'occurred_at' => now(),
            'user_id' => $sync->requested_by,
        ];

        if ($project) {
            $this->operationLogs->logProject(
                project: $project,
                channel: OperationLog::CHANNEL_SYSTEM,
                event: $event,
                status: $status,
                message: $message,
                attributes: $attributes
            );

            return;
        }

        $this->operationLogs->log(
            channel: OperationLog::CHANNEL_SYSTEM,
            event: $event,
            status: $status,
            message: $message,
            attributes: $attributes
        );
    }

    private function normalizeSyncStatus(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, [
            EcommerceRsSync::STATUS_QUEUED,
            EcommerceRsSync::STATUS_PROCESSING,
            EcommerceRsSync::STATUS_SUCCEEDED,
            EcommerceRsSync::STATUS_FAILED,
        ], true) ? $normalized : null;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $resolved = (int) $value;

            return $resolved > 0 ? $resolved : null;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }
}
