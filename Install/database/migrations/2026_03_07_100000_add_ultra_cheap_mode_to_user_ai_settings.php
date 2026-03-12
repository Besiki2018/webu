<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ai_settings', function (Blueprint $table) {
            $table->boolean('ultra_cheap_mode')->default(true)->after('preferred_model');
        });
    }

    public function down(): void
    {
        Schema::table('user_ai_settings', function (Blueprint $table) {
            $table->dropColumn('ultra_cheap_mode');
        });
    }
};
