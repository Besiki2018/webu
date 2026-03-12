<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'slug']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['site_id', 'slug']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('parent_id')->references('id')->on('service_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->longText('description_html')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'slug']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'category_id']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('service_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('photo_media_id')->nullable();
                $table->string('role_title')->nullable();
                $table->longText('bio_html')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'slug']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('photo_media_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('staff_services')) {
            Schema::create('staff_services', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_id');
                $table->unsignedBigInteger('service_id');
                $table->timestamps();

                $table->unique(['staff_id', 'service_id']);
                $table->index(['service_id', 'staff_id']);
                $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('type', 64);
                $table->unsignedInteger('capacity')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'type', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('availability_rules')) {
            Schema::create('availability_rules', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('owner_type', 32);
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('rrule');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('timezone', 80);
                $table->json('meta_json')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'owner_type', 'owner_id']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('blocked_times')) {
            Schema::create('blocked_times', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('owner_type', 32);
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('reason')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'owner_type', 'owner_id']);
                $table->index(['project_id', 'starts_at', 'ends_at']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_times');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('staff_services');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
