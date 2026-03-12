<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramCrossPhaseWorkstreamControlsGv02SyncTest extends TestCase
{
    public function test_cross_phase_workstreams_have_current_evidence_and_drift_audit_doc(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/PROGRAM_CROSS_PHASE_WORKSTREAM_CONTROLS_GV_02_2026_02_25.md');

        foreach ([$roadmapPath, $docPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('## 4. Cross-Phase Workstreams (Must Exist Throughout)', $roadmap);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:722', $doc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:756', $doc);
        $this->assertStringContainsString('`GV-02`', $doc);

        foreach ([
            'Security / Isolation',
            'Contracts / Versioning',
            'Quality / Validation',
            'Performance',
            'Observability',
        ] as $workstream) {
            $this->assertStringContainsString($workstream, $doc);
        }

        $anchors = [
            // Security / isolation
            'Install/tests/Feature/Security/TenantProjectRouteScopingMiddlewareTest.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/tests/Unit/CmsAiGeneratedComponentSecurityValidationServiceTest.php',
            'Install/app/Services/TenantProjectRouteScopeValidatorService.php',
            'Install/app/Services/CmsAiGeneratedComponentSecurityValidationService.php',

            // Contracts / versioning
            'Install/tests/Feature/Cms/CmsBoundaryContractsTest.php',
            'Install/tests/Feature/Cms/BuilderPayloadContractTest.php',
            'Install/tests/Unit/BuilderCmsRuntimeScriptContractsTest.php',
            'Install/tests/Unit/CmsCanonicalSchemaContractsTest.php',
            'Install/tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php',
            'Install/docs/openapi/README.md',

            // Quality / validation
            'Install/tests/Unit/CmsAiSchemaValidationServiceTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputValidationEngineTest.php',
            'Install/tests/Feature/Templates/TemplatePreviewRenderSmokeTest.php',
            'Install/tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php',
            'Install/tests/Feature/Forms/FormsLeadsModuleApiTest.php',
            'Install/tests/Feature/Notifications/NotificationsTemplatesLogsModuleApiTest.php',
            'Install/docs/qa/CMS_POST_CLOSURE_FRONTEND_VITEST_BASELINE_2026_02_25.md',
            '.github/workflows/cms-template-smokes.yml',

            // Performance
            'Install/tests/Feature/Cms/CmsPublicAssetsCachingBaselineTest.php',
            'Install/docs/qa/CMS_ECOMMERCE_STOREFRONT_PERF_CACHE_BASELINES.md',
            'Install/app/Services/PublishedProjectCacheService.php',

            // Observability
            'Install/tests/Feature/Cms/CmsTelemetryCollectorEndpointsTest.php',
            'Install/tests/Feature/Cms/CmsTelemetryAggregateCommandTest.php',
            'Install/tests/Feature/Cms/CmsTelemetryPruneCommandTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php',
            'Install/app/Services/CmsTelemetryCollectorService.php',
            'Install/app/Services/CmsTelemetryAggregatedMetricsService.php',
            'Install/app/Console/Commands/AggregateCmsTelemetry.php',
            'Install/app/Console/Commands/PruneCmsTelemetry.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Doc missing evidence anchor: {$relativePath}");

            $absolutePath = str_starts_with($relativePath, '.github/')
                ? base_path('../'.$relativePath)
                : base_path('../'.$relativePath);

            $this->assertFileExists($absolutePath, "Evidence anchor path missing: {$relativePath}");
        }

        $this->assertStringContainsString('## CI / Runtime Drift Summary', $doc);
        $this->assertStringContainsString('Missing/partial controls still open', $doc);
        $this->assertStringContainsString('no dedicated CI workflow currently runs frontend builder UI regression (Vitest) baseline', $doc);
        $this->assertStringContainsString('no explicit automated performance budget thresholds', $doc);
        $this->assertStringContainsString('no end-to-end external tracing exporter/alerting contract lock', $doc);
        $this->assertStringContainsString('`GV-02` is complete as an audit task', $doc);
    }
}
