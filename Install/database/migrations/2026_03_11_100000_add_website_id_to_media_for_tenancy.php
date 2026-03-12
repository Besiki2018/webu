<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media table: add website_id for CMS/tenant-scoped media rows.
 * When set, media belongs to tenant_id + website_id (e.g. Universal CMS uploads).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }
        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'website_id')) {
                $table->uuid('website_id')->nullable()->after('tenant_id');
            }
        });
        if (Schema::hasTable('websites')) {
            try {
                Schema::table('media', function (Blueprint $table): void {
                    $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
                });
            } catch (\Throwable) {
                //
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'website_id')) {
            Schema::table('media', function (Blueprint $table): void {
                $table->dropForeign(['website_id']);
                $table->dropColumn('website_id');
            });
        }
    }
};
