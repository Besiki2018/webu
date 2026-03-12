<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionProrationService
{
    /**
     * @return array<string, mixed>
     */
    public function preview(Subscription $subscription, Plan $targetPlan, bool $applyAtRenewal = false): array
    {
        $currentPlan = $subscription->plan;
        $currentPrice = $this->money($currentPlan?->price ?? 0);
        $targetPrice = $this->money($targetPlan->price);

        $renewalAt = $subscription->renewal_at;
        $cycleDays = $this->cycleLengthDays($subscription->plan?->billing_period ?? 'monthly');
        $daysRemaining = $renewalAt ? max(0, (int) now()->diffInDays($renewalAt, false)) : 0;
        $remainingRatio = $cycleDays > 0 ? min(1, max(0, $daysRemaining / $cycleDays)) : 0.0;

        $currentRemainingValue = $this->money($currentPrice * $remainingRatio);
        $targetRemainingValue = $this->money($targetPrice * $remainingRatio);
        $prorationAmount = $applyAtRenewal
            ? 0.0
            : $this->money($targetRemainingValue - $currentRemainingValue);

        $direction = $prorationAmount > 0
            ? 'debit'
            : ($prorationAmount < 0 ? 'credit' : 'neutral');

        return [
            'subscription_id' => $subscription->id,
            'current_plan' => [
                'id' => $currentPlan?->id,
                'name' => $currentPlan?->name,
                'price' => $currentPrice,
                'billing_period' => $currentPlan?->billing_period,
            ],
            'target_plan' => [
                'id' => $targetPlan->id,
                'name' => $targetPlan->name,
                'price' => $targetPrice,
                'billing_period' => $targetPlan->billing_period,
            ],
            'renewal_at' => $renewalAt?->toISOString(),
            'cycle_days' => $cycleDays,
            'days_remaining' => $daysRemaining,
            'remaining_ratio' => round($remainingRatio, 6),
            'apply_at_renewal' => $applyAtRenewal,
            'proration' => [
                'amount' => $prorationAmount,
                'direction' => $direction,
                'current_remaining_value' => $currentRemainingValue,
                'target_remaining_value' => $targetRemainingValue,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(User $actor, Subscription $subscription, Plan $targetPlan, bool $applyAtRenewal, ?string $reason = null): array
    {
        $quote = $this->preview($subscription, $targetPlan, $applyAtRenewal);
        $metadata = $subscription->metadata ?? [];

        return DB::transaction(function () use ($actor, $subscription, $targetPlan, $applyAtRenewal, $reason, $quote, $metadata): array {
            if ($applyAtRenewal) {
                $pending = [
                    'target_plan_id' => $targetPlan->id,
                    'requested_at' => now()->toISOString(),
                    'effective_on' => $subscription->renewal_at?->toISOString(),
                    'quote' => $quote['proration'] ?? null,
                    'reason' => $reason,
                ];

                $metadata['pending_plan_change'] = $pending;
                $subscription->update(['metadata' => $metadata]);

                return [
                    'mode' => 'scheduled',
                    'quote' => $quote,
                    'pending_plan_change' => $pending,
                    'transaction_id' => null,
                ];
            }

            $transactionId = null;
            $amount = (float) ($quote['proration']['amount'] ?? 0);
            $direction = (string) ($quote['proration']['direction'] ?? 'neutral');

            $subscription->update([
                'plan_id' => $targetPlan->id,
                'amount' => $targetPlan->price,
                'metadata' => array_merge($metadata, [
                    'last_plan_change' => [
                        'from_plan_id' => $quote['current_plan']['id'] ?? null,
                        'to_plan_id' => $targetPlan->id,
                        'applied_at' => now()->toISOString(),
                        'actor_user_id' => $actor->id,
                        'reason' => $reason,
                        'proration' => $quote['proration'] ?? null,
                    ],
                ]),
            ]);

            $actor->update([
                'plan_id' => $targetPlan->id,
            ]);

            if ($amount != 0.0) {
                $transaction = Transaction::create([
                    'user_id' => $actor->id,
                    'subscription_id' => $subscription->id,
                    'amount' => abs($amount),
                    'currency' => 'USD',
                    'status' => Transaction::STATUS_COMPLETED,
                    'type' => Transaction::TYPE_ADJUSTMENT,
                    'payment_method' => Transaction::PAYMENT_MANUAL,
                    'transaction_date' => now(),
                    'notes' => $direction === 'credit'
                        ? 'Proration credit applied for plan downgrade.'
                        : 'Proration charge applied for plan upgrade.',
                    'metadata' => [
                        'kind' => 'plan_proration',
                        'direction' => $direction,
                        'signed_amount' => $amount,
                        'from_plan_id' => $quote['current_plan']['id'] ?? null,
                        'to_plan_id' => $targetPlan->id,
                    ],
                ]);

                $transactionId = $transaction->id;
            }

            return [
                'mode' => 'immediate',
                'quote' => $quote,
                'pending_plan_change' => null,
                'transaction_id' => $transactionId,
            ];
        });
    }

    private function cycleLengthDays(string $period): int
    {
        return match (strtolower(trim($period))) {
            'yearly' => 365,
            'lifetime' => 0,
            default => 30,
        };
    }

    private function money(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }
}

