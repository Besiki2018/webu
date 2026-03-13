<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_generation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('queued');
            $table->text('requested_prompt');
            $table->string('requested_language', 16)->nullable();
            $table->string('requested_style', 32)->nullable();
            $table->string('requested_website_type', 32)->nullable();
            $table->json('requested_input')->nullable();
            $table->string('progress_message', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_generation_runs');
    }
};
