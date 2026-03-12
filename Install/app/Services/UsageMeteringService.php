<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BuildCreditUsage;
use App\Models\EcommerceOrder;
use App\Models\OperationLog;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UsageMeteringService
{
    public const AI_ACTION_GENERATE = 'generate';

    public const AI_ACTION_EDIT = 'edit';

    public const AI_ACTION_REBUILD = 'rebuild';

    /**
     * Resolve AI action type for current project session.
     * First user prompt in a project is treated as "generate", following prompts as "edit".
     */
    public function resolveAiActionForProject(Project $project): string
    {
        $history = is_array($project->conversation_history) ? $project->conversation_history : [];
        $userMessages = 0;

        foreach ($history as $entry) {
            if (($entry['role'] ?? null) !== 'user') {
                continue;
            }

            $userMessages++;

            if ($userMessages > 1) {
                return self::AI_ACTION_EDIT;
            }
        }

        return self::AI_ACTION_GENERATE;
    }

    public function currentPeriodLabel(?CarbonInterface $anchor = null): string
    {
        [$periodStart, ] = $this->periodBounds($anchor);

        return $periodStart->format('Y-m');
    }

    public function countMonthlyOrdersForOwner(User|int $owner, ?CarbonInterface $anchor = null): int
    {
        [$periodStart, $periodEnd] = $this->periodBounds($anchor);

        return $this->countOrdersForOwnerInPeriod($this->resolveOwnerId($owner), $periodStart, $periodEnd);
    }

    public function countMonthlyBookingsForOwner(User|int $owner, ?CarbonInterface $anchor = null): int
    {
        [$periodStart, $periodEnd] = $this->periodBounds($anchor);

        return $this->countBookingsForOwnerInPeriod($this->resolveOwnerId($owner), $periodStart, $periodEnd);
    }

    public function countMonthlyRebuildsForOwner(User|int $owner, ?CarbonInterface $anchor = null): int
    {
        [$periodStart, $periodEnd] = $this->periodBounds($anchor);

        return $this->countRebuildsForOwnerInPeriod($this->resolveOwnerId($owner), $periodStart, $periodEnd);
    }

    /**
     * Unified monthly usage payload used by billing UI and backend guards.
     */
    public function getOwnerUsageSummary(User $owner, ?bool $usedOwnApiKey = null, ?CarbonInterface $anchor = null): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($anchor);
        $ownerId = (int) $owner->id;
        $plan = $owner->getCurrentPlan();

        $aiActions = $this->aiActionBreakdown($ownerId, $periodStart, $periodEnd, $usedOwnApiKey);
        $rebuildCount = $this->countRebuildsForOwnerInPeriod($ownerId, $periodStart, $periodEnd);
        $ordersCount = $this->countOrdersForOwnerInPeriod($ownerId, $periodStart, $periodEnd);
        $bookingsCount = $this->countBookingsForOwnerInPeriod($ownerId, $periodStart, $periodEnd);

        return [
            'period' => $periodStart->format('Y-m'),
            'ai_operations' => [
                'generate' => $aiActions[self::AI_ACTION_GENERATE],
                'edit' => $aiActions[self::AI_ACTION_EDIT],
                'rebuild' => $rebuildCount,
                'total' => $aiActions[self::AI_ACTION_GENERATE] + $aiActions[self::AI_ACTION_EDIT] + $rebuildCount,
            ],
            'commerce' => [
                'orders' => $ordersCount,
                'orders_limit' => $plan?->getMaxMonthlyOrders(),
            ],
            'booking' => [
                'bookings' => $bookingsCount,
                'bookings_limit' => $plan?->getMaxMonthlyBookings(),
            ],
        ];
    }

    private function resolveOwnerId(User|int $owner): int
    {
        return $owner instanceof User ? (int) $owner->id : (int) $owner;
    }

    private function aiActionBreakdown(
        int $ownerId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?bool $usedOwnApiKey = null
    ): array {
        if ($ownerId <= 0) {
            return [
                self::AI_ACTION_GENERATE => 0,
                self::AI_ACTION_EDIT => 0,
            ];
        }

        $query = BuildCreditUsage::query()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        if ($usedOwnApiKey !== null) {
            $query->where('used_own_api_key', $usedOwnApiKey);
        }

        $stats = $query->selectRaw("
                SUM(CASE WHEN action IN ('generate', 'build') OR action IS NULL THEN 1 ELSE 0 END) as generate_count,
                SUM(CASE WHEN action IN ('edit', 'chat') THEN 1 ELSE 0 END) as edit_count
            ")
            ->first();

        return [
            self::AI_ACTION_GENERATE => (int) ($stats?->generate_count ?? 0),
            self::AI_ACTION_EDIT => (int) ($stats?->edit_count ?? 0),
        ];
    }

    private function countOrdersForOwnerInPeriod(int $ownerId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): int
    {
        if ($ownerId <= 0) {
            return 0;
        }

        return EcommerceOrder::query()
            ->whereIn('site_id', function ($query) use ($ownerId): void {
                $query->select('sites.id')
                    ->from('sites')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->where('projects.user_id', $ownerId);
            })
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$periodStart, $periodEnd])
            ->count();
    }

    private function countBookingsForOwnerInPeriod(int $ownerId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): int
    {
        if ($ownerId <= 0) {
            return 0;
        }

        return Booking::query()
            ->whereIn('site_id', function ($query) use ($ownerId): void {
                $query->select('sites.id')
                    ->from('sites')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->where('projects.user_id', $ownerId);
            })
            ->whereBetween(DB::raw('COALESCE(starts_at, created_at)'), [$periodStart, $periodEnd])
            ->count();
    }

    private function countRebuildsForOwnerInPeriod(int $ownerId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): int
    {
        if ($ownerId <= 0) {
            return 0;
        }

        return OperationLog::query()
            ->where('channel', OperationLog::CHANNEL_BUILD)
            ->where('event', 'preview_build_completed')
            ->where('status', OperationLog::STATUS_SUCCESS)
            ->whereIn('project_id', function ($query) use ($ownerId): void {
                $query->select('id')
                    ->from('projects')
                    ->where('user_id', $ownerId);
            })
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->count();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBounds(?CarbonInterface $anchor = null): array
    {
        $cursor = $anchor ? CarbonImmutable::instance($anchor) : CarbonImmutable::now();

        return [$cursor->startOfMonth(), $cursor->endOfMonth()];
    }
}
