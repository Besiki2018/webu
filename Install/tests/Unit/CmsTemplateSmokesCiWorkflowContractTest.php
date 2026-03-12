<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsTemplateSmokesCiWorkflowContractTest extends TestCase
{
    public function test_ci_workflow_includes_template_import_runtime_validation_and_smoke_suite_commands(): void
    {
        $workflowPath = base_path('../.github/workflows/cms-template-smokes.yml');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        $this->assertFileExists($workflowPath);
        $this->assertFileExists($roadmapPath);

        $workflow = File::get($workflowPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('name: CMS Template Smokes', $workflow);
        $this->assertStringContainsString('working-directory: Install', $workflow);
        $this->assertStringContainsString('composer install', $workflow);
        $this->assertStringContainsString('npm ci', $workflow);
        $this->assertStringContainsString('php artisan key:generate --force', $workflow);

        $this->assertStringContainsString('TemplateImportContractServiceTest', $workflow);
        $this->assertStringContainsString('CmsPreviewPublishAlignmentTest', $workflow);
        $this->assertStringContainsString('TemplateProvisioningSmokeTest', $workflow);
        $this->assertStringContainsString('TemplatePreviewRenderSmokeTest', $workflow);
        $this->assertStringContainsString('TemplateAppPreviewRenderSmokeTest', $workflow);
        $this->assertStringContainsString('TemplatePublishedRenderSmokeTest', $workflow);
        $this->assertStringContainsString('TemplateStorefrontE2eFlowMatrixSmokeTest', $workflow);
        $this->assertStringContainsString('CmsLayoutStability.contract.test.ts', $workflow);
        $this->assertStringContainsString('CmsDynamicAndThemeUx.contract.test.ts', $workflow);

        $this->assertStringContainsString('- ✅ Template import/runtime validation and smoke tests are in CI', $roadmap);
    }
}
