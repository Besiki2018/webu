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
        if (! Schema::hasColumn('plans', 'enable_booking_prepayment')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->boolean('enable_booking_prepayment')
                    ->default(false)
                    ->after('enable_file_storage');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('plans', 'enable_booking_prepayment')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('enable_booking_prepayment');
            });
        }
    }
};
