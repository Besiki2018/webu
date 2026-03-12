<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CrossPhaseWorkstreamsSummaryStatusSyncTest extends TestCase
{
    public function test_cross_phase_workstream_summary_lines_are_synced_to_existing_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $paths = [
            base_path('routes/web.php'),
            base_path('docs/architecture/UNIVERSAL_TENANT_PROJECT_SCOPING_CONTRACT_P5_F1_03.md'),
            base_path('tests/Feature/Security/TenantProjectRouteScopingMiddlewareTest.php'),
            base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md'),
            base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php'),
            base_path('tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php'),
            base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php'),
            base_path('docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md'),
            base_path('tests/Unit/CmsAiGeneratedComponentSecurityValidationServiceTest.php'),
            base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md'),
            base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'),
            base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'),
            base_path('docs/architecture/CMS_TEMPLATE_IMPORT_BACKWARD_COMPATIBILITY_STRATEGY_BASELINE.md'),
            base_path('tests/Feature/Templates/TemplateImportContractServiceTest.php'),
            base_path('tests/Unit/CmsTemplateSmokesCiWorkflowContractTest.php'),
            base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts'),
            base_path('docs/qa/CMS_ECOMMERCE_STOREFRONT_PERF_CACHE_BASELINES.md'),
            base_path('docs/architecture/CMS_ASSET_IMAGE_OPTIMIZATION_STRATEGY_BASELINE.md'),
            base_path('docs/architecture/CMS_BUILDER_EDITOR_PERFORMANCE_BASELINE.md'),
            base_path('tests/Feature/Ecommerce/EcommercePublicApiCachingBaselineTest.php'),
            base_path('docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md'),
            base_path('docs/architecture/CMS_TELEMETRY_STORAGE_RETENTION_P6_G1_02.md'),
            base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md'),
            base_path('docs/architecture/CMS_OBSERVABILITY_METRICS_TRACES_P6_BASELINE.md'),
            base_path('tests/Feature/Cms/CmsTelemetryCollectorEndpointsTest.php'),
            base_path('tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php'),
            base_path('tests/Feature/Cms/CmsPublicAssetsCachingBaselineTest.php'),
            base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsTelemetryCollectors.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsBuilderEditorPerformanceBaseline.contract.test.ts'),
        ];

        foreach (array_merge([$roadmapPath], $paths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $routes = File::get(base_path('routes/web.php'));
        $scopingDoc = File::get(base_path('docs/architecture/UNIVERSAL_TENANT_PROJECT_SCOPING_CONTRACT_P5_F1_03.md'));
        $runtimeContractDoc = File::get(base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md'));
        $securityDoc = File::get(base_path('docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md'));
        $componentSchemaDoc = File::get(base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md'));
        $pageSchemaDoc = File::get(base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md'));
        $bindingContractDoc = File::get(base_path('docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md'));
        $publicApiVersioningDoc = File::get(base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'));
        $templateCompatDoc = File::get(base_path('docs/architecture/CMS_TEMPLATE_IMPORT_BACKWARD_COMPATIBILITY_STRATEGY_BASELINE.md'));
        $perfDoc = File::get(base_path('docs/qa/CMS_ECOMMERCE_STOREFRONT_PERF_CACHE_BASELINES.md'));
        $assetImageStrategyDoc = File::get(base_path('docs/architecture/CMS_ASSET_IMAGE_OPTIMIZATION_STRATEGY_BASELINE.md'));
        $builderEditorPerfDoc = File::get(base_path('docs/architecture/CMS_BUILDER_EDITOR_PERFORMANCE_BASELINE.md'));
        $telemetryCollectorDoc = File::get(base_path('docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md'));
        $telemetryStorageDoc = File::get(base_path('docs/architecture/CMS_TELEMETRY_STORAGE_RETENTION_P6_G1_02.md'));
        $telemetryAggDoc = File::get(base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md'));
        $observabilityDoc = File::get(base_path('docs/architecture/CMS_OBSERVABILITY_METRICS_TRACES_P6_BASELINE.md'));

        // Security / isolation
        $this->assertStringContainsString('- ✅ Tenant/project scoping enforcement in all queries and APIs', $roadmap);
        $this->assertStringContainsString('- ✅ Preview/publish access rules', $roadmap);
        $this->assertStringContainsString('- ✅ Auth/session boundaries (tenant admin vs public customer)', $roadmap);
        $this->assertStringContainsString('- ✅ Generated content/component sanitization (HTML/scripts)', $roadmap);

        $this->assertStringContainsString('tenant.route.scope', $scopingDoc);
        $this->assertStringContainsString("Route::middleware(['auth', 'verified', 'tenant.route.scope'])->prefix('panel/sites/{site}')", $routes);
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/checkout'", $routes);
        $this->assertStringContainsString('preview/published parity', strtolower($runtimeContractDoc));
        $this->assertStringContainsString('security constraints', strtolower($securityDoc));
        $this->assertStringContainsString('CmsAiGeneratedComponentSecurityValidationService', $securityDoc);

        // Contracts / versioning
        $this->assertStringContainsString('- ✅ Version component schema and props schema', $roadmap);
        $this->assertStringContainsString('- ✅ Version public API contracts', $roadmap);
        $this->assertStringContainsString('- ✅ Version template/runtime binding contract', $roadmap);
        $this->assertStringContainsString('- ✅ Define backward compatibility strategy for imported themes/templates', $roadmap);

        $this->assertStringContainsString('v1', strtolower($componentSchemaDoc));
        $this->assertStringContainsString('v1', strtolower($pageSchemaDoc));
        $this->assertStringContainsString('versioned', strtolower($bindingContractDoc));
        $this->assertStringContainsString('public api contract versioning baseline', strtolower($publicApiVersioningDoc));
        $this->assertStringContainsString('adapter-backed', strtolower($publicApiVersioningDoc));
        $this->assertStringContainsString('public.sites.ecommerce', $publicApiVersioningDoc);
        $this->assertStringContainsString('backward compatibility', strtolower($templateCompatDoc));
        $this->assertStringContainsString('warning-first', strtolower($templateCompatDoc));
        $this->assertStringContainsString('resolveVersion', $templateCompatDoc);

        // Quality / validation
        $this->assertStringContainsString('- ✅ Schema validation (components/pages/theme settings)', $roadmap);
        $this->assertStringContainsString('- ✅ Template import validation', $roadmap);
        $this->assertStringContainsString('- ✅ Runtime render smoke tests', $roadmap);
        $this->assertStringContainsString('- ✅ API integration tests', $roadmap);
        $this->assertStringContainsString('- ✅ Builder UI regression tests', $roadmap);

        $this->assertStringContainsString('TemplateImportContractServiceTest', $templateCompatDoc);
        $this->assertStringContainsString('TemplatePublishedRenderSmokeTest', $perfDoc);
        $this->assertStringContainsString('EcommercePublicApiCachingBaselineTest', $perfDoc);

        // Performance (implemented baselines only)
        $this->assertStringContainsString('- ✅ Runtime rendering performance (public pages)', $roadmap);
        $this->assertStringContainsString('- ✅ E-commerce API response optimization', $roadmap);
        $this->assertStringContainsString('- ✅ Asset/image optimization strategy', $roadmap);
        $this->assertStringContainsString('- ✅ Builder editor performance (large pages, many nodes)', $roadmap);
        $this->assertStringContainsString('Cache-Control: public, max-age=3600', $perfDoc);
        $this->assertStringContainsString('Cache-Control: public, max-age=60', $perfDoc);
        $this->assertStringContainsString('no-store', strtolower($perfDoc));
        $this->assertStringContainsString('asset / image optimization strategy baseline', strtolower($assetImageStrategyDoc));
        $this->assertStringContainsString('max-age=31536000', $assetImageStrategyDoc);
        $this->assertStringContainsString('assetprefix', strtolower($assetImageStrategyDoc));
        $this->assertStringContainsString('loading="lazy"', strtolower($assetImageStrategyDoc));
        $this->assertStringContainsString('cms_builder.page_detail_loaded', $builderEditorPerfDoc);
        $this->assertStringContainsString('page_detail_load_latency_p95_ms', $builderEditorPerfDoc);
        $this->assertStringContainsString('large', strtolower($builderEditorPerfDoc));
        $this->assertStringContainsString('json_node_count >= 5000', $builderEditorPerfDoc);

        // Observability (structured logs baseline)
        $this->assertStringContainsString('- ✅ Structured logs (builder/runtime/api)', $roadmap);
        $this->assertStringContainsString('- ✅ Metrics (render failures, publish errors, API latency)', $roadmap);
        $this->assertStringContainsString('- ✅ Trace critical flows (checkout, publish, AI generation)', $roadmap);
        $this->assertStringContainsString('cms builder ui', strtolower($telemetryCollectorDoc));
        $this->assertStringContainsString('published runtime cms bootstrap script', strtolower($telemetryCollectorDoc));
        $this->assertStringContainsString('logs normalized event batches via app logger', strtolower($telemetryCollectorDoc));
        $this->assertStringContainsString('anonymization', strtolower($telemetryStorageDoc));
        $this->assertStringContainsString('aggregated metrics', strtolower($telemetryAggDoc));
        $this->assertStringContainsString('api latency', strtolower($observabilityDoc));
        $this->assertStringContainsString('publish errors', strtolower($observabilityDoc));
        $this->assertStringContainsString('checkout', strtolower($observabilityDoc));
        $this->assertStringContainsString('ai generation', strtolower($observabilityDoc));
    }
}
