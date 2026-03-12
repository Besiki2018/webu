<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\DesignDecisionService;
use App\Services\EcommerceDemoSeederService;
use App\Services\SiteProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generate test websites automatically using the AI flow (config → blueprint → provision + demo).
 * Results stored in ai-generation-tests/ for layout/design analysis.
 */
class GenerateAiTestSitesCommand extends Command
{
    protected $signature = 'webu:generate-ai-test-sites
                            {--scenarios=* : Scenario names to run (default: all 10)}
                            {--repeat=1 : Number of sites per scenario (e.g. 5 for 50 sites)}
                            {--user= : User ID to own projects (default: first user)}
                            {--no-demo : Skip demo product seeding}
                            {--dry-run : Only print what would be done}';

    protected $description = 'Generate test websites for each scenario; save manifest to ai-generation-tests/';

    private const SCENARIOS = [
        'online_clothing_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'fashion',
            'designStyle' => 'luxury_minimal',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'testimonials'],
        ],
        'electronics_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'electronics',
            'designStyle' => 'corporate_clean',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products'],
        ],
        'cosmetics_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'cosmetics',
            'designStyle' => 'soft_pastel',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'featured_products', 'testimonials', 'newsletter'],
        ],
        'pet_shop' => [
            'siteType' => 'ecommerce',
            'businessType' => 'pet',
            'designStyle' => 'bold_startup',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'promo_banner'],
        ],
        'kids_toys_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'kids',
            'designStyle' => 'soft_pastel',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'testimonials'],
        ],
        'furniture_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'furniture',
            'designStyle' => 'dark_modern',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'featured_products', 'best_sellers'],
        ],
        'grocery_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'grocery',
            'designStyle' => 'corporate_clean',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products'],
        ],
        'sports_equipment_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'sports',
            'designStyle' => 'bold_startup',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'promo_banner'],
        ],
        'digital_products_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'digital',
            'designStyle' => 'clean_minimal',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'featured_products'],
        ],
        'luxury_jewelry_store' => [
            'siteType' => 'ecommerce',
            'businessType' => 'jewelry',
            'designStyle' => 'luxury_brand',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'featured_products', 'testimonials'],
        ],
    ];

    public function handle(
        DesignDecisionService $designDecision,
        SiteProvisioningService $provisioning,
        EcommerceDemoSeederService $demoSeeder
    ): int {
        $scenarioNames = $this->option('scenarios') ?: array_keys(self::SCENARIOS);
        $repeat = (int) $this->option('repeat');
        $repeat = $repeat < 1 ? 1 : min($repeat, 20);
        $userId = $this->option('user');
        $user = $userId ? User::find($userId) : User::query()->first();
        if (! $user) {
            $this->error('No user found. Create a user or pass --user=ID');
            return self::FAILURE;
        }
        $provisionDemo = ! $this->option('no-demo');
        $dryRun = $this->option('dry-run');

        $outDir = base_path('ai-generation-tests');
        if (! $dryRun && ! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $results = [];
        $total = 0;
        foreach ($scenarioNames as $scenarioKey) {
            $config = self::SCENARIOS[$scenarioKey] ?? null;
            if (! $config) {
                $this->warn("Unknown scenario: {$scenarioKey}");
                continue;
            }
            for ($i = 0; $i < $repeat; $i++) {
                $total++;
                $name = $repeat > 1 ? "{$scenarioKey}_" . ($i + 1) : $scenarioKey;
                $this->info("Generating [{$total}] {$name}...");
                if ($dryRun) {
                    $results[] = ['scenario' => $scenarioKey, 'name' => $name, 'dry_run' => true];
                    continue;
                }
                try {
                    $project = Project::factory()->for($user)->create([
                        'name' => 'AI Test: ' . str_replace('_', ' ', $name),
                        'requirement_config' => $config,
                        'requirement_collection_state' => 'complete',
                    ]);
                    $blueprint = $designDecision->configToBlueprint($config);
                    $templateData = [
                        'name' => $blueprint['name'],
                        'theme_preset' => $blueprint['theme_preset'],
                        'default_pages' => $blueprint['default_pages'],
                    ];
                    $site = $provisioning->provisionFromReadyTemplate(
                        $project,
                        $templateData,
                        ['provision_demo_store' => $provisionDemo]
                    );
                    if ($provisionDemo && $site) {
                        $demoSeeder->run($site->fresh(), false);
                    }
                    $project->update(['theme_preset' => $blueprint['theme_preset']]);
                    $baseUrl = rtrim(config('app.url'), '/');
                    $storefrontBase = $baseUrl . '/app/' . $project->id;
                    $results[] = [
                        'scenario' => $scenarioKey,
                        'name' => $name,
                        'project_id' => $project->id,
                        'site_id' => $site->id,
                        'storefront_base' => $storefrontBase,
                        'pages' => ['home', 'shop', 'product', 'cart', 'checkout', 'contact'],
                        'theme_preset' => $blueprint['theme_preset'],
                    ];
                } catch (\Throwable $e) {
                    $this->error("  Failed: " . $e->getMessage());
                    $results[] = ['scenario' => $scenarioKey, 'name' => $name, 'error' => $e->getMessage()];
                }
            }
        }

        if (! $dryRun && $results !== []) {
            $manifestPath = $outDir . '/manifest.json';
            file_put_contents($manifestPath, json_encode(['generated_at' => now()->toIso8601String(), 'sites' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Manifest written to ' . $manifestPath);
        }
        $this->info('Done. Total: ' . count($results) . ' entries.');
        return self::SUCCESS;
    }
}
