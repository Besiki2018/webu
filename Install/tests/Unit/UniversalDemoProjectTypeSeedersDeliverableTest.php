<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalDemoProjectTypeSeedersDeliverableTest extends TestCase
{
    public function test_demo_project_type_seeders_deliverable_exists_with_ecommerce_clinic_booking_and_portfolio_baseline(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $seederPath = base_path('database/seeders/UniversalDemoProjectTypesSeeder.php');
        $docPath = base_path('docs/qa/UNIVERSAL_DEMO_PROJECT_TYPE_SEEDERS_DELIVERABLE_BASELINE.md');
        $databaseSeederPath = base_path('database/seeders/DatabaseSeeder.php');

        foreach ([$roadmapPath, $seederPath, $docPath, $databaseSeederPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $seeder = File::get($seederPath);
        $doc = File::get($docPath);
        $databaseSeeder = File::get($databaseSeederPath);

        // Source spec deliverable references.
        $this->assertStringContainsString('# 17) Deliverables', $roadmap);
        $this->assertStringContainsString('2) Seeders for demo project types:', $roadmap);
        $this->assertStringContainsString('- ecommerce demo', $roadmap);
        $this->assertStringContainsString('- clinic booking demo', $roadmap);
        $this->assertStringContainsString('- portfolio demo', $roadmap);

        // Seeder existence + core behavior.
        $this->assertStringContainsString('class UniversalDemoProjectTypesSeeder extends Seeder', $seeder);
        $this->assertStringContainsString('SiteProvisioningService', $seeder);
        $this->assertStringContainsString("'project_type' => 'ecommerce'", $seeder);
        $this->assertStringContainsString("'project_type' => 'booking'", $seeder);
        $this->assertStringContainsString("'project_type' => 'portfolio'", $seeder);
        $this->assertStringContainsString("'subdomain' => 'demo-ecommerce'", $seeder);
        $this->assertStringContainsString("'subdomain' => 'demo-clinic-booking'", $seeder);
        $this->assertStringContainsString("'subdomain' => 'demo-portfolio'", $seeder);
        $this->assertStringContainsString("'template_slugs' => ['ecommerce']", $seeder);
        $this->assertStringContainsString("'template_slugs' => ['booking-starter', 'medical', 'vet', 'grooming']", $seeder);
        $this->assertStringContainsString("'template_slugs' => ['portfolio']", $seeder);
        $this->assertStringContainsString('$provisioner->provisionForProject', $seeder);
        $this->assertStringContainsString("themeSettings['project_type'] = \$definition['project_type'];", $seeder);

        // Explicit/manual usage doc lock.
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6416', $doc);
        $this->assertStringContainsString('UniversalDemoProjectTypesSeeder.php', $doc);
        $this->assertStringContainsString('demo-ecommerce', $doc);
        $this->assertStringContainsString('demo-clinic-booking', $doc);
        $this->assertStringContainsString('demo-portfolio', $doc);
        $this->assertStringContainsString('SiteProvisioningService', $doc);
        $this->assertStringContainsString('SiteDemoContentSeederService', $doc);
        $this->assertStringContainsString('php artisan db:seed --class=Database', $doc);
        $this->assertStringContainsString('UniversalDemoProjectTypesSeeder', $doc);

        // Baseline should be explicit/manual, not forced into DatabaseSeeder by default.
        $this->assertStringNotContainsString('UniversalDemoProjectTypesSeeder::class', $databaseSeeder);
    }
}
