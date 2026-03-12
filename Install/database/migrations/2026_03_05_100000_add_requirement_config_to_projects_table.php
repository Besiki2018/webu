<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'requirement_config')) {
                $table->json('requirement_config')->nullable()->after('theme_preset');
            }
            if (! Schema::hasColumn('projects', 'requirement_collection_state')) {
                $table->string('requirement_collection_state', 32)->nullable()->after('requirement_config');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['requirement_config', 'requirement_collection_state']);
        });
    }
};
