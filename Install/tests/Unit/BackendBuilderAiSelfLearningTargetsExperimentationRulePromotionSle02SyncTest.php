<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderAiSelfLearningTargetsExperimentationRulePromotionSle02SyncTest extends TestCase
{
    public function test_sle_02_audit_doc_locks_learning_targets_experiments_rule_learning_and_promotion_guardrails(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_AI_SELF_LEARNING_TARGETS_EXPERIMENTATION_RULE_LEARNING_PROMOTION_AUDIT_SLE_02_2026_02_25.md');

        $targetsDocPath = base_path('docs/architecture/CMS_LEARNING_TARGETS_P6_BASELINE.md');
        $abDocPath = base_path('docs/architecture/CMS_AB_EXPERIMENTATION_MODEL_ASSIGNMENT_P6_G2_01.md');
        $ruleLearningDocPath = base_path('docs/architecture/CMS_RULE_LEARNING_FROM_BUILDER_DELTAS_P6_G2_02.md');
        $promotionDocPath = base_path('docs/architecture/CMS_RULE_METRIC_PROMOTION_THRESHOLDS_P6_G2_03.md');

        $targetsServicePath = base_path('app/Services/CmsLearningTargetsService.php');
        $experimentAssignmentServicePath = base_path('app/Services/CmsExperimentAssignmentService.php');
        $ruleLearningServicePath = base_path('app/Services/CmsRuleLearningFromBuilderDeltasService.php');
        $promotionServicePath = base_path('app/Services/CmsLearnedRuleMetricPromotionService.php');
        $learnCommandPath = base_path('app/Console/Commands/LearnCmsRulesFromBuilderDeltas.php');
        $evaluateCommandPath = base_path('app/Console/Commands/EvaluateCmsLearnedRuleMetrics.php');

        $experimentsMigrationPath = base_path('database/migrations/2026_02_24_234000_create_cms_experimentation_tables.php');
        $learnedRulesMigrationPath = base_path('database/migrations/2026_02_24_235000_create_cms_learned_rules_table.php');

        $experimentModelPath = base_path('app/Models/CmsExperiment.php');
        $variantModelPath = base_path('app/Models/CmsExperimentVariant.php');
        $assignmentModelPath = base_path('app/Models/CmsExperimentAssignment.php');
        $learnedRuleModelPath = base_path('app/Models/CmsLearnedRule.php');

        $targetsUnitTestPath = base_path('tests/Unit/CmsLearningTargetsServiceTest.php');
        $experimentAssignmentUnitTestPath = base_path('tests/Unit/CmsExperimentAssignmentServiceTest.php');
        $ruleLearningUnitTestPath = base_path('tests/Unit/CmsRuleLearningFromBuilderDeltasServiceTest.php');
        $promotionUnitTestPath = base_path('tests/Unit/CmsLearnedRuleMetricPromotionServiceTest.php');
        $learnCommandFeatureTestPath = base_path('tests/Feature/Cms/CmsRuleLearningFromBuilderDeltasCommandTest.php');
        $evaluateCommandFeatureTestPath = base_path('tests/Feature/Cms/CmsLearnedRuleMetricsEvaluationCommandTest.php');
        $targetsLockTestPath = base_path('tests/Unit/UniversalLearningTargetsP6Test.php');
        $abLockTestPath = base_path('tests/Unit/UniversalAbExperimentationAssignmentP6G2Test.php');
        $ruleLearningLockTestPath = base_path('tests/Unit/UniversalRuleLearningFromBuilderDeltasP6G2Test.php');
        $promotionLockTestPath = base_path('tests/Unit/UniversalRuleMetricPromotionThresholdsP6G2Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $targetsDocPath,
            $abDocPath,
            $ruleLearningDocPath,
            $promotionDocPath,
            $targetsServicePath,
            $experimentAssignmentServicePath,
            $ruleLearningServicePath,
            $promotionServicePath,
            $learnCommandPath,
            $evaluateCommandPath,
            $experimentsMigrationPath,
            $learnedRulesMigrationPath,
            $experimentModelPath,
            $variantModelPath,
            $assignmentModelPath,
            $learnedRuleModelPath,
            $targetsUnitTestPath,
            $experimentAssignmentUnitTestPath,
            $ruleLearningUnitTestPath,
            $promotionUnitTestPath,
            $learnCommandFeatureTestPath,
            $evaluateCommandFeatureTestPath,
            $targetsLockTestPath,
            $abLockTestPath,
            $ruleLearningLockTestPath,
            $promotionLockTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $targetsService = File::get($targetsServicePath);
        $experimentAssignmentService = File::get($experimentAssignmentServicePath);
        $ruleLearningService = File::get($ruleLearningServicePath);
        $promotionService = File::get($promotionServicePath);
        $learnCommand = File::get($learnCommandPath);
        $evaluateCommand = File::get($evaluateCommandPath);
        $experimentsMigration = File::get($experimentsMigrationPath);
        $learnedRulesMigration = File::get($learnedRulesMigrationPath);
        $targetsUnitTest = File::get($targetsUnitTestPath);
        $experimentAssignmentUnitTest = File::get($experimentAssignmentUnitTestPath);
        $ruleLearningUnitTest = File::get($ruleLearningUnitTestPath);
        $promotionUnitTest = File::get($promotionUnitTestPath);
        $learnCommandFeatureTest = File::get($learnCommandFeatureTestPath);
        $evaluateCommandFeatureTest = File::get($evaluateCommandFeatureTestPath);

        foreach ([
            '# 3) Learning Targets (What the system optimizes)',
            'Conversion funnel (view → add-to-cart → checkout → purchase)',
            'Builder friction (how many manual edits needed after AI generation)',
            'Each goal must be weighted; default weights:',
            '# 4) Experimentation Framework (A/B)',
            'Assignment must be stable per session/device.',
            '# 5) Learning Engine (Rule + Model Hybrid)',
            'Rule Learning from Builder Deltas',
            'learned_rules table:',
            '# 5.2 Metric-based Rule Promotion',
            'compare conversion_rate before/after applying rule',
            'minimum sample size + uplift',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + icon-marked notes/evidence.
        $this->assertStringContainsString('- `SLE-02` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_AI_SELF_LEARNING_TARGETS_EXPERIMENTATION_RULE_LEARNING_PROMOTION_AUDIT_SLE_02_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderAiSelfLearningTargetsExperimentationRulePromotionSle02SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` learning targets / experimentation / rule-learning / promotion coverage matrix audited', $backlog);
        $this->assertStringContainsString('`✅` assignment stability + rollout guardrails verified', $backlog);
        $this->assertStringContainsString('`✅` metric promotion/rollback thresholds + sample gates verified', $backlog);
        $this->assertStringContainsString('`🧪` targeted evidence batch passed', $backlog);

        // Audit doc structure + truthful findings + icon summary.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:4911',
            'PROJECT_ROADMAP_TASKS_KA.md:4991',
            '## ✅ What Was Done (Icon Summary)',
            '✅ Verified experimentation assignment guardrails',
            '✅ Verified rule-learning and promotion guardrails',
            '🧪 Added sync/lock test + ran targeted evidence test batch.',
            '## Executive Result (`SLE-02`)',
            '`SLE-02` is **complete as an audit/verification task**',
            'weighted profiles',
            'experiment patch application',
            'optional lightweight model scoring',
            '## Learning Loop Contract Matrix (`3`-`5`)',
            'cms_experiments',
            'cms_experiment_variants',
            'cms_experiment_assignments',
            'cms_learned_rules',
            'deterministic_weighted_hash_v1',
            'compare `conversion_rate` before/after applying rule',
            'minimum sample size + uplift thresholds',
            '## DoD Verdict (`SLE-02`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/docs/architecture/CMS_LEARNING_TARGETS_P6_BASELINE.md',
            'Install/docs/architecture/CMS_AB_EXPERIMENTATION_MODEL_ASSIGNMENT_P6_G2_01.md',
            'Install/docs/architecture/CMS_RULE_LEARNING_FROM_BUILDER_DELTAS_P6_G2_02.md',
            'Install/docs/architecture/CMS_RULE_METRIC_PROMOTION_THRESHOLDS_P6_G2_03.md',
            'Install/app/Services/CmsLearningTargetsService.php',
            'Install/app/Services/CmsExperimentAssignmentService.php',
            'Install/app/Services/CmsRuleLearningFromBuilderDeltasService.php',
            'Install/app/Services/CmsLearnedRuleMetricPromotionService.php',
            'Install/app/Console/Commands/LearnCmsRulesFromBuilderDeltas.php',
            'Install/app/Console/Commands/EvaluateCmsLearnedRuleMetrics.php',
            'Install/database/migrations/2026_02_24_234000_create_cms_experimentation_tables.php',
            'Install/database/migrations/2026_02_24_235000_create_cms_learned_rules_table.php',
            'Install/tests/Unit/CmsLearningTargetsServiceTest.php',
            'Install/tests/Unit/CmsExperimentAssignmentServiceTest.php',
            'Install/tests/Unit/CmsRuleLearningFromBuilderDeltasServiceTest.php',
            'Install/tests/Unit/CmsLearnedRuleMetricPromotionServiceTest.php',
            'Install/tests/Feature/Cms/CmsRuleLearningFromBuilderDeltasCommandTest.php',
            'Install/tests/Feature/Cms/CmsLearnedRuleMetricsEvaluationCommandTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing SLE-02 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing SLE-02 evidence file on disk: {$relativePath}");
        }

        // Learning targets truths.
        $this->assertStringContainsString('class CmsLearningTargetsService', $targetsService);
        $this->assertStringContainsString("public const CATALOG_VERSION = 'p6-learning-targets.v1';", $targetsService);
        $this->assertStringContainsString("'conversion'", $targetsService);
        $this->assertStringContainsString("'engagement'", $targetsService);
        $this->assertStringContainsString("'usability'", $targetsService);
        $this->assertStringContainsString("'performance'", $targetsService);
        $this->assertStringContainsString("'builder_friction'", $targetsService);
        $this->assertStringContainsString('metricPriorityForRulePromotion()', $targetsService);
        $this->assertStringContainsString("'builder_save_warnings_per_draft'", $targetsService);

        // Experiment assignment guardrails.
        $this->assertStringContainsString('class CmsExperimentAssignmentService', $experimentAssignmentService);
        $this->assertStringContainsString('assignActiveExperimentsForRequest(Site $site, Request $request, array $context = [])', $experimentAssignmentService);
        $this->assertStringContainsString('assignForExperiment(Site $site, CmsExperiment $experiment, Request $request, array $context = [])', $experimentAssignmentService);
        $this->assertStringContainsString('session_or_device', $experimentAssignmentService);
        $this->assertStringContainsString('outside_traffic_allocation', $experimentAssignmentService);
        $this->assertStringContainsString('deterministic_weighted_hash_v1', $experimentAssignmentService);
        $this->assertStringContainsString("hash_hmac('sha256'", $experimentAssignmentService);

        $this->assertStringContainsString("Schema::create('cms_experiments'", $experimentsMigration);
        $this->assertStringContainsString("Schema::create('cms_experiment_variants'", $experimentsMigration);
        $this->assertStringContainsString("Schema::create('cms_experiment_assignments'", $experimentsMigration);
        $this->assertStringContainsString('subject_hash', $experimentsMigration);
        $this->assertStringContainsString('traffic_percent', $experimentsMigration);

        // Rule learning truths.
        $this->assertStringContainsString('class CmsRuleLearningFromBuilderDeltasService', $ruleLearningService);
        $this->assertStringContainsString('learnCandidateRules(', $ruleLearningService);
        $this->assertStringContainsString('CmsBuilderDelta::query()', $ruleLearningService);
        $this->assertStringContainsString('builder_delta_cluster', $ruleLearningService);
        $this->assertStringContainsString('json_patch_template', $ruleLearningService);
        $this->assertStringContainsString('/props/style/', $ruleLearningService);
        $this->assertStringContainsString('updateOrCreate(', $ruleLearningService);

        $this->assertStringContainsString("Schema::create('cms_learned_rules'", $learnedRulesMigration);
        $this->assertStringContainsString("json('conditions_json')", $learnedRulesMigration);
        $this->assertStringContainsString("json('patch_json')", $learnedRulesMigration);
        $this->assertStringContainsString("boolean('active')", $learnedRulesMigration);

        // Metric promotion/rollback truths.
        $this->assertStringContainsString('class CmsLearnedRuleMetricPromotionService', $promotionService);
        $this->assertStringContainsString('evaluateRules(?Site $site = null', $promotionService);
        $this->assertStringContainsString('evaluateRule(CmsLearnedRule $rule', $promotionService);
        $this->assertStringContainsString('conversion_rate', $promotionService);
        $this->assertStringContainsString('builder_publish_per_open_rate', $promotionService);
        $this->assertStringContainsString('runtime_hydrate_success_rate', $promotionService);
        $this->assertStringContainsString('resolveActiveRuleAggregateComparison', $promotionService);
        $this->assertStringContainsString('validateComparisonSamples', $promotionService);
        $this->assertStringContainsString('metric_drop_exceeds_rollback_threshold', $promotionService);
        $this->assertStringContainsString('rollback_drop_threshold', $promotionService);

        // Command + test evidence.
        $this->assertStringContainsString('cms:learn-rules-from-builder-deltas', $learnCommand);
        $this->assertStringContainsString('cms.learning.cluster_builder_deltas', $learnCommand);
        $this->assertStringContainsString('cms:learned-rules-evaluate-metrics', $evaluateCommand);
        $this->assertStringContainsString('cms.learning.rule_metric_thresholds_evaluated', $evaluateCommand);

        $this->assertStringContainsString('p6-learning-targets.v1', $targetsUnitTest);
        $this->assertStringContainsString('builder_friction', $targetsUnitTest);
        $this->assertStringContainsString('deterministic_weighted_hash_v1', $experimentAssignmentUnitTest);
        $this->assertStringContainsString('outside_traffic_allocation', $experimentAssignmentUnitTest);
        $this->assertStringContainsString('json_patch_template', $ruleLearningUnitTest);
        $this->assertStringContainsString('headline', $ruleLearningUnitTest); // skipped content edit coverage
        $this->assertStringContainsString('before_sample_threshold_not_met', $promotionUnitTest);
        $this->assertStringContainsString('conversion_rate', $promotionUnitTest);
        $this->assertStringContainsString('Learned CMS rule candidates from builder deltas', $learnCommandFeatureTest);
        $this->assertStringContainsString('Evaluated learned rule metric thresholds', $evaluateCommandFeatureTest);
    }
}
