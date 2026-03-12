<?php

namespace App\Services;

use App\Contracts\PaymentGatewayPlugin;
use App\Helpers\CurrencyHelper;
use App\Models\OperationLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionLifecycleService
{
    public function __construct(
        private PluginManager $pluginManager,
        private OperationLogService $operationLogs,
        private NotificationService $notifications
    ) {}

    /**
     * Execute scheduled subscription renewal lifecycle.
     *
     * @return array{
     *     due:int,
     *     renewed:int,
     *     retry_scheduled:int,
     *     moved_to_grace:int,
     *     suspended:int,
     *     errors:int
     * }
     */
    public function processScheduledLifecycle(): array
    {
        $summary = [
            'due' => 0,
            'renewed' => 0,
            'retry_scheduled' => 0,
            'moved_to_grace' => 0,
            'suspended' => 0,
            'errors' => 0,
        ];

        $now = now();

        Subscription::query()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->whereNotNull('renewal_at')
            ->where('renewal_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', $now);
            })
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$summary): void {
                foreach ($subscriptions as $subscription) {
                    $summary['due']++;

                    try {
                        $result = $this->attemptScheduledRenewal((int) $subscription->id);
                        if (array_key_exists($result, $summary)) {
                            $summary[$result]++;
                        }
                    } catch (Throwable $e) {
                        $summary['errors']++;

                        $this->operationLogs->logSubscription(
                            event: 'subscription_renewal_attempt_exception',
                            status: OperationLog::STATUS_ERROR,
                            message: $e->getMessage(),
                            attributes: [
                                'source' => self::class,
                                'user_id' => $subscription->user_id,
                                'identifier' => (string) $subscription->id,
                                'context' => [
                                    'subscription_id' => $subscription->id,
                                ],
                            ]
                        );
                    }
                }
            });

        Subscription::query()
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$summary): void {
                foreach ($subscriptions as $subscription) {
                    $retryLimit = $this->resolveRetryLimit($subscription);
                    if ((int) $subscription->renewal_retry_count < $retryLimit) {
                        continue;
                    }

                    $this->transitionToGrace($subscription, 'retry_limit_reached');
                    $summary['moved_to_grace']++;
                }
            });

        Subscription::query()
            ->where('status', Subscription::STATUS_GRACE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$summary): void {
                foreach ($subscriptions as $subscription) {
                    $this->suspendSubscription($subscription, 'grace_period_expired');
                    $summary['suspended']++;
                }
            });

        return $summary;
    }

    /**
     * @return 'renewed'|'retry_scheduled'|'moved_to_grace'|'suspended'
     */
    public function attemptScheduledRenewal(int $subscriptionId): string
    {
        return DB::transaction(function () use ($subscriptionId): string {
            $subscription = Subscription::query()
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->with(['user', 'plan'])
                ->firstOrFail();

            if (! in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE], true)) {
                return 'retry_scheduled';
            }

            if (! $subscription->renewal_at || $subscription->renewal_at->isFuture()) {
                return 'retry_scheduled';
            }

            if ($subscription->next_retry_at && $subscription->next_retry_at->isFuture()) {
                return 'retry_scheduled';
            }

            $this->applyPendingPlanChangeIfDue($subscription);
            $subscription->loadMissing('plan', 'user');

            $attemptNo = (int) $subscription->renewal_retry_count + 1;
            $transaction = $this->createPendingRenewalInvoice($subscription, $attemptNo);
            $outcome = $this->attemptAutoCharge($subscription);

            if (($outcome['success'] ?? false) === true) {
                $nextRenewalAt = $this->calculateNextRenewalAt($subscription);

                $transaction->update([
                    'status' => Transaction::STATUS_COMPLETED,
                    'notes' => 'Automatic renewal charge completed.',
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'charge_status' => 'completed',
                        'provider_status' => $outcome['provider_status'] ?? null,
                    ]),
                ]);

                $subscription->update([
                    'status' => Subscription::STATUS_ACTIVE,
                    'renewal_at' => $nextRenewalAt,
                    'renewal_retry_count' => 0,
                    'last_renewal_attempt_at' => now(),
                    'next_retry_at' => null,
                    'grace_ends_at' => null,
                    'suspended_at' => null,
                    'last_renewal_error' => null,
                ]);

                if ($subscription->user) {
                    $subscription->user->update([
                        'plan_id' => $subscription->plan_id,
                    ]);
                }

                $this->operationLogs->logSubscription(
                    event: 'subscription_renewal_completed',
                    status: OperationLog::STATUS_SUCCESS,
                    message: "Subscription #{$subscription->id} renewed successfully.",
                    attributes: [
                        'source' => self::class,
                        'user_id' => $subscription->user_id,
                        'identifier' => (string) $subscription->id,
                        'context' => [
                            'transaction_id' => $transaction->id,
                            'next_renewal_at' => $nextRenewalAt->toISOString(),
                            'provider_status' => $outcome['provider_status'] ?? null,
                        ],
                    ]
                );

                return 'renewed';
            }

            $reason = (string) ($outcome['reason'] ?? 'Automatic renewal failed.');
            $retryLimit = $this->resolveRetryLimit($subscription);
            $retryCount = min($retryLimit, (int) $subscription->renewal_retry_count + 1);
            if (($outcome['retryable'] ?? true) !== true) {
                $retryCount = $retryLimit;
            }

            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'notes' => $reason,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'charge_status' => 'failed',
                    'provider_status' => $outcome['provider_status'] ?? null,
                    'failure_reason' => $reason,
                ]),
            ]);

            $subscription->update([
                'last_renewal_attempt_at' => now(),
                'renewal_retry_count' => $retryCount,
                'last_renewal_error' => $reason,
            ]);

            if ($retryCount >= $retryLimit) {
                $graceDays = $this->graceDays();
                if ($graceDays <= 0) {
                    $this->suspendSubscription($subscription, 'retry_limit_reached');

                    return 'suspended';
                }

                $this->transitionToGrace($subscription, 'retry_limit_reached');

                return 'moved_to_grace';
            }

            $nextRetryAt = now()->addHours($this->retryIntervalHours());

            $subscription->update([
                'status' => Subscription::STATUS_PAST_DUE,
                'next_retry_at' => $nextRetryAt,
                'grace_ends_at' => null,
            ]);

            $this->operationLogs->logSubscription(
                event: 'subscription_renewal_retry_scheduled',
                status: OperationLog::STATUS_WARNING,
                message: "Subscription #{$subscription->id} renewal failed, retry scheduled.",
                attributes: [
                    'source' => self::class,
                    'user_id' => $subscription->user_id,
                    'identifier' => (string) $subscription->id,
                    'context' => [
                        'transaction_id' => $transaction->id,
                        'retry_count' => $retryCount,
                        'retry_limit' => $retryLimit,
                        'next_retry_at' => $nextRetryAt->toISOString(),
                        'reason' => $reason,
                    ],
                ]
            );

            return 'retry_scheduled';
        });
    }

    private function createPendingRenewalInvoice(Subscription $subscription, int $attemptNo): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'amount' => $subscription->amount,
            'currency' => CurrencyHelper::getCode(),
            'status' => Transaction::STATUS_PENDING,
            'type' => Transaction::TYPE_SUBSCRIPTION_RENEWAL,
            'payment_method' => (string) ($subscription->payment_method ?: Transaction::PAYMENT_MANUAL),
            'transaction_date' => now(),
            'metadata' => [
                'invoice_kind' => 'subscription_renewal',
                'attempt_no' => $attemptNo,
                'subscription_status_before_attempt' => $subscription->status,
                'renewal_due_at' => $subscription->renewal_at?->toISOString(),
            ],
        ]);

        $this->operationLogs->logSubscription(
            event: 'subscription_renewal_invoice_issued',
            status: OperationLog::STATUS_INFO,
            message: "Renewal invoice issued for subscription #{$subscription->id}.",
            attributes: [
                'source' => self::class,
                'user_id' => $subscription->user_id,
                'identifier' => (string) $subscription->id,
                'context' => [
                    'transaction_id' => $transaction->id,
                    'amount' => (float) $subscription->amount,
                    'attempt_no' => $attemptNo,
                ],
            ]
        );

        return $transaction;
    }

    /**
     * @return array{success: bool, retryable: bool, reason?: string, provider_status?: string}
     */
    private function attemptAutoCharge(Subscription $subscription): array
    {
        $paymentMethod = strtolower(str_replace([' ', '-'], ['_', '_'], (string) $subscription->payment_method));

        if ($paymentMethod === '' || in_array($paymentMethod, [Subscription::PAYMENT_MANUAL, Subscription::PAYMENT_BANK_TRANSFER], true)) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => 'Payment method requires manual renewal approval.',
                'provider_status' => 'manual',
            ];
        }

        $slug = $this->resolveGatewaySlug($paymentMethod);
        if (! $slug) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => 'Payment gateway mapping is not configured for this subscription.',
                'provider_status' => 'gateway_mapping_missing',
            ];
        }

        $gateway = $this->pluginManager->getGatewayBySlug($slug);
        if (! $gateway instanceof PaymentGatewayPlugin) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => "Payment gateway [{$slug}] is not available.",
                'provider_status' => 'gateway_unavailable',
            ];
        }

        if (! $gateway->supportsAutoRenewal()) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => "Payment gateway [{$slug}] does not support auto-renewal.",
                'provider_status' => 'auto_renewal_unsupported',
            ];
        }

        if (! $subscription->external_subscription_id) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => 'External subscription ID is missing.',
                'provider_status' => 'missing_external_subscription_id',
            ];
        }

        try {
            $providerStatus = strtolower((string) data_get(
                $gateway->getSubscriptionStatus($subscription->external_subscription_id),
                'status',
                'unknown'
            ));
            $providerStatus = str_replace([' ', '-'], ['_', '_'], $providerStatus);

            if (in_array($providerStatus, ['active', 'approved', 'completed', 'paid'], true)) {
                return [
                    'success' => true,
                    'retryable' => false,
                    'provider_status' => $providerStatus,
                ];
            }

            if (in_array($providerStatus, ['pending', 'approval_pending', 'processing', 'past_due'], true)) {
                return [
                    'success' => false,
                    'retryable' => true,
                    'reason' => "Provider status is {$providerStatus}.",
                    'provider_status' => $providerStatus,
                ];
            }

            return [
                'success' => false,
                'retryable' => false,
                'reason' => "Provider status is {$providerStatus}.",
                'provider_status' => $providerStatus,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'retryable' => true,
                'reason' => $e->getMessage(),
                'provider_status' => 'exception',
            ];
        }
    }

    private function transitionToGrace(Subscription $subscription, string $reason): void
    {
        $graceEndsAt = now()->addDays($this->graceDays());

        $subscription->update([
            'status' => Subscription::STATUS_GRACE,
            'next_retry_at' => null,
            'grace_ends_at' => $graceEndsAt,
            'last_renewal_error' => $this->appendReason($subscription->last_renewal_error, $reason),
        ]);

        $this->operationLogs->logSubscription(
            event: 'subscription_grace_started',
            status: OperationLog::STATUS_WARNING,
            message: "Subscription #{$subscription->id} entered grace period.",
            attributes: [
                'source' => self::class,
                'user_id' => $subscription->user_id,
                'identifier' => (string) $subscription->id,
                'context' => [
                    'reason' => $reason,
                    'grace_ends_at' => $graceEndsAt->toISOString(),
                    'retry_count' => (int) $subscription->renewal_retry_count,
                ],
            ]
        );
    }

    public function suspendSubscription(Subscription $subscription, string $reason): void
    {
        $subscription->loadMissing(['user', 'plan']);

        $subscription->update([
            'status' => Subscription::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'ends_at' => $subscription->ends_at ?? now(),
            'next_retry_at' => null,
            'grace_ends_at' => null,
            'last_renewal_error' => $this->appendReason($subscription->last_renewal_error, $reason),
        ]);

        $fallbackPlanId = null;
        if ($this->fallbackToDefaultPlanOnSuspend() && $subscription->user) {
            $fallbackPlanId = $this->applyFallbackPlanForUser($subscription->user);
        }

        if ($subscription->user) {
            $this->notifications->notifySubscriptionExpired($subscription->user, $subscription);
        }

        $this->operationLogs->logSubscription(
            event: 'subscription_suspended',
            status: OperationLog::STATUS_WARNING,
            message: "Subscription #{$subscription->id} suspended.",
            attributes: [
                'source' => self::class,
                'user_id' => $subscription->user_id,
                'identifier' => (string) $subscription->id,
                'context' => [
                    'reason' => $reason,
                    'fallback_plan_id' => $fallbackPlanId,
                ],
            ]
        );
    }

    private function applyFallbackPlanForUser($user): ?int
    {
        $defaultPlanId = (int) SystemSetting::get('default_plan_id', 0);
        $defaultPlan = $defaultPlanId > 0 ? Plan::query()->find($defaultPlanId) : null;

        if (! $defaultPlan) {
            $user->update(['plan_id' => null]);

            return null;
        }

        $user->update(['plan_id' => $defaultPlan->id]);
        $user->refillBuildCredits();

        return $defaultPlan->id;
    }

    private function applyPendingPlanChangeIfDue(Subscription $subscription): void
    {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $pending = is_array($metadata['pending_plan_change'] ?? null) ? $metadata['pending_plan_change'] : null;

        if (! $pending) {
            return;
        }

        $targetPlanId = isset($pending['target_plan_id']) ? (int) $pending['target_plan_id'] : 0;
        if ($targetPlanId <= 0) {
            return;
        }

        $effectiveOnRaw = $pending['effective_on'] ?? null;
        $effectiveOn = is_string($effectiveOnRaw) && $effectiveOnRaw !== ''
            ? Carbon::parse($effectiveOnRaw)
            : $subscription->renewal_at;

        if ($effectiveOn && $effectiveOn->isFuture()) {
            return;
        }

        $targetPlan = Plan::query()
            ->whereKey($targetPlanId)
            ->where('is_active', true)
            ->first();

        if (! $targetPlan) {
            return;
        }

        $fromPlanId = $subscription->plan_id;
        $subscription->update([
            'plan_id' => $targetPlan->id,
            'amount' => $targetPlan->price,
            'metadata' => array_merge($metadata, [
                'last_plan_change' => [
                    'from_plan_id' => $fromPlanId,
                    'to_plan_id' => $targetPlan->id,
                    'applied_at' => now()->toISOString(),
                    'source' => 'pending_plan_change_renewal',
                ],
                'pending_plan_change' => null,
            ]),
        ]);

        if ($subscription->user) {
            $subscription->user->update([
                'plan_id' => $targetPlan->id,
            ]);
        }

        $this->operationLogs->logSubscription(
            event: 'subscription_pending_plan_change_applied',
            status: OperationLog::STATUS_INFO,
            message: "Pending plan change applied for subscription #{$subscription->id}.",
            attributes: [
                'source' => self::class,
                'user_id' => $subscription->user_id,
                'identifier' => (string) $subscription->id,
                'context' => [
                    'from_plan_id' => $fromPlanId,
                    'to_plan_id' => $targetPlan->id,
                ],
            ]
        );
    }

    private function calculateNextRenewalAt(Subscription $subscription): CarbonInterface
    {
        $anchor = $subscription->renewal_at;
        if (! $anchor || $anchor->isPast()) {
            $anchor = now();
        }

        $billingPeriod = $subscription->plan?->billing_period ?? 'monthly';

        return match ($billingPeriod) {
            'yearly' => $anchor->copy()->addYear(),
            'lifetime' => $anchor->copy()->addYears(100),
            default => $anchor->copy()->addMonth(),
        };
    }

    private function resolveGatewaySlug(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'paypal' => 'paypal',
            'bank_transfer' => 'bank-transfer',
            'bank_of_georgia', 'bog', 'bankofgeorgia' => 'bank-of-georgia',
            'fleet', 'flitt' => 'fleet',
            default => str_contains($paymentMethod, '_')
                ? str_replace('_', '-', $paymentMethod)
                : $paymentMethod,
        };
    }

    private function resolveRetryLimit(Subscription $subscription): int
    {
        $configured = (int) ($subscription->renewal_retry_limit ?? $this->maxRetries());

        return max(0, $configured);
    }

    private function maxRetries(): int
    {
        return max(0, (int) config('billing.subscriptions.max_retries', 3));
    }

    private function retryIntervalHours(): int
    {
        return max(1, (int) config('billing.subscriptions.retry_interval_hours', 24));
    }

    private function graceDays(): int
    {
        return max(0, (int) config('billing.subscriptions.grace_days', 5));
    }

    private function fallbackToDefaultPlanOnSuspend(): bool
    {
        return (bool) config('billing.subscriptions.fallback_to_default_plan_on_suspend', true);
    }

    private function appendReason(?string $current, string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            return (string) ($current ?? '');
        }

        if (! $current || trim($current) === '') {
            return $reason;
        }

        return trim($current)."\n".$reason;
    }
}
