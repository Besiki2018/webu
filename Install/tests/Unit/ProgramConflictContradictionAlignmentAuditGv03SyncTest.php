<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramConflictContradictionAlignmentAuditGv03SyncTest extends TestCase
{
    public function test_gv_03_conflict_alignment_audit_maps_all_conflicts_to_canonical_decisions_and_owned_followups(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/PROGRAM_CONFLICT_CONTRADICTION_ALIGNMENT_CANONICAL_NAMING_DOC_CORRECTIONS_AUDIT_GV_03_2026_02_25.md');

        $evidencePaths = [
            'Install/docs/qa/PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md',
            'Install/docs/qa/PROGRAM_CROSS_PHASE_WORKSTREAM_CONTROLS_GV_02_2026_02_25.md',
            'Install/docs/qa/PROGRAM_SOURCE_SPEC_BACKLOG_COVERAGE_SYNC_GV_05_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md',
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_API_CORE_CONTRACT_AUDIT_API_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_10_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_11_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_12_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_GENERATION_PRIVACY_ADMIN_UI_ACCEPTANCE_AUDIT_SLE_03_2026_02_25.md',
            'Install/docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md',
            'Install/docs/architecture/CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md',
            'Install/docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'Install/docs/architecture/CMS_AI_BINDING_GENERATION_RULES_V1.md',
            'Install/tests/Unit/BackendBuilderApiCoreContractApi01SyncTest.php',
            'Install/tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php',
            'Install/tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Install/tests/Unit/BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationThemeSiteSettingsReconciliationAr01SyncTest.php',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationBuilderCoreRegistryDynamicBindingComponentRequirementsReconciliationAr02SyncTest.php',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationPageTemplatesRoutingPageParamsNotificationsReconciliationAr03SyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryGlobalStandardsDataBindingRuleCoverageRs0003SyncTest.php',
            'Install/tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest.php',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest.php',
            'Install/tests/Unit/CmsCanonicalBindingResolverTest.php',
            'Install/tests/Unit/CmsCanonicalSchemaContractsTest.php',
            'Install/tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php',
            'Install/tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'Install/tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php',
            'Install/tests/Feature/Cms/CmsModuleRegistryTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php',
            'Install/tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php',
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

        // Source conflict block lines and headings exist in roadmap.
        $this->assertStringContainsString('## 5. Critical Conflict / Contradiction Notes (Keep All Features, But Align Them)', $roadmap);
        foreach ([
            'Conflict 1 — "Template" vs "Theme" vs "Theme Settings" Terminology',
            'Conflict 2 — API Endpoint Naming / Contract Versions',
            'Conflict 3 — Builder Storage Naming (`page_json/page_css`) vs Revision-Based Content Storage',
            'Conflict 4 — Duplicate Component Specs (E-commerce Spec vs Universal Library Spec)',
            'Conflict 5 — Scope Explosion (E-commerce MVP + Universal Platform + AI Auto-Gen + Self-Learning)',
            'Conflict 6 — Public Site Builder Components vs Admin/Internal Components',
            'Conflict 7 — Universal Routing Across Industries',
            'Conflict 8 — AI-Generated Components vs Hand-Curated Component Library',
            '## 6. Implementation Rules (To Protect Your Goal)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure, evidence, and icon notes.
        $this->assertStringContainsString('- `GV-03` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('PROGRAM_CONFLICT_CONTRADICTION_ALIGNMENT_CANONICAL_NAMING_DOC_CORRECTIONS_AUDIT_GV_03_2026_02_25.md', $backlog);
        $this->assertStringContainsString('ProgramConflictContradictionAlignmentAuditGv03SyncTest.php', $backlog);
        $this->assertStringContainsString('all 8 roadmap conflicts are reconciled into a contradiction-resolution matrix', $backlog);
        $this->assertStringContainsString('doc-corrections backlog is extracted with owners; no contradiction row remains unowned (`conflicts_unowned=0`)', $backlog);
        $this->assertStringContainsString('3 conflicts remain `partial_with_owner`', $backlog);

        // Audit doc structure and summary truth.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:756',
            'PROJECT_ROADMAP_TASKS_KA.md:842',
            '## Goal (`GV-03`)',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`GV-03`)',
            '`GV-03` is **complete as an audit/reconciliation task**',
            'conflict rows=`8`',
            'conflict rows mapped=`8`',
            'conflicts resolved/documented=`5`',
            'conflicts partial_with_owner=`3`',
            'conflicts_unowned=`0`',
            '## Contradiction Resolution Matrix (`Conflict 1`-`8`)',
            '## Conflict Matrix Summary',
            '## Enforced Canonical Naming / Contract Map (Program-Level)',
            '## Doc Corrections Backlog (Owners + Follow-up Tasks)',
            'Doc-corrections backlog summary:',
            'unowned=`0`',
            '## DoD Verdict (`GV-03`)',
            '## Result',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Conflict rows and partial-owner linkage must be explicit.
        foreach ([
            '`Conflict 1` — Template vs Theme vs Theme Settings terminology',
            '`Conflict 2` — API endpoint naming / contract versions',
            '`Conflict 3` — `page_json/page_css` naming vs revision-based storage',
            '`Conflict 4` — Duplicate component specs (ecommerce spec vs universal library spec)',
            '`Conflict 5` — Scope explosion (MVP + universal + AI + self-learning)',
            '`Conflict 6` — Public site builder components vs admin/internal components',
            '`Conflict 7` — Universal routing across industries',
            '`Conflict 8` — AI-generated components vs hand-curated component library',
            '`partial_with_owner`',
            '`GV-04`',
            '`ECM-01`',
            '`ECM-02`',
            '`RS-05-03`',
            '`RS-13-01`',
            '`RS-07-01`',
            '`RS-10-01`',
            '`RS-11-01`',
            '`RS-12-01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Canonical naming map rows.
        foreach ([
            'Theme terminology',
            'Public API path style',
            'Builder page persistence',
            'Component taxonomy',
            'Route param binding syntax',
            'AI-generated component registration',
            'Admin/store-owner builder component isolation',
            'Site-scoped public API (`/public/sites/{site}/...`)',
            '`pages` + `page_revisions.content_json` runtime path',
            '`{{route.params.*}}` canonical syntax',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Representative evidence anchors are embedded in the doc (not every supporting file in $evidencePaths).
        $docAnchorPaths = [
            'Install/docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_API_CORE_CONTRACT_AUDIT_API_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_10_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_11_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_12_01_2026_02_25.md',
            'Install/docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md',
            'Install/docs/architecture/CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md',
            'Install/docs/architecture/CMS_AI_BINDING_GENERATION_RULES_V1.md',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationThemeSiteSettingsReconciliationAr01SyncTest.php',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationPageTemplatesRoutingPageParamsNotificationsReconciliationAr03SyncTest.php',
            'Install/tests/Unit/BackendBuilderApiCoreContractApi01SyncTest.php',
            'Install/tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php',
            'Install/tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            'Install/tests/Unit/BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php',
            'Install/tests/Unit/LegacyReferenceArchiveEcommerceFullIntegrationBuilderCoreRegistryDynamicBindingComponentRequirementsReconciliationAr02SyncTest.php',
            'Install/tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'Install/tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'Install/tests/Unit/ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest.php',
            'Install/tests/Feature/Cms/CmsModuleRegistryTest.php',
            'Install/tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php',
            'Install/tests/Unit/UniversalComponentLibraryActivationP5F5Test.php',
            'Install/tests/Unit/CmsCanonicalBindingResolverTest.php',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest.php',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest.php',
            'Install/tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php',
            'Install/tests/Unit/CmsCanonicalSchemaContractsTest.php',
            'Install/tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php',
        ];

        foreach ($docAnchorPaths as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "GV-03 doc missing evidence anchor: {$relativePath}");
        }
    }
}
