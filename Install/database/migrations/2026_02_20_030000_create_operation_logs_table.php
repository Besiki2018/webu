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
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40);
            $table->string('event', 120);
            $table->string('status', 20)->default('info');
            $table->string('source', 80)->nullable();
            $table->string('domain')->nullable();
            $table->string('identifier', 191)->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index(['project_id', 'occurred_at']);
            $table->index(['channel', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
            $table->index('domain');
            $table->index('identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
