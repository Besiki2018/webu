<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderPreviewPublishCachingObservabilityApi04SyncTest extends TestCase
{
    public function test_api_04_audit_doc_locks_preview_publish_caching_observability_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PREVIEW_PUBLISH_CACHING_OBSERVABILITY_AUDIT_API_04_2026_02_25.md');

        $bootstrapAppPath = base_path('bootstrap/app.php');
        $webRoutesPath = base_path('routes/web.php');
        $previewControllerPath = base_path('app/Http/Controllers/PreviewController.php');
        $appPreviewControllerPath = base_path('app/Http/Controllers/AppPreviewController.php');
        $publishedControllerPath = base_path('app/Http/Controllers/PublishedProjectController.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $subdomainMiddlewarePath = base_path('app/Http/Middleware/IdentifyProjectBySubdomain.php');
        $customDomainMiddlewarePath = base_path('app/Http/Middleware/IdentifyProjectByCustomDomain.php');
        $observabilityMiddlewarePath = base_path('app/Http/Middleware/CapturePublicApiObservabilityTelemetry.php');
        $runtimePayloadServicePath = base_path('app/Services/CmsRuntimePayloadService.php');
        $publishedCacheServicePath = base_path('app/Services/PublishedProjectCacheService.php');
        $projectPublishControllerPath = base_path('app/Http/Controllers/ProjectPublishController.php');
        $projectCustomDomainControllerPath = base_path('app/Http/Controllers/ProjectCustomDomainController.php');
        $cmsPanelPageServicePath = base_path('app/Cms/Services/CmsPanelPageService.php');
        $cmsPublicSiteServicePath = base_path('app/Cms/Services/CmsPublicSiteService.php');
        $cmsFrontendContractPath = base_path('docs/cms-frontend-contract.md');
        $openApiPublicCorePath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');
        $openApiEcommercePath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $bootstrapAppPath,
            $webRoutesPath,
            $previewControllerPath,
            $appPreviewControllerPath,
            $publishedControllerPath,
            $publicSiteControllerPath,
            $publicStorefrontControllerPath,
            $subdomainMiddlewarePath,
            $customDomainMiddlewarePath,
            $observabilityMiddlewarePath,
            $runtimePayloadServicePath,
            $publishedCacheServicePath,
            $projectPublishControllerPath,
            $projectCustomDomainControllerPath,
            $cmsPanelPageServicePath,
            $cmsPublicSiteServicePath,
            $cmsFrontendContractPath,
            $openApiPublicCorePath,
            $openApiEcommercePath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $bootstrapApp = File::get($bootstrapAppPath);
        $webRoutes = File::get($webRoutesPath);
        $previewController = File::get($previewControllerPath);
        $appPreviewController = File::get($appPreviewControllerPath);
        $publishedController = File::get($publishedControllerPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $publicStorefrontController = File::get($publicStorefrontControllerPath);
        $subdomainMiddleware = File::get($subdomainMiddlewarePath);
        $customDomainMiddleware = File::get($customDomainMiddlewarePath);
        $observabilityMiddleware = File::get($observabilityMiddlewarePath);
        $runtimePayloadService = File::get($runtimePayloadServicePath);
        $publishedCacheService = File::get($publishedCacheServicePath);
        $projectPublishController = File::get($projectPublishControllerPath);
        $projectCustomDomainController = File::get($projectCustomDomainControllerPath);
        $cmsPanelPageService = File::get($cmsPanelPageServicePath);
        $cmsPublicSiteService = File::get($cmsPublicSiteServicePath);
        $cmsFrontendContract = File::get($cmsFrontendContractPath);
        $openApiPublicCore = File::get($openApiPublicCorePath);
        $openApiEcommerce = File::get($openApiEcommercePath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Backend → Builder Integration Contract (Exact API Spec v1)', $roadmap);
        $this->assertStringContainsString('11) Builder Preview vs Publish', $roadmap);
        $this->assertStringContainsString('12) Caching & Invalidation', $roadmap);
        $this->assertStringContainsString('13) Observability', $roadmap);
        $this->assertStringContainsString('14) Acceptance Criteria', $roadmap);

        $this->assertStringContainsString('- `API-04` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_PREVIEW_PUBLISH_CACHING_OBSERVABILITY_AUDIT_API_04_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderPreviewPublishCachingObservabilityApi04SyncTest.php', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:2629',
            'PROJECT_ROADMAP_TASKS_KA.md:2669',
            '## Preview vs Publish Behavior Diff Audit (Spec `11.x`)',
            '## Caching & Invalidation Checklist (Spec `12`)',
            '## Observability / Traceability Audit (Spec `13`)',
            '## API Acceptance Criteria Audit (Spec `14`)',
            'X-Store-Id',
            'X-Preview',
            'meta.request_id',
            'trace_id',
            'PublishedProjectCacheService::flushProject()',
            'PublicSiteController::rememberPublicRead()',
            '`API-04` is complete as an audit task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $anchors = [
            'Install/bootstrap/app.php',
            'Install/routes/web.php',
            'Install/app/Http/Middleware/IdentifyProjectBySubdomain.php',
            'Install/app/Http/Middleware/IdentifyProjectByCustomDomain.php',
            'Install/app/Http/Middleware/CapturePublicApiObservabilityTelemetry.php',
            'Install/app/Http/Controllers/PreviewController.php',
            'Install/app/Http/Controllers/AppPreviewController.php',
            'Install/app/Http/Controllers/PublishedProjectController.php',
            'Install/app/Http/Controllers/Cms/PublicSiteController.php',
            'Install/app/Http/Controllers/Ecommerce/PublicStorefrontController.php',
            'Install/app/Http/Controllers/ProjectPublishController.php',
            'Install/app/Http/Controllers/ProjectCustomDomainController.php',
            'Install/app/Services/CmsRuntimePayloadService.php',
            'Install/app/Services/PublishedProjectCacheService.php',
            'Install/app/Cms/Services/CmsPublicSiteService.php',
            'Install/app/Cms/Services/CmsPanelPageService.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/tests/Feature/Cms/TenantIsolationTest.php',
            'Install/tests/Feature/Cms/CmsPublicAssetsCachingBaselineTest.php',
            'Install/tests/Feature/Cms/AssetFirstComposeFlowTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiCachingBaselineTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php',
            'Install/tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing API-04 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing API-04 evidence file on disk: {$relativePath}");
        }

        // Route-level preview/app/published and observability truths.
        $this->assertStringContainsString("Route::middleware('public.api.observability')->group", $webRoutes);
        $this->assertStringContainsString("Route::get('/preview/{project}/{path?}', [PreviewController::class, 'serve'])", $webRoutes);
        $this->assertStringContainsString("Route::get('/app/{project}/{path?}', [AppPreviewController::class, 'serve'])", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/pages/{slug}'", $webRoutes);
        $this->assertStringContainsString("Route::post('/pages/{page}/publish'", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/ecommerce/products'", $webRoutes);

        // Observability middleware alias and behavior.
        $this->assertStringContainsString("'public.api.observability' => \\App\\Http\\Middleware\\CapturePublicApiObservabilityTelemetry::class", $bootstrapApp);
        $this->assertStringContainsString("Log::info('cms.api.request_completed'", $observabilityMiddleware);
        $this->assertStringContainsString("'trace_id' => \$traceId", $observabilityMiddleware);
        $this->assertStringContainsString("'site_id' => (string) \$routeSite->id", $observabilityMiddleware);
        $this->assertStringContainsString("'project_id' => (string) \$routeSite->project_id", $observabilityMiddleware);
        $this->assertStringContainsString("headers->get('X-Webu-Trace-Id')", $observabilityMiddleware);
        $this->assertStringNotContainsString('request_id', $observabilityMiddleware);
        $this->assertStringNotContainsString('store_id', $observabilityMiddleware);

        // Preview/publish controllers use no-store for bridge and no explicit X-Store-Id/X-Preview headers.
        foreach ([$previewController, $appPreviewController, $publishedController] as $controllerSource) {
            $this->assertStringContainsString("__cms/bootstrap", $controllerSource);
            $this->assertStringContainsString("->header('Cache-Control', 'no-cache, no-store, must-revalidate')", $controllerSource);
            $this->assertStringNotContainsString('X-Store-Id', $controllerSource);
            $this->assertStringNotContainsString('X-Preview', $controllerSource);
        }

        // Published controller serves cached transformed output with public cache.
        $this->assertStringContainsString("return response()->file(\$cachedPath", $publishedController);
        $this->assertStringContainsString("'Cache-Control' => 'public, max-age=3600'", $publishedController);
        $this->assertStringContainsString('published/{$projectId}', $publishedCacheService);
        $this->assertStringContainsString('deleteDirectory($cachePath)', $publishedCacheService);
        $this->assertStringContainsString('$this->publishedCache->flushProject((string) $project->id);', $projectPublishController);
        $this->assertStringContainsString('$this->publishedCache->flushProject((string) $project->id);', $projectCustomDomainController);

        // Public CMS JSON read caching exists, but page-publish path lacks explicit flush wiring.
        $this->assertStringContainsString('private const PUBLIC_READ_CACHE_TTL_SECONDS = 60;', $publicSiteController);
        $this->assertStringContainsString("if (app()->environment('testing'))", $publicSiteController);
        $this->assertStringContainsString('return Cache::remember($key, self::PUBLIC_READ_CACHE_TTL_SECONDS, $resolver);', $publicSiteController);
        $this->assertStringContainsString('$siteVersion = $scope->updated_at?->timestamp ?? 0;', $publicSiteController);
        $this->assertStringContainsString('$projectVersion = $scope->project?->updated_at?->timestamp ?? 0;', $publicSiteController);
        $this->assertStringNotContainsString('Cache::forget', $publicSiteController);
        $this->assertStringContainsString('public function publish(Site $site, Page $page, ?int $revisionId = null): PageRevision', $cmsPanelPageService);
        $this->assertStringNotContainsString('flushProject(', $cmsPanelPageService);
        $this->assertStringNotContainsString('Cache::forget', $cmsPanelPageService);

        // Public ecommerce caching behavior is route-based: catalog short TTL, stateful no-store.
        $this->assertStringContainsString('private function cacheControlForCurrentRequest(): string', $publicStorefrontController);
        $this->assertStringContainsString("'public.sites.ecommerce.products.index'", $publicStorefrontController);
        $this->assertStringContainsString("'public.sites.ecommerce.products.show'", $publicStorefrontController);
        $this->assertStringContainsString("return 'public, max-age=60';", $publicStorefrontController);
        $this->assertStringContainsString("return 'no-store';", $publicStorefrontController);

        // Runtime bridge payload exposes site identity and endpoints (payload-based mode/context, not preview headers).
        $this->assertStringContainsString("'site_id' => \$site->id", $runtimePayloadService);
        $this->assertStringContainsString("'source' => 'cms-runtime-bridge'", $runtimePayloadService);
        $this->assertStringContainsString("'resolve' => route('public.sites.resolve')", $runtimePayloadService);
        $this->assertStringContainsString("'ecommerce_products' => route('public.sites.ecommerce.products.index'", $runtimePayloadService);

        // Public CMS service documents draft preview + published visibility behavior.
        $this->assertStringContainsString("'draft_preview' => \$draftPreviewAllowedForViewer", $cmsPublicSiteService);
        $this->assertStringContainsString("if (! \$draftPreviewAllowedForViewer && \$page->status !== 'published')", $cmsPublicSiteService);
        $this->assertStringContainsString("if (\$project->published_visibility !== 'private')", $cmsPublicSiteService);

        // Docs and OpenAPI baselines expose bridge/public API fetch model, not the spec preview headers.
        $this->assertStringContainsString('/preview/{project}/__cms/bootstrap', $cmsFrontendContract);
        $this->assertStringContainsString('/app/{project}/__cms/bootstrap', $cmsFrontendContract);
        $this->assertStringContainsString('/public/sites/{site_id}/pages/{slug}', $cmsFrontendContract);
        $this->assertStringContainsString('/public/sites/{site}/pages/{slug}:', $openApiPublicCore);
        $this->assertStringContainsString('/public/sites/{site}/ecommerce/products:', $openApiEcommerce);
        $this->assertStringNotContainsString('X-Store-Id', $cmsFrontendContract);
        $this->assertStringNotContainsString('X-Preview', $cmsFrontendContract);
    }
}
