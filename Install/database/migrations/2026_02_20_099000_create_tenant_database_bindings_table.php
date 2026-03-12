<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_database_bindings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->string('status', 24)->default('provisioning');
            $table->string('driver', 32)->default('mysql');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->json('options_json')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('project_id');
            $table->index(['status', 'updated_at']);
            $table->index(['driver', 'host', 'database']);

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_database_bindings');
    }
};

