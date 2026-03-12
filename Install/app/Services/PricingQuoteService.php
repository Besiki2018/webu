<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

class PricingQuoteService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function compose(Plan $plan, array $payload): array
    {
        $version = $this->resolveVersion($plan, $payload['version_id'] ?? null);

        $selectedAddonCodes = $this->normalizeCodes($payload['addon_codes'] ?? []);
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];

        $version->loadMissing('moduleAddons', 'priceRules');

        $base = $this->money($version->base_price);
        $runningTotal = $base;
        $lineItems = [];
        $appliedAddonCodes = [];
        $appliedRuleCodes = [];

        $addonsByCode = $version->moduleAddons
            ->filter(fn ($addon) => (bool) $addon->is_active)
            ->keyBy(fn ($addon) => strtolower(trim((string) $addon->code)));

        foreach ($selectedAddonCodes as $code) {
            $addon = $addonsByCode->get($code);
            if (! $addon) {
                continue;
            }

            $delta = $this->calculateAdjustment(
                amount: $this->money($addon->amount),
                mode: (string) $addon->pricing_mode,
                base: $base,
                runningTotal: $runningTotal
            );

            if ($delta == 0.0) {
                continue;
            }

            $runningTotal = $this->money($runningTotal + $delta);
            $appliedAddonCodes[] = $code;
            $lineItems[] = [
                'type' => 'addon',
                'code' => $addon->code,
                'name' => $addon->name,
                'adjustment_type' => $addon->pricing_mode,
                'amount' => $this->money($delta),
                'metadata' => [
                    'base_amount' => $this->money($addon->amount),
                ],
            ];
        }

        $resolvedContext = array_merge($context, [
            'usage' => is_array($usage) ? $usage : [],
            'addons' => $selectedAddonCodes,
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
            ],
        ]);

        $rules = $version->priceRules
            ->filter(fn ($rule) => (bool) $rule->is_active)
            ->sortBy('priority')
            ->values();

        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule->conditions_json, $resolvedContext)) {
                continue;
            }

            $delta = $this->calculateAdjustment(
                amount: $this->money($rule->amount),
                mode: (string) $rule->adjustment_type,
                base: $base,
                runningTotal: $runningTotal
            );

            if ($delta == 0.0) {
                continue;
            }

            $runningTotal = $this->money($runningTotal + $delta);
            $appliedRuleCodes[] = (string) $rule->code;

            $lineItems[] = [
                'type' => 'rule',
                'code' => $rule->code,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'adjustment_type' => $rule->adjustment_type,
                'amount' => $this->money($delta),
                'metadata' => [
                    'priority' => (int) $rule->priority,
                ],
            ];
        }

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'version' => [
                'id' => $version->id,
                'version_number' => (int) $version->version_number,
                'status' => $version->status,
                'currency' => $version->currency,
                'billing_period' => $version->billing_period,
            ],
            'base_price' => $base,
            'line_items' => $lineItems,
            'selected_addons' => $selectedAddonCodes,
            'applied_addons' => $appliedAddonCodes,
            'applied_rules' => $appliedRuleCodes,
            'totals' => [
                'subtotal' => $base,
                'final' => $this->money($runningTotal),
                'delta' => $this->money($runningTotal - $base),
            ],
            'usage' => $usage,
        ];
    }

    /**
     * @param  mixed  $versionId
     */
    public function resolveVersion(Plan $plan, mixed $versionId): PlanVersion
    {
        if (is_numeric($versionId)) {
            $version = PlanVersion::query()
                ->where('plan_id', $plan->id)
                ->whereKey((int) $versionId)
                ->first();

            if ($version) {
                return $version;
            }

            throw (new ModelNotFoundException)->setModel(PlanVersion::class, [(int) $versionId]);
        }

        $active = $plan->activeVersion()->first();
        if ($active) {
            return $active;
        }

        return PlanVersion::query()
            ->where('plan_id', $plan->id)
            ->orderByDesc('version_number')
            ->firstOrFail();
    }

    /**
     * @param  array<int, mixed>|mixed  $value
     * @return array<int, string>
     */
    private function normalizeCodes(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $code = strtolower(trim((string) $item));
            if ($code === '' || in_array($code, $normalized, true)) {
                continue;
            }

            $normalized[] = $code;
        }

        return $normalized;
    }

    private function calculateAdjustment(float $amount, string $mode, float $base, float $runningTotal): float
    {
        $normalizedMode = strtolower(trim($mode));

        if ($normalizedMode === 'percentage') {
            return $this->money(($runningTotal * $amount) / 100);
        }

        return $this->money($amount);
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>  $context
     */
    private function ruleMatches(?array $conditions, array $context): bool
    {
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        if (array_key_exists('all', $conditions) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $condition) {
                if (! $this->ruleMatches(is_array($condition) ? $condition : null, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $conditions) && is_array($conditions['any'])) {
            foreach ($conditions['any'] as $condition) {
                if ($this->ruleMatches(is_array($condition) ? $condition : null, $context)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($conditions['key'] ?? null)) {
            return $this->matchSingleCondition(
                key: (string) $conditions['key'],
                operator: (string) ($conditions['operator'] ?? 'eq'),
                expected: $conditions['value'] ?? null,
                context: $context
            );
        }

        return $this->matchFlatConditionMap($conditions, $context);
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $context
     */
    private function matchFlatConditionMap(array $conditions, array $context): bool
    {
        foreach ($conditions as $key => $expected) {
            if (! is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'min_')) {
                $lookup = substr($key, 4);
                $actual = Arr::get($context, $lookup, Arr::get($context, "usage.{$lookup}"));
                if (! is_numeric($actual) || (float) $actual < (float) $expected) {
                    return false;
                }

                continue;
            }

            if (str_starts_with($key, 'max_')) {
                $lookup = substr($key, 4);
                $actual = Arr::get($context, $lookup, Arr::get($context, "usage.{$lookup}"));
                if (! is_numeric($actual) || (float) $actual > (float) $expected) {
                    return false;
                }

                continue;
            }

            $actual = Arr::get($context, $key, Arr::get($context, "usage.{$key}"));
            if (is_array($expected)) {
                if (! in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ((string) $actual !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchSingleCondition(string $key, string $operator, mixed $expected, array $context): bool
    {
        $actual = Arr::get($context, $key, Arr::get($context, "usage.{$key}"));

        return match (strtolower(trim($operator))) {
            'neq', 'not_eq' => (string) $actual !== (string) $expected,
            'gt' => is_numeric($actual) && (float) $actual > (float) $expected,
            'gte' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'lt' => is_numeric($actual) && (float) $actual < (float) $expected,
            'lte' => is_numeric($actual) && (float) $actual <= (float) $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'exists' => $actual !== null && $actual !== '',
            default => (string) $actual === (string) $expected,
        };
    }

    private function money(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }
}

