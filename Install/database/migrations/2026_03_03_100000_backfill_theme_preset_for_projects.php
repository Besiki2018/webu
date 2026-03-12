<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill theme_preset = 'default' for existing projects that have null.
     * Ensures no breakage when CmsThemeTokenLayerResolver reads project.theme_preset.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::table('projects')
                ->whereNull('theme_preset')
                ->update(['theme_preset' => 'default']);
        } else {
            DB::table('projects')
                ->whereNull('theme_preset')
                ->orWhere('theme_preset', '')
                ->update(['theme_preset' => 'default']);
        }
    }

    public function down(): void
    {
        // Optional: set back to null where we set default (not easily reversible without storing previous state)
    }
};
