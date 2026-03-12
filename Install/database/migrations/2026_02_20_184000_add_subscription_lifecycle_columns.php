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
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('renewal_retry_count')->default(0)->after('renewal_at');
            $table->unsignedSmallInteger('renewal_retry_limit')->nullable()->after('renewal_retry_count');
            $table->timestamp('last_renewal_attempt_at')->nullable()->after('renewal_retry_limit');
            $table->timestamp('next_retry_at')->nullable()->after('last_renewal_attempt_at');
            $table->timestamp('grace_ends_at')->nullable()->after('next_retry_at');
            $table->timestamp('suspended_at')->nullable()->after('grace_ends_at');
            $table->text('last_renewal_error')->nullable()->after('suspended_at');

            $table->index(['status', 'renewal_at']);
            $table->index('next_retry_at');
            $table->index('grace_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'renewal_at']);
            $table->dropIndex(['next_retry_at']);
            $table->dropIndex(['grace_ends_at']);

            $table->dropColumn([
                'renewal_retry_count',
                'renewal_retry_limit',
                'last_renewal_attempt_at',
                'next_retry_at',
                'grace_ends_at',
                'suspended_at',
                'last_renewal_error',
            ]);
        });
    }
};
