<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * website_seo: per-page SEO (CMS-managed).
     * website_revisions: undo/redo snapshots for CMS-level edits.
     */
    public function up(): void
    {
        Schema::create('website_seo', function (Blueprint $table) {
            $table->id();
            $table->uuid('website_id');
            $table->unsignedBigInteger('website_page_id');
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_image')->nullable();
            $table->string('locale', 10)->default('ka');
            $table->timestamps();

            $table->unique(['website_page_id', 'locale']);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->foreign('website_page_id')->references('id')->on('website_pages')->cascadeOnDelete();
        });

        Schema::create('website_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('website_id');
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot_json')->nullable()->comment('Full website pages + sections snapshot or diff');
            $table->string('change_type', 50)->nullable()->comment('manual,builder,ai');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['website_id', 'version']);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_revisions');
        Schema::dropIfExists('website_seo');
    }
};
