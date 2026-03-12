<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            if (! Schema::hasColumn('websites', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        if (Schema::hasTable('tenants')) {
            try {
                Schema::table('websites', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may exist
            }
        }
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
