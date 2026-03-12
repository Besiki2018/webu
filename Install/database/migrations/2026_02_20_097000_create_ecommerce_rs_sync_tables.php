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
        Schema::create('ecommerce_rs_syncs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('export_id');
            $table->unsignedBigInteger('order_id');
            $table->string('connector', 64)->default('rs-v2-skeleton');
            $table->string('idempotency_key', 191);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('remote_reference', 191)->nullable();
            $table->text('last_error')->nullable();
            $table->json('response_snapshot_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'export_id', 'connector']);
            $table->unique(['site_id', 'idempotency_key']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'next_retry_at']);
            $table->index(['site_id', 'order_id']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('export_id')->references('id')->on('ecommerce_rs_exports')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ecommerce_rs_sync_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('sync_id');
            $table->unsignedBigInteger('export_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 16)->default('processing');
            $table->json('request_payload_json')->nullable();
            $table->json('response_payload_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['sync_id', 'attempt_no']);
            $table->index(['site_id', 'sync_id']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'export_id']);
            $table->index(['site_id', 'order_id']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('sync_id')->references('id')->on('ecommerce_rs_syncs')->onDelete('cascade');
            $table->foreign('export_id')->references('id')->on('ecommerce_rs_exports')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_rs_sync_attempts');
        Schema::dropIfExists('ecommerce_rs_syncs');
    }
};
