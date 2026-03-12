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
        Schema::table('plans', function (Blueprint $table) {
            // Firebase settings
            $table->boolean('enable_firebase')->default(false)->after('allow_private_visibility');
            $table->boolean('allow_user_firebase_config')->default(false)->after('enable_firebase');

            // File storage settings
            $table->boolean('enable_file_storage')->default(false)->after('allow_user_firebase_config');
            $table->unsignedBigInteger('max_storage_mb')->nullable()->after('enable_file_storage');
            $table->unsignedInteger('max_file_size_mb')->default(10)->after('max_storage_mb');
            $table->json('allowed_file_types')->nullable()->after('max_file_size_mb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'enable_firebase',
                'allow_user_firebase_config',
                'enable_file_storage',
                'max_storage_mb',
                'max_file_size_mb',
                'allowed_file_types',
            ]);
        });
    }
};
