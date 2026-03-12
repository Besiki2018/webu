<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalTelemetryCollectorP6G1Test extends TestCase
{
    public function test_p6_g1_01_builder_and_runtime_telemetry_collectors_are_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md');
        $schemaPath = base_path('docs/architecture/schemas/cms-telemetry-event.v1.schema.json');
        $servicePath = base_path('app/Services/CmsTelemetryCollectorService.php');
        $panelControllerPath = base_path('app/Http/Controllers/Cms/PanelTelemetryController.php');
        $publicControllerPath = base_path('app/Http/Controllers/Cms/PublicTelemetryController.php');
        $routesPath = base_path('routes/web.php');
        $bootstrapPath = base_path('bootstrap/app.php');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $schemaPath, $servicePath, $panelControllerPath, $publicControllerPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $schema = File::get($schemaPath);
        $service = File::get($servicePath);
        $panelController = File::get($panelControllerPath);
        $publicController = File::get($publicControllerPath);
        $routes = File::get($routesPath);
        $bootstrap = File::get($bootstrapPath);
        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G1-01', $doc);
        $this->assertStringContainsString('cms.telemetry.event.v1', $doc);
        $this->assertStringContainsString('CmsTelemetryCollectorService', $doc);
        $this->assertStringContainsString('cms_builder.save_draft', $doc);
        $this->assertStringContainsString('cms_runtime.route_hydrated', $doc);
        $this->assertStringContainsString('P6-G1-02', $doc);

        $this->assertJson($schema);
        $this->assertStringContainsString('"const": "cms.telemetry.event.v1"', $schema);
        $this->assertStringContainsString('"enum":["builder","runtime"]', str_replace(["\n", ' '], '', $schema));

        $this->assertStringContainsString('class CmsTelemetryCollectorService', $service);
        $this->assertStringContainsString('public const SCHEMA_VERSION = \'cms.telemetry.event.v1\';', $service);
        $this->assertStringContainsString('function collectFromRequest', $service);
        $this->assertStringContainsString("Log::info('cms.telemetry.collector'", $service);

        $this->assertStringContainsString('class PanelTelemetryController', $panelController);
        $this->assertStringContainsString('panel', $panelController);
        $this->assertStringContainsString('class PublicTelemetryController', $publicController);
        $this->assertStringContainsString('function options', $publicController);
        $this->assertStringContainsString('Access-Control-Allow-Methods', $publicController);

        $this->assertStringContainsString('public.sites.cms.telemetry.store', $routes);
        $this->assertStringContainsString('public.sites.cms.telemetry.options', $routes);
        $this->assertStringContainsString('panel.sites.cms.telemetry.store', $routes);
        $this->assertStringContainsString('public/sites/*/cms/telemetry', $bootstrap);

        $this->assertStringContainsString('/panel/sites/${site.id}/cms/telemetry', $cms);
        $this->assertStringContainsString("cms_builder.open", $cms);
        $this->assertStringContainsString("cms_builder.save_draft", $cms);
        $this->assertStringContainsString("cms_builder.publish_page", $cms);

        $this->assertStringContainsString('\'telemetry_url\' => $site ? $apiBaseUrl."/public/sites/{$site->id}/cms/telemetry" : null', $builderService);
        $this->assertStringContainsString('function postRuntimeTelemetry(eventName, routeInfo, meta)', $builderService);
        $this->assertStringContainsString('cms_runtime.route_hydrated', $builderService);
        $this->assertStringContainsString('cms_runtime.hydrate_failed', $builderService);

        $this->assertStringContainsString('- ✅ Telemetry collector (builder + runtime)', $roadmap);
        $this->assertStringContainsString("`P6-G1-01` (✅ `DONE`) Builder + runtime telemetry event schema and collectors.", $roadmap);
    }
}
