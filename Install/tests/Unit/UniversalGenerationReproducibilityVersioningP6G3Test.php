<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalGenerationReproducibilityVersioningP6G3Test extends TestCase
{
    public function test_p6_g3_02_generation_reproducibility_versioning_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_GENERATION_REPRODUCIBILITY_VERSIONING_P6_G3_02.md');
        $learnedRuleServicePath = base_path('app/Services/CmsAiLearnedRuleApplicationService.php');
        $pageGenServicePath = base_path('app/Services/CmsAiPageGenerationService.php');
        $schemaPath = base_path('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $learnedRuleServicePath, $pageGenServicePath, $schemaPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $learnedRuleService = File::get($learnedRuleServicePath);
        $pageGenService = File::get($pageGenServicePath);
        $schema = File::get($schemaPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G3-02', $doc);
        $this->assertStringContainsString('reproducibility', strtolower($doc));
        $this->assertStringContainsString('eligible_rule_set_version', $doc);
        $this->assertStringContainsString('output_fingerprint', $doc);
        $this->assertStringContainsString('replay_key', $doc);
        $this->assertStringContainsString('P6-G3-04', $doc);

        $this->assertStringContainsString('RULE_VERSIONING_VERSION', $learnedRuleService);
        $this->assertStringContainsString('eligible_rule_set_version', $learnedRuleService);
        $this->assertStringContainsString('matched_rule_set_version', $learnedRuleService);
        $this->assertStringContainsString('applied_rule_set_version', $learnedRuleService);
        $this->assertStringContainsString('selection_order_version', $learnedRuleService);

        $this->assertStringContainsString('REPRODUCIBILITY_VERSION', $pageGenService);
        $this->assertStringContainsString('buildGenerationReproducibilityMetadata(', $pageGenService);
        $this->assertStringContainsString("'reproducibility' => \$reproducibilitySummary", $pageGenService);
        $this->assertStringContainsString("'replay_key'", $pageGenService);
        $this->assertStringContainsString("'output_fingerprint'", $pageGenService);

        $this->assertStringContainsString('"reproducibility"', $schema);
        $this->assertStringContainsString('"page_fingerprint"', $schema);
        $this->assertStringContainsString('"learned_rule_set_version"', $schema);

        $this->assertStringContainsString("`P6-G3-02` (✅ `DONE`) Version learned rules and generation outputs for reproducibility.", $roadmap);
    }
}
