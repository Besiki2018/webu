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
            if (! Schema::hasColumn('plans', 'enable_ecommerce')) {
                $table->boolean('enable_ecommerce')
                    ->default(true)
                    ->after('enable_booking_prepayment');
            }

            if (! Schema::hasColumn('plans', 'enable_booking')) {
                $table->boolean('enable_booking')
                    ->default(true)
                    ->after('enable_ecommerce');
            }

            if (! Schema::hasColumn('plans', 'max_products')) {
                $table->unsignedInteger('max_products')
                    ->nullable()
                    ->after('enable_booking');
            }

            if (! Schema::hasColumn('plans', 'max_monthly_orders')) {
                $table->unsignedInteger('max_monthly_orders')
                    ->nullable()
                    ->after('max_products');
            }

            if (! Schema::hasColumn('plans', 'max_monthly_bookings')) {
                $table->unsignedInteger('max_monthly_bookings')
                    ->nullable()
                    ->after('max_monthly_orders');
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
                'enable_ecommerce',
                'enable_booking',
                'max_products',
                'max_monthly_orders',
                'max_monthly_bookings',
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
