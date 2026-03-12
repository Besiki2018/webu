<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'build_credit_overage_balance')) {
                $table->bigInteger('build_credit_overage_balance')
                    ->default(0)
                    ->after('build_credits');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'build_credit_overage_balance')) {
                $table->dropColumn('build_credit_overage_balance');
            }
        });
    }
};
