<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: Composite indexes for tenant-scoped query patterns.
 * All list queries should use WHERE tenant_id=? AND website_id=? ORDER BY ...
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_pages') && Schema::hasColumn('website_pages', 'tenant_id')) {
            Schema::table('website_pages', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'website_pages', 'idx_website_pages_tenant_website_slug', ['tenant_id', 'website_id', 'slug']);
                $this->addIndexIfMissing($table, 'website_pages', 'idx_website_pages_tenant_website_order', ['tenant_id', 'website_id', 'order']);
            });
        }

        if (Schema::hasTable('page_sections') && Schema::hasColumn('page_sections', 'tenant_id')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'page_sections', 'idx_page_sections_tenant_website_page_order', ['tenant_id', 'website_id', 'page_id', 'order']);
            });
        }

        if (Schema::hasTable('media') && Schema::hasColumn('media', 'tenant_id')) {
            Schema::table('media', function (Blueprint $table): void {
                if (Schema::hasColumn('media', 'website_id')) {
                    $this->addIndexIfMissing($table, 'media', 'idx_media_tenant_website_created', ['tenant_id', 'website_id', 'created_at']);
                } else {
                    $this->addIndexIfMissing($table, 'media', 'idx_media_tenant_created', ['tenant_id', 'created_at']);
                }
            });
        }
    }

    private function addIndexIfMissing(Blueprint $table, string $tableName, string $indexName, array $columns): void
    {
        $indexes = Schema::getIndexListing($tableName);
        if (in_array($indexName, $indexes, true)) {
            return;
        }
        try {
            $table->index($columns, $indexName);
        } catch (\Throwable) {
            // Index may already exist with different name
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('website_pages')) {
            Schema::table('website_pages', function (Blueprint $table): void {
                $table->dropIndex('idx_website_pages_tenant_website_slug');
                $table->dropIndex('idx_website_pages_tenant_website_order');
            });
        }
        if (Schema::hasTable('page_sections')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->dropIndex('idx_page_sections_tenant_website_page_order');
            });
        }
        if (Schema::hasTable('media')) {
            Schema::table('media', function (Blueprint $table): void {
                try {
                    $table->dropIndex('idx_media_tenant_website_created');
                } catch (\Throwable) {
                    //
                }
                try {
                    $table->dropIndex('idx_media_tenant_created');
                } catch (\Throwable) {
                    //
                }
            });
        }
    }
};
