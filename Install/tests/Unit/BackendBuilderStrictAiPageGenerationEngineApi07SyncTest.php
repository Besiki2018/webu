<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderStrictAiPageGenerationEngineApi07SyncTest extends TestCase
{
    public function test_api_07_audit_doc_locks_strict_ai_page_generation_engine_contract_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_STRICT_AI_PAGE_GENERATION_ENGINE_CONTRACT_AUDIT_API_07_2026_02_25.md');

        $inputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-input.v1.schema.json');
        $outputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json');
        $canonicalPageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $pageEngineDocPath = base_path('docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md');
        $placementDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_PLACEMENT_STYLING_ENGINE_V1.md');
        $validationDocPath = base_path('docs/architecture/CMS_AI_OUTPUT_VALIDATION_ENGINE_V1.md');
        $saveDocPath = base_path('docs/architecture/CMS_AI_OUTPUT_SAVE_ENGINE_V1.md');
        $renderDocPath = base_path('docs/architecture/CMS_AI_OUTPUT_RENDER_TEST_ENGINE_V1.md');
        $compatPolicyDocPath = base_path('docs/architecture/CMS_AI_GENERATION_COMPATIBILITY_POLICY_V1.md');
        $qualityDocPath = base_path('docs/architecture/CMS_AI_GENERATION_QUALITY_SCORING_ENGINE_V1.md');
        $rolloutDocPath = base_path('docs/architecture/CMS_AI_GENERATION_ROLLOUT_CONTROL_V1.md');

        $validateCommandPath = base_path('app/Console/Commands/ValidateAiGenerationPayload.php');
        $pageEnginePath = base_path('app/Services/CmsAiPageGenerationEngine.php');
        $themeEnginePath = base_path('app/Services/CmsAiThemeGenerationEngine.php');
        $pageServicePath = base_path('app/Services/CmsAiPageGenerationService.php');
        $placementEnginePath = base_path('app/Services/CmsAiComponentPlacementStylingEngine.php');
        $validationEnginePath = base_path('app/Services/CmsAiOutputValidationEngine.php');
        $saveEnginePath = base_path('app/Services/CmsAiOutputSaveEngine.php');
        $renderEnginePath = base_path('app/Services/CmsAiOutputRenderTestEngine.php');
        $qualityEnginePath = base_path('app/Services/CmsAiGenerationQualityScoringEngine.php');
        $rolloutServicePath = base_path('app/Services/CmsAiGenerationRolloutControlService.php');

        $payloadValidationCmdTestPath = base_path('tests/Feature/Cms/CmsAiGenerationPayloadValidationCommandTest.php');
        $pageEngineTestPath = base_path('tests/Unit/CmsAiPageGenerationEngineTest.php');
        $themeEngineTestPath = base_path('tests/Unit/CmsAiThemeGenerationEngineTest.php');
        $pageServiceTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');
        $placementEngineTestPath = base_path('tests/Unit/CmsAiComponentPlacementStylingEngineTest.php');
        $bindingRulesTestPath = base_path('tests/Unit/CmsAiBindingGenerationRulesPipelineTest.php');
        $outputValidationTestPath = base_path('tests/Feature/Cms/CmsAiOutputValidationEngineTest.php');
        $outputSaveTestPath = base_path('tests/Feature/Cms/CmsAiOutputSaveEngineTest.php');
        $outputRenderTestPath = base_path('tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php');
        $qualityScoringTestPath = base_path('tests/Unit/CmsAiGenerationQualityScoringEngineTest.php');
        $rolloutControlTestPath = base_path('tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php');
        $builderEditabilityTestPath = base_path('tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php');
        $learningAcceptanceTestPath = base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $inputSchemaPath,
            $outputSchemaPath,
            $canonicalPageNodeSchemaPath,
            $pageEngineDocPath,
            $placementDocPath,
            $validationDocPath,
            $saveDocPath,
            $renderDocPath,
            $compatPolicyDocPath,
            $qualityDocPath,
            $rolloutDocPath,
            $validateCommandPath,
            $pageEnginePath,
            $themeEnginePath,
            $pageServicePath,
            $placementEnginePath,
            $validationEnginePath,
            $saveEnginePath,
            $renderEnginePath,
            $qualityEnginePath,
            $rolloutServicePath,
            $payloadValidationCmdTestPath,
            $pageEngineTestPath,
            $themeEngineTestPath,
            $pageServiceTestPath,
            $placementEngineTestPath,
            $bindingRulesTestPath,
            $outputValidationTestPath,
            $outputSaveTestPath,
            $outputRenderTestPath,
            $qualityScoringTestPath,
            $rolloutControlTestPath,
            $builderEditabilityTestPath,
            $learningAcceptanceTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $inputSchema = File::get($inputSchemaPath);
        $outputSchema = File::get($outputSchemaPath);
        $canonicalPageNodeSchema = File::get($canonicalPageNodeSchemaPath);
        $pageEngineDoc = File::get($pageEngineDocPath);
        $placementDoc = File::get($placementDocPath);
        $validationDoc = File::get($validationDocPath);
        $saveDoc = File::get($saveDocPath);
        $renderDoc = File::get($renderDocPath);
        $compatPolicyDoc = File::get($compatPolicyDocPath);
        $qualityDoc = File::get($qualityDocPath);
        $rolloutDoc = File::get($rolloutDocPath);

        $validateCommand = File::get($validateCommandPath);
        $pageEngine = File::get($pageEnginePath);
        $themeEngine = File::get($themeEnginePath);
        $pageService = File::get($pageServicePath);
        $placementEngine = File::get($placementEnginePath);
        $validationEngine = File::get($validationEnginePath);
        $saveEngine = File::get($saveEnginePath);
        $renderEngine = File::get($renderEnginePath);
        $qualityEngine = File::get($qualityEnginePath);
        $rolloutService = File::get($rolloutServicePath);

        $payloadValidationCmdTest = File::get($payloadValidationCmdTestPath);
        $pageEngineTest = File::get($pageEngineTestPath);
        $themeEngineTest = File::get($themeEngineTestPath);
        $pageServiceTest = File::get($pageServiceTestPath);
        $placementEngineTest = File::get($placementEngineTestPath);
        $bindingRulesTest = File::get($bindingRulesTestPath);
        $outputValidationTest = File::get($outputValidationTestPath);
        $outputSaveTest = File::get($outputSaveTestPath);
        $outputRenderTest = File::get($outputRenderTestPath);
        $qualityScoringTest = File::get($qualityScoringTestPath);
        $rolloutControlTest = File::get($rolloutControlTestPath);
        $builderEditabilityTest = File::get($builderEditabilityTestPath);
        $learningAcceptanceTest = File::get($learningAcceptanceTestPath);

        // Source-spec anchors.
        $this->assertStringContainsString('CODEX PROMPT — Webu AI → Builder JSON Generator Engine (Deterministic Core)', $roadmap);
        $this->assertStringContainsString('2. OUTPUT FORMAT (STRICT)', $roadmap);
        $this->assertStringContainsString('No extra fields.', $roadmap);
        $this->assertStringContainsString('5. RESPONSIVE RULES', $roadmap);
        $this->assertStringContainsString('8. VALIDATION ENGINE (CRITICAL)', $roadmap);
        $this->assertStringContainsString('9. RENDER TEST ENGINE', $roadmap);
        $this->assertStringContainsString('10. PERFORMANCE REQUIREMENT', $roadmap);
        $this->assertStringContainsString('< 3 seconds', $roadmap);
        $this->assertStringContainsString('11. FUTURE SUPPORT', $roadmap);
        $this->assertStringContainsString('UPDATE mode', $roadmap);
        $this->assertStringContainsString('12. FINAL ACCEPTANCE CRITERIA', $roadmap);

        // Backlog closure.
        $this->assertStringContainsString('- `API-07` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_STRICT_AI_PAGE_GENERATION_ENGINE_CONTRACT_AUDIT_API_07_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderStrictAiPageGenerationEngineApi07SyncTest.php', $backlog);

        // Audit doc structure + truthful findings.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:3604',
            'PROJECT_ROADMAP_TASKS_KA.md:3962',
            '## Capability Matrix (Spec items `1`-`12`)',
            '## Spec `2` Output Format (STRICT) Audit',
            'No extra fields',
            'fragment engine',
            'minimal internal output envelope',
            '## Spec `8` Validation Engine (CRITICAL) Audit',
            '## Spec `9` Render Test Engine Audit',
            '## Spec `10` Performance Requirement (`< 3 seconds`) Audit',
            'No audited hard performance gate',
            '## Spec `11` Future Support (UPDATE mode) Audit',
            '## Spec `12` Final Acceptance Criteria Audit',
            'no parallel storage',
            '`API-07` is **DONE as an audit/verification task**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $docAnchors = [
            'Install/docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            'Install/docs/architecture/schemas/cms-ai-generation-output.v1.schema.json',
            'Install/docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
            'Install/docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_COMPONENT_PLACEMENT_STYLING_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_OUTPUT_VALIDATION_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_OUTPUT_SAVE_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_OUTPUT_RENDER_TEST_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_GENERATION_COMPATIBILITY_POLICY_V1.md',
            'Install/docs/architecture/CMS_AI_GENERATION_QUALITY_SCORING_ENGINE_V1.md',
            'Install/docs/architecture/CMS_AI_GENERATION_ROLLOUT_CONTROL_V1.md',
            'Install/app/Services/CmsAiPageGenerationEngine.php',
            'Install/app/Services/CmsAiThemeGenerationEngine.php',
            'Install/app/Services/CmsAiPageGenerationService.php',
            'Install/app/Services/CmsAiComponentPlacementStylingEngine.php',
            'Install/app/Services/CmsAiOutputValidationEngine.php',
            'Install/app/Services/CmsAiOutputSaveEngine.php',
            'Install/app/Services/CmsAiOutputRenderTestEngine.php',
            'Install/app/Services/CmsAiGenerationQualityScoringEngine.php',
            'Install/app/Services/CmsAiGenerationRolloutControlService.php',
            'Install/tests/Unit/CmsAiPageGenerationEngineTest.php',
            'Install/tests/Unit/CmsAiComponentPlacementStylingEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputValidationEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputSaveEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiOutputRenderTestEngineTest.php',
            'Install/tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php',
        ];

        foreach ($docAnchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing API-07 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing API-07 evidence file on disk: {$relativePath}");
        }

        // Schema strictness and canonical node contracts.
        $this->assertStringContainsString('"additionalProperties": false', $outputSchema);
        $this->assertStringContainsString('"schema_version"', $outputSchema);
        $this->assertStringContainsString('"theme"', $outputSchema);
        $this->assertStringContainsString('"pages"', $outputSchema);
        $this->assertStringContainsString('"header"', $outputSchema);
        $this->assertStringContainsString('"footer"', $outputSchema);
        $this->assertStringContainsString('"meta"', $outputSchema);
        $this->assertStringContainsString('"strict_top_level"', $outputSchema);
        $this->assertStringContainsString('"component_availability_check_required"', $outputSchema);
        $this->assertStringContainsString('"binding_validation_required"', $outputSchema);

        $this->assertStringContainsString('"required": [', $canonicalPageNodeSchema);
        $this->assertStringContainsString('"bindings"', $canonicalPageNodeSchema);
        $this->assertStringContainsString('"responsive"', $canonicalPageNodeSchema);
        $this->assertStringContainsString('"states"', $canonicalPageNodeSchema);
        $this->assertStringContainsString('"schema_version"', $canonicalPageNodeSchema);

        // Architecture docs lock current deterministic split and strict gate semantics.
        $this->assertStringContainsString('# CMS AI Page Generation Engine v1', $pageEngineDoc);
        $this->assertStringContainsString('P4-E2-02', $pageEngineDoc);
        $this->assertStringContainsString('builder-native page output fragments', $pageEngineDoc);
        $this->assertStringContainsString('no parallel AI page storage', $pageEngineDoc);

        $this->assertStringContainsString('# CMS AI Component Placement & Styling Engine v1', $placementDoc);
        $this->assertStringContainsString('P4-E2-03', $placementDoc);
        $this->assertStringContainsString('cms-ai-generation-output.v1', $placementDoc);
        $this->assertStringContainsString('no parallel AI page storage', $placementDoc);

        $this->assertStringContainsString('# CMS AI Output Validation Engine v1', $validationDoc);
        $this->assertStringContainsString('component availability', $validationDoc);
        $this->assertStringContainsString('binding validation', $validationDoc);
        $this->assertStringContainsString('strict top-level envelope', strtolower($validationDoc));

        $this->assertStringContainsString('# CMS AI Output Save Engine v1', $saveDoc);
        $this->assertStringContainsString('no parallel page storage', $saveDoc);
        $this->assertStringContainsString('page_revisions.content_json', $saveDoc);

        $this->assertStringContainsString('# CMS AI Output Render Test Engine v1', $renderDoc);
        $this->assertStringContainsString('__cms/bootstrap', $renderDoc);
        $this->assertStringContainsString('preview.serve', $renderDoc);

        $this->assertStringContainsString('Strict top-level envelope (`theme`, `pages`, `header`, `footer`, `meta`) is required; extra top-level keys => `incompatible`.', $compatPolicyDoc);
        $this->assertStringContainsString('mobile friendliness score', strtolower($qualityDoc));
        $this->assertStringContainsString('cms_ai_generation_min_quality_score', $rolloutDoc);

        // Core service truths.
        $this->assertStringContainsString('public function generateFromAiInput(array $input): array', $pageEngine);
        $this->assertStringContainsString('request.mode=generate_theme; page generation engine returned no page fragments.', $pageEngine);
        $this->assertStringContainsString('validateOutputPayload($this->minimalOutputEnvelope($pagesOutput))', $pageEngine);
        $this->assertStringContainsString("'strict_top_level' => true", $pageEngine);
        $this->assertStringContainsString("'component_availability_check_required' => true", $pageEngine);
        $this->assertStringContainsString("'binding_validation_required' => true", $pageEngine);
        $this->assertStringContainsString('private function ecommerceCorePageSlugs(): array', $pageEngine);
        $this->assertStringContainsString("return ['home', 'shop', 'product', 'cart', 'checkout'];", $pageEngine);
        $this->assertStringContainsString('in_array($mode, [\'generate_site\', \'edit_site\'], true)', $pageEngine);
        $this->assertStringContainsString('if ($mode === \'edit_page\') {', $pageEngine);

        $this->assertStringContainsString('preserve_theme_settings', $themeEngine);
        $this->assertStringContainsString('keep_existing', $themeEngine);

        $this->assertStringContainsString("Log::info('cms.ai.generation.trace'", $pageService);
        $this->assertStringContainsString('\'duration_ms\' => $durationMs', $pageService);

        $this->assertStringContainsString('public function applyToPagesOutput(array $input, array $pagesOutput, array $themeOutput = []): array', $placementEngine);
        $this->assertStringContainsString('\'grid_columns_desktop\' => $pageType === \'home\' ? 4 : 3', $placementEngine);
        $this->assertStringContainsString("'grid_columns_tablet' => 2", $placementEngine);
        $this->assertStringContainsString("'grid_columns_mobile' => 1", $placementEngine);
        $this->assertStringContainsString("'resource' => 'auth.session'", $placementEngine);

        $this->assertStringContainsString('public function validateOutputForSite(Site $site, array $aiOutput, array $options = []): array', $validationEngine);
        $this->assertStringContainsString("'code' => 'missing_enabled_fixed_section_type'", $validationEngine);
        $this->assertStringContainsString("'code' => 'invalid_required_route_binding'", $validationEngine);

        $this->assertStringContainsString('public function persistOutputForSite(Site $site, array $aiOutput, ?int $actorId = null, array $options = []): array', $saveEngine);
        $this->assertStringContainsString("'storage_channels' => [", $saveEngine);
        $this->assertStringContainsString("'no_parallel_storage' => true", $saveEngine);

        $this->assertStringContainsString('public function runPreviewSmokeForProject(Project $project, array $options = []): array', $renderEngine);
        $this->assertStringContainsString("'code' => 'preview_asset_missing'", $renderEngine);
        $this->assertStringContainsString("'path' => '__cms/bootstrap'", $renderEngine);

        $this->assertStringContainsString('public function scoreOutput(array $aiOutput, array $context = []): array', $qualityEngine);
        $this->assertStringContainsString('public function rankCandidates(array $candidates): array', $qualityEngine);
        $this->assertStringContainsString('public function evaluateAndAudit(Project $project, array $reports = [], ?User $actor = null, array $options = []): array', $rolloutService);
        $this->assertStringContainsString('FLAG_MIN_QUALITY_SCORE', $rolloutService);

        $this->assertStringContainsString('protected $signature = \'cms:ai-validate-payload', $validateCommand);

        // Test evidence locks.
        $this->assertStringContainsString("'contract' => 'input'", $payloadValidationCmdTest);
        $this->assertStringContainsString("'contract' => 'output'", $payloadValidationCmdTest);

        $this->assertStringContainsString('test_it_generates_builder_native_pages_with_route_metadata_and_canonical_nodes', $pageEngineTest);
        $this->assertStringContainsString('$this->assertIsArray(data_get($home, \'builder_nodes.0.props.responsive\'));', $pageEngineTest);
        $this->assertStringContainsString('$this->assertSame(\'{{route.params.slug}}\', data_get($product, \'builder_nodes.0.bindings.product_slug\'));', $pageEngineTest);
        $this->assertStringContainsString('test_it_respects_target_page_slugs_in_edit_page_mode_and_marks_keep_existing', $pageEngineTest);

        $this->assertStringContainsString('preserve_theme_settings=true; returned keep_existing theme output with no patch.', $themeEngineTest);

        $this->assertStringContainsString('test_it_emits_structured_ai_generation_trace_log_for_page_generation', $pageServiceTest);
        $this->assertStringContainsString("'cms.ai.generation.trace'", $pageServiceTest);
        $this->assertStringContainsString('$context[\'duration_ms\']', $pageServiceTest);

        $this->assertStringContainsString('test_it_applies_deterministic_component_placement_and_styling_rules_to_generated_pages', $placementEngineTest);
        $this->assertStringContainsString('webu-ai-placement:v1', $placementEngineTest);
        $this->assertStringContainsString("grid_columns_desktop", $placementEngineTest);
        $this->assertStringContainsString("data-webu-ai-priority", $placementEngineTest);

        $this->assertStringContainsString('ecommerce.checkout', $bindingRulesTest);
        $this->assertStringContainsString('auth.session', $bindingRulesTest);
        $this->assertStringContainsString('{{route.params.slug}}', $bindingRulesTest);

        $this->assertStringContainsString('missing_enabled_fixed_section_type', $outputValidationTest);
        $this->assertStringContainsString('invalid_required_route_binding', $outputValidationTest);
        $this->assertStringContainsString('unsupported_namespace', $outputValidationTest);

        $this->assertStringContainsString('persists_ai_output_into_current_site_page_and_revision_models_without_parallel_storage', $outputSaveTest);
        $this->assertStringContainsString("'saved.no_parallel_storage'", $outputSaveTest);
        $this->assertStringContainsString("'saved.storage_channels'", $outputSaveTest);
        $this->assertStringContainsString('CmsAiOutputSaveEngine', $outputSaveTest);

        $this->assertStringContainsString('runPreviewSmokeForProject($project->fresh()', $outputRenderTest);
        $this->assertStringContainsString('preview_asset_missing', $outputRenderTest);
        $this->assertStringContainsString('__cms/bootstrap', $outputRenderTest);
        $this->assertStringContainsString('id="preview-inspector"', $outputRenderTest);

        $this->assertStringContainsString('scoreOutput($good)', $qualityScoringTest);
        $this->assertStringContainsString('rankCandidates([', $qualityScoringTest);
        $this->assertStringContainsString('mobile friendliness score', strtolower($qualityScoringTest));

        $this->assertStringContainsString('evaluateAndAudit($project', $rolloutControlTest);
        $this->assertStringContainsString('FLAG_MIN_QUALITY_SCORE', $rolloutControlTest);

        $this->assertStringContainsString('test_ai_generated_page_can_be_opened_edited_and_republished_via_standard_cms_builder_endpoints', $builderEditabilityTest);
        $this->assertStringContainsString("'saved.no_parallel_storage'", $builderEditabilityTest);
        $this->assertStringContainsString('latest_revision.content_json.ai_generation.saved_via', $builderEditabilityTest);

        $this->assertStringContainsString('test_acceptance_no_cross_tenant_leakage_and_deterministic_replay_for_learning_enhanced_generation', $learningAcceptanceTest);
        $this->assertStringContainsString('test_acceptance_tenant_opt_out_disables_learning_application_and_replay_metadata', $learningAcceptanceTest);
    }
}
