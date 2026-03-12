<?php

namespace App\Http\Controllers;

use App\Ecommerce\Contracts\EcommercePaymentWebhookServiceContract;
use App\Ecommerce\Contracts\EcommerceGatewayConfigServiceContract;
use App\Models\OperationLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\OperationLogService;
use App\Services\PluginManager;
use App\Services\ReferralRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private PluginManager $pluginManager,
        private EcommerceGatewayConfigServiceContract $ecommerceGatewayConfig,
        private ReferralRedemptionService $redemptionService,
        private OperationLogService $operationLogs,
        private EcommercePaymentWebhookServiceContract $ecommerceWebhookSync
    ) {}

    /**
     * Handle incoming webhook from a payment gateway.
     */
    public function webhook(Request $request, string $plugin): Response
    {
        $webhookEventId = $this->resolveWebhookEventId($request);
        $idempotencyCacheKey = null;

        if ($webhookEventId !== null) {
            $idempotencyCacheKey = sprintf('payment-webhook:%s:%s', $plugin, sha1($webhookEventId));
            $inserted = Cache::add($idempotencyCacheKey, now()->toIso8601String(), now()->addDay());

            if (! $inserted) {
                Log::info("Duplicate payment webhook ignored for {$plugin}", [
                    'event_id' => $webhookEventId,
                ]);

                $this->operationLogs->logPayment(
                    event: 'webhook_duplicate_ignored',
                    status: OperationLog::STATUS_WARNING,
                    message: "Duplicate webhook ignored for gateway {$plugin}.",
                    attributes: [
                        'source' => self::class,
                        'identifier' => $webhookEventId,
                        'context' => [
                            'gateway' => $plugin,
                        ],
                    ]
                );

                return response('Duplicate webhook ignored', 200);
            }
        }

        try {
            $gateway = $this->ecommerceGatewayConfig->resolveGatewayForWebhook($plugin, $request->all())
                ?? $this->pluginManager->getGatewayBySlug($plugin);

            if (! $gateway) {
                if ($idempotencyCacheKey !== null) {
                    Cache::forget($idempotencyCacheKey);
                }

                Log::warning("Payment webhook received for unknown gateway: {$plugin}");

                $this->operationLogs->logPayment(
                    event: 'webhook_gateway_not_found',
                    status: OperationLog::STATUS_ERROR,
                    message: "Webhook received for unknown gateway {$plugin}.",
                    attributes: [
                        'source' => self::class,
                        'identifier' => $webhookEventId,
                        'context' => ['gateway' => $plugin],
                    ]
                );

                return response('Gateway not found', 404);
            }

            Log::info("Processing webhook for {$plugin}", [
                'headers' => $request->headers->all(),
                'payload_size' => strlen($request->getContent()),
                'event_id' => $webhookEventId,
            ]);

            $response = $gateway->handleWebhook($request);

            if ($response->getStatusCode() < 400) {
                $ecommerceSync = $this->ecommerceWebhookSync->synchronize(
                    provider: $plugin,
                    payload: $request->all(),
                    webhookEventId: $webhookEventId
                );

                $this->logEcommerceWebhookSyncResult(
                    plugin: $plugin,
                    eventId: $webhookEventId,
                    result: $ecommerceSync
                );
            } else {
                $this->operationLogs->logPayment(
                    event: 'ecommerce_webhook_sync_skipped',
                    status: OperationLog::STATUS_WARNING,
                    message: "Ecommerce webhook sync skipped for {$plugin} due to gateway HTTP {$response->getStatusCode()}.",
                    attributes: [
                        'source' => self::class,
                        'identifier' => $webhookEventId,
                        'context' => [
                            'gateway' => $plugin,
                            'reason' => 'gateway_rejected',
                            'http_status' => $response->getStatusCode(),
                        ],
                    ]
                );
            }

            // Allow provider retry when webhook was not accepted.
            if ($idempotencyCacheKey !== null && $response->getStatusCode() >= 400) {
                Cache::forget($idempotencyCacheKey);
            }

            $this->operationLogs->logPayment(
                event: 'webhook_processed',
                status: $response->getStatusCode() >= 400 ? OperationLog::STATUS_WARNING : OperationLog::STATUS_SUCCESS,
                message: "Webhook processed for {$plugin} with HTTP {$response->getStatusCode()}.",
                attributes: [
                    'source' => self::class,
                    'identifier' => $webhookEventId,
                    'context' => [
                        'gateway' => $plugin,
                        'http_status' => $response->getStatusCode(),
                        'payload_size' => strlen($request->getContent()),
                    ],
                ]
            );

            return $response;
        } catch (\Exception $e) {
            if ($idempotencyCacheKey !== null) {
                // Processing crashed; keep retry path open.
                Cache::forget($idempotencyCacheKey);
            }

            Log::error("Webhook error for {$plugin}: ".$e->getMessage(), [
                'exception' => $e,
                'event_id' => $webhookEventId,
            ]);

            $this->operationLogs->logPayment(
                event: 'webhook_exception',
                status: OperationLog::STATUS_ERROR,
                message: $e->getMessage(),
                attributes: [
                    'source' => self::class,
                    'identifier' => $webhookEventId,
                    'context' => [
                        'gateway' => $plugin,
                    ],
                ]
            );

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle callback/return from a payment gateway.
     */
    public function callback(Request $request)
    {
        $plugin = $request->query('gateway');

        if (! $plugin) {
            Log::warning('Payment callback received without gateway parameter');

            return redirect()->route('create')
                ->with('error', 'Invalid payment callback.');
        }

        try {
            $gateway = $this->ecommerceGatewayConfig->resolveGatewayForWebhook($plugin, $request->query())
                ?? $this->pluginManager->getGatewayBySlug($plugin);

            if (! $gateway) {
                Log::warning("Payment callback received for unknown gateway: {$plugin}");

                return redirect()->route('create')
                    ->with('error', 'Payment gateway not found.');
            }

            Log::info("Processing callback for {$plugin}", [
                'query' => $request->query(),
            ]);

            return $gateway->callback($request);
        } catch (\Exception $e) {
            Log::error("Callback error for {$plugin}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return redirect()->route('create')
                ->with('error', 'Payment processing failed. Please contact support.');
        }
    }

    /**
     * Initiate a payment for a subscription.
     */
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'gateway' => 'required|string',
            'apply_referral_credits' => 'boolean',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $user = $request->user();

            // Check if user already has an active subscription
            if ($user->hasActiveSubscription()) {
                return back()->withErrors(['subscription' => 'You already have an active subscription.']);
            }

            // Check if user has a pending subscription
            if ($user->hasPendingSubscription()) {
                return back()->withErrors(['subscription' => 'You have a pending subscription. Please wait for it to be processed or cancel it first.']);
            }

            // Handle referral credits payment
            if ($validated['apply_referral_credits'] ?? false) {
                return $this->handleReferralCreditsPayment($user, $plan);
            }

            // Regular payment gateway flow
            $gateway = $this->pluginManager->getGatewayBySlug($validated['gateway']);

            if (! $gateway) {
                return back()->withErrors(['gateway' => 'Payment gateway not available.']);
            }

            $result = $gateway->initPayment($plan, $user);

            // If result is an array (e.g., bank transfer data), flash it and redirect back
            if (is_array($result)) {
                return back()->with('bankTransfer', $result);
            }

            // If result is a string (URL), redirect to it
            if (is_string($result)) {
                return redirect($result);
            }

            // Otherwise, it's a RedirectResponse
            return $result;
        } catch (\Exception $e) {
            Log::error('Payment initiation failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id,
                'plan_id' => $validated['plan_id'],
                'gateway' => $validated['gateway'] ?? 'referral_credits',
                'exception' => $e,
            ]);

            $this->operationLogs->logPayment(
                event: 'payment_initiation_failed',
                status: OperationLog::STATUS_ERROR,
                message: $e->getMessage(),
                attributes: [
                    'source' => self::class,
                    'user_id' => $request->user()->id,
                    'context' => [
                        'plan_id' => $validated['plan_id'],
                        'gateway' => $validated['gateway'] ?? 'referral_credits',
                    ],
                ]
            );

            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /**
     * Handle payment using referral credits.
     */
    private function handleReferralCreditsPayment($user, Plan $plan)
    {
        $balance = (float) $user->referral_credit_balance;

        // Check if user has enough credits to cover the full plan price
        if ($balance < $plan->price) {
            return back()->withErrors([
                'referral' => 'Insufficient referral credits. You need $'.number_format($plan->price, 2).
                    ' but only have $'.number_format($balance, 2).'.',
            ]);
        }

        // Redeem credits for the plan price
        $result = $this->redemptionService->redeemForBillingDiscount($user, $plan->price);

        if (! $result['success']) {
            return back()->withErrors(['referral' => $result['error']]);
        }

        // Create subscription directly (skip payment gateway)
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_method' => 'Referral Credits',
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => $plan->price,
            'starts_at' => now(),
            'renewal_at' => $this->calculateRenewalDate($plan),
            'metadata' => [
                'referral_credits_used' => $plan->price,
                'referral_transaction_id' => $result['transaction']->id,
            ],
        ]);

        // Update user's plan
        $user->update(['plan_id' => $plan->id]);

        Log::info('Subscription created with referral credits', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'credits_used' => $plan->price,
        ]);

        return redirect()->route('billing.index')
            ->with('success', 'Subscription activated with referral credits!');
    }

    /**
     * Calculate renewal date based on plan billing period.
     */
    private function calculateRenewalDate(Plan $plan): \Carbon\Carbon
    {
        return match ($plan->billing_period) {
            'yearly' => now()->addYear(),
            'lifetime' => now()->addYears(100),
            default => now()->addMonth(),
        };
    }

    /**
     * Extract provider webhook event identifier for idempotency.
     */
    private function resolveWebhookEventId(Request $request): ?string
    {
        $payload = $request->all();
        $candidate = $payload['id']
            ?? data_get($payload, 'event.id')
            ?? data_get($payload, 'event_id')
            ?? $request->header('X-Webhook-ID')
            ?? $request->header('PayPal-Transmission-Id')
            ?? null;

        if (! is_string($candidate)) {
            return null;
        }

        $value = trim($candidate);

        return $value === '' ? null : $value;
    }

    /**
     * Get available payment gateways for checkout.
     */
    public function getAvailableGateways()
    {
        $gateways = $this->pluginManager->getActiveGateways();

        $result = [];
        foreach ($gateways as $gateway) {
            $result[] = [
                'slug' => $this->pluginManager->getGatewaySlug($gateway),
                'name' => $gateway->getName(),
                'description' => $gateway->getDescription(),
                'icon' => $gateway->getIcon(),
                'supports_auto_renewal' => $gateway->supportsAutoRenewal(),
                'requires_manual_approval' => $gateway->requiresManualApproval(),
            ];
        }

        return response()->json($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function logEcommerceWebhookSyncResult(string $plugin, ?string $eventId, array $result): void
    {
        $handled = (bool) ($result['handled'] ?? false);
        $reason = is_string($result['reason'] ?? null) ? $result['reason'] : null;
        $status = (string) ($result['normalized_status'] ?? '');
        $paymentId = $result['payment_id'] ?? null;
        $orderId = $result['order_id'] ?? null;
        $projectId = $result['project_id'] ?? null;

        if (! $handled) {
            if ($reason === null || $reason === 'not_ecommerce_event') {
                return;
            }

            $this->operationLogs->logPayment(
                event: 'ecommerce_webhook_sync_skipped',
                status: OperationLog::STATUS_WARNING,
                message: "Ecommerce webhook sync skipped for {$plugin}: {$reason}.",
                attributes: [
                    'source' => self::class,
                    'identifier' => $eventId,
                    'project_id' => $projectId,
                    'context' => [
                        'gateway' => $plugin,
                        'reason' => $reason,
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                    ],
                ]
            );

            return;
        }

        $isIdempotent = (bool) ($result['idempotent'] ?? false);

        $this->operationLogs->logPayment(
            event: 'ecommerce_webhook_synced',
            status: $isIdempotent ? OperationLog::STATUS_INFO : OperationLog::STATUS_SUCCESS,
            message: $isIdempotent
                ? "Ecommerce webhook already applied for {$plugin}."
                : "Ecommerce webhook synced for {$plugin}.",
            attributes: [
                'source' => self::class,
                'identifier' => $eventId,
                'project_id' => $projectId,
                'context' => [
                    'gateway' => $plugin,
                    'payment_status' => $status,
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'idempotent' => $isIdempotent,
                ],
            ]
        );
    }
}
