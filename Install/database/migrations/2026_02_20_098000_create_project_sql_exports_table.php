<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sql_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('status', 24)->default('queued');
            $table->string('storage_disk', 32)->default('local');
            $table->string('sql_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('tables_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'created_at']);

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sql_exports');
    }
};

