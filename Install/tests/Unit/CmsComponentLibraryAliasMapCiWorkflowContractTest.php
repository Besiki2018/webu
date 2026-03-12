<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsComponentLibraryAliasMapCiWorkflowContractTest extends TestCase
{
    public function test_ci_workflow_includes_alias_map_validation_export_and_contract_locks(): void
    {
        $workflowPath = base_path('../.github/workflows/component-library-alias-map-hygiene.yml');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');

        $this->assertFileExists($workflowPath);
        $this->assertFileExists($docPath);

        $workflow = File::get($workflowPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('name: Component Library Alias Map Hygiene', $workflow);
        $this->assertStringContainsString('working-directory: Install', $workflow);
        $this->assertStringContainsString('composer install', $workflow);
        $this->assertStringContainsString('php artisan key:generate --force', $workflow);

        $this->assertStringContainsString('cms:component-library-alias-map-validate --ci-baseline', $workflow);
        $this->assertStringContainsString('cms:component-library-alias-map-validate --fingerprints --json', $workflow);
        $this->assertStringContainsString('cms:component-library-alias-map-validate --strict-export-schema --json', $workflow);
        $this->assertStringContainsString('--export-json --output=ci/alias-map.json --overwrite', $workflow);
        $this->assertStringContainsString('--export-csv --output=ci/alias-map.csv --overwrite', $workflow);
        $this->assertStringContainsString('--export-md --output=ci/alias-map.md --overwrite', $workflow);

        $this->assertStringContainsString('CmsComponentLibraryAliasMapValidationCommandTest', $workflow);
        $this->assertStringContainsString('CmsComponentLibrarySpecEquivalenceAliasMapServiceTest', $workflow);
        $this->assertStringContainsString('UniversalComponentLibrarySpecEquivalenceAliasMapTest', $workflow);
        $this->assertStringContainsString('UniversalComponentLibrarySpecEquivalenceAliasMapSchemaTest', $workflow);
        $this->assertStringContainsString('UniversalComponentLibrarySpecEquivalenceAliasMapExportSchemaTest', $workflow);
        $this->assertStringContainsString('UniversalComponentLibrarySourceSpecCompletionSummaryTest', $workflow);

        $this->assertStringContainsString('component-library-alias-map-hygiene.yml', $doc);
    }
}
