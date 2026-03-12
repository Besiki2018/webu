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
        Schema::table('templates', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
            $table->string('version')->default('1.0.0')->after('category');
            $table->string('zip_path')->nullable()->after('thumbnail');
            $table->json('metadata')->nullable()->after('zip_path');
        });

        // Make slug unique after adding nullable
        Schema::table('templates', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'version', 'zip_path', 'metadata']);
        });
    }
};
