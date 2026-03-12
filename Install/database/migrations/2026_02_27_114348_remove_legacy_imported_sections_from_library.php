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
        if (! Schema::hasTable('sections_library')) {
            return;
        }

        DB::table('sections_library')
            ->where('key', 'like', 'webu%_section_%')
            ->delete();

        DB::table('sections_library')
            ->whereIn('key', [
                'webu_home_hero_01',
                'webu_feature_icons_01',
                'webu_promo_banners_01',
                'webu_product_slider_01',
                'webu_instagram_feed_01',
                'webu_client_logo_slider_01',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback for deleted legacy imported rows.
    }
};
