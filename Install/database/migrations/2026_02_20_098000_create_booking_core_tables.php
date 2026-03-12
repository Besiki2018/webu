<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_services', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedSmallInteger('slot_step_minutes')->nullable();
            $table->unsignedSmallInteger('max_parallel_bookings')->default(1);
            $table->boolean('requires_staff')->default(true);
            $table->boolean('allow_online_payment')->default(false);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('GEL');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug'], 'bks_site_slug_uq');
            $table->index(['site_id', 'status'], 'bks_site_status_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('booking_staff_resources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('type', 20)->default('staff');
            $table->string('status', 20)->default('active');
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('timezone', 64)->default(config('app.timezone', 'UTC'));
            $table->unsignedSmallInteger('max_parallel_bookings')->default(1);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug'], 'bsr_site_slug_uq');
            $table->index(['site_id', 'type', 'status'], 'bsr_site_type_status_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('booking_staff_roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->string('key', 60);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'key'], 'bsr_role_site_key_uq');
            $table->index(['site_id', 'is_system'], 'bsr_role_site_sys_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('booking_staff_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('role_id');
            $table->string('permission_key', 120);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'role_id', 'permission_key'], 'bsr_perm_site_role_key_uq');
            $table->index(['site_id', 'permission_key'], 'bsr_perm_site_key_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('booking_staff_roles')->onDelete('cascade');
        });

        Schema::create('booking_staff_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('staff_resource_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->string('timezone', 64)->default(config('app.timezone', 'UTC'));
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'staff_resource_id', 'day_of_week'], 'bsws_site_staff_day_idx');
            $table->index(['site_id', 'day_of_week'], 'bsws_site_day_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('staff_resource_id')->references('id')->on('booking_staff_resources')->onDelete('cascade');
        });

        Schema::create('booking_staff_time_off', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('staff_resource_id');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('approved');
            $table->string('reason', 255)->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'staff_resource_id', 'starts_at'], 'bsto_site_staff_start_idx');
            $table->index(['site_id', 'status', 'starts_at'], 'bsto_site_status_start_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('staff_resource_id')->references('id')->on('booking_staff_resources')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('staff_resource_id')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('rule_type', 20)->default('include');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'service_id'], 'bar_site_service_idx');
            $table->index(['site_id', 'staff_resource_id'], 'bar_site_staff_idx');
            $table->index(['site_id', 'rule_type', 'priority'], 'bar_site_rule_pri_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('booking_services')->nullOnDelete();
            $table->foreign('staff_resource_id')->references('id')->on('booking_staff_resources')->nullOnDelete();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('staff_resource_id')->nullable();
            $table->string('booking_number', 60);
            $table->string('status', 30)->default('pending');
            $table->string('source', 30)->default('panel');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('collision_starts_at');
            $table->timestamp('collision_ends_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->string('timezone', 64)->default(config('app.timezone', 'UTC'));
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('outstanding_total', 12, 2)->default(0);
            $table->string('currency', 3)->default('GEL');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'booking_number'], 'bkg_site_number_uq');
            $table->index(['site_id', 'status'], 'bkg_site_status_idx');
            $table->index(['site_id', 'service_id', 'starts_at'], 'bkg_site_service_start_idx');
            $table->index(['site_id', 'staff_resource_id', 'starts_at'], 'bkg_site_staff_start_idx');
            $table->index(['site_id', 'collision_starts_at', 'collision_ends_at'], 'bkg_site_collision_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('booking_services')->onDelete('cascade');
            $table->foreign('staff_resource_id')->references('id')->on('booking_staff_resources')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('event_type', 60);
            $table->string('event_key', 180)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'event_key'], 'bke_site_event_key_uq');
            $table->index(['site_id', 'booking_id'], 'bke_site_booking_idx');
            $table->index(['site_id', 'event_type'], 'bke_site_type_idx');
            $table->index(['site_id', 'occurred_at'], 'bke_site_occurred_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('staff_resource_id');
            $table->string('assignment_type', 30)->default('primary');
            $table->string('status', 30)->default('assigned');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'booking_id', 'staff_resource_id'], 'bka_site_booking_staff_uq');
            $table->index(['site_id', 'staff_resource_id', 'status'], 'bka_site_staff_status_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('staff_resource_id')->references('id')->on('booking_staff_resources')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_assignments');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_availability_rules');
        Schema::dropIfExists('booking_staff_time_off');
        Schema::dropIfExists('booking_staff_work_schedules');
        Schema::dropIfExists('booking_staff_role_permissions');
        Schema::dropIfExists('booking_staff_roles');
        Schema::dropIfExists('booking_staff_resources');
        Schema::dropIfExists('booking_services');
    }
};
