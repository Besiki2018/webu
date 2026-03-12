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
            if (! Schema::hasColumn('plans', 'enable_shipping')) {
                $table->boolean('enable_shipping')
                    ->default(true)
                    ->after('allowed_installment_providers');
            }

            if (! Schema::hasColumn('plans', 'allowed_courier_providers')) {
                $table->json('allowed_courier_providers')
                    ->nullable()
                    ->after('enable_shipping');
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
                'enable_shipping',
                'allowed_courier_providers',
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
