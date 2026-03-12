<?php

namespace App\Services;

use App\Models\ModuleAddon;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\PlanVersionAudit;
use App\Models\PriceRule;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PricingCatalogService
{
    public function ensurePlanHasInitialVersion(Plan $plan, ?User $actor = null): PlanVersion
    {
        $plan->loadMissing('activeVersion');
        if ($plan->activeVersion) {
            return $plan->activeVersion;
        }

        return DB::transaction(function () use ($plan, $actor): PlanVersion {
            $active = PlanVersion::query()
                ->where('plan_id', $plan->id)
                ->where('status', PlanVersion::STATUS_ACTIVE)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if ($active) {
                return $active;
            }

            $latest = PlanVersion::query()
                ->where('plan_id', $plan->id)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if (! $latest) {
                $version = PlanVersion::query()->create([
                    'plan_id' => $plan->id,
                    'version_number' => 1,
                    'status' => PlanVersion::STATUS_ACTIVE,
                    'base_price' => $plan->price,
                    'billing_period' => $plan->billing_period,
                    'currency' => 'USD',
                    'effective_from' => now(),
                    'created_by' => $actor?->id,
                    'activated_by' => $actor?->id,
                    'activated_at' => now(),
                    'metadata' => [
                        'source' => 'plan_bootstrap',
                    ],
                ]);

                foreach ($this->defaultAddonDefinitions($plan) as $index => $definition) {
                    ModuleAddon::query()->create([
                        'plan_version_id' => $version->id,
                        'code' => $definition['code'],
                        'name' => $definition['name'],
                        'addon_group' => $definition['addon_group'],
                        'pricing_mode' => 'fixed',
                        'amount' => 0,
                        'currency' => 'USD',
                        'is_active' => $definition['is_active'],
                        'sort_order' => $index,
                        'metadata' => [],
                    ]);
                }

                $this->audit(
                    $version,
                    'bootstrapped',
                    $actor,
                    [
                        'source' => 'ensure_plan_has_initial_version',
                    ]
                );

                return $version->load(['moduleAddons', 'priceRules', 'audits.actor']);
            }

            $latest->status = PlanVersion::STATUS_ACTIVE;
            $latest->effective_from ??= now();
            $latest->effective_to = null;
            $latest->activated_by = $actor?->id;
            $latest->activated_at ??= now();
            $latest->save();

            $this->audit(
                $latest,
                'status_repaired',
                $actor,
                [
                    'source' => 'ensure_plan_has_initial_version',
                    'note' => 'No active version found; latest version promoted to active.',
                ]
            );

            return $latest->load(['moduleAddons', 'priceRules', 'audits.actor']);
        });
    }

    public function syncActiveVersionFromPlanSnapshot(Plan $plan, ?User $actor = null, string $source = 'admin_plan_update'): PlanVersion
    {
        $active = $this->ensurePlanHasInitialVersion($plan, $actor);

        if ($this->samePricing($active, $plan)) {
            return $active;
        }

        return DB::transaction(function () use ($plan, $actor, $source, $active): PlanVersion {
            $active = PlanVersion::query()->lockForUpdate()->findOrFail($active->id);
            $nextNumber = ((int) PlanVersion::query()->where('plan_id', $plan->id)->max('version_number')) + 1;
            $now = now();

            $active->update([
                'status' => PlanVersion::STATUS_ARCHIVED,
                'effective_to' => $now,
            ]);

            $version = PlanVersion::query()->create([
                'plan_id' => $plan->id,
                'version_number' => $nextNumber,
                'status' => PlanVersion::STATUS_ACTIVE,
                'base_price' => $plan->price,
                'billing_period' => $plan->billing_period,
                'currency' => $this->normalizeCurrency($active->currency ?? 'USD'),
                'effective_from' => $now,
                'created_by' => $actor?->id,
                'activated_by' => $actor?->id,
                'activated_at' => $now,
                'metadata' => [
                    'source' => $source,
                ],
            ]);

            $this->cloneAddons($active, $version);
            $this->cloneRules($active, $version);

            $this->audit(
                $version,
                'version_created_from_plan_update',
                $actor,
                [
                    'source' => $source,
                    'previous_version_id' => $active->id,
                    'previous_version_number' => $active->version_number,
                    'price' => [
                        'from' => (float) $active->base_price,
                        'to' => (float) $version->base_price,
                    ],
                    'billing_period' => [
                        'from' => $active->billing_period,
                        'to' => $version->billing_period,
                    ],
                ]
            );

            $this->audit(
                $version,
                'version_activated',
                $actor,
                [
                    'source' => $source,
                    'previous_active_version_id' => $active->id,
                ]
            );

            return $version->load(['moduleAddons', 'priceRules', 'audits.actor']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogForPlan(Plan $plan): array
    {
        $active = $this->ensurePlanHasInitialVersion($plan);
        $plan->unsetRelation('activeVersion');

        $versions = PlanVersion::query()
            ->where('plan_id', $plan->id)
            ->with([
                'moduleAddons',
                'priceRules',
                'createdBy:id,name,email',
                'activatedBy:id,name,email',
                'audits' => fn ($query) => $query
                    ->latest('id')
                    ->limit(30)
                    ->with('actor:id,name,email'),
            ])
            ->orderByDesc('version_number')
            ->get();

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'billing_period' => $plan->billing_period,
            ],
            'active_version_id' => $active->id,
            'active_version_number' => (int) $active->version_number,
            'versions' => $versions,
        ];
    }

    public function createDraftVersion(Plan $plan, array $payload, User $actor): PlanVersion
    {
        $this->ensurePlanHasInitialVersion($plan, $actor);

        return DB::transaction(function () use ($plan, $payload, $actor): PlanVersion {
            $source = $this->resolveSourceVersion($plan, $payload['source_version_id'] ?? null);
            $nextNumber = ((int) PlanVersion::query()->where('plan_id', $plan->id)->max('version_number')) + 1;

            $version = PlanVersion::query()->create([
                'plan_id' => $plan->id,
                'version_number' => $nextNumber,
                'status' => PlanVersion::STATUS_DRAFT,
                'base_price' => $payload['base_price'] ?? $source->base_price,
                'billing_period' => $payload['billing_period'] ?? $source->billing_period,
                'currency' => $this->normalizeCurrency($payload['currency'] ?? $source->currency ?? 'USD'),
                'effective_from' => $payload['effective_from'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                'created_by' => $actor->id,
            ]);

            $this->cloneAddons($source, $version);
            $this->cloneRules($source, $version);

            $this->audit(
                $version,
                'version_created',
                $actor,
                [
                    'source_version_id' => $source->id,
                    'source_version_number' => $source->version_number,
                ]
            );

            return $version->load(['moduleAddons', 'priceRules', 'audits.actor']);
        });
    }

    public function activateVersion(Plan $plan, PlanVersion $version, User $actor, ?string $reason = null): PlanVersion
    {
        $this->assertVersionBelongsToPlan($plan, $version);

        return DB::transaction(function () use ($plan, $version, $actor, $reason): PlanVersion {
            $version = PlanVersion::query()->lockForUpdate()->findOrFail($version->id);
            $now = now();

            $previousActiveIds = PlanVersion::query()
                ->where('plan_id', $plan->id)
                ->where('status', PlanVersion::STATUS_ACTIVE)
                ->where('id', '!=', $version->id)
                ->pluck('id')
                ->all();

            if ($previousActiveIds !== []) {
                PlanVersion::query()
                    ->whereIn('id', $previousActiveIds)
                    ->update([
                        'status' => PlanVersion::STATUS_ARCHIVED,
                        'effective_to' => $now,
                        'updated_at' => $now,
                    ]);
            }

            $version->update([
                'status' => PlanVersion::STATUS_ACTIVE,
                'effective_from' => $version->effective_from ?? $now,
                'effective_to' => null,
                'activated_by' => $actor->id,
                'activated_at' => $now,
            ]);

            $plan->update([
                'price' => $version->base_price,
                'billing_period' => $version->billing_period,
            ]);

            $this->audit(
                $version,
                'version_activated',
                $actor,
                [
                    'reason' => $reason,
                    'previous_active_version_ids' => $previousActiveIds,
                    'applied_to_plan' => [
                        'price' => (float) $version->base_price,
                        'billing_period' => $version->billing_period,
                    ],
                ]
            );

            return $version->load(['moduleAddons', 'priceRules', 'audits.actor']);
        });
    }

    public function upsertAddon(Plan $plan, PlanVersion $version, array $payload, User $actor): ModuleAddon
    {
        $this->assertVersionBelongsToPlan($plan, $version);

        $code = $this->normalizeCode((string) $payload['code']);

        return DB::transaction(function () use ($version, $payload, $actor, $code): ModuleAddon {
            $addon = ModuleAddon::query()->updateOrCreate(
                [
                    'plan_version_id' => $version->id,
                    'code' => $code,
                ],
                [
                    'name' => trim((string) ($payload['name'] ?? $code)),
                    'addon_group' => trim((string) ($payload['addon_group'] ?? 'module')),
                    'pricing_mode' => trim((string) ($payload['pricing_mode'] ?? 'fixed')),
                    'amount' => $payload['amount'] ?? 0,
                    'currency' => $this->normalizeCurrency($payload['currency'] ?? 'USD'),
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                    'sort_order' => (int) ($payload['sort_order'] ?? 0),
                    'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                ]
            );

            $this->audit(
                $version,
                'addon_upserted',
                $actor,
                [
                    'addon_id' => $addon->id,
                    'code' => $addon->code,
                    'name' => $addon->name,
                    'amount' => (float) $addon->amount,
                    'pricing_mode' => $addon->pricing_mode,
                    'is_active' => (bool) $addon->is_active,
                ]
            );

            return $addon;
        });
    }

    public function deleteAddon(Plan $plan, PlanVersion $version, ModuleAddon $addon, User $actor): void
    {
        $this->assertVersionBelongsToPlan($plan, $version);

        if ((int) $addon->plan_version_id !== (int) $version->id) {
            throw (new ModelNotFoundException)->setModel(ModuleAddon::class, [$addon->id]);
        }

        DB::transaction(function () use ($version, $addon, $actor): void {
            $snapshot = [
                'addon_id' => $addon->id,
                'code' => $addon->code,
                'name' => $addon->name,
                'amount' => (float) $addon->amount,
            ];

            $addon->delete();

            $this->audit(
                $version,
                'addon_deleted',
                $actor,
                $snapshot
            );
        });
    }

    public function upsertPriceRule(Plan $plan, PlanVersion $version, array $payload, User $actor): PriceRule
    {
        $this->assertVersionBelongsToPlan($plan, $version);

        $code = $this->normalizeCode((string) $payload['code']);

        return DB::transaction(function () use ($version, $payload, $actor, $code): PriceRule {
            $rule = PriceRule::query()->updateOrCreate(
                [
                    'plan_version_id' => $version->id,
                    'code' => $code,
                ],
                [
                    'name' => trim((string) ($payload['name'] ?? $code)),
                    'rule_type' => trim((string) ($payload['rule_type'] ?? 'manual')),
                    'adjustment_type' => trim((string) ($payload['adjustment_type'] ?? 'fixed')),
                    'amount' => $payload['amount'] ?? 0,
                    'conditions_json' => is_array($payload['conditions_json'] ?? null) ? $payload['conditions_json'] : null,
                    'priority' => (int) ($payload['priority'] ?? 0),
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                    'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                ]
            );

            $this->audit(
                $version,
                'price_rule_upserted',
                $actor,
                [
                    'price_rule_id' => $rule->id,
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'rule_type' => $rule->rule_type,
                    'adjustment_type' => $rule->adjustment_type,
                    'amount' => (float) $rule->amount,
                    'is_active' => (bool) $rule->is_active,
                ]
            );

            return $rule;
        });
    }

    public function deletePriceRule(Plan $plan, PlanVersion $version, PriceRule $rule, User $actor): void
    {
        $this->assertVersionBelongsToPlan($plan, $version);

        if ((int) $rule->plan_version_id !== (int) $version->id) {
            throw (new ModelNotFoundException)->setModel(PriceRule::class, [$rule->id]);
        }

        DB::transaction(function () use ($version, $rule, $actor): void {
            $snapshot = [
                'price_rule_id' => $rule->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'adjustment_type' => $rule->adjustment_type,
                'amount' => (float) $rule->amount,
            ];

            $rule->delete();

            $this->audit(
                $version,
                'price_rule_deleted',
                $actor,
                $snapshot
            );
        });
    }

    private function resolveSourceVersion(Plan $plan, mixed $sourceVersionId): PlanVersion
    {
        if (is_numeric($sourceVersionId)) {
            $source = PlanVersion::query()->find((int) $sourceVersionId);
            if (! $source) {
                throw (new ModelNotFoundException)->setModel(PlanVersion::class, [(int) $sourceVersionId]);
            }

            $this->assertVersionBelongsToPlan($plan, $source);

            return $source;
        }

        return PlanVersion::query()
            ->where('plan_id', $plan->id)
            ->orderByDesc('version_number')
            ->firstOrFail();
    }

    private function cloneAddons(PlanVersion $source, PlanVersion $target): void
    {
        $source->loadMissing('moduleAddons');

        foreach ($source->moduleAddons as $addon) {
            ModuleAddon::query()->create([
                'plan_version_id' => $target->id,
                'code' => $addon->code,
                'name' => $addon->name,
                'addon_group' => $addon->addon_group,
                'pricing_mode' => $addon->pricing_mode,
                'amount' => $addon->amount,
                'currency' => $addon->currency,
                'is_active' => (bool) $addon->is_active,
                'sort_order' => (int) $addon->sort_order,
                'metadata' => $addon->metadata ?? [],
            ]);
        }
    }

    private function cloneRules(PlanVersion $source, PlanVersion $target): void
    {
        $source->loadMissing('priceRules');

        foreach ($source->priceRules as $rule) {
            PriceRule::query()->create([
                'plan_version_id' => $target->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'adjustment_type' => $rule->adjustment_type,
                'amount' => $rule->amount,
                'conditions_json' => $rule->conditions_json,
                'priority' => (int) $rule->priority,
                'is_active' => (bool) $rule->is_active,
                'metadata' => $rule->metadata ?? [],
            ]);
        }
    }

    private function audit(PlanVersion $version, string $action, ?User $actor, array $payload = []): PlanVersionAudit
    {
        return PlanVersionAudit::query()->create([
            'plan_version_id' => $version->id,
            'plan_id' => $version->plan_id,
            'action' => $action,
            'actor_id' => $actor?->id,
            'payload' => $payload,
        ]);
    }

    private function assertVersionBelongsToPlan(Plan $plan, PlanVersion $version): void
    {
        if ((int) $version->plan_id !== (int) $plan->id) {
            throw (new ModelNotFoundException)->setModel(PlanVersion::class, [$version->id]);
        }
    }

    private function samePricing(PlanVersion $version, Plan $plan): bool
    {
        return (float) $version->base_price === (float) $plan->price
            && (string) $version->billing_period === (string) $plan->billing_period;
    }

    private function normalizeCurrency(mixed $currency): string
    {
        $normalized = strtoupper(trim((string) $currency));

        if ($normalized === '' || ! preg_match('/^[A-Z]{3}$/', $normalized)) {
            return 'USD';
        }

        return $normalized;
    }

    private function normalizeCode(string $code): string
    {
        $normalized = strtolower(trim($code));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-._');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Code must contain valid alphanumeric characters.');
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultAddonDefinitions(Plan $plan): array
    {
        return [
            [
                'code' => 'ecommerce',
                'name' => 'Ecommerce Module',
                'addon_group' => 'module',
                'is_active' => (bool) ($plan->enable_ecommerce ?? true),
            ],
            [
                'code' => 'payments-installments',
                'name' => 'Payments & Installments',
                'addon_group' => 'module',
                'is_active' => (bool) ($plan->enable_online_payments ?? true),
            ],
            [
                'code' => 'shipping',
                'name' => 'Shipping',
                'addon_group' => 'module',
                'is_active' => (bool) ($plan->enable_shipping ?? true),
            ],
            [
                'code' => 'booking',
                'name' => 'Booking Module',
                'addon_group' => 'module',
                'is_active' => (bool) ($plan->enable_booking ?? true),
            ],
            [
                'code' => 'inventory',
                'name' => 'Inventory Management',
                'addon_group' => 'advanced_ecommerce',
                'is_active' => false,
            ],
            [
                'code' => 'accounting',
                'name' => 'Accounting Suite',
                'addon_group' => 'advanced_ecommerce',
                'is_active' => false,
            ],
            [
                'code' => 'rs-integration',
                'name' => 'RS Integration',
                'addon_group' => 'advanced_ecommerce',
                'is_active' => false,
            ],
            [
                'code' => 'booking-team-scheduling',
                'name' => 'Booking Team Scheduling',
                'addon_group' => 'advanced_booking',
                'is_active' => false,
            ],
            [
                'code' => 'booking-finance',
                'name' => 'Booking Finance',
                'addon_group' => 'advanced_booking',
                'is_active' => false,
            ],
            [
                'code' => 'booking-advanced-calendar',
                'name' => 'Booking Advanced Calendar',
                'addon_group' => 'advanced_booking',
                'is_active' => false,
            ],
        ];
    }
}
