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
        Schema::create('site_forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('key', 120);
            $table->string('name', 255);
            $table->string('status', 20)->default('draft'); // draft|active|inactive|disabled
            $table->json('schema_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_forms');
    }
};
