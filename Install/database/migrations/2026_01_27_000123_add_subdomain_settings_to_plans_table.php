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
            $table->boolean('enable_subdomains')->default(false)->after('max_projects');
            $table->integer('max_subdomains_per_user')->nullable()->after('enable_subdomains');
            $table->boolean('allow_private_visibility')->default(false)->after('max_subdomains_per_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['enable_subdomains', 'max_subdomains_per_user', 'allow_private_visibility']);
        });
    }
};
