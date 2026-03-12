<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Director for Chat Editing (PART 6): rollback entry for every applied patch.
     */
    public function up(): void
    {
        Schema::create('chat_patch_rollbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id');
            $table->uuid('site_id')->nullable();
            $table->string('patch_type', 64); // theme_preset, add_section
            $table->json('snapshot_json'); // state before patch (theme_preset + theme_settings, or page content)
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'rolled_back_at']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_patch_rollbacks');
    }
};
