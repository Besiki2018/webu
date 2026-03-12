<?php

namespace App\Services;

use App\Models\ReferralCreditTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BuildCreditOverageService
{
    public function getAvailablePacks(): array
    {
        $packs = config('billing.credit_packs', []);

        if (! is_array($packs)) {
            return [];
        }

        $normalized = [];
        foreach ($packs as $pack) {
            if (! is_array($pack)) {
                continue;
            }

            $key = trim((string) ($pack['key'] ?? ''));
            $name = trim((string) ($pack['name'] ?? ''));
            $credits = (int) ($pack['credits'] ?? 0);
            $price = (float) ($pack['price'] ?? 0);
            $currency = strtoupper(trim((string) ($pack['currency'] ?? 'USD')));
            $enabled = (bool) ($pack['enabled'] ?? true);

            if (! $enabled || $key === '' || $name === '' || $credits <= 0 || $price <= 0 || $currency === '') {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'name' => $name,
                'credits' => $credits,
                'price' => round($price, 2),
                'currency' => $currency,
            ];
        }

        usort($normalized, fn (array $a, array $b): int => $a['credits'] <=> $b['credits']);

        return array_values($normalized);
    }

    public function findPack(string $packKey): ?array
    {
        $normalizedKey = trim($packKey);
        if ($normalizedKey === '') {
            return null;
        }

        foreach ($this->getAvailablePacks() as $pack) {
            if ($pack['key'] === $normalizedKey) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * Purchase build-credit overage pack using referral credit balance.
     *
     * @return array{
     *   pack: array<string, mixed>,
     *   balance_after: float,
     *   build_credits_after: int,
     *   overage_balance_after: int,
     *   transaction_id: int
     * }
     */
    public function purchaseWithReferralCredits(User $user, string $packKey): array
    {
        if ($user->hasUnlimitedCredits()) {
            throw new \DomainException('Your current plan already includes unlimited build credits.');
        }

        $pack = $this->findPack($packKey);
        if (! $pack) {
            throw new \DomainException('Selected credit pack is not available.');
        }

        $price = (float) $pack['price'];
        $credits = (int) $pack['credits'];
        $currency = (string) $pack['currency'];

        return DB::transaction(function () use ($user, $pack, $price, $credits, $currency): array {
            /** @var User|null $lockedUser */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedUser) {
                throw new \DomainException('User account was not found.');
            }

            $referralBalance = (float) $lockedUser->referral_credit_balance;
            if ($referralBalance < $price) {
                throw new \DomainException(
                    sprintf(
                        'Insufficient referral credits. Required %.2f %s, available %.2f %s.',
                        $price,
                        $currency,
                        $referralBalance,
                        $currency
                    )
                );
            }

            $lockedUser->decrement('referral_credit_balance', $price);
            $lockedUser->increment('build_credits', $credits);
            $lockedUser->increment('build_credit_overage_balance', $credits);
            $lockedUser->refresh();

            ReferralCreditTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'referral_id' => null,
                'amount' => -$price,
                'balance_after' => $lockedUser->referral_credit_balance,
                'type' => ReferralCreditTransaction::TYPE_BILLING_REDEMPTION,
                'description' => sprintf('Build credit top-up (%s)', $pack['name']),
                'metadata' => [
                    'source' => 'build_credit_overage',
                    'pack_key' => $pack['key'],
                    'credits' => $credits,
                    'price' => $price,
                    'currency' => $currency,
                ],
            ]);

            $transaction = Transaction::query()->create([
                'user_id' => $lockedUser->id,
                'subscription_id' => null,
                'amount' => $price,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_CREDIT_TOPUP,
                'payment_method' => Transaction::PAYMENT_MANUAL,
                'transaction_date' => now(),
                'notes' => sprintf('Build credit overage purchase: %s (%d credits)', $pack['name'], $credits),
                'metadata' => [
                    'source' => 'referral_credits',
                    'pack_key' => $pack['key'],
                    'pack_name' => $pack['name'],
                    'credits_awarded' => $credits,
                    'overage_balance_after' => (int) $lockedUser->build_credit_overage_balance,
                ],
            ]);

            return [
                'pack' => $pack,
                'balance_after' => (float) $lockedUser->referral_credit_balance,
                'build_credits_after' => (int) $lockedUser->build_credits,
                'overage_balance_after' => (int) $lockedUser->build_credit_overage_balance,
                'transaction_id' => (int) $transaction->id,
            ];
        });
    }
}
