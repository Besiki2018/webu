<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalAbExperimentationAssignmentP6G2Test extends TestCase
{
    public function test_p6_g2_01_ab_experimentation_model_and_assignment_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_AB_EXPERIMENTATION_MODEL_ASSIGNMENT_P6_G2_01.md');
        $migrationPath = base_path('database/migrations/2026_02_24_234000_create_cms_experimentation_tables.php');
        $experimentModelPath = base_path('app/Models/CmsExperiment.php');
        $variantModelPath = base_path('app/Models/CmsExperimentVariant.php');
        $assignmentModelPath = base_path('app/Models/CmsExperimentAssignment.php');
        $servicePath = base_path('app/Services/CmsExperimentAssignmentService.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $migrationPath, $experimentModelPath, $variantModelPath, $assignmentModelPath, $servicePath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migration = File::get($migrationPath);
        $experimentModel = File::get($experimentModelPath);
        $variantModel = File::get($variantModelPath);
        $assignmentModel = File::get($assignmentModelPath);
        $service = File::get($servicePath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G2-01', $doc);
        $this->assertStringContainsString('cms_experiments', $doc);
        $this->assertStringContainsString('cms_experiment_variants', $doc);
        $this->assertStringContainsString('cms_experiment_assignments', $doc);
        $this->assertStringContainsString('stable per session/device', $doc);
        $this->assertStringContainsString('deterministic_weighted_hash_v1', $doc);
        $this->assertStringContainsString('traffic_percent', $doc);
        $this->assertStringContainsString('P6-G2-02', $doc);

        $this->assertStringContainsString("Schema::create('cms_experiments'", $migration);
        $this->assertStringContainsString("Schema::create('cms_experiment_variants'", $migration);
        $this->assertStringContainsString("Schema::create('cms_experiment_assignments'", $migration);
        $this->assertStringContainsString('$table->char(\'subject_hash\', 64)', $migration);
        $this->assertStringContainsString('$table->unsignedTinyInteger(\'traffic_percent\')', $migration);

        $this->assertStringContainsString('class CmsExperiment extends Model', $experimentModel);
        $this->assertStringContainsString("'targeting_json' => 'array'", $experimentModel);
        $this->assertStringContainsString('variants(): HasMany', $experimentModel);

        $this->assertStringContainsString('class CmsExperimentVariant extends Model', $variantModel);
        $this->assertStringContainsString("'payload_json' => 'array'", $variantModel);

        $this->assertStringContainsString('class CmsExperimentAssignment extends Model', $assignmentModel);
        $this->assertStringContainsString("'context_json' => 'array'", $assignmentModel);
        $this->assertStringContainsString("'assigned_at' => 'datetime'", $assignmentModel);

        $this->assertStringContainsString('class CmsExperimentAssignmentService', $service);
        $this->assertStringContainsString('assignActiveExperimentsForRequest(Site $site, Request $request, array $context = [])', $service);
        $this->assertStringContainsString('assignForExperiment(Site $site, CmsExperiment $experiment, Request $request, array $context = [])', $service);
        $this->assertStringContainsString('deterministic_weighted_hash_v1', $service);
        $this->assertStringContainsString('outside_traffic_allocation', $service);
        $this->assertStringContainsString('session_or_device', $service);

        $this->assertStringContainsString('- ✅ Experimentation framework (A/B)', $roadmap);
        $this->assertStringContainsString("`P6-G2-01` (✅ `DONE`) A/B experimentation model and assignment rules.", $roadmap);
    }
}
