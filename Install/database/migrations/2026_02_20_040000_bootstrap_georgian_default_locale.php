<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('system_settings')) {
            $exists = DB::table('system_settings')->where('key', 'default_locale')->exists();

            if ($exists) {
                DB::table('system_settings')
                    ->where('key', 'default_locale')
                    ->update([
                        'value' => 'ka',
                        'type' => 'string',
                        'group' => 'general',
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('system_settings')->insert([
                    'key' => 'default_locale',
                    'value' => 'ka',
                    'type' => 'string',
                    'group' => 'general',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('languages')) {
            DB::table('languages')->where('is_default', true)->update([
                'is_default' => false,
                'updated_at' => $now,
            ]);

            DB::table('languages')->upsert(
                [
                    [
                        'code' => 'ka',
                        'country_code' => 'GE',
                        'name' => 'Georgian',
                        'native_name' => 'ქართული',
                        'is_rtl' => false,
                        'is_active' => true,
                        'is_default' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'code' => 'en',
                        'country_code' => 'US',
                        'name' => 'English',
                        'native_name' => 'English',
                        'is_rtl' => false,
                        'is_active' => true,
                        'is_default' => false,
                        'sort_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ],
                ['code'],
                ['country_code', 'name', 'native_name', 'is_rtl', 'is_active', 'is_default', 'sort_order', 'updated_at']
            );
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'locale')) {
            DB::table('users')
                ->where(function ($query) {
                    $query->whereNull('locale')
                        ->orWhere('locale', '');
                })
                ->update([
                    'locale' => 'ka',
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $now = now();

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->where('key', 'default_locale')
                ->update([
                    'value' => 'en',
                    'type' => 'string',
                    'group' => 'general',
                    'updated_at' => $now,
                ]);
        }

        if (Schema::hasTable('languages')) {
            DB::table('languages')->where('is_default', true)->update([
                'is_default' => false,
                'updated_at' => $now,
            ]);

            DB::table('languages')
                ->where('code', 'en')
                ->update([
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => 0,
                    'updated_at' => $now,
                ]);

            DB::table('languages')
                ->where('code', 'ka')
                ->update([
                    'is_default' => false,
                    'sort_order' => 1,
                    'updated_at' => $now,
                ]);
        }
    }
};
