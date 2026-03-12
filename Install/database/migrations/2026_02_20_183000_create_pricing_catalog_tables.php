<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plan_versions')) {
            Schema::create('plan_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('status', 32)->default('draft');
                $table->decimal('base_price', 10, 2)->default(0);
                $table->string('billing_period', 32)->default('monthly');
                $table->string('currency', 3)->default('USD');
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();

                $table->unique(['plan_id', 'version_number']);
                $table->index(['plan_id', 'status']);
                $table->index(['plan_id', 'effective_from']);
            });
        }

        if (! Schema::hasTable('module_addons')) {
            Schema::create('module_addons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
                $table->string('code', 100);
                $table->string('name', 140);
                $table->string('addon_group', 64)->default('module');
                $table->string('pricing_mode', 32)->default('fixed');
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['plan_version_id', 'code']);
                $table->index(['plan_version_id', 'addon_group']);
            });
        }

        if (! Schema::hasTable('price_rules')) {
            Schema::create('price_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
                $table->string('code', 100);
                $table->string('name', 140);
                $table->string('rule_type', 64)->default('manual');
                $table->string('adjustment_type', 32)->default('fixed');
                $table->decimal('amount', 10, 2)->default(0);
                $table->json('conditions_json')->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['plan_version_id', 'code']);
                $table->index(['plan_version_id', 'priority']);
            });
        }

        if (! Schema::hasTable('plan_version_audits')) {
            Schema::create('plan_version_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('action', 80);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['plan_id', 'action']);
                $table->index('created_at');
            });
        }

        $this->bootstrapExistingPlans();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_version_audits');
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('module_addons');
        Schema::dropIfExists('plan_versions');
    }

    private function bootstrapExistingPlans(): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasTable('plan_versions')) {
            return;
        }

        $hasVersions = DB::table('plan_versions')->exists();
        if ($hasVersions) {
            return;
        }

        $plans = DB::table('plans')
            ->select([
                'id',
                'price',
                'billing_period',
                'enable_ecommerce',
                'enable_online_payments',
                'enable_shipping',
                'enable_booking',
            ])
            ->orderBy('id')
            ->get();

        if ($plans->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($plans as $plan) {
            $versionId = DB::table('plan_versions')->insertGetId([
                'plan_id' => $plan->id,
                'version_number' => 1,
                'status' => 'active',
                'base_price' => $plan->price,
                'billing_period' => $plan->billing_period,
                'currency' => 'USD',
                'effective_from' => $now,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $addons = $this->defaultAddons((array) $plan, $versionId, $now);
            if ($addons !== []) {
                DB::table('module_addons')->insert($addons);
            }

            DB::table('plan_version_audits')->insert([
                'plan_version_id' => $versionId,
                'plan_id' => $plan->id,
                'action' => 'bootstrapped',
                'actor_id' => null,
                'payload' => json_encode([
                    'source' => 'migration_seed',
                    'version_number' => 1,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array<string, mixed>>
     */
    private function defaultAddons(array $plan, int $planVersionId, \Carbon\CarbonInterface $now): array
    {
        $definitions = [
            ['code' => 'ecommerce', 'name' => 'Ecommerce Module', 'group' => 'module', 'active' => (bool) ($plan['enable_ecommerce'] ?? true)],
            ['code' => 'payments-installments', 'name' => 'Payments & Installments', 'group' => 'module', 'active' => (bool) ($plan['enable_online_payments'] ?? true)],
            ['code' => 'shipping', 'name' => 'Shipping', 'group' => 'module', 'active' => (bool) ($plan['enable_shipping'] ?? true)],
            ['code' => 'booking', 'name' => 'Booking Module', 'group' => 'module', 'active' => (bool) ($plan['enable_booking'] ?? true)],
            ['code' => 'inventory', 'name' => 'Inventory Management', 'group' => 'advanced_ecommerce', 'active' => false],
            ['code' => 'accounting', 'name' => 'Accounting Suite', 'group' => 'advanced_ecommerce', 'active' => false],
            ['code' => 'rs-integration', 'name' => 'RS Integration', 'group' => 'advanced_ecommerce', 'active' => false],
            ['code' => 'booking-team-scheduling', 'name' => 'Booking Team Scheduling', 'group' => 'advanced_booking', 'active' => false],
            ['code' => 'booking-finance', 'name' => 'Booking Finance', 'group' => 'advanced_booking', 'active' => false],
            ['code' => 'booking-advanced-calendar', 'name' => 'Booking Advanced Calendar', 'group' => 'advanced_booking', 'active' => false],
        ];

        return array_map(static function (array $definition, int $index) use ($planVersionId, $now): array {
            return [
                'plan_version_id' => $planVersionId,
                'code' => $definition['code'],
                'name' => $definition['name'],
                'addon_group' => $definition['group'],
                'pricing_mode' => 'fixed',
                'amount' => 0,
                'currency' => 'USD',
                'is_active' => $definition['active'],
                'sort_order' => $index,
                'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $definitions, array_keys($definitions));
    }
};
