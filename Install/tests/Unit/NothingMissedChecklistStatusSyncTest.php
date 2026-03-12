<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class NothingMissedChecklistStatusSyncTest extends TestCase
{
    public function test_program_management_checklist_contract_and_release_lines_match_existing_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $paths = [
            base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'),
            base_path('docs/architecture/UNIVERSAL_CORE_SCHEMA_MIGRATION_PLAN_P5_F1_02.md'),
            base_path('docs/architecture/UNIVERSAL_CORE_TENANT_ACCESS_FOUNDATION_P5_SUMMARY_BASELINE.md'),
            base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md'),
            base_path('docs/qa/CMS_HEADER_FOOTER_PREVIEW_PUBLISH_PARITY_CHECKLIST.md'),
            base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'),
            base_path('tests/Unit/CmsTemplateSmokesCiWorkflowContractTest.php'),
            base_path('tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts'),
        ];

        foreach (array_merge([$roadmapPath], $paths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $publicApiDoc = File::get(base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'));
        $runtimeBindingDoc = File::get(base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md'));
        $migrationPlanDoc = File::get(base_path('docs/architecture/UNIVERSAL_CORE_SCHEMA_MIGRATION_PLAN_P5_F1_02.md'));
        $coreFoundationDoc = File::get(base_path('docs/architecture/UNIVERSAL_CORE_TENANT_ACCESS_FOUNDATION_P5_SUMMARY_BASELINE.md'));
        $uiChecklistDoc = File::get(base_path('docs/qa/CMS_HEADER_FOOTER_PREVIEW_PUBLISH_PARITY_CHECKLIST.md'));
        $schemaContractsTest = File::get(base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'));
        $ciWorkflowContractTest = File::get(base_path('tests/Unit/CmsTemplateSmokesCiWorkflowContractTest.php'));
        $milestoneSyncTest = File::get(base_path('tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php'));
        $layoutContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts'));

        $this->assertStringContainsString('- ✅ Parallel system creation was explicitly avoided or migration documented', $roadmap);
        $this->assertStringContainsString('- ✅ Builder schema changes documented and versioned', $roadmap);
        $this->assertStringContainsString('- ✅ Runtime/template binding changes documented and tested', $roadmap);
        $this->assertStringContainsString('- ✅ Public API changes versioned or adapter-backed', $roadmap);
        $this->assertStringContainsString('- ✅ Smoke tests updated for affected flow', $roadmap);
        $this->assertStringContainsString('- ✅ UI regression checklist executed for impacted CMS areas', $roadmap);
        $this->assertStringContainsString('- ✅ Milestone exit criteria section updated with actual status', $roadmap);

        $this->assertStringContainsString('v1', strtolower($schemaContractsTest));
        $this->assertStringContainsString('public api contract versioning baseline', strtolower($publicApiDoc));
        $this->assertStringContainsString('adapter-backed', strtolower($publicApiDoc));
        $this->assertStringContainsString('migration plan', strtolower($migrationPlanDoc));
        $this->assertStringContainsString('additive first, destructive later', strtolower($migrationPlanDoc));
        $this->assertStringContainsString('additive', strtolower($coreFoundationDoc));
        $this->assertStringContainsString('runtime binding contract', strtolower($runtimeBindingDoc));
        $this->assertStringContainsString('preview-publish parity checklist', strtolower($uiChecklistDoc));
        $this->assertStringContainsString('smoke flow', strtolower($uiChecklistDoc));
        $this->assertStringContainsString('cms template smokes', strtolower($ciWorkflowContractTest));
        $this->assertStringContainsString('milestone_a_b_c_exit_criteria_statuses', strtolower($milestoneSyncTest));
        $this->assertStringContainsString('layout stability', strtolower($layoutContract));
    }
}
