<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional: one domain per tenant (unique tenant_id + domain).
 * Run only if you have no duplicate (tenant_id, domain) pairs; multiple NULL domains are allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites') || ! Schema::hasColumn('websites', 'tenant_id') || ! Schema::hasColumn('websites', 'domain')) {
            return;
        }
        try {
            Schema::table('websites', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'domain'], 'websites_tenant_id_domain_unique');
            });
        } catch (\Throwable $e) {
            // Skip if index exists or duplicate (tenant_id, domain) rows exist
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('websites')) {
            try {
                Schema::table('websites', function (Blueprint $table): void {
                    $table->dropUnique('websites_tenant_id_domain_unique');
                });
            } catch (\Throwable) {
                //
            }
        }
    }
};
