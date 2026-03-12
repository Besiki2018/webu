<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest extends TestCase
{
    public function test_gv_01_verification_pack_maps_milestones_and_risks_to_evidence_and_task_linked_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md');

        $evidencePaths = [
            'Install/tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php',
            'Install/tests/Unit/ProgramCrossPhaseWorkstreamControlsGv02SyncTest.php',
            'Install/tests/Unit/ProgramManagementQueueAndSourcePriorityChecklistSyncTest.php',
            'Install/tests/Unit/ProgramManagementTaskTemplateAndCoverageChecklistSyncTest.php',
            'Install/tests/Unit/NothingMissedChecklistStatusSyncTest.php',
            'Install/tests/Unit/ProgramSourceSpecExecutionBacklogCoverageSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningTelemetryStoragePrivacySle01SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningTargetsExperimentationRulePromotionSle02SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningGenerationPrivacyAdminUiAcceptanceSle03SyncTest.php',
            'Install/tests/Unit/UniversalGenerationPrivacyEnforcementP6G3Test.php',
            'Install/tests/Unit/UniversalGenerationReproducibilityVersioningP6G3Test.php',
            'Install/tests/Unit/UniversalGenerationLearningAcceptanceP6G3Test.php',
            'Install/tests/Unit/UniversalRuleMetricPromotionThresholdsP6G2Test.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'Install/tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputValidationEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php',
            'Install/tests/Feature/Cms/CmsLearningAdminControlsTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php',
            'Install/tests/Feature/Booking/BookingAcceptanceTest.php',
            'Install/tests/Unit/UniversalServicesBookingContractsP5F3Test.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
            'Install/tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_TARGETS_EXPERIMENTATION_RULE_LEARNING_PROMOTION_AUDIT_SLE_02_2026_02_25.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_GENERATION_PRIVACY_ADMIN_UI_ACCEPTANCE_AUDIT_SLE_03_2026_02_25.md',
            'Install/docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md',
            'Install/docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'Install/docs/architecture/UNIVERSAL_CORE_SCHEMA_MIGRATION_PLAN_P5_F1_02.md',
            '.github/workflows/cms-template-smokes.yml',
        ];

        foreach (array_merge([$roadmapPath, $backlogPath, $docPath], array_map(
            fn (string $path) => base_path('../'.$path),
            $evidencePaths
        )) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        // Source scope is present in roadmap.
        foreach ([
            '## 3.6 Milestone Exit Criteria (Program-Level)',
            '### Milestone A — Ecommerce Builder Production MVP',
            '### Milestone B — AI Assisted Store Generation',
            '### Milestone C — Universal Industry Platform',
            '### Milestone D — Learning/Optimization Layer',
            '## 3.7 Program Risk Register (Initial)',
            '### R1 — Scope Explosion / Parallel Builds',
            '### R2 — Contract Drift (Builder vs Runtime vs API)',
            '### R3 — Greenfield Prompt Wording Causes Wrong Implementation',
            '### R4 — Universal Schema Introduced Too Early',
            '### R5 — AI Generation Without Strong Validation',
            '### R6 — Self-Learning Privacy/Determinism Failures',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog row closure and evidence linkage.
        $this->assertStringContainsString('- `GV-01` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md', $backlog);
        $this->assertStringContainsString('ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest.php', $backlog);
        $this->assertStringContainsString('milestones `A-D` are reconciled into a single criteria-to-evidence matrix', $backlog);
        $this->assertStringContainsString('risk register `R1..R6` mitigation controls now have explicit owner/control evidence', $backlog);
        $this->assertStringContainsString('open mitigation gaps are linked to existing executable backlog tasks (`GV-03`, `GV-04`, `ECM-01`, `ECM-02`, `RS-05-03`, `RS-13-01`)', $backlog);
        $this->assertStringContainsString('milestone matrix is intentionally mixed (`pass=14`, `partial=2`, `fail=0`)', $backlog);

        // Audit doc structure and summary truths.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:623',
            'PROJECT_ROADMAP_TASKS_KA.md:699',
            '## Goal (`GV-01`)',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`GV-01`)',
            '`GV-01` is **complete as an audit/verification task**',
            'criteria mapped=16',
            'criteria unmapped=0',
            'risk rows=6',
            'risk unmapped=0',
            'orphan mitigation gaps=0',
            'overall: `pass=14`, `partial=2`, `fail=0`',
            '## Milestone Criteria-to-Evidence Matrix (A-D)',
            '### Milestone A — Ecommerce Builder Production MVP',
            '### Milestone B — AI Assisted Store Generation',
            '### Milestone C — Universal Industry Platform',
            '### Milestone D — Learning/Optimization Layer',
            '## Risk Mitigation Control Ownership Audit (`R1..R6`)',
            '## Open Mitigation Gap → Executable Task Conversion (DoD)',
            'No new backlog rows were created in `GV-01`',
            '## DoD Verdict (`GV-01`)',
            '## Result',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Milestone criteria source phrases should be present in the matrix.
        foreach ([
            'Builder components (core + ecommerce) are builder-editable and runtime-renderable',
            'Preview and publish flows are stable',
            'Product → Cart → Checkout → Order works end-to-end',
            'Auth/account/orders pages work for customer flows',
            'Template import/runtime validation and smoke tests are in CI',
            'No known page-level horizontal overflow / blocking UI regressions in CMS critical tabs',
            'Documentation exists for template contract + component/binding contract',
            'AI outputs are schema-validated builder-native artifacts',
            'Generated pages pass render smoke tests',
            'AI-generated site remains editable in builder without special cases',
            'Audit logs and feature flags exist for rollout control',
            'Project-type feature flags control module/component visibility',
            'At least one non-ecommerce vertical works end-to-end (recommended: services+booking)',
            'Universal component taxonomy is active without duplicating builder systems',
            'Telemetry, privacy, opt-out, and deterministic rule application are verified',
            'Learned rules can be enabled/disabled and rolled back safely',
        ] as $criterion) {
            $this->assertStringContainsString($criterion, $doc);
        }

        // Risk rows and task-linked follow-ups are explicit.
        foreach ([
            '`R1 — Scope Explosion / Parallel Builds`',
            '`R2 — Contract Drift (Builder vs Runtime vs API)`',
            '`R3 — Greenfield Prompt Wording Causes Wrong Implementation`',
            '`R4 — Universal Schema Introduced Too Early`',
            '`R5 — AI Generation Without Strong Validation`',
            '`R6 — Self-Learning Privacy/Determinism Failures`',
            '`GV-03`',
            '`GV-04`',
            '`ECM-01`',
            '`ECM-02`',
            '`RS-05-03`',
            '`RS-13-01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Representative evidence anchors are embedded in the doc and exist on disk.
        foreach ([
            'Install/tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php',
            'Install/tests/Unit/ProgramCrossPhaseWorkstreamControlsGv02SyncTest.php',
            'Install/tests/Unit/ProgramManagementQueueAndSourcePriorityChecklistSyncTest.php',
            'Install/tests/Unit/ProgramManagementTaskTemplateAndCoverageChecklistSyncTest.php',
            'Install/tests/Unit/NothingMissedChecklistStatusSyncTest.php',
            'Install/tests/Unit/ProgramSourceSpecExecutionBacklogCoverageSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningTelemetryStoragePrivacySle01SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningTargetsExperimentationRulePromotionSle02SyncTest.php',
            'Install/tests/Unit/BackendBuilderAiSelfLearningGenerationPrivacyAdminUiAcceptanceSle03SyncTest.php',
            'Install/tests/Unit/UniversalGenerationPrivacyEnforcementP6G3Test.php',
            'Install/tests/Unit/UniversalGenerationReproducibilityVersioningP6G3Test.php',
            'Install/tests/Unit/UniversalRuleMetricPromotionThresholdsP6G2Test.php',
            'Install/tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php',
            'Install/tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php',
            'Install/tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputValidationEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php',
            'Install/tests/Feature/Cms/CmsLearningAdminControlsTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php',
            'Install/tests/Feature/Booking/BookingAcceptanceTest.php',
            'Install/tests/Unit/UniversalServicesBookingContractsP5F3Test.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
            'Install/tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_TARGETS_EXPERIMENTATION_RULE_LEARNING_PROMOTION_AUDIT_SLE_02_2026_02_25.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_GENERATION_PRIVACY_ADMIN_UI_ACCEPTANCE_AUDIT_SLE_03_2026_02_25.md',
            'Install/docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md',
            'Install/docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'Install/docs/architecture/UNIVERSAL_CORE_SCHEMA_MIGRATION_PLAN_P5_F1_02.md',
            '.github/workflows/cms-template-smokes.yml',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "GV-01 doc missing evidence anchor: {$relativePath}");
        }
    }
}
