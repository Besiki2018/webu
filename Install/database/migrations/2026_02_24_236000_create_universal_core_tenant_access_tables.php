<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status', 32)->default('active')->index();
                $table->string('default_currency', 3)->nullable();
                $table->string('default_locale', 10)->nullable();
                $table->string('timezone', 80)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_users')) {
            Schema::create('tenant_users', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->foreignId('platform_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('email')->nullable()->index();
                $table->string('phone')->nullable();
                $table->string('status', 32)->default('active')->index();
                $table->string('role_legacy', 64)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique(['tenant_id', 'platform_user_id']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id')->nullable();
                $table->string('key', 120);
                $table->string('label', 180);
                $table->string('scope', 32)->default('tenant');
                $table->string('status', 32)->default('active');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->unique(['tenant_id', 'key']);
                $table->index(['scope', 'status']);
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 150)->unique();
                $table->string('label', 180);
                $table->string('group_key', 120)->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->boolean('allowed')->default(true);
                $table->timestamps();

                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->uuid('project_id')->nullable();
                $table->string('scope_type', 32)->default('tenant')->index();
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->unique(['tenant_user_id', 'role_id', 'project_id']);
            });
        }

        if (! Schema::hasTable('project_members')) {
            Schema::create('project_members', function (Blueprint $table): void {
                $table->id();
                $table->uuid('project_id');
                $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
                $table->string('role', 64)->default('viewer');
                $table->string('status', 32)->default('active')->index();
                $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->unique(['project_id', 'tenant_user_id']);
                $table->index(['project_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }
};
