<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsComponentLibraryAliasMapConvergenceCiWorkflowContractTest extends TestCase
{
    public function test_ci_workflow_includes_convergence_smoke_exports_and_contract_locks(): void
    {
        $workflowPath = base_path('../.github/workflows/component-library-alias-map-convergence-hygiene.yml');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');

        $this->assertFileExists($workflowPath);
        $this->assertFileExists($docPath);

        $workflow = File::get($workflowPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('name: Component Library Alias Map Convergence Hygiene', $workflow);
        $this->assertStringContainsString('working-directory: Install', $workflow);
        $this->assertStringContainsString('composer install', $workflow);
        $this->assertStringContainsString('php artisan key:generate --force', $workflow);

        $this->assertStringContainsString('cms:component-library-alias-map-convergence --json --limit=5', $workflow);
        $this->assertStringContainsString('cms:component-library-alias-map-convergence --source-key=basic.heading --assert-max-blocked=1', $workflow);
        $this->assertStringContainsString('--export-json --output=ci/convergence-report.json --overwrite', $workflow);
        $this->assertStringContainsString('--export-patch-preview --output=ci/convergence-patch-preview.json --overwrite', $workflow);

        $this->assertStringContainsString('CmsComponentLibraryAliasMapConvergenceCommandTest', $workflow);
        $this->assertStringContainsString('CmsComponentLibraryAliasMapConvergenceCiWorkflowContractTest', $workflow);

        $this->assertStringContainsString('.github/workflows/component-library-alias-map-convergence-hygiene.yml', $doc);
        $this->assertStringContainsString('cms:component-library-alias-map-convergence', $doc);
        $this->assertStringContainsString('CMS_COMPONENT_LIBRARY_ALIAS_MAP_EXACTNESS_CONVERGENCE_REPORT_HC_H1_01_2026_02_25.md', $doc);
    }
}
