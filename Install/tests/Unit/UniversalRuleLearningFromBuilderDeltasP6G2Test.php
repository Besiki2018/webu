<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalRuleLearningFromBuilderDeltasP6G2Test extends TestCase
{
    public function test_p6_g2_02_rule_learning_from_builder_deltas_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_RULE_LEARNING_FROM_BUILDER_DELTAS_P6_G2_02.md');
        $migrationPath = base_path('database/migrations/2026_02_24_235000_create_cms_learned_rules_table.php');
        $modelPath = base_path('app/Models/CmsLearnedRule.php');
        $servicePath = base_path('app/Services/CmsRuleLearningFromBuilderDeltasService.php');
        $commandPath = base_path('app/Console/Commands/LearnCmsRulesFromBuilderDeltas.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $migrationPath, $modelPath, $servicePath, $commandPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migration = File::get($migrationPath);
        $model = File::get($modelPath);
        $service = File::get($servicePath);
        $command = File::get($commandPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G2-02', $doc);
        $this->assertStringContainsString('cms_learned_rules', $doc);
        $this->assertStringContainsString('builder deltas', strtolower($doc));
        $this->assertStringContainsString('cluster common fixes', strtolower($doc));
        $this->assertStringContainsString('conditions_json', $doc);
        $this->assertStringContainsString('patch_json', $doc);
        $this->assertStringContainsString('compare conversion_rate before/after applying rule', $doc);
        $this->assertStringContainsString('P6-G2-03', $doc);

        $this->assertStringContainsString("Schema::create('cms_learned_rules'", $migration);
        $this->assertStringContainsString('$table->json(\'conditions_json\')', $migration);
        $this->assertStringContainsString('$table->json(\'patch_json\')', $migration);
        $this->assertStringContainsString('$table->decimal(\'confidence\'', $migration);
        $this->assertStringContainsString('$table->boolean(\'active\')', $migration);

        $this->assertStringContainsString('class CmsLearnedRule extends Model', $model);
        $this->assertStringContainsString("'conditions_json' => 'array'", $model);
        $this->assertStringContainsString("'patch_json' => 'array'", $model);
        $this->assertStringContainsString("'confidence' => 'decimal:4'", $model);

        $this->assertStringContainsString('class CmsRuleLearningFromBuilderDeltasService', $service);
        $this->assertStringContainsString('learnCandidateRules(', $service);
        $this->assertStringContainsString('builder_delta_cluster', $service);
        $this->assertStringContainsString('deterministic', strtolower($service));
        $this->assertStringContainsString('json_patch_template', $service);
        $this->assertStringContainsString('prompt_intent_tags', $service);

        $this->assertStringContainsString('class LearnCmsRulesFromBuilderDeltas extends Command', $command);
        $this->assertStringContainsString('cms:learn-rules-from-builder-deltas', $command);
        $this->assertStringContainsString('cms.learning.cluster_builder_deltas', $command);

        $this->assertStringContainsString("`P6-G2-02` (✅ `DONE`) Rule learning from builder deltas (cluster common fixes).", $roadmap);
    }
}
