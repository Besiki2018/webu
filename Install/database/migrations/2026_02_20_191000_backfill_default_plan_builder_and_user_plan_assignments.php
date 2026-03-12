<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasTable('plans')) {
            return;
        }

        $now = now();

        $defaultPlanId = DB::table('plans')
            ->where('is_active', true)
            ->where('slug', 'free')
            ->value('id');

        if (! $defaultPlanId) {
            $defaultPlanId = DB::table('plans')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');
        }

        $defaultBuilderId = Schema::hasTable('builders')
            ? DB::table('builders')
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id')
            : null;

        $defaultAiProviderId = Schema::hasTable('ai_providers')
            ? DB::table('ai_providers')
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id')
            : null;

        $setSettingIfEmpty = static function (
            string $key,
            mixed $value,
            string $type,
            string $group
        ) use ($now): void {
            if ($value === null || $value === '') {
                return;
            }

            $existing = DB::table('system_settings')
                ->where('key', $key)
                ->first();

            if (! $existing) {
                DB::table('system_settings')->insert([
                    'key' => $key,
                    'value' => (string) $value,
                    'type' => $type,
                    'group' => $group,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }

            if ($existing->value === null || $existing->value === '') {
                DB::table('system_settings')
                    ->where('id', $existing->id)
                    ->update([
                        'value' => (string) $value,
                        'type' => $type,
                        'group' => $group,
                        'updated_at' => $now,
                    ]);
            }
        };

        $setSettingIfEmpty('default_plan_id', $defaultPlanId, 'integer', 'plans');
        $setSettingIfEmpty('default_builder_id', $defaultBuilderId, 'integer', 'plans');
        $setSettingIfEmpty('default_ai_provider_id', $defaultAiProviderId, 'integer', 'plans');

        if ($defaultPlanId && Schema::hasTable('users')) {
            $userIdsWithoutPlan = DB::table('users')
                ->whereNull('plan_id')
                ->pluck('id');

            if ($userIdsWithoutPlan->isNotEmpty()) {
                DB::table('users')
                    ->whereIn('id', $userIdsWithoutPlan->all())
                    ->update([
                        'plan_id' => $defaultPlanId,
                        'updated_at' => $now,
                    ]);

                $monthlyCredits = (int) DB::table('plans')
                    ->where('id', $defaultPlanId)
                    ->value('monthly_build_credits');

                if ($monthlyCredits > 0) {
                    DB::table('users')
                        ->whereIn('id', $userIdsWithoutPlan->all())
                        ->where('build_credits', '<=', 0)
                        ->update([
                            'build_credits' => $monthlyCredits,
                            'credits_reset_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op: backfill migration should not remove user assignments.
    }
};

