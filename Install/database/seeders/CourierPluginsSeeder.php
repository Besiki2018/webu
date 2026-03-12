<?php

namespace Database\Seeders;

use App\Models\Plugin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CourierPluginsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plugins = [
            [
                'name' => 'Manual Courier',
                'slug' => 'manual-courier',
                'type' => 'courier',
                'class' => 'App\Plugins\Couriers\ManualCourier\ManualCourierPlugin',
                'version' => '1.0.0',
                'status' => 'active',
                'config' => [
                    'service_name' => 'Standard Delivery',
                    'base_rate' => 7,
                    'per_item_rate' => 0,
                    'currency' => 'GEL',
                    'eta_min_days' => 1,
                    'eta_max_days' => 3,
                ],
                'metadata' => $this->getPluginMetadata('ManualCourier'),
                'migrations' => null,
                'installed_at' => now(),
            ],
            [
                'name' => 'OnWay',
                'slug' => 'onway',
                'type' => 'courier',
                'class' => 'App\Plugins\Couriers\OnWay\OnWayCourierPlugin',
                'version' => '1.0.0',
                'status' => 'active',
                'config' => [
                    'sandbox' => true,
                    'api_base_url' => 'https://onway.ge/api',
                    'merchant_id' => '',
                    'api_key' => '',
                    'default_service_name' => 'OnWay Delivery',
                    'currency' => 'GEL',
                    'eta_min_days' => 1,
                    'eta_max_days' => 2,
                    'fallback_rate' => 0,
                    'tracking_base_url' => 'https://onway.ge/',
                    'supported_countries' => ['GE'],
                ],
                'metadata' => $this->getPluginMetadata('OnWay'),
                'migrations' => null,
                'installed_at' => now(),
            ],
        ];

        foreach ($plugins as $pluginData) {
            Plugin::updateOrCreate(
                ['slug' => $pluginData['slug']],
                $pluginData
            );

            $this->command?->info("Installed: {$pluginData['name']}");
        }

        $this->command?->info('Total courier plugins: '.Plugin::byType('courier')->count());
    }

    /**
     * Get plugin metadata from plugin.json file.
     */
    private function getPluginMetadata(string $pluginDir): ?array
    {
        $path = app_path("Plugins/Couriers/{$pluginDir}/plugin.json");

        if (! File::exists($path)) {
            return null;
        }

        return json_decode(File::get($path), true);
    }
}
