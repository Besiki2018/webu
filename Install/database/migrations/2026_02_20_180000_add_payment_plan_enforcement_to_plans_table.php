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
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'enable_online_payments')) {
                $table->boolean('enable_online_payments')
                    ->default(true)
                    ->after('max_monthly_bookings');
            }

            if (! Schema::hasColumn('plans', 'enable_installments')) {
                $table->boolean('enable_installments')
                    ->default(true)
                    ->after('enable_online_payments');
            }

            if (! Schema::hasColumn('plans', 'allowed_payment_providers')) {
                $table->json('allowed_payment_providers')
                    ->nullable()
                    ->after('enable_installments');
            }

            if (! Schema::hasColumn('plans', 'allowed_installment_providers')) {
                $table->json('allowed_installment_providers')
                    ->nullable()
                    ->after('allowed_payment_providers');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'enable_online_payments',
                'enable_installments',
                'allowed_payment_providers',
                'allowed_installment_providers',
            ] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
