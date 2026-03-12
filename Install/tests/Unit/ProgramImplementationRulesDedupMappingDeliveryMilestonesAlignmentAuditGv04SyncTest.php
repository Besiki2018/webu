<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramImplementationRulesDedupMappingDeliveryMilestonesAlignmentAuditGv04SyncTest extends TestCase
{
    public function test_gv_04_audit_locks_implementation_rules_dedup_mapping_exceptions_and_milestone_alignment(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/PROGRAM_IMPLEMENTATION_RULES_DEDUP_MAPPING_DELIVERY_MILESTONES_ALIGNMENT_AUDIT_GV_04_2026_02_25.md');

        $supportingPaths = [
            'Install/docs/qa/PROGRAM_CONFLICT_CONTRADICTION_ALIGNMENT_CANONICAL_NAMING_DOC_CORRECTIONS_AUDIT_GV_03_2026_02_25.md',
            'Install/docs/qa/PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md',
            'Install/docs/qa/PROGRAM_CROSS_PHASE_WORKSTREAM_CONTROLS_GV_02_2026_02_25.md',
            'Install/docs/qa/PROGRAM_SOURCE_SPEC_BACKLOG_COVERAGE_SYNC_GV_05_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_STRICT_AI_PAGE_GENERATION_ENGINE_CONTRACT_AUDIT_API_07_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_API_CORE_CONTRACT_AUDIT_API_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'Install/docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md',
            'Install/docs/architecture/CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md',
            'Install/docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'Install/tests/Unit/ProgramConflictContradictionAlignmentAuditGv03SyncTest.php',
            'Install/tests/Unit/ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest.php',
            'Install/tests/Unit/ProgramCrossPhaseWorkstreamControlsGv02SyncTest.php',
            'Install/tests/Unit/ProgramSourceSpecExecutionBacklogCoverageSyncTest.php',
            'Install/tests/Unit/BackendBuilderAiWebsiteGenerationFlowApi06SyncTest.php',
            'Install/tests/Unit/BackendBuilderStrictAiPageGenerationEngineApi07SyncTest.php',
            'Install/tests/Unit/BackendBuilderApiCoreContractApi01SyncTest.php',
            'Install/tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
            'Install/tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php',
            'Install/tests/Feature/Cms/CmsModuleRegistryTest.php',
        ];

        foreach (array_merge([$roadmapPath, $backlogPath, $docPath], array_map(
            fn (string $path) => base_path('../'.$path),
            $supportingPaths
        )) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        // Source block headings and key rule/mapping/milestone lines must exist.
        foreach ([
            '## 6. Implementation Rules (To Protect Your Goal)',
            'Do not build feature-specific UIs outside the builder contract',
            'Do not create a second runtime binding system for AI-generated themes',
            'Do not create per-industry custom builders',
            'Do not merge tenant-admin and public-customer auth/session models',
            'Do not let prompt specs become the runtime contract without versioned schemas',
            'Do not duplicate existing platform modules under new names',
            'When a prompt says "create new X"',
            '## 6.1 Explicit Deduplication / Replace-Mapping (Applied to This Document)',
            '"Create NEW THEME" → "Extend current template + theme settings + preset system"',
            '"Build API X" (generic path) → "Map to current site-scoped public API or add versioned adapter"',
            '"Save page_json/page_css" → "Persist through current page revision/content model"',
            '"Add admin/store owner builder components" → "Feature-flag and isolate; do not pollute public site builder default UX"',
            '## 7. Suggested Delivery Milestones (Practical)',
            '### Milestone A — Ecommerce Builder Production MVP',
            '### Milestone D — Learning/Optimization Layer',
            '## 8. How To Use The Reference Spec Library (Below)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog row closure + evidence/icon notes.
        $this->assertStringContainsString('- `GV-04` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('PROGRAM_IMPLEMENTATION_RULES_DEDUP_MAPPING_DELIVERY_MILESTONES_ALIGNMENT_AUDIT_GV_04_2026_02_25.md', $backlog);
        $this->assertStringContainsString('ProgramImplementationRulesDedupMappingDeliveryMilestonesAlignmentAuditGv04SyncTest.php', $backlog);
        $this->assertStringContainsString('source `6` implementation rules are reconciled into a rule-compliance matrix', $backlog);
        $this->assertStringContainsString('source `6.1` dedup/replace mappings are audited row-by-row; exceptions/variants are enumerated with owners (`unowned=0`)', $backlog);
        $this->assertStringContainsString('source `7` suggested delivery milestones are aligned against current stronger wrapper evidence (`GV-01`)', $backlog);
        $this->assertStringContainsString('non-pass rule rows remain around builder-first parity completeness, auth/session interoperability exactness, and contract-versioning centralization', $backlog);

        // Doc structure and summary counts.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:842',
            'PROJECT_ROADMAP_TASKS_KA.md:894',
            '## Goal (`GV-04`)',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`GV-04`)',
            '`GV-04` is **complete as an audit/reconciliation task**',
            'implementation rules rows=`7`, mapped=`7`, unmapped=`0`',
            'rule verdicts: `pass=4`, `partial_with_owner=3`, `violated=0`, `violated_unowned=0`',
            'dedup mapping rows=`6`, mapped=`6`, unmapped=`0`',
            'dedup exceptions summary: `no_exception=3`, `tracked_exception_open_owned=3`, `unowned=0`',
            'suggested milestone alignment rows=`4`, `aligned=2`, `partial_aligned_with_owner=2`, `unowned=0`',
            '## Implementation Rule Compliance Matrix (`6`)',
            '## Dedup / Replace-Mapping Audit (`6.1`)',
            '## Suggested Delivery Milestones Alignment Audit (`7`)',
            '## Rule Remediation Linkage Register (Non-Pass Rows)',
            '## DoD Verdict (`GV-04`)',
            '## Result',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Matrix rows and remediation links for non-pass rules.
        foreach ([
            'Do not build feature-specific UIs outside the builder contract if the same capability should be a reusable component.',
            'Do not create a second runtime binding system for AI-generated themes; AI must target the same builder/template/runtime contract.',
            'Do not create per-industry custom builders; keep one builder and feature-flag component categories/modules.',
            'Do not merge tenant-admin and public-customer auth/session models; keep them separate but interoperable.',
            'Do not let prompt specs become the runtime contract without versioned schemas.',
            'Do not duplicate existing platform modules under new names (CMS/theme/settings/pages/ecommerce/media/menu) just because a prompt spec uses different terminology.',
            'When a prompt says "create new X", first check if Webu already has X and convert the task to "upgrade/normalize existing X".',
            '`partial_with_owner`',
            '`ECM-01`',
            '`ECM-02`',
            '`RS-05-03`',
            '`RS-13-01`',
            '`API-03`',
            '`GV-02` drift tracking',
            '`violated=0`',
            '`violated_unowned=0`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Dedup mapping exceptions list and milestone alignment rows.
        foreach ([
            '## Dedup Mapping Exceptions List (Deliverable)',
            'Generic API-path wording vs site-scoped/versioned runtime contracts',
            'Builder-contract normalization still converging for some component families',
            'Admin/store-owner builder component isolation policy lacks an explicit registry-category lock test/doc',
            'rows=`6`',
            '`no_exception=3`',
            '`tracked_exception_open_owned=3`',
            'Milestone A — Ecommerce Builder Production MVP',
            'Milestone B — AI Assisted Store Generation',
            'Milestone C — Universal Industry Platform',
            'Milestone D — Learning/Optimization Layer',
            '`partial_aligned_with_owner`',
            '`aligned=2`',
            '`partial_aligned_with_owner=2`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Representative evidence anchors should be embedded in the doc.
        $docAnchorPaths = [
            'Install/docs/qa/PROGRAM_CONFLICT_CONTRADICTION_ALIGNMENT_CANONICAL_NAMING_DOC_CORRECTIONS_AUDIT_GV_03_2026_02_25.md',
            'Install/docs/qa/PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_STRICT_AI_PAGE_GENERATION_ENGINE_CONTRACT_AUDIT_API_07_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_API_CORE_CONTRACT_AUDIT_API_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'Install/docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md',
            'Install/docs/architecture/CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md',
            'Install/docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'Install/tests/Unit/BackendBuilderAiWebsiteGenerationFlowApi06SyncTest.php',
            'Install/tests/Unit/BackendBuilderStrictAiPageGenerationEngineApi07SyncTest.php',
            'Install/tests/Feature/Cms/CmsModuleRegistryTest.php',
            'Install/tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
        ];

        foreach ($docAnchorPaths as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "GV-04 doc missing evidence anchor: {$relativePath}");
        }
    }
}
