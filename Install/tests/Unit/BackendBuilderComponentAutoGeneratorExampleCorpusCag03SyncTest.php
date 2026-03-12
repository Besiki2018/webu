<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest extends TestCase
{
    public function test_cag_03_audit_doc_locks_example_corpus_and_generator_usage_flow_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md');

        $fixtureDir = base_path('tests/Fixtures/CmsAiComponentFeatureSpecExamples');
        $fixturePaths = [
            $fixtureDir.'/wishlist.json',
            $fixtureDir.'/reviews.json',
            $fixtureDir.'/subscriptions.json',
            $fixtureDir.'/loyalty.json',
            $fixtureDir.'/compare.json',
        ];

        $parserServicePath = base_path('app/Services/CmsAiComponentFeatureSpecParser.php');
        $factoryServicePath = base_path('app/Services/CmsAiComponentFactoryGenerator.php');
        $parserDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md');
        $factoryDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md');
        $schemaPath = base_path('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json');

        $corpusRegressionTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php');
        $parserTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecParserTest.php');
        $schemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');

        foreach (array_merge([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $fixtureDir,
            $parserServicePath,
            $factoryServicePath,
            $parserDocPath,
            $factoryDocPath,
            $schemaPath,
            $corpusRegressionTestPath,
            $parserTestPath,
            $schemaContractsTestPath,
        ], $fixturePaths) as $path) {
            if (str_ends_with($path, 'CmsAiComponentFeatureSpecExamples')) {
                $this->assertDirectoryExists($path);
            } else {
                $this->assertFileExists($path);
            }
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $parserService = File::get($parserServicePath);
        $factoryService = File::get($factoryServicePath);
        $parserDoc = File::get($parserDocPath);
        $factoryDoc = File::get($factoryDocPath);
        $schema = File::get($schemaPath);
        $corpusRegressionTest = File::get($corpusRegressionTestPath);
        $parserTest = File::get($parserTestPath);
        $schemaContractsTest = File::get($schemaContractsTestPath);

        // Source-spec corpus and usage-flow anchors.
        foreach ([
            '3. FEATURE SPEC EXAMPLE #1 — WISHLIST',
            '4. FEATURE SPEC EXAMPLE #2 — PRODUCT REVIEWS',
            '5. FEATURE SPEC EXAMPLE #3 — SUBSCRIPTIONS',
            '6. FEATURE SPEC EXAMPLE #4 — LOYALTY POINTS',
            '7. FEATURE SPEC EXAMPLE #5 — PRODUCT COMPARE',
            '8. HOW GENERATOR USES THIS',
            'FINAL RESULT',
            'Builder components',
            'API bindings',
            'Renderer',
            'Automatically.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence links.
        $this->assertStringContainsString('- `CAG-03` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md', $backlog);
        $this->assertStringContainsString('CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php', $backlog);
        $this->assertStringContainsString('BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest.php', $backlog);

        // Audit doc structure + truthful findings.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:4480',
            'PROJECT_ROADMAP_TASKS_KA.md:4798',
            '## Executive Result (`CAG-03`)',
            '`CAG-03` is **complete as an audit/verification task**',
            'machine-checkable JSON fixtures',
            'missing `permissions`',
            'missing endpoint `name/auth` for `compare`',
            'The roadmap statement “How generator uses this” is only partially true',
            'CmsAiComponentFactoryGenerator',
            'CmsAiFeatureSpecParser',
            'no audited direct adapter',
            '## Example Corpus Coverage Matrix (`3`-`7`)',
            '`#5 Product Compare`',
            'GetCompare',
            '## Generator Usage Flow Audit (Source Section `8`)',
            'partially implemented / split across parser + generator + renderer/preflight layers',
            '## Corpus Coverage Note For Future Add-Ons',
            'returns',
            'gift_registry',
            '## DoD Verdict (`CAG-03`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/wishlist.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/reviews.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/subscriptions.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/loyalty.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/compare.json',
            'Install/tests/Unit/CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php',
            'Install/app/Services/CmsAiComponentFeatureSpecParser.php',
            'Install/app/Services/CmsAiComponentFactoryGenerator.php',
            'Install/docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md',
            'Install/docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md',
            'Install/docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json',
            'Install/tests/Unit/CmsAiComponentFeatureSpecParserTest.php',
            'Install/tests/Unit/CmsCanonicalSchemaContractsTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing CAG-03 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing CAG-03 evidence file on disk: {$relativePath}");
        }

        // Fixture corpus sanity.
        foreach ([
            '"feature_key": "wishlist"',
            '"feature_key": "reviews"',
            '"feature_key": "subscriptions"',
            '"feature_key": "loyalty"',
            '"feature_key": "compare"',
        ] as $needle) {
            $fixturesCombined = collect($fixturePaths)->map(fn (string $path): string => File::get($path))->implode("\n\n");
            $this->assertStringContainsString($needle, $fixturesCombined);
        }

        // Parser/generator split truth.
        $this->assertStringContainsString('class CmsAiComponentFeatureSpecParser', $parserService);
        $this->assertStringContainsString('public function parseJsonString(string $json, array $options = []): array', $parserService);
        $this->assertStringContainsString('ui.primary / ui.secondary', $parserService);
        $this->assertStringContainsString('public function generateFromRawSpec(array $rawFeatureSpec, array $options = []): array', $factoryService);
        $this->assertStringContainsString('protected CmsAiFeatureSpecParser $featureSpecParser', $factoryService);

        // Docs + schema anchors for corpus compatibility.
        $this->assertStringContainsString('# CMS AI Component Feature Spec Parser v1', $parserDoc);
        $this->assertStringContainsString('wishlist', strtolower($parserDoc));
        $this->assertStringContainsString('reviews', strtolower($parserDoc));
        $this->assertStringContainsString('subscriptions', strtolower($parserDoc));
        $this->assertStringContainsString('ecom.<feature_key>.<component>', $parserDoc);
        $this->assertStringContainsString('# CMS AI Component Factory Generator v1', $factoryDoc);
        $this->assertStringContainsString('P4-E4-02', $factoryDoc);
        $this->assertStringContainsString('"additionalProperties": false', $schema);
        $this->assertStringContainsString('"ui_intent"', $schema);
        $this->assertStringContainsString('"generator_hints"', $schema);

        // Regression suite and supporting parser/schema tests.
        $this->assertStringContainsString('test_source_example_fixtures_are_parser_compatible_and_machine_checkable', $corpusRegressionTest);
        $this->assertStringContainsString('test_corpus_fixtures_cover_all_roadmap_examples_wishlist_reviews_subscriptions_loyalty_and_compare', $corpusRegressionTest);
        $this->assertStringContainsString('exampleFixtureProvider', $corpusRegressionTest);
        $this->assertStringContainsString('wishlist.json', $corpusRegressionTest);
        $this->assertStringContainsString('reviews.json', $corpusRegressionTest);
        $this->assertStringContainsString('subscriptions.json', $corpusRegressionTest);
        $this->assertStringContainsString('loyalty.json', $corpusRegressionTest);
        $this->assertStringContainsString('compare.json', $corpusRegressionTest);
        $this->assertStringContainsString('ecom.loyalty.balance', $corpusRegressionTest);
        $this->assertStringContainsString('GetCompare', $corpusRegressionTest);

        $this->assertStringContainsString('test_it_parses_legacy_ui_alias_format_and_derives_endpoint_defaults_for_generator', $parserTest);
        $this->assertStringContainsString('test_it_parses_ui_intent_feature_spec_into_canonical_auto_generator_contract', $parserTest);
        $this->assertStringContainsString('test_ai_component_feature_spec_v1_schema_exists_and_requires_endpoints_ui_intent_and_generator_hints', $schemaContractsTest);
    }
}
