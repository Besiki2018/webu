<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run AFTER backfilling tenant_id (php artisan tenancy:backfill).
 * Makes tenant_id NOT NULL and adds foreign keys where missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->websites();
        $this->websitePages();
        $this->pageSections();
        $this->websiteSeo();
        $this->websiteRevisions();
    }

    private function websites(): void
    {
        if (! Schema::hasTable('websites') || ! Schema::hasColumn('websites', 'tenant_id')) {
            return;
        }
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        if (Schema::hasTable('tenants')) {
            Schema::table('websites', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    private function websitePages(): void
    {
        if (! Schema::hasTable('website_pages') || ! Schema::hasColumn('website_pages', 'tenant_id')) {
            return;
        }
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        if (Schema::hasTable('tenants')) {
            Schema::table('website_pages', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    private function pageSections(): void
    {
        if (! Schema::hasTable('page_sections') || ! Schema::hasColumn('page_sections', 'tenant_id')) {
            return;
        }
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            if (Schema::hasColumn('page_sections', 'website_id')) {
                $table->dropForeign(['website_id']);
            }
        });
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        if (Schema::hasColumn('page_sections', 'website_id')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->uuid('website_id')->nullable(false)->change();
                $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            });
        }
        if (Schema::hasTable('tenants')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    private function websiteSeo(): void
    {
        if (! Schema::hasTable('website_seo') || ! Schema::hasColumn('website_seo', 'tenant_id')) {
            return;
        }
        Schema::table('website_seo', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('website_seo', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        if (Schema::hasTable('tenants')) {
            Schema::table('website_seo', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    private function websiteRevisions(): void
    {
        if (! Schema::hasTable('website_revisions') || ! Schema::hasColumn('website_revisions', 'tenant_id')) {
            return;
        }
        Schema::table('website_revisions', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('website_revisions', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        if (Schema::hasTable('tenants')) {
            Schema::table('website_revisions', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Revert to nullable (no data loss)
        $tables = ['websites', 'website_pages', 'page_sections', 'website_seo', 'website_revisions'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->uuid('tenant_id')->nullable()->change();
                });
            }
        }
        if (Schema::hasTable('page_sections') && Schema::hasColumn('page_sections', 'website_id')) {
            Schema::table('page_sections', function (Blueprint $table): void {
                $table->uuid('website_id')->nullable()->change();
            });
        }
    }
};
