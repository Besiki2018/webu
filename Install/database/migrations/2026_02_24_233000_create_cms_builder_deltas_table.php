<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_builder_deltas')) {
            return;
        }

        Schema::create('cms_builder_deltas', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('site_id', 36);
            $table->char('project_id', 36);
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('baseline_revision_id')->nullable();
            $table->unsignedBigInteger('target_revision_id');
            $table->string('generation_id', 96);
            $table->string('locale', 10)->nullable();
            $table->string('captured_from', 40)->default('panel_revision_save');
            $table->json('patch_ops');
            $table->json('patch_stats_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'page_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['generation_id']);
            $table->index(['target_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_builder_deltas');
    }
};
