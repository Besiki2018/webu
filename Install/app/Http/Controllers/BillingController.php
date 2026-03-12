<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BuildCreditService;
use App\Services\InvoiceService;
use App\Services\PricingQuoteService;
use App\Services\SubscriptionProrationService;
use App\Services\UsageMeteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected BuildCreditService $buildCreditService,
        protected UsageMeteringService $usageMeteringService,
        protected PricingQuoteService $pricingQuote,
        protected SubscriptionProrationService $proration
    ) {}

    /**
     * Display the user billing page.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();

        // Get active subscription with plan
        $subscription = $user->activeSubscription()
            ->with('plan')
            ->first();

        // Get pending bank transfer subscription (if any)
        $pendingSubscription = $user->subscriptions()
            ->where('status', Subscription::STATUS_PENDING)
            ->where('payment_method', Subscription::PAYMENT_BANK_TRANSFER)
            ->with('plan')
            ->first();

        // Get user's transactions with pagination
        $transactions = $user->transactions()
            ->with('subscription.plan')
            ->latest('transaction_date')
            ->paginate(10);

        // Get available active plans
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get();

        // Get available payment gateways
        $paymentGateways = $this->getAvailableGateways();

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription,
            'pendingSubscription' => $pendingSubscription,
            'transactions' => $transactions,
            'plans' => $plans,
            'paymentGateways' => $paymentGateways,
            'currentPlanId' => $subscription?->plan_id ?? $user->plan_id,
            'billingOverview' => $this->buildBillingOverviewPayload($user, $subscription, $plans),
        ]);
    }

    /**
     * Display the plans page.
     */
    public function plans(): InertiaResponse
    {
        $user = Auth::user();

        // Get available active plans
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get();

        // Get available payment gateways
        $paymentGateways = $this->getAvailableGateways();

        // Get current plan ID if user has active subscription
        $currentPlanId = $user->activeSubscription?->plan_id;

        return Inertia::render('Billing/Plans', [
            'plans' => $plans,
            'paymentGateways' => $paymentGateways,
            'currentPlanId' => $currentPlanId,
            'referralCreditBalance' => (float) $user->referral_credit_balance,
        ]);
    }

    /**
     * Download/view invoice PDF for a transaction.
     */
    public function downloadInvoice(Transaction $transaction): Response
    {
        // Authorization: User must own the transaction
        if ($transaction->user_id !== Auth::id()) {
            abort(403, 'You are not authorized to view this invoice.');
        }

        return $this->invoiceService->streamPdf($transaction);
    }

    /**
     * Cancel the user's active subscription.
     */
    public function cancelSubscription(Request $request)
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription;

        if (! $subscription) {
            return back()->with('error', 'You do not have an active subscription to cancel.');
        }

        $reason = $request->input('reason', 'Cancelled by user');
        $subscription->cancel($user->id, false, $reason);

        return back()->with('success', 'Your subscription has been cancelled.');
    }

    /**
     * Preview dynamic monthly pricing using selected add-ons and usage context.
     */
    public function pricePreview(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'version_id' => ['nullable', 'integer', 'exists:plan_versions,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'usage' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $quote = $this->pricingQuote->compose($plan, $validated);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'error' => 'Pricing version not found for this plan.',
            ], 404);
        }

        return response()->json([
            'quote' => $quote,
        ]);
    }

    /**
     * Preview prorated adjustment for plan upgrade/downgrade.
     */
    public function prorationPreview(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'apply_at_renewal' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $subscription = $user->activeSubscription()->with('plan')->first();

        if (! $subscription) {
            return response()->json([
                'error' => 'No billable subscription found.',
                'code' => 'subscription_required',
            ], 422);
        }

        $preview = $this->proration->preview(
            $subscription,
            $plan,
            (bool) ($validated['apply_at_renewal'] ?? false)
        );

        return response()->json([
            'proration' => $preview,
        ]);
    }

    /**
     * Apply or schedule plan change with proration/effective-date policy.
     */
    public function changePlan(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'apply_at_renewal' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $subscription = $user->activeSubscription()->with('plan')->first();

        if (! $subscription) {
            return response()->json([
                'error' => 'No billable subscription found.',
                'code' => 'subscription_required',
            ], 422);
        }

        if ((int) $subscription->plan_id === (int) $plan->id) {
            return response()->json([
                'error' => 'Subscription is already on this plan.',
                'code' => 'plan_unchanged',
            ], 422);
        }

        $result = $this->proration->apply(
            actor: $user,
            subscription: $subscription,
            targetPlan: $plan,
            applyAtRenewal: (bool) ($validated['apply_at_renewal'] ?? false),
            reason: $validated['reason'] ?? null
        );

        return response()->json([
            'message' => $result['mode'] === 'scheduled'
                ? 'Plan change scheduled for next renewal.'
                : 'Plan changed successfully.',
            'result' => $result,
        ]);
    }

    /**
     * Get available payment gateways.
     * Filters by active status and currency support.
     */
    protected function getAvailableGateways(): array
    {
        // Get active gateways that support the current system currency
        $plugins = \App\Models\Plugin::active()
            ->byType('payment_gateway')
            ->get();

        $currency = \App\Helpers\CurrencyHelper::getCode();

        return $plugins->filter(function ($plugin) use ($currency) {
            $gateway = $plugin->getInstance();
            $supported = $gateway->getSupportedCurrencies();

            // Empty array means all currencies supported
            return empty($supported) || in_array($currency, $supported);
        })->map(function ($plugin) {
            $gateway = $plugin->getInstance();

            return [
                'slug' => $plugin->slug,
                'name' => $gateway->getName(),
                'description' => $gateway->getDescription() ?? '',
                'icon' => $gateway->getIcon() ?? '',
                'supports_auto_renewal' => method_exists($gateway, 'supportsAutoRenewal') ? $gateway->supportsAutoRenewal() : false,
                'requires_manual_approval' => method_exists($gateway, 'requiresManualApproval') ? $gateway->requiresManualApproval() : false,
            ];
        })->values()->toArray();
    }

    /**
     * Compose a single billing summary payload for the main Billing page.
     */
    protected function buildBillingOverviewPayload(User $user, ?Subscription $subscription, Collection $plans): array
    {
        $currentPlan = $subscription?->plan ?? $user->getCurrentPlan();
        $stats = $this->buildCreditService->getMonthlyStats($user);
        $metering = $this->usageMeteringService->getOwnerUsageSummary($user);
        $recommendations = $this->resolvePlanRecommendations($plans, $currentPlan);
        $renewalAt = $subscription?->renewal_at;
        $renewalState = 'none';
        $subscriptionStatus = $subscription?->status;

        if ($subscription && $subscription->plan?->billing_period === 'lifetime') {
            $renewalState = 'lifetime';
        } elseif ($subscriptionStatus === Subscription::STATUS_SUSPENDED) {
            $renewalState = 'suspended';
        } elseif ($subscriptionStatus === Subscription::STATUS_GRACE) {
            $renewalState = 'grace';
        } elseif ($subscriptionStatus === Subscription::STATUS_PAST_DUE) {
            $renewalState = 'past_due';
        } elseif ($renewalAt !== null) {
            $renewalState = $renewalAt->isPast() ? 'due' : 'upcoming';
        }

        return [
            'current_plan' => $this->serializePlanSummary($currentPlan),
            'renewal' => [
                'date' => $renewalAt?->toISOString(),
                'days_until' => $subscription?->days_until_renewal,
                'state' => $renewalState,
                'subscription_status' => $subscriptionStatus,
                'retry_count' => $subscription?->renewal_retry_count,
                'next_retry_at' => $subscription?->next_retry_at?->toISOString(),
                'grace_ends_at' => $subscription?->grace_ends_at?->toISOString(),
            ],
            'usage' => [
                'period' => $metering['period'] ?? now()->format('Y-m'),
                'build_credits' => [
                    'remaining' => (int) ($stats['remaining_credits'] ?? 0),
                    'used' => (int) ($stats['used_tokens'] ?? 0),
                    'monthly_limit' => (int) ($stats['monthly_allocation'] ?? 0),
                    'usage_percentage' => (float) ($stats['usage_percentage'] ?? 0),
                    'is_unlimited' => (bool) ($stats['is_unlimited'] ?? false),
                    'overage_balance' => (int) ($user->build_credit_overage_balance ?? 0),
                ],
                'orders' => [
                    'used' => (int) ($metering['commerce']['orders'] ?? 0),
                    'limit' => isset($metering['commerce']['orders_limit'])
                        ? ($metering['commerce']['orders_limit'] === null ? null : (int) $metering['commerce']['orders_limit'])
                        : null,
                ],
                'bookings' => [
                    'used' => (int) ($metering['booking']['bookings'] ?? 0),
                    'limit' => isset($metering['booking']['bookings_limit'])
                        ? ($metering['booking']['bookings_limit'] === null ? null : (int) $metering['booking']['bookings_limit'])
                        : null,
                ],
            ],
            'recommendations' => [
                'upgrade' => $this->serializePlanSummary($recommendations['upgrade']),
                'downgrade' => $this->serializePlanSummary($recommendations['downgrade']),
            ],
        ];
    }

    /**
     * Resolve nearest upgrade/downgrade targets from active plans.
     *
     * @return array{upgrade: ?Plan, downgrade: ?Plan}
     */
    protected function resolvePlanRecommendations(Collection $plans, ?Plan $currentPlan): array
    {
        $activePlans = $plans->where('is_active', true)->values();

        if (! $currentPlan) {
            return [
                'upgrade' => $activePlans
                    ->sortBy(fn (Plan $plan): float => $this->planRank($plan))
                    ->first(),
                'downgrade' => null,
            ];
        }

        $upgrade = $activePlans
            ->filter(fn (Plan $plan): bool => $plan->id !== $currentPlan->id && $this->isHigherTierPlan($plan, $currentPlan))
            ->sortBy(fn (Plan $plan): float => $this->planRank($plan))
            ->first();

        $downgrade = $activePlans
            ->filter(fn (Plan $plan): bool => $plan->id !== $currentPlan->id && $this->isLowerTierPlan($plan, $currentPlan))
            ->sortBy(fn (Plan $plan): float => $this->planRank($plan))
            ->last();

        return [
            'upgrade' => $upgrade,
            'downgrade' => $downgrade,
        ];
    }

    protected function isHigherTierPlan(Plan $candidate, Plan $current): bool
    {
        if ((int) $candidate->sort_order !== (int) $current->sort_order) {
            return (int) $candidate->sort_order > (int) $current->sort_order;
        }

        return (float) $candidate->price > (float) $current->price;
    }

    protected function isLowerTierPlan(Plan $candidate, Plan $current): bool
    {
        if ((int) $candidate->sort_order !== (int) $current->sort_order) {
            return (int) $candidate->sort_order < (int) $current->sort_order;
        }

        return (float) $candidate->price < (float) $current->price;
    }

    protected function serializePlanSummary(?Plan $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        return [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'billing_period' => $plan->billing_period,
            'monthly_build_credits' => $plan->getMonthlyBuildCredits(),
            'is_unlimited_credits' => $plan->hasUnlimitedBuildCredits(),
            'max_projects' => $plan->getMaxProjects(),
            'enable_ecommerce' => (bool) $plan->enable_ecommerce,
            'enable_booking' => (bool) $plan->enable_booking,
        ];
    }

    protected function planRank(Plan $plan): float
    {
        return ((float) ((int) $plan->sort_order)) * 1000000 + (float) $plan->price;
    }
}
