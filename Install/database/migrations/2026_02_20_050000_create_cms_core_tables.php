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
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('name');
            $table->string('primary_domain')->nullable();
            $table->string('subdomain')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('locale', 10)->default('ka');
            $table->json('theme_settings')->nullable();
            $table->timestamps();

            $table->unique('project_id');
            $table->unique('primary_domain');
            $table->unique('subdomain');
            $table->index(['status', 'locale']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::create('sections_library', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category');
            $table->json('schema_json');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('title');
            $table->string('slug');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content_json');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'version']);
            $table->index(['site_id', 'published_at']);
            $table->index(['site_id', 'page_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('key');
            $table->json('items_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('global_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->json('contact_json')->nullable();
            $table->json('social_links_json')->nullable();
            $table->json('analytics_ids_json')->nullable();
            $table->timestamps();

            $table->unique('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('logo_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_settings');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('media');
        Schema::dropIfExists('sections_library');
        Schema::dropIfExists('sites');
    }
};

