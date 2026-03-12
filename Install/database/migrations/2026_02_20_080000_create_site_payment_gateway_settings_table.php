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
        Schema::create('site_payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('provider_slug', 120);
            $table->string('availability', 20)->default('inherit');
            $table->json('config')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'provider_slug']);
            $table->index(['site_id', 'availability']);
            $table->index(['provider_slug']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_payment_gateway_settings');
    }
};
