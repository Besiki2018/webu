<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->foreignId('owner_user_id')->nullable()->after('slug')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tenants', 'plan')) {
                $table->string('plan', 64)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->dropForeign(['owner_user_id']);
            }
            if (Schema::hasColumn('tenants', 'plan')) {
                $table->dropColumn('plan');
            }
        });
    }
};
