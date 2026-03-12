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
            $table->decimal('referral_credit_balance', 10, 2)->default(0)->after('build_credits');
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_credit_balance')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('referral_code_id_used')->nullable()->after('referred_by_user_id')
                ->constrained('referral_codes')->nullOnDelete();

            $table->index('referred_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropForeign(['referral_code_id_used']);
            $table->dropIndex(['referred_by_user_id']);
            $table->dropColumn(['referral_credit_balance', 'referred_by_user_id', 'referral_code_id_used']);
        });
    }
};
