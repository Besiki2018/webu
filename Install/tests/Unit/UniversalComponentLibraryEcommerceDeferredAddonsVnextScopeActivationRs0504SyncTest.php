<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceDeferredAddonsVnextScopeActivationRs0504SyncTest extends TestCase
{
    public function test_rs_05_04_completion_audit_doc_locks_deferred_addons_vnext_scope_and_activation_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_DEFERRED_ADDONS_VNEXT_SCOPE_ACTIVATION_AUDIT_RS_05_04_2026_02_25.md');

        $rs0501DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $rs0502DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');

        $cag02DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md');
        $cag03DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md');

        $cag02SyncTestPath = base_path('tests/Unit/BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest.php');
        $cag03SyncTestPath = base_path('tests/Unit/BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest.php');
        $corpusRegressionTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php');
        $parserTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecParserTest.php');
        $factoryGeneratorTestPath = base_path('tests/Unit/CmsAiComponentFactoryGeneratorTest.php');

        $fixtureDir = base_path('tests/Fixtures/CmsAiComponentFeatureSpecExamples');
        $fixturePaths = [
            $fixtureDir.'/wishlist.json',
            $fixtureDir.'/reviews.json',
            $fixtureDir.'/subscriptions.json',
            $fixtureDir.'/loyalty.json',
            $fixtureDir.'/compare.json',
        ];

        foreach (array_merge([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $rs0501DocPath,
            $rs0502DocPath,
            $rs0503DocPath,
            $cag02DocPath,
            $cag03DocPath,
            $cag02SyncTestPath,
            $cag03SyncTestPath,
            $corpusRegressionTestPath,
            $parserTestPath,
            $factoryGeneratorTestPath,
            $fixtureDir,
        ], $fixturePaths) as $path) {
            if ($path === $fixtureDir) {
                $this->assertDirectoryExists($path);
            } else {
                $this->assertFileExists($path);
            }
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $rs0501Doc = File::get($rs0501DocPath);
        $rs0502Doc = File::get($rs0502DocPath);
        $rs0503Doc = File::get($rs0503DocPath);
        $cag02Doc = File::get($cag02DocPath);
        $cag03Doc = File::get($cag03DocPath);
        $corpusRegressionTest = File::get($corpusRegressionTestPath);
        $parserTest = File::get($parserTestPath);
        $factoryGeneratorTest = File::get($factoryGeneratorTestPath);

        foreach ([
            '(Ready for auto-gen add-ons: wishlist, reviews, compare, loyalty, subscriptions)',
            'When a new backend module/endpoint is added (e.g. wishlist, reviews, loyalty points, subscriptions),',
            '3. FEATURE SPEC EXAMPLE #1 — WISHLIST',
            '4. FEATURE SPEC EXAMPLE #2 — PRODUCT REVIEWS',
            '5. FEATURE SPEC EXAMPLE #3 — SUBSCRIPTIONS',
            '6. FEATURE SPEC EXAMPLE #4 — LOYALTY POINTS',
            '7. FEATURE SPEC EXAMPLE #5 — PRODUCT COMPARE',
            '8. HOW GENERATOR USES THIS',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-05-04` (`DONE`, `P2`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_DEFERRED_ADDONS_VNEXT_SCOPE_ACTIVATION_AUDIT_RS_05_04_2026_02_25.md',
            'UniversalComponentLibraryEcommerceDeferredAddonsVnextScopeActivationRs0504SyncTest.php',
            'WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md',
            'CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php',
            'CmsAiComponentFeatureSpecParserTest.php',
            'CmsAiComponentFactoryGeneratorTest.php',
            'CmsAiComponentFeatureSpecExamples/wishlist.json',
            'CmsAiComponentFeatureSpecExamples/reviews.json',
            'CmsAiComponentFeatureSpecExamples/subscriptions.json',
            'CmsAiComponentFeatureSpecExamples/loyalty.json',
            'CmsAiComponentFeatureSpecExamples/compare.json',
            '`✅` explicit v1 out-of-scope statement documented for deferred ecommerce add-ons (`wishlist`, `reviews`, `compare`, `loyalty`, `subscriptions`)',
            '`✅` activation criteria matrix documented (backend contract, auth mode, builder schemas/runtime hooks, OpenAPI, tests, AI feature-spec corpus readiness)',
            '`✅` per-add-on vNext task slices/priorities documented with dependency notes from `RS-05-01`/`RS-05-02`/`RS-05-03` audits',
            '`✅` roadmap auto-generator example corpus + parser/generator evidence cross-linked (`CAG-02`/`CAG-03` + fixtures/tests)',
            '`🧪` RS-05-04 completion sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Scope',
            'PROJECT_ROADMAP_TASKS_KA.md:6691',
            'PROJECT_ROADMAP_TASKS_KA.md:3999',
            'PROJECT_ROADMAP_TASKS_KA.md:4798',
            '## Goal (`RS-05-04`)',
            '## Why `RS-05-04` Can Close Without Runtime Implementation',
            '`RS-05-01` / `RS-05-02` / `RS-05-03` audits remain `IN_PROGRESS` baseline/gap audits',
            '`CAG-02` / `CAG-03` already provide audited FeatureSpec parser compatibility and example corpus fixture coverage',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-05-04`)',
            '`RS-05-04` is **complete as a planning/scoping task**.',
            '## Explicit v1 Out-of-Scope Statement',
            'For the current v1 delivery scope, the following ecommerce add-ons are **out of scope**:',
            '## Activation Criteria Matrix (For vNext Add-on Activation)',
            'Backend contract finalized (routes/controllers/request+response payloads)',
            'Auth mode explicitly chosen (`public` / `customer`) per endpoint',
            'Public routes + OpenAPI aligned',
            'Canonical builder schemas exist for components',
            'Runtime widget hooks/selectors implemented (no generic-only claims)',
            'AI FeatureSpec corpus readiness exists for add-on class',
            'Backlog + sync test lock added',
            '## Per-Add-on vNext Task Slices / Priorities',
            '### `wishlist` (`P1` after base parity)',
            '### `reviews` (`P1` after base parity)',
            '### `compare` (`P2`)',
            '### `loyalty` (`P2`)',
            '### `subscriptions` (`P2`)',
            '## Dependency Synthesis from `RS-05-01` / `RS-05-02` / `RS-05-03`',
            'data-webby-ecommerce-search',
            'data-webby-ecommerce-categories',
            'product_slug/qty` vs `product_id/quantity`',
            'no source-style `POST /checkout/validate`',
            'no public customer-auth `GET /orders/my` / `GET /orders/{id}`',
            'checkout OpenAPI/controller response status drift (`200` docs vs `201` runtime)',
            '## AI FeatureSpec Corpus Readiness (Cross-Link to `CAG-02` / `CAG-03`)',
            'parser/generator integration is still truthfully documented as split in `CAG-03`',
            '## DoD Verdict (`RS-05-04`)',
            'Conclusion: `RS-05-04` is `DONE`.',
            '## Evidence Anchors',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0501Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-02` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0502Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0503Doc);
        }

        foreach ([
            '`CAG-02` is **complete as an audit/verification task**.',
            'strict feature spec structure',
            'CmsAiComponentFeatureSpecParser',
            'ui_intent',
            'explicit structured errors',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cag02Doc);
        }

        foreach ([
            '`CAG-03` is **complete as an audit/verification task**.',
            'machine-checkable JSON fixtures',
            'wishlist`, `reviews`, `subscriptions`, `loyalty`, `compare`',
            'The roadmap statement “How generator uses this” is only partially true',
            'partially implemented / split across parser + generator + renderer/preflight layers',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cag03Doc);
        }

        foreach ([
            'test_source_example_fixtures_are_parser_compatible_and_machine_checkable',
            'test_corpus_fixtures_cover_all_roadmap_examples_wishlist_reviews_subscriptions_loyalty_and_compare',
        ] as $needle) {
            $this->assertStringContainsString($needle, $corpusRegressionTest);
        }

        $this->assertStringContainsString('test_it_parses_ui_intent_feature_spec_into_canonical_auto_generator_contract', $parserTest);
        $this->assertStringContainsString('test_it_generates_registry_entries_node_scaffolds_and_renderer_scaffolds_from_raw_feature_spec', $factoryGeneratorTest);

        $fixturesCombined = collect($fixturePaths)
            ->map(fn (string $path): string => File::get($path))
            ->implode("\n\n");

        foreach ([
            '"feature_key": "wishlist"',
            '"feature_key": "reviews"',
            '"feature_key": "subscriptions"',
            '"feature_key": "loyalty"',
            '"feature_key": "compare"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $fixturesCombined);
        }

        foreach ([
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md',
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md',
            'Install/docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_EXAMPLE_CORPUS_USAGE_AUDIT_CAG_03_2026_02_25.md',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest.php',
            'Install/tests/Unit/BackendBuilderComponentAutoGeneratorExampleCorpusCag03SyncTest.php',
            'Install/tests/Unit/CmsAiComponentFeatureSpecExampleCorpusRegressionTest.php',
            'Install/tests/Unit/CmsAiComponentFeatureSpecParserTest.php',
            'Install/tests/Unit/CmsAiComponentFactoryGeneratorTest.php',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/wishlist.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/reviews.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/subscriptions.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/loyalty.json',
            'Install/tests/Fixtures/CmsAiComponentFeatureSpecExamples/compare.json',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing RS-05-04 evidence anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing RS-05-04 evidence file on disk: {$relativePath}");
        }
    }
}
