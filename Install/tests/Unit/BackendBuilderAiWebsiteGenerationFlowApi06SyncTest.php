<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderAiWebsiteGenerationFlowApi06SyncTest extends TestCase
{
    public function test_api_06_audit_doc_locks_ai_website_generation_flow_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md');

        $inputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-input.v1.schema.json');
        $outputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json');
        $validatePayloadCommandPath = base_path('app/Console/Commands/ValidateAiGenerationPayload.php');

        $themeServicePath = base_path('app/Services/CmsAiThemeGenerationService.php');
        $themeEnginePath = base_path('app/Services/CmsAiThemeGenerationEngine.php');
        $pageServicePath = base_path('app/Services/CmsAiPageGenerationService.php');
        $pageEnginePath = base_path('app/Services/CmsAiPageGenerationEngine.php');
        $placementEnginePath = base_path('app/Services/CmsAiComponentPlacementStylingEngine.php');
        $validationEnginePath = base_path('app/Services/CmsAiOutputValidationEngine.php');
        $saveEnginePath = base_path('app/Services/CmsAiOutputSaveEngine.php');
        $renderEnginePath = base_path('app/Services/CmsAiOutputRenderTestEngine.php');
        $qualityEnginePath = base_path('app/Services/CmsAiGenerationQualityScoringEngine.php');
        $rolloutControlPath = base_path('app/Services/CmsAiGenerationRolloutControlService.php');
        $patchControllerPath = base_path('app/Http/Controllers/ProjectAiContentPatchController.php');

        $payloadValidationCommandTestPath = base_path('tests/Feature/Cms/CmsAiGenerationPayloadValidationCommandTest.php');
        $themeServiceTestPath = base_path('tests/Unit/CmsAiThemeGenerationServiceTest.php');
        $themeEngineTestPath = base_path('tests/Unit/CmsAiThemeGenerationEngineTest.php');
        $pageEngineTestPath = base_path('tests/Unit/CmsAiPageGenerationEngineTest.php');
        $pageServiceTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');
        $placementEngineTestPath = base_path('tests/Unit/CmsAiComponentPlacementStylingEngineTest.php');
        $bindingRulesPipelineTestPath = base_path('tests/Unit/CmsAiBindingGenerationRulesPipelineTest.php');
        $outputValidationTestPath = base_path('tests/Feature/Cms/CmsAiOutputValidationEngineTest.php');
        $outputSaveTestPath = base_path('tests/Feature/Cms/CmsAiOutputSaveEngineTest.php');
        $outputRenderTestPath = base_path('tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php');
        $qualityScoringTestPath = base_path('tests/Unit/CmsAiGenerationQualityScoringEngineTest.php');
        $rolloutControlTestPath = base_path('tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php');
        $generatedSiteEditabilityTestPath = base_path('tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php');
        $aiPatchFlowTestPath = base_path('tests/Feature/Cms/AiContentPatchFlowTest.php');
        $learningAcceptanceTestPath = base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $inputSchemaPath,
            $outputSchemaPath,
            $validatePayloadCommandPath,
            $themeServicePath,
            $themeEnginePath,
            $pageServicePath,
            $pageEnginePath,
            $placementEnginePath,
            $validationEnginePath,
            $saveEnginePath,
            $renderEnginePath,
            $qualityEnginePath,
            $rolloutControlPath,
            $patchControllerPath,
            $payloadValidationCommandTestPath,
            $themeServiceTestPath,
            $themeEngineTestPath,
            $pageEngineTestPath,
            $pageServiceTestPath,
            $placementEngineTestPath,
            $bindingRulesPipelineTestPath,
            $outputValidationTestPath,
            $outputSaveTestPath,
            $outputRenderTestPath,
            $qualityScoringTestPath,
            $rolloutControlTestPath,
            $generatedSiteEditabilityTestPath,
            $aiPatchFlowTestPath,
            $learningAcceptanceTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $inputSchema = File::get($inputSchemaPath);
        $outputSchema = File::get($outputSchemaPath);
        $validatePayloadCommand = File::get($validatePayloadCommandPath);

        $themeService = File::get($themeServicePath);
        $themeEngine = File::get($themeEnginePath);
        $pageService = File::get($pageServicePath);
        $pageEngine = File::get($pageEnginePath);
        $placementEngine = File::get($placementEnginePath);
        $validationEngine = File::get($validationEnginePath);
        $saveEngine = File::get($saveEnginePath);
        $renderEngine = File::get($renderEnginePath);
        $qualityEngine = File::get($qualityEnginePath);
        $rolloutControl = File::get($rolloutControlPath);
        $patchController = File::get($patchControllerPath);

        $payloadValidationCommandTest = File::get($payloadValidationCommandTestPath);
        $themeServiceTest = File::get($themeServiceTestPath);
        $themeEngineTest = File::get($themeEngineTestPath);
        $pageEngineTest = File::get($pageEngineTestPath);
        $pageServiceTest = File::get($pageServiceTestPath);
        $placementEngineTest = File::get($placementEngineTestPath);
        $bindingRulesPipelineTest = File::get($bindingRulesPipelineTestPath);
        $outputValidationTest = File::get($outputValidationTestPath);
        $outputSaveTest = File::get($outputSaveTestPath);
        $outputRenderTest = File::get($outputRenderTestPath);
        $qualityScoringTest = File::get($qualityScoringTestPath);
        $rolloutControlTest = File::get($rolloutControlTestPath);
        $generatedSiteEditabilityTest = File::get($generatedSiteEditabilityTestPath);
        $aiPatchFlowTest = File::get($aiPatchFlowTestPath);
        $learningAcceptanceTest = File::get($learningAcceptanceTestPath);

        $this->assertStringContainsString('CODEX PROMPT — Webu AI Website Generator Engine (Full Auto Site Creation)', $roadmap);
        $this->assertStringContainsString('1. HIGH LEVEL FLOW', $roadmap);
        $this->assertStringContainsString('2. AI INPUT', $roadmap);
        $this->assertStringContainsString('3. AI OUTPUT STRUCTURE', $roadmap);
        $this->assertStringContainsString('15. USER EXPERIENCE FLOW', $roadmap);
        $this->assertStringContainsString('16. FUTURE SUPPORT (prepare architecture)', $roadmap);
        $this->assertStringContainsString('17. ACCEPTANCE CRITERIA', $roadmap);

        $this->assertStringContainsString('- `API-06` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_AI_WEBSITE_GENERATION_FLOW_AUDIT_API_06_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderAiWebsiteGenerationFlowApi06SyncTest.php', $backlog);

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:3168',
            'PROJECT_ROADMAP_TASKS_KA.md:3552',
            '## Executive Result (`API-06`)',
            '`API-06` is **complete as an audit task**',
            '## Capability Matrix (Spec items `1`-`17`)',
            'implemented_variant',
            '## Spec `1` High-Level Flow Audit (Pipeline Mapping)',
            '## Spec `2` AI Input Audit (Canonical Contract vs Source Example)',
            '## Spec `3` AI Output Structure Audit',
            '## Spec `4` Theme Generation Audit',
            '## Spec `5`-`11` Page / Header / Footer Generation Audit',
            '## Spec `7`-`9` Placement / Styling / Data Binding Audit',
            '## Spec `12` Save Into Database Audit',
            '## Spec `13` Auto Connect To Backend APIs Audit',
            '## Spec `14` Generation Quality Rules Audit',
            '## Spec `15` User Experience Flow Audit (Create Website with AI + Timing)',
            '## Spec `16` Future Support (Edit Website via AI) Audit',
            '## Spec `17` Acceptance Criteria Audit',
            '< 10 seconds',
            '`API-02`',
            '`API-03`',
            '`API-05`',
            '`API-07`',
            'No single audited production endpoint/controller',
            'structured patch/edit flows and edit modes',
            'logical data resources',
            'cms-ai-generation-input.v1',
            'cms-ai-generation-output.v1',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $anchors = [
            'Install/docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            'Install/docs/architecture/schemas/cms-ai-generation-output.v1.schema.json',
            'Install/app/Console/Commands/ValidateAiGenerationPayload.php',
            'Install/app/Services/CmsAiThemeGenerationService.php',
            'Install/app/Services/CmsAiThemeGenerationEngine.php',
            'Install/app/Services/CmsAiPageGenerationService.php',
            'Install/app/Services/CmsAiPageGenerationEngine.php',
            'Install/app/Services/CmsAiComponentPlacementStylingEngine.php',
            'Install/app/Services/CmsAiOutputValidationEngine.php',
            'Install/app/Services/CmsAiOutputSaveEngine.php',
            'Install/app/Services/CmsAiOutputRenderTestEngine.php',
            'Install/app/Services/CmsAiGenerationQualityScoringEngine.php',
            'Install/app/Services/CmsAiGenerationRolloutControlService.php',
            'Install/app/Http/Controllers/ProjectAiContentPatchController.php',
            'Install/tests/Feature/Cms/CmsAiGenerationPayloadValidationCommandTest.php',
            'Install/tests/Unit/CmsAiThemeGenerationServiceTest.php',
            'Install/tests/Unit/CmsAiThemeGenerationEngineTest.php',
            'Install/tests/Unit/CmsAiPageGenerationEngineTest.php',
            'Install/tests/Unit/CmsAiPageGenerationServiceTest.php',
            'Install/tests/Unit/CmsAiComponentPlacementStylingEngineTest.php',
            'Install/tests/Unit/CmsAiBindingGenerationRulesPipelineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputValidationEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputSaveEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php',
            'Install/tests/Unit/CmsAiGenerationQualityScoringEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php',
            'Install/tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php',
            'Install/tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php',
            'Install/tests/Feature/Cms/AiContentPatchFlowTest.php',
        ];

        foreach ($anchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing API-06 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing API-06 evidence file on disk: {$relativePath}");
        }

        // Canonical schema contract truth: nested input contract and strict output envelope superset.
        foreach (['"schema_version"', '"request"', '"platform_context"', '"meta"'] as $requiredInputTopLevel) {
            $this->assertStringContainsString($requiredInputTopLevel, $inputSchema);
        }
        foreach (['"theme"', '"pages"', '"header"', '"footer"', '"meta"'] as $requiredOutputTopLevel) {
            $this->assertStringContainsString($requiredOutputTopLevel, $outputSchema);
        }

        $this->assertStringContainsString('protected $signature = \'cms:ai-validate-payload', $validatePayloadCommand);
        $this->assertStringContainsString('Validate AI generation input/output payloads against canonical CMS JSON schemas.', $validatePayloadCommand);
        $this->assertStringContainsString("'input' => \$this->validator->validateInputJsonString", $validatePayloadCommand);
        $this->assertStringContainsString("'output' => \$this->validator->validateOutputJsonString", $validatePayloadCommand);

        // Theme/page generation layers and contracts.
        $this->assertStringContainsString('public function generateThemeFragment(array $aiInput): array', $themeService);
        $this->assertStringContainsString("'kind' => 'rule_based_theme_generation'", $themeService);
        $this->assertStringContainsString("'theme_settings_patch' =>", $themeService);

        $this->assertStringContainsString('public function generateFromAiInput(array $input): array', $themeEngine);
        $this->assertStringContainsString('theme_output', $themeEngine);
        $this->assertStringContainsString('theme_settings_patch', $themeEngine);
        $this->assertStringContainsString('preserve_theme_settings=true; returned keep_existing theme output with no patch.', $themeEngine);

        $this->assertStringContainsString('public function generatePagesFragment(array $aiInput): array', $pageService);
        $this->assertStringContainsString('generateThemeFragment($aiInput)', $pageService);
        $this->assertStringContainsString('applyRules($pages, [', $pageService);
        $this->assertStringContainsString('learnedRuleApplication->applyToGeneratedPages', $pageService);
        $this->assertStringContainsString("Log::info('cms.ai.generation.trace'", $pageService);
        $this->assertStringContainsString("'step' => 'page_generation'", $pageService);

        $this->assertStringContainsString('public function generateFromAiInput(array $input): array', $pageEngine);
        $this->assertStringContainsString('request.mode=generate_theme; page generation engine returned no page fragments.', $pageEngine);
        $this->assertStringContainsString('ecommerceCorePageSlugs()', $pageEngine);
        $this->assertStringContainsString("return ['home', 'shop', 'product', 'cart', 'checkout'];", $pageEngine);
        $this->assertStringContainsString("'header' => [", $pageEngine);
        $this->assertStringContainsString("'footer' => [", $pageEngine);

        $this->assertStringContainsString('public const RULESET_VERSION = 1;', $placementEngine);
        $this->assertStringContainsString('public function applyToPagesOutput(array $input, array $pagesOutput, array $themeOutput = []): array', $placementEngine);
        $this->assertStringContainsString('webu-ai-placement:v%d', $placementEngine);
        $this->assertStringContainsString("'resource' => 'ecommerce.checkout'", $placementEngine);
        $this->assertStringContainsString("'resource' => 'auth.session'", $placementEngine);

        // Validation/save/render/quality/rollout gates.
        $this->assertStringContainsString('public function validateOutputForSite(Site $site, array $aiOutput, array $options = []): array', $validationEngine);
        $this->assertStringContainsString("'blocking_checks' => [", $validationEngine);
        $this->assertStringContainsString("'component_availability'", $validationEngine);
        $this->assertStringContainsString("'bindings'", $validationEngine);

        $this->assertStringContainsString('public function persistOutputForSite(Site $site, array $aiOutput, ?int $actorId = null, array $options = []): array', $saveEngine);
        $this->assertStringContainsString("'storage_channels' => [", $saveEngine);
        $this->assertStringContainsString("'no_parallel_storage' => true", $saveEngine);
        $this->assertStringContainsString("'saved_via' => 'CmsAiOutputSaveEngine'", $saveEngine);

        $this->assertStringContainsString('public function runPreviewSmoke(Project $project, User $actor, array $options = []): array', $renderEngine);
        $this->assertStringContainsString('public function runPreviewSmokeForProject(Project $project, array $options = []): array', $renderEngine);
        $this->assertStringContainsString('data-webu-menu="header"', $renderEngine);
        $this->assertStringContainsString('__cms/bootstrap', $renderEngine);

        $this->assertStringContainsString('public function scoreOutput(array $aiOutput, array $context = []): array', $qualityEngine);
        $this->assertStringContainsString('public function rankCandidates(array $candidates): array', $qualityEngine);
        $this->assertStringContainsString("'visual_consistency'", $qualityEngine);
        $this->assertStringContainsString("'funnel_readiness'", $qualityEngine);
        $this->assertStringContainsString("'mobile_friendliness'", $qualityEngine);

        $this->assertStringContainsString('Read rollout feature flags with safe defaults (fail-closed rollout).', $rolloutControl);
        $this->assertStringContainsString('public function evaluateAndAudit(Project $project, array $reports = [], ?User $actor = null, array $options = []): array', $rolloutControl);
        $this->assertStringContainsString('FLAG_MIN_QUALITY_SCORE', $rolloutControl);
        $this->assertStringContainsString('AUDIT_ACTION', $rolloutControl);

        $this->assertStringContainsString('idempotency_key', $patchController);
        $this->assertStringContainsString('ai_content_patch', $patchController);

        // Test evidence locks key stage behaviors and gaps/variants.
        $this->assertStringContainsString("'cms:ai-validate-payload'", $payloadValidationCommandTest);
        $this->assertStringContainsString("'contract' => 'input'", $payloadValidationCommandTest);
        $this->assertStringContainsString("'contract' => 'output'", $payloadValidationCommandTest);

        $this->assertStringContainsString('test_it_generates_builder_native_theme_fragment_from_prompt_signals', $themeServiceTest);
        $this->assertStringContainsString('theme.theme_settings_patch.theme_tokens.colors.primary', $themeServiceTest);
        $this->assertStringContainsString('test_it_keeps_existing_theme_preset_when_preserve_theme_settings_constraint_is_requested', $themeServiceTest);

        $this->assertStringContainsString('test_it_generates_theme_output_fragment_using_existing_theme_settings_patch_contract', $themeEngineTest);
        $this->assertStringContainsString("'theme_output.theme_settings_patch.theme_tokens.colors.primary'", $themeEngineTest);

        $this->assertStringContainsString('test_it_generates_builder_native_pages_with_route_metadata_and_canonical_nodes', $pageEngineTest);
        $this->assertStringContainsString('$this->assertContains(\'shop\', $slugs);', $pageEngineTest);
        $this->assertStringContainsString('$this->assertContains(\'checkout\', $slugs);', $pageEngineTest);
        $this->assertStringContainsString('test_it_respects_target_page_slugs_in_edit_page_mode_and_marks_keep_existing', $pageEngineTest);

        $this->assertStringContainsString('test_it_generates_builder_native_ecommerce_pages_with_route_metadata_and_canonical_nodes', $pageServiceTest);
        foreach (['home', 'shop', 'product', 'cart', 'checkout', 'login', 'account', 'orders', 'order', 'contact'] as $slug) {
            $this->assertStringContainsString("'{$slug}'", $pageServiceTest);
        }
        $this->assertStringContainsString('test_it_emits_structured_ai_generation_trace_log_for_page_generation', $pageServiceTest);
        $this->assertStringContainsString("'cms.ai.generation.trace'", $pageServiceTest);

        $this->assertStringContainsString('test_it_applies_deterministic_component_placement_and_styling_rules_to_generated_pages', $placementEngineTest);
        $this->assertStringContainsString("'conversion'", $placementEngineTest);
        $this->assertStringContainsString('webu-ai-placement:v1', $placementEngineTest);

        $this->assertStringContainsString('test_page_generation_engine_normalizes_section_library_bindings_and_injects_product_route_binding', $bindingRulesPipelineTest);
        $this->assertStringContainsString('{{route.params.slug}}', $bindingRulesPipelineTest);
        $this->assertStringContainsString('ecommerce.checkout', $bindingRulesPipelineTest);
        $this->assertStringContainsString('auth.session', $bindingRulesPipelineTest);

        $this->assertStringContainsString('test_it_validates_schema_component_availability_and_bindings_for_ai_output_v1', $outputValidationTest);
        foreach (['missing_enabled_fixed_section_type', 'disabled_component', 'unavailable_component', 'invalid_syntax', 'invalid_required_route_binding'] as $errorCode) {
            $this->assertStringContainsString($errorCode, $outputValidationTest);
        }

        $this->assertStringContainsString('test_it_persists_ai_output_into_current_site_page_and_revision_models_without_parallel_storage', $outputSaveTest);
        $this->assertStringContainsString("'saved.no_parallel_storage'", $outputSaveTest);
        $this->assertStringContainsString('CmsAiOutputSaveEngine', $outputSaveTest);
        $this->assertStringContainsString('/product/{slug}', $outputSaveTest);

        $this->assertStringContainsString('test_it_runs_preview_smoke_checks_against_saved_generated_output_using_app_preview_and_bootstrap_bridge', $outputRenderTest);
        $this->assertStringContainsString('runPreviewSmokeForProject', $outputRenderTest);
        $this->assertStringContainsString('runPreviewSmoke', $outputRenderTest);
        $this->assertStringContainsString('preview-inspector', $outputRenderTest);

        $this->assertStringContainsString('test_it_scores_and_ranks_candidates_using_rule_based_quality_dimensions', $qualityScoringTest);
        $this->assertStringContainsString('funnel_readiness', $qualityScoringTest);
        $this->assertStringContainsString('mobile friendliness score', $qualityScoringTest);
        $this->assertStringContainsString('rankCandidates', $qualityScoringTest);

        $this->assertStringContainsString('test_it_allows_rollout_when_feature_flags_are_enabled_and_all_gates_pass_and_writes_audit_log', $rolloutControlTest);
        $this->assertStringContainsString('quality_score_below_threshold', $rolloutControlTest);
        $this->assertStringContainsString('fail-closed', strtolower($rolloutControlTest));

        $this->assertStringContainsString('test_ai_generated_page_can_be_opened_edited_and_republished_via_standard_cms_builder_endpoints', $generatedSiteEditabilityTest);
        $this->assertStringContainsString("'saved.no_parallel_storage'", $generatedSiteEditabilityTest);

        $this->assertStringContainsString('test_owner_can_apply_ai_content_patch_and_publish_revision', $aiPatchFlowTest);
        $this->assertStringContainsString('idempotency_key', $aiPatchFlowTest);
        $this->assertStringContainsString('test_ai_content_patch_honors_idempotency_key_replay', $aiPatchFlowTest);

        $this->assertStringContainsString('test_acceptance_no_cross_tenant_leakage_and_deterministic_replay_for_learning_enhanced_generation', $learningAcceptanceTest);
        $this->assertStringContainsString('test_acceptance_tenant_opt_out_disables_learning_application_and_replay_metadata', $learningAcceptanceTest);
        $this->assertStringContainsString('page_plan.reproducibility.output_fingerprint', $learningAcceptanceTest);
        $this->assertStringContainsString('tenant_opt_out', $learningAcceptanceTest);
    }
}
