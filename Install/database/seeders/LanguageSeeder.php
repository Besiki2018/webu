<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'ka',
                'country_code' => 'GE',
                'name' => 'Georgian',
                'native_name' => 'ქართული',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
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
            ],
            [
                'code' => 'ar',
                'country_code' => 'SA',
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'is_rtl' => true,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'ja',
                'country_code' => 'JP',
                'name' => 'Japanese',
                'native_name' => '日本語',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
            [
                'code' => 'ru',
                'country_code' => 'RU',
                'name' => 'Russian',
                'native_name' => 'Русский',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'de',
                'country_code' => 'DE',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 5,
            ],
            [
                'code' => 'fr',
                'country_code' => 'FR',
                'name' => 'French',
                'native_name' => 'Français',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 6,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
