<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_experiments')) {
            Schema::create('cms_experiments', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->char('site_id', 36);
                $table->char('project_id', 36);
                $table->string('key', 120);
                $table->string('name', 160);
                $table->string('status', 20)->default('draft');
                $table->string('assignment_unit', 32)->default('session_or_device');
                $table->unsignedTinyInteger('traffic_percent')->default(100);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('targeting_json')->nullable();
                $table->json('meta_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['site_id', 'key'], 'cms_experiments_site_key_unique');
                $table->index(['project_id', 'created_at']);
                $table->index(['site_id', 'status'], 'cms_experiments_site_status_idx');
                $table->index(['status', 'starts_at', 'ends_at'], 'cms_experiments_status_window_idx');
            });
        }

        if (! Schema::hasTable('cms_experiment_variants')) {
            Schema::create('cms_experiment_variants', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('experiment_id');
                $table->string('variant_key', 64);
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('weight')->default(100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('payload_json')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->unique(['experiment_id', 'variant_key'], 'cms_experiment_variants_exp_variant_unique');
                $table->index(['experiment_id', 'status'], 'cms_experiment_variants_exp_status_idx');
            });
        }

        if (! Schema::hasTable('cms_experiment_assignments')) {
            Schema::create('cms_experiment_assignments', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('experiment_id');
                $table->char('site_id', 36);
                $table->char('project_id', 36);
                $table->string('variant_key', 64);
                $table->string('assignment_basis', 20)->default('session');
                $table->char('subject_hash', 64);
                $table->char('session_id_hash', 64)->nullable();
                $table->char('device_id_hash', 64)->nullable();
                $table->json('context_json')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->unique(['experiment_id', 'subject_hash'], 'cms_experiment_assignments_exp_subject_unique');
                $table->index(['site_id', 'created_at']);
                $table->index(['project_id', 'created_at']);
                $table->index(['experiment_id', 'variant_key'], 'cms_experiment_assignments_exp_variant_idx');
                $table->index(['session_id_hash']);
                $table->index(['device_id_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_experiment_assignments');
        Schema::dropIfExists('cms_experiment_variants');
        Schema::dropIfExists('cms_experiments');
    }
};
