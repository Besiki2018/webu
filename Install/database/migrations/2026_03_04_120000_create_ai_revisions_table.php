<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->text('prompt_text')->nullable();
            $table->json('ai_raw_output')->nullable();
            $table->json('applied_patch');
            $table->json('snapshot_before');
            $table->json('snapshot_after');
            $table->string('snapshot_hash', 64)->nullable();
            $table->unsignedBigInteger('page_revision_id')->nullable();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('page_revision_id')->references('id')->on('page_revisions')->nullOnDelete();

            $table->index(['site_id', 'page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_revisions');
    }
};
