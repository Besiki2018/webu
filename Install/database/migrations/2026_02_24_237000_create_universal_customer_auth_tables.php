<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('password_hash')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'email']);
                $table->index(['project_id', 'phone']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('customer_sessions')) {
            Schema::create('customer_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('token_hash', 190);
                $table->timestamp('expires_at');
                $table->string('ip_hash', 128)->nullable();
                $table->string('user_agent', 1024)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique('token_hash');
                $table->index(['customer_id', 'expires_at']);
                $table->index(['customer_id', 'last_seen_at']);
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('type', 32);
                $table->string('name');
                $table->string('phone', 64);
                $table->string('country', 120);
                $table->string('city', 120);
                $table->string('address1', 255);
                $table->string('address2', 255)->nullable();
                $table->string('zip', 64)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['customer_id', 'type']);
                $table->index(['customer_id', 'is_default']);
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('otp_requests')) {
            Schema::create('otp_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->string('phone', 64);
                $table->string('code_hash', 255);
                $table->timestamp('expires_at');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('status', 32)->default('pending');
                $table->string('ip_hash', 128)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['tenant_id', 'project_id', 'phone']);
                $table->index(['project_id', 'status', 'created_at']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('provider', 64);
                $table->string('provider_user_id', 191);
                $table->string('email')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['provider', 'provider_user_id']);
                $table->index(['customer_id', 'provider']);
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('otp_requests');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_sessions');
        Schema::dropIfExists('customers');
    }
};
