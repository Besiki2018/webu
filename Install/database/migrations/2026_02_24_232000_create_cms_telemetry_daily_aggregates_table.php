<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_telemetry_daily_aggregates')) {
            return;
        }

        Schema::create('cms_telemetry_daily_aggregates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->date('metric_date');
            $table->char('site_id', 36);
            $table->char('project_id', 36);
            $table->unsignedInteger('total_events')->default(0);
            $table->unsignedInteger('builder_events')->default(0);
            $table->unsignedInteger('runtime_events')->default(0);
            $table->unsignedInteger('unique_sessions_total')->default(0);
            $table->unsignedInteger('unique_sessions_builder')->default(0);
            $table->unsignedInteger('unique_sessions_runtime')->default(0);
            $table->unsignedInteger('builder_open_count')->default(0);
            $table->unsignedInteger('builder_save_draft_count')->default(0);
            $table->unsignedInteger('builder_publish_page_count')->default(0);
            $table->unsignedInteger('builder_save_warning_total')->default(0);
            $table->unsignedInteger('runtime_route_hydrated_count')->default(0);
            $table->unsignedInteger('runtime_hydrate_failed_count')->default(0);
            $table->json('metrics_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['metric_date', 'site_id'], 'cms_telemetry_daily_aggregates_date_site_unique');
            $table->index(['project_id', 'metric_date']);
            $table->index(['metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_telemetry_daily_aggregates');
    }
};
