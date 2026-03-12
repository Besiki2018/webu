<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalBuilderDeltaCaptureP6G1Test extends TestCase
{
    public function test_p6_g1_04_builder_delta_capture_pipeline_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_BUILDER_DELTA_CAPTURE_P6_G1_04.md');
        $migrationPath = base_path('database/migrations/2026_02_24_233000_create_cms_builder_deltas_table.php');
        $modelPath = base_path('app/Models/CmsBuilderDelta.php');
        $servicePath = base_path('app/Services/CmsBuilderDeltaCaptureService.php');
        $panelPageServicePath = base_path('app/Cms/Services/CmsPanelPageService.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $migrationPath, $modelPath, $servicePath, $panelPageServicePath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migration = File::get($migrationPath);
        $model = File::get($modelPath);
        $service = File::get($servicePath);
        $panelPageService = File::get($panelPageServicePath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G1-04', $doc);
        $this->assertStringContainsString('cms_builder_deltas', $doc);
        $this->assertStringContainsString('generation_id', $doc);
        $this->assertStringContainsString('patch_ops', $doc);
        $this->assertStringContainsString('JSON Patch', $doc);
        $this->assertStringContainsString('CmsPanelPageService::createRevision()', $doc);
        $this->assertStringContainsString('fingerprint', $doc);
        $this->assertStringContainsString('non-primary locale saves are skipped', $doc);

        $this->assertStringContainsString("Schema::create('cms_builder_deltas'", $migration);
        $this->assertStringContainsString('$table->string(\'generation_id\'', $migration);
        $this->assertStringContainsString('$table->json(\'patch_ops\')', $migration);
        $this->assertStringContainsString('$table->json(\'patch_stats_json\')', $migration);

        $this->assertStringContainsString('class CmsBuilderDelta extends Model', $model);
        $this->assertStringContainsString("'patch_ops' => 'array'", $model);
        $this->assertStringContainsString("'patch_stats_json' => 'array'", $model);

        $this->assertStringContainsString('class CmsBuilderDeltaCaptureService', $service);
        $this->assertStringContainsString('captureAfterManualRevisionSave(', $service);
        $this->assertStringContainsString('resolveBaselineRevisionForGeneration(', $service);
        $this->assertStringContainsString('buildJsonPatchOps(', $service);
        $this->assertStringContainsString("'panel_revision_save'", $service);
        $this->assertStringContainsString('cms.builder_delta_capture_failed', $panelPageService);
        $this->assertStringContainsString('captureAfterManualRevisionSave(', $panelPageService);

        $this->assertStringContainsString('- ✅ Builder delta capture', $roadmap);
        $this->assertStringContainsString("`P6-G1-04` (✅ `DONE`) Builder delta capture pipeline (manual edits after AI generation).", $roadmap);
    }
}
