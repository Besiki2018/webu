<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderAiSelfLearningGenerationPrivacyAdminUiAcceptanceSle03SyncTest extends TestCase
{
    public function test_sle_03_audit_doc_locks_generation_privacy_admin_ui_acceptance_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_AI_SELF_LEARNING_GENERATION_PRIVACY_ADMIN_UI_ACCEPTANCE_AUDIT_SLE_03_2026_02_25.md');

        $g3ApplyDocPath = base_path('docs/architecture/CMS_GENERATION_TIME_LEARNED_RULE_APPLICATION_P6_G3_01.md');
        $g3ReproDocPath = base_path('docs/architecture/CMS_GENERATION_REPRODUCIBILITY_VERSIONING_P6_G3_02.md');
        $g3PrivacyDocPath = base_path('docs/architecture/CMS_GENERATION_PRIVACY_ENFORCEMENT_P6_G3_03.md');
        $g3AcceptanceDocPath = base_path('docs/architecture/CMS_GENERATION_LEARNING_ACCEPTANCE_TESTS_P6_G3_04.md');
        $adminControlsDocPath = base_path('docs/architecture/CMS_LEARNING_ADMIN_CONTROLS_P6_G2_04.md');
        $adminUiDocPath = base_path('docs/architecture/CMS_LEARNING_EXPERIMENTS_ADMIN_UI_P6_BASELINE.md');
        $qualityDocPath = base_path('docs/architecture/CMS_AI_GENERATION_QUALITY_SCORING_ENGINE_V1.md');
        $sle01DocPath = base_path('docs/qa/WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md');

        $learnedRuleApplyServicePath = base_path('app/Services/CmsAiLearnedRuleApplicationService.php');
        $pageGenServicePath = base_path('app/Services/CmsAiPageGenerationService.php');
        $privacyPolicyServicePath = base_path('app/Services/CmsAiLearningPrivacyPolicyService.php');
        $qualityScoringServicePath = base_path('app/Services/CmsAiGenerationQualityScoringEngine.php');
        $panelLearningControllerPath = base_path('app/Http/Controllers/Cms/PanelLearningController.php');
        $learningAdminServicePath = base_path('app/Services/CmsLearningAdminControlService.php');
        $telemetryStorageServicePath = base_path('app/Services/CmsTelemetryEventStorageService.php');

        $cmsPagePath = base_path('resources/js/Pages/Project/Cms.tsx');
        $cmsUiContractTestPath = base_path('resources/js/Pages/Project/__tests__/CmsLearningExperimentsAdminUi.contract.test.ts');

        $learnedRuleApplyUnitTestPath = base_path('tests/Unit/CmsAiLearnedRuleApplicationServiceTest.php');
        $pageGenIntegrationTestPath = base_path('tests/Unit/CmsAiPageGenerationLearningRulesIntegrationTest.php');
        $privacyPolicyUnitTestPath = base_path('tests/Unit/CmsAiLearningPrivacyPolicyServiceTest.php');
        $qualityScoringUnitTestPath = base_path('tests/Unit/CmsAiGenerationQualityScoringEngineTest.php');
        $g3ApplyLockTestPath = base_path('tests/Unit/UniversalGenerationTimeLearnedRuleApplicationP6G3Test.php');
        $g3ReproLockTestPath = base_path('tests/Unit/UniversalGenerationReproducibilityVersioningP6G3Test.php');
        $g3PrivacyLockTestPath = base_path('tests/Unit/UniversalGenerationPrivacyEnforcementP6G3Test.php');
        $g3AcceptanceLockTestPath = base_path('tests/Unit/UniversalGenerationLearningAcceptanceP6G3Test.php');
        $adminControlsLockTestPath = base_path('tests/Unit/UniversalLearningAdminControlsP6G2Test.php');
        $adminUiLockTestPath = base_path('tests/Unit/UniversalLearningExperimentsAdminUiP6Test.php');
        $acceptanceFeatureTestPath = base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php');
        $adminControlsFeatureTestPath = base_path('tests/Feature/Cms/CmsLearningAdminControlsTest.php');
        $telemetryStorageUnitTestPath = base_path('tests/Unit/CmsTelemetryEventStorageServiceTest.php');
        $telemetryPruneFeatureTestPath = base_path('tests/Feature/Cms/CmsTelemetryPruneCommandTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $g3ApplyDocPath,
            $g3ReproDocPath,
            $g3PrivacyDocPath,
            $g3AcceptanceDocPath,
            $adminControlsDocPath,
            $adminUiDocPath,
            $qualityDocPath,
            $sle01DocPath,
            $learnedRuleApplyServicePath,
            $pageGenServicePath,
            $privacyPolicyServicePath,
            $qualityScoringServicePath,
            $panelLearningControllerPath,
            $learningAdminServicePath,
            $telemetryStorageServicePath,
            $cmsPagePath,
            $cmsUiContractTestPath,
            $learnedRuleApplyUnitTestPath,
            $pageGenIntegrationTestPath,
            $privacyPolicyUnitTestPath,
            $qualityScoringUnitTestPath,
            $g3ApplyLockTestPath,
            $g3ReproLockTestPath,
            $g3PrivacyLockTestPath,
            $g3AcceptanceLockTestPath,
            $adminControlsLockTestPath,
            $adminUiLockTestPath,
            $acceptanceFeatureTestPath,
            $adminControlsFeatureTestPath,
            $telemetryStorageUnitTestPath,
            $telemetryPruneFeatureTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $learnedRuleApplyService = File::get($learnedRuleApplyServicePath);
        $pageGenService = File::get($pageGenServicePath);
        $privacyPolicyService = File::get($privacyPolicyServicePath);
        $qualityScoringService = File::get($qualityScoringServicePath);
        $panelLearningController = File::get($panelLearningControllerPath);
        $learningAdminService = File::get($learningAdminServicePath);
        $telemetryStorageService = File::get($telemetryStorageServicePath);
        $cmsPage = File::get($cmsPagePath);
        $cmsUiContractTest = File::get($cmsUiContractTestPath);

        $learnedRuleApplyUnitTest = File::get($learnedRuleApplyUnitTestPath);
        $pageGenIntegrationTest = File::get($pageGenIntegrationTestPath);
        $privacyPolicyUnitTest = File::get($privacyPolicyUnitTestPath);
        $qualityScoringUnitTest = File::get($qualityScoringUnitTestPath);
        $acceptanceFeatureTest = File::get($acceptanceFeatureTestPath);
        $adminControlsFeatureTest = File::get($adminControlsFeatureTestPath);
        $telemetryStorageUnitTest = File::get($telemetryStorageUnitTestPath);
        $telemetryPruneFeatureTest = File::get($telemetryPruneFeatureTestPath);

        foreach ([
            '# 6) Generation-Time Application',
            'Must expose:',
            'generation_version',
            'applied_rules list',
            '# 7) “Quality Scorer” (Optional Lightweight Model)',
            'visual consistency score',
            'layout balance score',
            'funnel readiness score',
            'mobile friendliness score',
            '# 8) Privacy & Safety',
            'Hash session IDs',
            'Do not store raw search strings; store length/buckets',
            'Do not store full IP',
            'Opt-out per tenant/store',
            'Admin UI to delete telemetry',
            '# 9) Admin UI (for Webu team / tenant owners)',
            'AI Improvements',
            'enable learning',
            'enable AB tests',
            'Admin can view/disable learning and experiments.',
            '# Deliverables',
            'Rule application middleware in generator',
            'Minimal admin dashboard endpoints',
            'Docs: event taxonomy + how to add new event',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + icon notes.
        $this->assertStringContainsString('- `SLE-03` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('WEBU_AI_SELF_LEARNING_GENERATION_PRIVACY_ADMIN_UI_ACCEPTANCE_AUDIT_SLE_03_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderAiSelfLearningGenerationPrivacyAdminUiAcceptanceSle03SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` generation-time learned-rule application + reproducibility audit documented', $backlog);
        $this->assertStringContainsString('`✅` privacy/tenant opt-out enforcement + acceptance coverage linked', $backlog);
        $this->assertStringContainsString('`✅` admin controls/UI checklist verified (backend + frontend contract refs)', $backlog);
        $this->assertStringContainsString('`✅` quality scorer optional baseline status truthfully reconciled', $backlog);
        $this->assertStringContainsString('`🧪` targeted evidence batch passed', $backlog);

        // Audit doc structure + truth/gaps.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:4992',
            'PROJECT_ROADMAP_TASKS_KA.md:5104',
            '## ✅ What Was Done (Icon Summary)',
            'generation-time learned-rule application',
            'reproducibility versioning',
            'Privacy/tenant opt-out',
            'admin control endpoints + Activity-section UI wiring',
            'optional quality scorer',
            'acceptance criteria and deliverables',
            '## Executive Result (`SLE-03`)',
            '`SLE-03` is **complete as an audit/verification task**',
            'Generation-time application of learned rules is implemented and deterministic',
            'Reproducibility/versioning metadata is implemented',
            'Admin controls to inspect/disable learned rules and experiments are implemented',
            'Optional quality scorer is implemented as a rule-based baseline',
            'telemetry deletion',
            'raw search-string',
            '## Generation-Time Application Audit (`6`)',
            'conflict_with_higher_priority_rule',
            '`page_plan.learning_generation_version`',
            '`page_plan.applied_rules`',
            '`page_plan.learned_rules_application`',
            '## Optional Quality Scorer Audit (`7`)',
            'visual consistency score',
            'mobile friendliness score',
            'rankCandidates(...)',
            '## Privacy & Safety Audit (`8`)',
            'cms_ai_learning_generation_enabled',
            'cms_ai_learning_allow_global_rules',
            'cms_ai_learning_reproducibility_enabled',
            'Cross-link to `SLE-01`',
            '## Admin UI / Controls Checklist (`9`)',
            'data-webu-learning-admin-panel="activity"',
            'standardized scope mismatch payload',
            '## Acceptance Criteria Reconciliation (`10`)',
            'No cross-tenant data leakage and no PII stored',
            '## Deliverables Reconciliation (Source `Deliverables`)',
            'Rule application middleware in generator',
            'Docs: event taxonomy + how to add new event',
            '## DoD Verdict (`SLE-03`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/docs/architecture/CMS_GENERATION_TIME_LEARNED_RULE_APPLICATION_P6_G3_01.md',
            'Install/docs/architecture/CMS_GENERATION_REPRODUCIBILITY_VERSIONING_P6_G3_02.md',
            'Install/docs/architecture/CMS_GENERATION_PRIVACY_ENFORCEMENT_P6_G3_03.md',
            'Install/docs/architecture/CMS_GENERATION_LEARNING_ACCEPTANCE_TESTS_P6_G3_04.md',
            'Install/docs/architecture/CMS_LEARNING_ADMIN_CONTROLS_P6_G2_04.md',
            'Install/docs/architecture/CMS_LEARNING_EXPERIMENTS_ADMIN_UI_P6_BASELINE.md',
            'Install/docs/architecture/CMS_AI_GENERATION_QUALITY_SCORING_ENGINE_V1.md',
            'Install/docs/qa/WEBU_AI_SELF_LEARNING_TELEMETRY_STORAGE_PRIVACY_AUDIT_SLE_01_2026_02_25.md',
            'Install/app/Services/CmsAiLearnedRuleApplicationService.php',
            'Install/app/Services/CmsAiPageGenerationService.php',
            'Install/app/Services/CmsAiLearningPrivacyPolicyService.php',
            'Install/app/Services/CmsAiGenerationQualityScoringEngine.php',
            'Install/app/Http/Controllers/Cms/PanelLearningController.php',
            'Install/app/Services/CmsLearningAdminControlService.php',
            'Install/resources/js/Pages/Project/Cms.tsx',
            'Install/resources/js/Pages/Project/__tests__/CmsLearningExperimentsAdminUi.contract.test.ts',
            'Install/tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php',
            'Install/tests/Feature/Cms/CmsLearningAdminControlsTest.php',
            'Install/tests/Unit/CmsAiLearnedRuleApplicationServiceTest.php',
            'Install/tests/Unit/CmsAiPageGenerationLearningRulesIntegrationTest.php',
            'Install/tests/Unit/CmsAiLearningPrivacyPolicyServiceTest.php',
            'Install/tests/Unit/CmsAiGenerationQualityScoringEngineTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing SLE-03 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing SLE-03 evidence file on disk: {$relativePath}");
        }

        // Generation-time apply / determinism / explainability truths.
        $this->assertStringContainsString('class CmsAiLearnedRuleApplicationService', $learnedRuleApplyService);
        $this->assertStringContainsString('GENERATION_VERSION', $learnedRuleApplyService);
        $this->assertStringContainsString('applyToGeneratedPages(array $pages, array $aiInput, array $context = [])', $learnedRuleApplyService);
        $this->assertStringContainsString('orderByDesc(\'confidence\')', $learnedRuleApplyService);
        $this->assertStringContainsString('orderByDesc(\'sample_size\')', $learnedRuleApplyService);
        $this->assertStringContainsString('conflict_with_higher_priority_rule', $learnedRuleApplyService);
        $this->assertStringContainsString("'applied_rules' => []", $learnedRuleApplyService);
        $this->assertStringContainsString("'privacy_enforcement' =>", $learnedRuleApplyService);
        $this->assertStringContainsString('tenant_opt_out', $learnedRuleApplyService);
        $this->assertStringContainsString('system_learning_generation_disabled', $learnedRuleApplyService);
        $this->assertStringContainsString('RULE_VERSIONING_VERSION', $learnedRuleApplyService);
        $this->assertStringContainsString('applied_rule_set_version', $learnedRuleApplyService);

        $this->assertStringContainsString('CmsAiLearnedRuleApplicationService', $pageGenService);
        $this->assertStringContainsString("'learning_generation_version'", $pageGenService);
        $this->assertStringContainsString("'applied_rules'", $pageGenService);
        $this->assertStringContainsString("'learned_rules_application'", $pageGenService);
        $this->assertStringContainsString("'learning_privacy' =>", $pageGenService);
        $this->assertStringContainsString("'reproducibility' =>", $pageGenService);
        $this->assertStringContainsString('REPRODUCIBILITY_VERSION', $pageGenService);
        $this->assertStringContainsString('buildGenerationReproducibilityMetadata(', $pageGenService);

        // Privacy policy service truths.
        $this->assertStringContainsString('class CmsAiLearningPrivacyPolicyService', $privacyPolicyService);
        $this->assertStringContainsString('FLAG_GENERATION_LEARNING_ENABLED', $privacyPolicyService);
        $this->assertStringContainsString('FLAG_ALLOW_GLOBAL_LEARNED_RULES', $privacyPolicyService);
        $this->assertStringContainsString('FLAG_REPRODUCIBILITY_ENABLED', $privacyPolicyService);
        $this->assertStringContainsString('resolveForAiInput(array $aiInput): array', $privacyPolicyService);
        $this->assertStringContainsString('tenant_opt_out', $privacyPolicyService);
        $this->assertStringContainsString('ai_learning.opt_out', $privacyPolicyService);

        // Optional quality scorer truths.
        $this->assertStringContainsString('class CmsAiGenerationQualityScoringEngine', $qualityScoringService);
        $this->assertStringContainsString('Rule-based baseline scorer for AI generation outputs.', $qualityScoringService);
        $this->assertStringContainsString('public function scoreOutput(array $aiOutput, array $context = []): array', $qualityScoringService);
        $this->assertStringContainsString('public function rankCandidates(array $candidates): array', $qualityScoringService);
        $this->assertStringContainsString("'kind' => 'rule_based_quality_scorer'", $qualityScoringService);
        $this->assertStringNotContainsString('CmsAiGenerationQualityScoringEngine', $pageGenService); // standalone optional scorer, not default page-generation path here

        // Admin controls + UI truths.
        $this->assertStringContainsString('class PanelLearningController extends Controller', $panelLearningController);
        $this->assertStringContainsString('rules(Request $request, Site $site)', $panelLearningController);
        $this->assertStringContainsString('disableRule(Request $request, Site $site, CmsLearnedRule $rule)', $panelLearningController);
        $this->assertStringContainsString('experiments(Request $request, Site $site)', $panelLearningController);
        $this->assertStringContainsString('disableExperiment(Request $request, Site $site, CmsExperiment $experiment)', $panelLearningController);

        $this->assertStringContainsString('class CmsLearningAdminControlService', $learningAdminService);
        $this->assertStringContainsString('listLearnedRules(Site $site', $learningAdminService);
        $this->assertStringContainsString('disableLearnedRule(Site $site, CmsLearnedRule $rule', $learningAdminService);
        $this->assertStringContainsString('listExperiments(Site $site', $learningAdminService);
        $this->assertStringContainsString('disableExperiment(Site $site, CmsExperiment $experiment', $learningAdminService);
        $this->assertStringContainsString('Learned rule disabled.', $learningAdminService);
        $this->assertStringContainsString('Experiment disabled (paused).', $learningAdminService);

        $this->assertStringContainsString("const isLearningAdminActivitySection = isAdminUser && activeSection === 'activity'", $cmsPage);
        $this->assertStringContainsString('/panel/sites/${site.id}/cms/learning/rules', $cmsPage);
        $this->assertStringContainsString('/panel/sites/${site.id}/cms/learning/experiments', $cmsPage);
        $this->assertStringContainsString('/cms/learning/rules/${rule.id}/disable', $cmsPage);
        $this->assertStringContainsString('/cms/learning/experiments/${experiment.id}/disable', $cmsPage);
        $this->assertStringContainsString('data-webu-learning-admin-panel="activity"', $cmsPage);
        $this->assertStringContainsString('data-webu-learning-rules-list', $cmsPage);
        $this->assertStringContainsString('data-webu-learning-experiments-list', $cmsPage);

        $this->assertStringContainsString('CMS learning/experiments admin UI contract', $cmsUiContractTest);
        $this->assertStringContainsString('data-webu-learning-admin-panel="activity"', $cmsUiContractTest);

        // Behavior/acceptance/privacy tests.
        $this->assertStringContainsString('conflict_with_higher_priority_rule', $learnedRuleApplyUnitTest);
        $this->assertStringContainsString('tenant_opt_out', $learnedRuleApplyUnitTest);
        $this->assertStringContainsString('applied_rule_set_version', $learnedRuleApplyUnitTest);

        $this->assertStringContainsString('page_plan.learning_generation_version', $pageGenIntegrationTest);
        $this->assertStringContainsString('page_plan.learned_rules_application', $pageGenIntegrationTest);
        $this->assertStringContainsString('page_plan.reproducibility', $pageGenIntegrationTest);
        $this->assertStringContainsString('tenant_opt_out', $pageGenIntegrationTest);

        $this->assertStringContainsString('p6-g3-03.v1', $privacyPolicyUnitTest);
        $this->assertStringContainsString('tenant_opt_out', $privacyPolicyUnitTest);
        $this->assertStringContainsString('FLAG_ALLOW_GLOBAL_LEARNED_RULES', $privacyPolicyUnitTest);
        $this->assertStringContainsString('FLAG_REPRODUCIBILITY_ENABLED', $privacyPolicyUnitTest);

        $this->assertStringContainsString('rankCandidates([', $qualityScoringUnitTest);
        $this->assertStringContainsString('visual consistency score', $qualityScoringUnitTest);
        $this->assertStringContainsString('mobile friendliness score', $qualityScoringUnitTest);

        $this->assertStringContainsString('no_cross_tenant_leakage_and_deterministic_replay', $acceptanceFeatureTest);
        $this->assertStringContainsString('tenant_opt_out_disables_learning_application_and_replay_metadata', $acceptanceFeatureTest);
        $this->assertStringContainsString('page_plan.reproducibility', $acceptanceFeatureTest);
        $this->assertStringContainsString('page_plan.learning_privacy.status', $acceptanceFeatureTest);

        $this->assertStringContainsString('panel.sites.cms.learning.rules.index', $adminControlsFeatureTest);
        $this->assertStringContainsString('tenant_scope_route_binding_mismatch', $adminControlsFeatureTest);
        $this->assertStringContainsString('Pause experiment due to review', $adminControlsFeatureTest);

        // Cross-slice telemetry privacy truths used for SLE-03 privacy reconciliation.
        $this->assertStringContainsString("hash_hmac('sha256'", $telemetryStorageService);
        $this->assertStringContainsString("'[redacted]'", $telemetryStorageService);
        $this->assertStringContainsString('data_retention_days_cms_telemetry', $telemetryStorageService);
        $this->assertStringContainsString('client_ip_hash', $telemetryStorageUnitTest);
        $this->assertStringContainsString('[redacted]', $telemetryStorageUnitTest);
        $this->assertStringContainsString('cms:telemetry-prune', $telemetryPruneFeatureTest);
    }
}
