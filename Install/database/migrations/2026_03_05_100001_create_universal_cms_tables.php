<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Universal CMS: websites → website_pages → page_sections.
     * Every AI-generated site becomes fully editable; content stored in CMS DB.
     */
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->json('theme')->nullable();
            $table->uuid('site_id')->nullable()->unique();
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
        });

        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('website_id');
            $table->string('slug');
            $table->string('title');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedBigInteger('page_id')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'slug']);
            $table->index(['website_id', 'order']);
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->string('section_type');
            $table->unsignedInteger('order')->default(0);
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'order']);
            $table->foreign('page_id')->references('id')->on('website_pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('website_pages');
        Schema::dropIfExists('websites');
    }
};
