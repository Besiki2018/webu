<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites') || Schema::hasColumn('websites', 'status')) {
            return;
        }
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('status', 32)->nullable()->default('active')->after('domain');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('websites', 'status')) {
            Schema::table('websites', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
