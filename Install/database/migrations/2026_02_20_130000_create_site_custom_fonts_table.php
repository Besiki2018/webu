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
        Schema::create('site_custom_fonts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->string('key', 64);
            $table->string('label', 120);
            $table->string('font_family', 120);
            $table->string('storage_path');
            $table->string('mime', 100);
            $table->string('format', 16);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('font_weight')->default(400);
            $table->string('font_style', 16)->default('normal');
            $table->string('font_display', 16)->default('swap');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'created_at']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_custom_fonts');
    }
};
