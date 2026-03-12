<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderAiSelfLearningTelemetryStoragePrivacySle01SyncTest extends TestCase
{
    public function test_sle_01_audit_doc_locks_telemetry_storage_and_privacy_foundations_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md');

        $collectorDocPath = base_path('docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md');
        $storageDocPath = base_path('docs/architecture/CMS_TELEMETRY_STORAGE_RETENTION_P6_G1_02.md');
        $aggregateDocPath = base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md');
        $builderDeltaDocPath = base_path('docs/architecture/CMS_BUILDER_DELTA_CAPTURE_P6_G1_04.md');
        $telemetrySchemaPath = base_path('docs/architecture/schemas/cms-telemetry-event.v1.schema.json');

        $collectorServicePath = base_path('app/Services/CmsTelemetryCollectorService.php');
        $storageServicePath = base_path('app/Services/CmsTelemetryEventStorageService.php');
        $aggregateServicePath = base_path('app/Services/CmsTelemetryAggregatedMetricsService.php');
        $builderDeltaServicePath = base_path('app/Services/CmsBuilderDeltaCaptureService.php');
        $apiTelemetryMiddlewarePath = base_path('app/Http/Middleware/CapturePublicApiObservabilityTelemetry.php');
        $aggregateCommandPath = base_path('app/Console/Commands/AggregateCmsTelemetry.php');
        $pruneCommandPath = base_path('app/Console/Commands/PruneCmsTelemetry.php');

        $eventsMigrationPath = base_path('database/migrations/2026_02_24_231000_create_cms_telemetry_events_table.php');
        $aggregatesMigrationPath = base_path('database/migrations/2026_02_24_232000_create_cms_telemetry_daily_aggregates_table.php');
        $builderDeltasMigrationPath = base_path('database/migrations/2026_02_24_233000_create_cms_builder_deltas_table.php');
        $eventModelPath = base_path('app/Models/CmsTelemetryEvent.php');
        $aggregateModelPath = base_path('app/Models/CmsTelemetryDailyAggregate.php');
        $builderDeltaModelPath = base_path('app/Models/CmsBuilderDelta.php');

        $collectorUnitTestPath = base_path('tests/Unit/CmsTelemetryCollectorServiceTest.php');
        $collectorEndpointsFeatureTestPath = base_path('tests/Feature/Cms/CmsTelemetryCollectorEndpointsTest.php');
        $storageUnitTestPath = base_path('tests/Unit/CmsTelemetryEventStorageServiceTest.php');
        $pruneFeatureTestPath = base_path('tests/Feature/Cms/CmsTelemetryPruneCommandTest.php');
        $aggregateFeatureTestPath = base_path('tests/Feature/Cms/CmsTelemetryAggregateCommandTest.php');
        $builderDeltaUnitTestPath = base_path('tests/Unit/CmsBuilderDeltaCaptureServiceTest.php');
        $builderDeltaFeatureTestPath = base_path('tests/Feature/Cms/CmsBuilderDeltaCapturePipelineTest.php');
        $apiTelemetryFeatureTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php');
        $collectorLockTestPath = base_path('tests/Unit/UniversalTelemetryCollectorP6G1Test.php');
        $storageLockTestPath = base_path('tests/Unit/UniversalTelemetryStorageRetentionP6G1Test.php');
        $aggregateLockTestPath = base_path('tests/Unit/UniversalTelemetryAggregatedMetricsP6G1Test.php');
        $builderDeltaLockTestPath = base_path('tests/Unit/UniversalBuilderDeltaCaptureP6G1Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $collectorDocPath,
            $storageDocPath,
            $aggregateDocPath,
            $builderDeltaDocPath,
            $telemetrySchemaPath,
            $collectorServicePath,
            $storageServicePath,
            $aggregateServicePath,
            $builderDeltaServicePath,
            $apiTelemetryMiddlewarePath,
            $aggregateCommandPath,
            $pruneCommandPath,
            $eventsMigrationPath,
            $aggregatesMigrationPath,
            $builderDeltasMigrationPath,
            $eventModelPath,
            $aggregateModelPath,
            $builderDeltaModelPath,
            $collectorUnitTestPath,
            $collectorEndpointsFeatureTestPath,
            $storageUnitTestPath,
            $pruneFeatureTestPath,
            $aggregateFeatureTestPath,
            $builderDeltaUnitTestPath,
            $builderDeltaFeatureTestPath,
            $apiTelemetryFeatureTestPath,
            $collectorLockTestPath,
            $storageLockTestPath,
            $aggregateLockTestPath,
            $builderDeltaLockTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $telemetrySchema = File::get($telemetrySchemaPath);
        $collectorService = File::get($collectorServicePath);
        $storageService = File::get($storageServicePath);
        $aggregateService = File::get($aggregateServicePath);
        $builderDeltaService = File::get($builderDeltaServicePath);
        $apiTelemetryMiddleware = File::get($apiTelemetryMiddlewarePath);
        $eventsMigration = File::get($eventsMigrationPath);
        $aggregatesMigration = File::get($aggregatesMigrationPath);
        $builderDeltasMigration = File::get($builderDeltasMigrationPath);
        $collectorUnitTest = File::get($collectorUnitTestPath);
        $collectorEndpointsFeatureTest = File::get($collectorEndpointsFeatureTestPath);
        $storageUnitTest = File::get($storageUnitTestPath);
        $aggregateFeatureTest = File::get($aggregateFeatureTestPath);
        $pruneFeatureTest = File::get($pruneFeatureTestPath);
        $builderDeltaUnitTest = File::get($builderDeltaUnitTestPath);
        $apiTelemetryFeatureTest = File::get($apiTelemetryFeatureTestPath);

        // Source-spec anchors for SLE-01 slice.
        foreach ([
            'CODEX PROMPT — Webu AI Self-Learning Engine (Feedback → Better Generations)',
            '0) Principles (Non-negotiable)',
            'No cross-tenant data leakage',
            'Never store raw customer PII in learning logs',
            'Improvements must be applied deterministically',
            'Allow per-tenant opt-out',
            '1.1 Telemetry Collector',
            'Builder events (editor)',
            'Runtime events (public site)',
            'Event payload must include:',
            'tenant_id',
            'store_id',
            'AB test variant id (optional)',
            'event_logs (append-only)',
            'aggregated_metrics (daily rollups)',
            '2.3 builder_deltas (learn from edits)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + icon-marked notes/evidence.
        $this->assertStringContainsString('- `SLE-01` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderAiSelfLearningTelemetryStoragePrivacySle01SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` telemetry/event schema coverage matrix audited', $backlog);
        $this->assertStringContainsString('`✅` privacy-safe instrumentation audit documented', $backlog);
        $this->assertStringContainsString('`🧪` targeted evidence batch passed', $backlog);

        // Audit doc structure + truthful findings + icons.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:4799',
            'PROJECT_ROADMAP_TASKS_KA.md:4910',
            '## ✅ What Was Done (Icon Summary)',
            '✅ Mapped source `SLE-01` telemetry/data-storage requirements',
            '🧪 Added sync/lock test + ran targeted evidence test batch.',
            '## Executive Result (`SLE-01`)',
            '`SLE-01` is **complete as an audit/verification task**',
            '`event_logs` → `cms_telemetry_events`',
            '`aggregated_metrics` → `cms_telemetry_daily_aggregates`',
            '`builder_deltas` → `cms_builder_deltas`',
            'semantic event names',
            'tenant opt-out',
            '## Principles Audit (`0`)',
            'No raw customer PII in learning logs',
            'partial (later layer)',
            '## Event Family Coverage Matrix (`1.1` Telemetry Collector)',
            'cms_builder.open',
            'cms_runtime.route_hydrated',
            'cms_api.request_completed',
            '## Event Payload / Envelope Audit (Source vs Current)',
            'component_node_id',
            'ab_test_variant_id',
            '## Privacy-Safe Instrumentation Audit',
            '✅ Implemented (Verified)',
            '⚠️ Partial / Gap Notes',
            '## DoD Verdict (`SLE-01`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/docs/architecture/CMS_TELEMETRY_COLLECTOR_P6_G1_01.md',
            'Install/docs/architecture/CMS_TELEMETRY_STORAGE_RETENTION_P6_G1_02.md',
            'Install/docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md',
            'Install/docs/architecture/CMS_BUILDER_DELTA_CAPTURE_P6_G1_04.md',
            'Install/docs/architecture/schemas/cms-telemetry-event.v1.schema.json',
            'Install/app/Services/CmsTelemetryCollectorService.php',
            'Install/app/Services/CmsTelemetryEventStorageService.php',
            'Install/app/Services/CmsTelemetryAggregatedMetricsService.php',
            'Install/app/Services/CmsBuilderDeltaCaptureService.php',
            'Install/app/Http/Middleware/CapturePublicApiObservabilityTelemetry.php',
            'Install/database/migrations/2026_02_24_231000_create_cms_telemetry_events_table.php',
            'Install/database/migrations/2026_02_24_232000_create_cms_telemetry_daily_aggregates_table.php',
            'Install/database/migrations/2026_02_24_233000_create_cms_builder_deltas_table.php',
            'Install/tests/Unit/CmsTelemetryCollectorServiceTest.php',
            'Install/tests/Feature/Cms/CmsTelemetryCollectorEndpointsTest.php',
            'Install/tests/Unit/CmsTelemetryEventStorageServiceTest.php',
            'Install/tests/Feature/Cms/CmsTelemetryPruneCommandTest.php',
            'Install/tests/Feature/Cms/CmsTelemetryAggregateCommandTest.php',
            'Install/tests/Unit/CmsBuilderDeltaCaptureServiceTest.php',
            'Install/tests/Feature/Cms/CmsBuilderDeltaCapturePipelineTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePublicApiObservabilityTelemetryTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing SLE-01 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing SLE-01 evidence file on disk: {$relativePath}");
        }

        // Telemetry schema and envelope truths.
        $this->assertStringContainsString('"const": "cms.telemetry.event.v1"', $telemetrySchema);
        $this->assertStringContainsString('"enum":["builder","runtime"]', str_replace(["\n", ' '], '', $telemetrySchema));
        $this->assertStringContainsString('"session_id"', $telemetrySchema);
        $this->assertStringContainsString('"route"', $telemetrySchema);
        $this->assertStringContainsString('"context"', $telemetrySchema);
        $this->assertStringContainsString('"events"', $telemetrySchema);
        $this->assertStringNotContainsString('"tenant_id"', $telemetrySchema);
        $this->assertStringNotContainsString('"store_id"', $telemetrySchema);
        $this->assertStringNotContainsString('ab_test_variant', $telemetrySchema);

        // Service/storage/aggregation truths.
        $this->assertStringContainsString('public const SCHEMA_VERSION = \'cms.telemetry.event.v1\';', $collectorService);
        $this->assertStringContainsString('MAX_EVENTS_PER_REQUEST = 25', $collectorService);
        $this->assertStringContainsString('storage_table_missing', $collectorService);
        $this->assertStringContainsString('stored', $collectorService);
        $this->assertStringContainsString('retention_days', $collectorService);
        $this->assertStringContainsString('privacy', $collectorService);

        $this->assertStringContainsString('hash_hmac(\'sha256\'', $storageService);
        $this->assertStringContainsString("'[redacted]'", $storageService);
        $this->assertStringContainsString('data_retention_days_cms_telemetry', $storageService);
        $this->assertStringContainsString('privacySummary()', $storageService);

        $this->assertStringContainsString('aggregateDate(string|Carbon|null $date = null)', $aggregateService);
        $this->assertStringContainsString('cms_runtime.route_hydrated', $aggregateService);
        $this->assertStringContainsString('cms_builder.save_draft', $aggregateService);
        $this->assertStringContainsString('derived_rates', $aggregateService);

        $this->assertStringContainsString('captureAfterManualRevisionSave(', $builderDeltaService);
        $this->assertStringContainsString('patch_ops', $builderDeltaService);
        $this->assertStringContainsString('generation_id', $builderDeltaService);
        $this->assertStringContainsString('JSON Patch', File::get($builderDeltaDocPath));

        // Migrations map source storage concepts to current tables.
        $this->assertStringContainsString("Schema::create('cms_telemetry_events'", $eventsMigration);
        $this->assertStringContainsString("Schema::create('cms_telemetry_daily_aggregates'", $aggregatesMigration);
        $this->assertStringContainsString("Schema::create('cms_builder_deltas'", $builderDeltasMigration);

        // Tests prove privacy-safe instrumentation and foundational event families.
        $this->assertStringContainsString('cms_runtime.route_hydrated', $collectorUnitTest);
        $this->assertStringContainsString('cms_runtime.hydrate_failed', $collectorUnitTest);
        $this->assertStringContainsString('cms_builder.save_draft', $collectorEndpointsFeatureTest);
        $this->assertStringContainsString('public.sites.cms.telemetry.store', $collectorEndpointsFeatureTest);
        $this->assertStringContainsString('client_ip_hash', $collectorEndpointsFeatureTest);
        $this->assertStringContainsString('email', $storageUnitTest);
        $this->assertStringContainsString('[redacted]', $storageUnitTest);
        $this->assertStringContainsString('cms:telemetry-prune', $pruneFeatureTest);
        $this->assertStringContainsString('cms:telemetry-aggregate', $aggregateFeatureTest);
        $this->assertStringContainsString('patch_ops', $builderDeltaUnitTest);
        $this->assertStringContainsString('cms_api.request_completed', $apiTelemetryFeatureTest);
        $this->assertStringContainsString('checkout', $apiTelemetryFeatureTest);

        // Public API observability bridge broadens runtime coverage but uses API-prefixed event family.
        $this->assertStringContainsString('cms_api.request_completed', $apiTelemetryMiddleware);
        $this->assertStringContainsString("'api',", $apiTelemetryMiddleware);
        $this->assertStringContainsString('public_api', $apiTelemetryMiddleware);
    }
}
