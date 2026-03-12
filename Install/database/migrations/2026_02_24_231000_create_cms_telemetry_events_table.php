<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_telemetry_events')) {
            return;
        }

        Schema::create('cms_telemetry_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('site_id', 36);
            $table->char('project_id', 36);
            $table->string('channel', 20);
            $table->string('source', 20);
            $table->string('event_name', 120);
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('page_slug', 120)->nullable();
            $table->string('route_path', 255)->nullable();
            $table->string('route_slug', 120)->nullable();
            $table->json('route_params_json')->nullable();
            $table->json('context_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->char('session_hash', 64)->nullable();
            $table->char('client_ip_hash', 64)->nullable();
            $table->string('user_agent_family', 120)->nullable();
            $table->string('actor_scope', 20)->default('guest');
            $table->char('actor_hash', 64)->nullable();
            $table->timestamp('retention_expires_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['source', 'event_name']);
            $table->index(['retention_expires_at']);
            $table->index(['site_id', 'event_name', 'occurred_at'], 'cms_telemetry_site_event_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_telemetry_events');
    }
};
