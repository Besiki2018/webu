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
            if (! Schema::hasColumn('plans', 'enable_custom_fonts')) {
                $table->boolean('enable_custom_fonts')
                    ->default(true)
                    ->after('allowed_courier_providers');
            }

            if (! Schema::hasColumn('plans', 'allowed_typography_font_keys')) {
                $table->json('allowed_typography_font_keys')
                    ->nullable()
                    ->after('enable_custom_fonts');
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
                'enable_custom_fonts',
                'allowed_typography_font_keys',
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
