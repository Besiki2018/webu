<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBillableSubscription
{
    /**
     * @param  'publish'|'modules'|'general'  $scope
     */
    public function handle(Request $request, Closure $next, string $scope = 'general'): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 'Authentication is required.', 'subscription_auth_required', 401);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        $subscription = $user->activeSubscription()->with('plan')->first();
        $plan = $subscription?->plan ?? $user->getCurrentPlan();

        // Backward compatibility: legacy users without subscription records are allowed.
        if (! $subscription && ! $user->subscriptions()->exists()) {
            return $next($request);
        }

        if (! $subscription && ! $this->isFreePlan($plan)) {
            return $this->deny(
                $request,
                'An active subscription is required for this action.',
                'subscription_required',
                402
            );
        }

        if ($subscription) {
            $status = (string) $subscription->status;
            $allowedStatuses = match ($scope) {
                'publish' => [Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE],
                'modules' => [Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE],
                default => Subscription::billableStatuses(),
            };

            if (! in_array($status, $allowedStatuses, true)) {
                return $this->deny(
                    $request,
                    'Your subscription status does not allow this action right now.',
                    'subscription_enforcement',
                    402
                );
            }
        }

        return $next($request);
    }

    private function isFreePlan(?Plan $plan): bool
    {
        if (! $plan) {
            return false;
        }

        return (float) $plan->price <= 0.0;
    }

    private function deny(Request $request, string $message, string $code, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('public/*') || $request->is('panel/*')) {
            return response()->json([
                'error' => $message,
                'code' => $code,
            ], $status);
        }

        return redirect()->route('billing.index')->with('error', $message);
    }
}
