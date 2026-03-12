<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('code', 120);
                $table->string('name');
                $table->string('status', 32)->default('active');
                $table->json('config_json')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'code']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('payable_type', 40);
                $table->unsignedBigInteger('payable_id');
                $table->string('method_code', 120);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3);
                $table->string('status', 32)->default('initiated');
                $table->string('transaction_id', 191)->nullable();
                $table->json('provider_payload')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'payable_type', 'payable_id']);
                $table->index(['project_id', 'method_code']);
                $table->index(['transaction_id']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payment_webhooks')) {
            Schema::create('payment_webhooks', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 120);
                $table->string('event_id', 191);
                $table->json('payload_json');
                $table->string('status', 32)->default('received');
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['provider', 'event_id']);
                $table->index(['provider', 'status']);
                $table->index(['processed_at']);
            });
        }

        if (! Schema::hasTable('shipping_methods')) {
            Schema::create('shipping_methods', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('code', 120);
                $table->string('name');
                $table->string('status', 32)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['project_id', 'code']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status', 'sort_order']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('name');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'name']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('shipping_zone_regions')) {
            Schema::create('shipping_zone_regions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('zone_id');
                $table->string('country', 120);
                $table->string('city', 120)->nullable();
                $table->string('zip_from', 64)->nullable();
                $table->string('zip_to', 64)->nullable();
                $table->timestamps();

                $table->index(['zone_id', 'country']);
                $table->foreign('zone_id')->references('id')->on('shipping_zones')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('shipping_rates')) {
            Schema::create('shipping_rates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('method_id');
                $table->unsignedBigInteger('zone_id');
                $table->string('rule_type', 32);
                $table->decimal('min_value', 12, 2)->nullable();
                $table->decimal('max_value', 12, 2)->nullable();
                $table->decimal('price', 12, 2);
                $table->unsignedSmallInteger('eta_days')->nullable();
                $table->timestamps();

                $table->index(['method_id', 'zone_id']);
                $table->index(['zone_id', 'rule_type']);
                $table->foreign('method_id')->references('id')->on('shipping_methods')->cascadeOnDelete();
                $table->foreign('zone_id')->references('id')->on('shipping_zones')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('code', 120);
                $table->string('type', 32);
                $table->decimal('value', 12, 2);
                $table->decimal('min_order', 12, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->string('status', 32)->default('active');
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'code']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('order_id');
                $table->timestamp('created_at')->nullable();

                $table->index(['coupon_id', 'created_at']);
                $table->index(['customer_id', 'created_at']);
                $table->index(['order_id']);
                $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                $table->foreign('order_id')->references('id')->on('ecommerce_orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zone_regions');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
