<?php

namespace App\Http\Controllers;

use App\Services\BuildCreditOverageService;
use App\Services\BuildCreditService;
use App\Services\UsageMeteringService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildCreditController extends Controller
{
    public function __construct(
        protected BuildCreditService $creditService,
        protected UsageMeteringService $usageMetering,
        protected BuildCreditOverageService $creditOverage
    ) {}

    /**
     * Display build credits page with stats and history.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $plan = $user->getCurrentPlan();
        $perPage = $request->input('per_page', 10);
        $period = $request->input('period', 'current_month');
        $usedOwnApiKey = $request->boolean('used_own_api_key');

        $history = $this->creditService->getUsageHistory($user, $perPage, $period, $usedOwnApiKey);

        // Transform history data to include project names
        $historyData = $history->through(function ($item) {
            return [
                'id' => $item->id,
                'project_id' => $item->project_id,
                'project_name' => $item->project?->name,
                'model' => $item->model,
                'prompt_tokens' => $item->prompt_tokens,
                'completion_tokens' => $item->completion_tokens,
                'total_tokens' => $item->total_tokens,
                'estimated_cost' => (float) $item->estimated_cost,
                'action' => $item->action,
                'used_own_api_key' => $item->used_own_api_key,
                'created_at' => $item->created_at->toISOString(),
            ];
        });

        return Inertia::render('Billing/Usage', [
            'stats' => $this->formatStatsForUI($user),
            'metering' => $this->usageMetering->getOwnerUsageSummary($user),
            'credit_packs' => $this->creditOverage->getAvailablePacks(),
            'referral_credit_balance' => (float) $user->referral_credit_balance,
            'plan' => $plan ? [
                'name' => $plan->name,
                'monthly_build_credits' => $plan->getMonthlyBuildCredits(),
                'is_unlimited' => $plan->hasUnlimitedBuildCredits(),
                'allows_own_api_key' => $plan->allowsUserAiApiKey(),
            ] : null,
            'history' => $historyData,
            'period' => $period,
            'used_own_api_key' => $usedOwnApiKey,
        ]);
    }

    /**
     * API endpoint to get credit stats (for widgets/AJAX).
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        return response()->json([
            ...$this->formatStatsForUI($user),
            'metering' => $this->usageMetering->getOwnerUsageSummary($user),
        ]);
    }

    /**
     * Get available overage credit packs and current referral balance.
     */
    public function packs(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'packs' => $this->creditOverage->getAvailablePacks(),
            'referral_credit_balance' => (float) $user->referral_credit_balance,
            'is_unlimited' => $user->hasUnlimitedCredits(),
        ]);
    }

    /**
     * Purchase additional build credits from available overage packs.
     */
    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'pack_key' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $purchase = $this->creditOverage->purchaseWithReferralCredits(
                $user,
                $validated['pack_key']
            );

            $this->creditService->broadcastCreditsUpdated($user);

            return response()->json([
                'success' => true,
                'message' => 'Credit top-up completed successfully.',
                'purchase' => $purchase,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format stats in the format expected by the UI components.
     */
    protected function formatStatsForUI($user, ?array $stats = null): array
    {
        if ($stats === null) {
            $stats = $this->creditService->getMonthlyStats($user);
        }

        // Calculate reset date (first of next month)
        $resetDate = now()->addMonth()->startOfMonth();

        return [
            'credits_remaining' => $stats['remaining_credits'],
            'credits_used' => $stats['used_tokens'],
            'monthly_limit' => $stats['monthly_allocation'],
            'is_unlimited' => $stats['is_unlimited'],
            'overage_balance' => (int) ($user->build_credit_overage_balance ?? 0),
            'reset_date' => $resetDate->format('M j, Y'),
            'percentage_used' => $stats['usage_percentage'],
            'using_own_key' => $stats['using_own_key'],
        ];
    }
}
