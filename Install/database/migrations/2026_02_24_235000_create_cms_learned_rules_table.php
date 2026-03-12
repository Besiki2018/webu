<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_learned_rules')) {
            return;
        }

        Schema::create('cms_learned_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope', 20)->default('tenant');
            $table->char('project_id', 36)->nullable();
            $table->char('site_id', 36)->nullable();
            $table->string('rule_key', 96);
            $table->string('status', 20)->default('candidate');
            $table->boolean('active')->default(false);
            $table->string('source', 40)->default('builder_delta_cluster');
            $table->json('conditions_json');
            $table->json('patch_json');
            $table->json('evidence_json')->nullable();
            $table->decimal('confidence', 6, 4)->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('delta_count')->default(0);
            $table->timestamp('last_learned_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'site_id', 'rule_key'], 'cms_learned_rules_scope_site_rule_unique');
            $table->index(['project_id', 'status'], 'cms_learned_rules_project_status_idx');
            $table->index(['site_id', 'status', 'active'], 'cms_learned_rules_site_status_active_idx');
            $table->index(['status', 'confidence'], 'cms_learned_rules_status_confidence_idx');
            $table->index(['source', 'created_at'], 'cms_learned_rules_source_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_learned_rules');
    }
};
