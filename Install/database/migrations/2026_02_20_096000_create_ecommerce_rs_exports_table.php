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
        Schema::create('ecommerce_rs_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('order_id');
            $table->string('schema_version', 40)->default('rs.v1');
            $table->string('status', 16)->default('invalid');
            $table->string('export_hash', 64);
            $table->json('payload_json');
            $table->json('validation_errors_json')->nullable();
            $table->json('validation_warnings_json')->nullable();
            $table->json('totals_json')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'order_id']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'generated_at']);
            $table->index(['site_id', 'export_hash']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_rs_exports');
    }
};
