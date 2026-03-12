<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            if (! Schema::hasColumn('page_sections', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('page_sections', 'website_id')) {
                $table->uuid('website_id')->nullable()->after('tenant_id');
            }
        });

        Schema::table('page_sections', function (Blueprint $table): void {
            if (Schema::hasColumn('page_sections', 'tenant_id')) {
                $table->index('tenant_id', 'idx_page_sections_tenant');
            }
            if (Schema::hasColumn('page_sections', 'tenant_id') && Schema::hasColumn('page_sections', 'website_id')) {
                $table->index(['tenant_id', 'website_id'], 'idx_page_sections_tenant_website');
            }
            if (Schema::hasColumn('page_sections', 'tenant_id')) {
                $table->index(['tenant_id', 'page_id'], 'idx_page_sections_tenant_page');
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('page_sections', 'tenant_id')) {
            try {
                Schema::table('page_sections', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may exist
            }
        }

        if (Schema::hasTable('websites') && Schema::hasColumn('page_sections', 'website_id')) {
            try {
                Schema::table('page_sections', function (Blueprint $table): void {
                    $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may exist
            }
        }
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            if (Schema::hasColumn('page_sections', 'tenant_id') && Schema::hasColumn('page_sections', 'website_id')) {
                try {
                    $table->dropIndex('idx_page_sections_tenant_website');
                } catch (\Throwable) {
                    // Index may not exist
                }
            }
            if (Schema::hasColumn('page_sections', 'website_id')) {
                $table->dropForeign(['website_id']);
                $table->dropColumn('website_id');
            }
            if (Schema::hasColumn('page_sections', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                try {
                    $table->dropIndex('idx_page_sections_tenant');
                    $table->dropIndex('idx_page_sections_tenant_page');
                } catch (\Throwable) {
                    // Indexes may not exist
                }
                $table->dropColumn('tenant_id');
            }
        });
    }
};
