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
        Schema::create('ecommerce_shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('order_id');
            $table->string('provider_slug', 120);
            $table->string('shipment_reference', 191);
            $table->string('tracking_number', 191)->nullable();
            $table->text('tracking_url')->nullable();
            $table->string('status', 40)->default('created');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'shipment_reference']);
            $table->index(['site_id', 'order_id']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'tracking_number']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ecommerce_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('shipment_id');
            $table->string('event_type', 50);
            $table->string('status', 40)->nullable();
            $table->text('message')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'shipment_id']);
            $table->index(['site_id', 'event_type']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('shipment_id')->references('id')->on('ecommerce_shipments')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_shipment_events');
        Schema::dropIfExists('ecommerce_shipments');
    }
};

