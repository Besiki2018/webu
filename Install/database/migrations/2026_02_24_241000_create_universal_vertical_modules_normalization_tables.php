<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('room_type', 64);
                $table->unsignedInteger('capacity')->default(1);
                $table->decimal('price_per_night', 12, 2);
                $table->string('currency', 3);
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('room_images')) {
            Schema::create('room_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('media_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['room_id', 'media_id']);
                $table->index(['room_id', 'sort_order']);
                $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('room_reservations')) {
            Schema::create('room_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('room_id');
                $table->date('checkin_date');
                $table->date('checkout_date');
                $table->string('status', 32)->default('pending');
                $table->decimal('total_price', 12, 2);
                $table->string('currency', 3);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'room_id', 'status']);
                $table->index(['project_id', 'checkin_date', 'checkout_date']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('restaurant_menu_categories')) {
            Schema::create('restaurant_menu_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'sort_order']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('restaurant_menu_items')) {
            Schema::create('restaurant_menu_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->unsignedBigInteger('category_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->unsignedBigInteger('media_id')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'category_id', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('restaurant_menu_categories')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('table_reservations')) {
            Schema::create('table_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('customer_name');
                $table->string('phone', 64);
                $table->unsignedInteger('guests');
                $table->timestamp('starts_at');
                $table->string('status', 32)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status', 'starts_at']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('portfolio_items')) {
            Schema::create('portfolio_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('title');
                $table->string('slug');
                $table->text('excerpt')->nullable();
                $table->longText('content_html')->nullable();
                $table->unsignedBigInteger('cover_media_id')->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['project_id', 'slug']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('cover_media_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('portfolio_images')) {
            Schema::create('portfolio_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_item_id');
                $table->unsignedBigInteger('media_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['portfolio_item_id', 'media_id']);
                $table->index(['portfolio_item_id', 'sort_order']);
                $table->foreign('portfolio_item_id')->references('id')->on('portfolio_items')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('title');
                $table->string('slug');
                $table->decimal('price', 12, 2);
                $table->string('currency', 3);
                $table->string('location_text');
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->unsignedInteger('bedrooms')->nullable();
                $table->unsignedInteger('bathrooms')->nullable();
                $table->decimal('area_m2', 12, 2)->nullable();
                $table->longText('description_html')->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamps();

                $table->unique(['project_id', 'slug']);
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'price']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('property_images')) {
            Schema::create('property_images', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('media_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['property_id', 'media_id']);
                $table->index(['property_id', 'sort_order']);
                $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
                $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('portfolio_images');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('table_reservations');
        Schema::dropIfExists('restaurant_menu_items');
        Schema::dropIfExists('restaurant_menu_categories');
        Schema::dropIfExists('room_reservations');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('rooms');
    }
};
