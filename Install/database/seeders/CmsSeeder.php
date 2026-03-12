<?php

namespace Database\Seeders;

use App\Cms\Services\SectionLibraryPresetService;
use App\Models\Project;
use App\Services\SiteProvisioningService;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSectionLibrary();
        $this->seedProjectSites();
    }

    private function seedSectionLibrary(): void
    {
        app(SectionLibraryPresetService::class)->syncDefaults();
    }

    private function seedProjectSites(): void
    {
        $provisioner = app(SiteProvisioningService::class);

        Project::query()
            ->withTrashed()
            ->orderBy('created_at')
            ->chunk(100, function ($projects) use ($provisioner): void {
                foreach ($projects as $project) {
                    $provisioner->provisionForProject($project);
                }
            });
    }
}
