<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryGlobalStandardsDataBindingRuleCoverageRs0003SyncTest extends TestCase
{
    public function test_rs_00_03_audit_doc_locks_data_binding_rule_field_type_matrix_and_validation_runtime_evidence_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');

        $resolverContractDocPath = base_path('docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md');
        $namespaceStandardizationDocPath = base_path('docs/architecture/CMS_BINDING_NAMESPACE_STANDARDIZATION.md');
        $universalNamespaceCompatDocPath = base_path('docs/architecture/UNIVERSAL_BINDING_NAMESPACE_COMPATIBILITY_P5_F5_03.md');
        $aiBindingRulesDocPath = base_path('docs/architecture/CMS_AI_BINDING_GENERATION_RULES_V1.md');

        $resolverPath = base_path('app/Services/CmsCanonicalBindingResolver.php');
        $validatorPath = base_path('app/Services/CmsBindingExpressionValidator.php');
        $dynamicHooksPath = base_path('app/Services/CmsDynamicControlHookService.php');
        $sectionBindingServicePath = base_path('app/Services/CmsSectionBindingService.php');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');

        $resolverTestPath = base_path('tests/Unit/CmsCanonicalBindingResolverTest.php');
        $validatorTestPath = base_path('tests/Unit/CmsBindingExpressionValidatorTest.php');
        $dynamicHooksTestPath = base_path('tests/Unit/CmsDynamicControlHookServiceTest.php');
        $aiBindingPipelineTestPath = base_path('tests/Unit/CmsAiBindingGenerationRulesPipelineTest.php');
        $universalNamespaceCompatLockTestPath = base_path('tests/Unit/UniversalBindingNamespaceCompatibilityP5F5Test.php');
        $dynamicThemeUxContractPath = base_path('resources/js/Pages/Project/__tests__/CmsDynamicAndThemeUx.contract.test.ts');
        $universalNamespaceCompatContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalBindingNamespaceCompatibility.contract.test.ts');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $resolverContractDocPath,
            $namespaceStandardizationDocPath,
            $universalNamespaceCompatDocPath,
            $aiBindingRulesDocPath,
            $resolverPath,
            $validatorPath,
            $dynamicHooksPath,
            $sectionBindingServicePath,
            $cmsPath,
            $resolverTestPath,
            $validatorTestPath,
            $dynamicHooksTestPath,
            $aiBindingPipelineTestPath,
            $universalNamespaceCompatLockTestPath,
            $dynamicThemeUxContractPath,
            $universalNamespaceCompatContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $resolverContractDoc = File::get($resolverContractDocPath);
        $namespaceStandardizationDoc = File::get($namespaceStandardizationDocPath);
        $universalNamespaceCompatDoc = File::get($universalNamespaceCompatDocPath);
        $aiBindingRulesDoc = File::get($aiBindingRulesDocPath);

        $resolver = File::get($resolverPath);
        $validator = File::get($validatorPath);
        $dynamicHooks = File::get($dynamicHooksPath);
        $sectionBindingService = File::get($sectionBindingServicePath);
        $cms = File::get($cmsPath);

        $resolverTest = File::get($resolverTestPath);
        $validatorTest = File::get($validatorTestPath);
        $dynamicHooksTest = File::get($dynamicHooksTestPath);
        $aiBindingPipelineTest = File::get($aiBindingPipelineTestPath);
        $universalNamespaceCompatLockTest = File::get($universalNamespaceCompatLockTestPath);
        $dynamicThemeUxContract = File::get($dynamicThemeUxContractPath);
        $universalNamespaceCompatContract = File::get($universalNamespaceCompatContractPath);

        foreach ([
            '## 0.4 Data Binding Rule',
            'Any text/image/link field may bind to:',
            '- project.* (name, logo, contact)',
            '- page.* (title, seo)',
            '- customer.* (name)',
            '- ecommerce.* (products, cart, orders)',
            '- booking.* (services, staff, slots, bookings)',
            '- content.* (posts, portfolio, properties)',
            'Binding syntax: {{key.path}}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-00-03` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md',
            'UniversalComponentLibraryGlobalStandardsDataBindingRuleCoverageRs0003SyncTest.php',
            'CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md',
            'CMS_BINDING_NAMESPACE_STANDARDIZATION.md',
            'CmsCanonicalBindingResolverTest.php',
            'CmsBindingExpressionValidatorTest.php',
            'CmsDynamicControlHookServiceTest.php',
            'CmsDynamicAndThemeUx.contract.test.ts',
            '`✅` data-binding rule source namespaces + `{{key.path}}` syntax audited against canonical resolver/validator contracts',
            '`✅` bindable field support matrix documented for `text` / `image` / `link` with current coverage notes',
            '`✅` syntax parse + runtime resolution + invalid path handling evidence mapped to existing unit tests',
            '`✅` validation/runtime render test plan documented (including deferred semantic namespace coverage and E2E parity gap)',
            '`🧪` targeted RS-00-03 sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6493',
            'PROJECT_ROADMAP_TASKS_KA.md:6494',
            'PROJECT_ROADMAP_TASKS_KA.md:6501',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`RS-00-03`)',
            '## Source Rule Audit (Namespaces + Syntax)',
            '## Documented Supported Bindable Fields (Current Implementation)',
            '## Binding Support Matrix by Field Type (`text` / `image` / `link`)',
            '## Syntax Parse + Runtime Resolution + Invalid Path Handling (Evidence Matrix)',
            '## Validation / Runtime Render Test Plan (`RS-00-03` Deliverable)',
            '## DoD Verdict (`RS-00-03`)',
            '`text`',
            '`image`',
            '`link`',
            '`project.*`',
            '`page.*`',
            '`customer.*`',
            '`ecommerce.*`',
            '`booking.*`',
            '`content.*`',
            '`{{key.path}}`',
            'raw-path mode',
            'route.slug',
            'deferred semantic bindings',
            '`partial`',
            'Link object behavior',
            'onApplyExpression(...) -> onChangePath([...field.path, \'url\'], expression)',
            'no single end-to-end DOM runtime render parity test yet',
            'CmsSectionBindingService',
            'CmsDynamicControlHookService',
            'CmsCanonicalBindingResolver',
            'CmsBindingExpressionValidator',
            'unsupported_namespace',
            'invalid_syntax',
            'unresolved_path',
            'deferred semantic bindings',
            'A. Validation Coverage (Incremental Hardening)',
            'B. Runtime Resolution / Render Parity (Missing Global E2E Lock)',
            'C. Deferred Semantic Namespace Coverage (Context Fetch Path)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'project',
            'site',
            'page',
            'route',
            'menu',
            'global',
            'customer',
            'ecommerce',
            'booking',
            'content',
            'system',
            'Invalid syntax returns structured error (`invalid_syntax`)',
            'Unsupported namespace returns structured error (`unsupported_namespace`)',
            'Deferred semantic bindings',
        ] as $needle) {
            $this->assertStringContainsString($needle, $resolverContractDoc);
        }

        foreach ([
            'Canonical syntax for builder values:',
            '{{site.name}}',
            '{{page.title}}',
            '{{route.slug}}',
            '{{menu.header.items}}',
            '{{ecommerce.cart.total}}',
            'Invalid paths should surface builder validation warnings',
        ] as $needle) {
            $this->assertStringContainsString($needle, $namespaceStandardizationDoc);
        }

        foreach ([
            'CmsCanonicalBindingResolver',
            'CmsBindingExpressionValidator',
            'same save-time validation surface',
            'content.properties',
            'content.rooms',
            'webu_hotel_room_availability_01',
            'webu_realestate_map_01',
            'webu_book_slots_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $universalNamespaceCompatDoc);
        }

        foreach ([
            '# CMS AI Binding Generation Rules v1',
            'Data-binding generation rules',
            '{{route.params.slug}}',
            '{{route.params.id}}',
            'ecommerce.product',
            'ecommerce.checkout',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiBindingRulesDoc);
        }

        foreach ([
            'private const SUPPORTED_NAMESPACES = [',
            "'project'",
            "'page'",
            "'customer'",
            "'ecommerce'",
            "'booking'",
            "'content'",
            'public function parseExpression(string $expression): array',
            "return ['ok' => false, 'error' => 'invalid_syntax'];",
            "return ['ok' => false, 'error' => 'empty_expression'];",
            "'unsupported_namespace'",
            "'unresolved_path'",
            "'deferred' => true",
            'public function normalizeExpression(string $expression): ?string',
            'public function isBindingExpression(string $value): bool',
        ] as $needle) {
            $this->assertStringContainsString($needle, $resolver);
        }

        foreach ([
            'private function looksLikeBindingExpression(string $value): bool',
            'Optional raw-path mode for advanced users',
            'project|site|page|route|menu|global|customer|ecommerce|booking|content|system',
            'collectWarningsForValue',
            'collectRouteParamBindingWarnings',
            'private function routeBindingRules(): array',
            'webu_book_slots_01',
            'webu_realestate_map_01',
            'webu_hotel_room_availability_01',
            'invalid_syntax',
            'unresolved_path',
            'unmapped_path',
            "'error' => \$errorCode !== '' ? \$errorCode : 'invalid_syntax'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $validator);
        }

        foreach ([
            'class CmsDynamicControlHookService',
            "'kind' => \$kind",
            "'binding_namespaces' => \$this->recommendedNamespaces(\$kind)",
            "'image' => ['string', 'object']",
            "'link' => ['string', 'object']",
            "default => ['string'],",
            "if (Str::lower(\$fieldKey) === 'product_slug')",
            "{{route.params.slug}}",
            'private function detectKind(string $field, array $schemaProperty): ?string',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicHooks);
        }

        foreach ([
            "'dynamic_controls' => \$this->dynamicControlHooks->buildHooks(\$editableFields, \$schema)",
            "'bindings_normalized' => \$this->normalizeBindingDefinitions(\$bindings)",
            '$this->canonicalBindings->normalizeExpression',
        ] as $needle) {
            $this->assertStringContainsString($needle, $sectionBindingService);
        }

        foreach ([
            'parseSectionDynamicControlHooks(bindingMeta: unknown)',
            'inferDynamicControlKindForField(',
            "return 'image';",
            "return 'link';",
            "return 'text';",
            'renderDynamicBindingHint',
            'renderDynamicBindingActions',
            "{t('Dynamic')}",
            "{t('Clear')}",
            "onApplyExpression: (expression) => options.onChangePath(field.path, expression)",
            "onApplyExpression: (expression) => options.onChangePath([...field.path, 'url'], expression)",
            'binding_namespaces',
            'supports_dynamic',
            'renderFieldBindingWarnings',
            "t('Binding Warning')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'test_it_resolves_canonical_paths_against_runtime_payload',
            'test_it_normalizes_legacy_paths_to_canonical_expressions',
            'test_it_returns_safe_fallback_for_invalid_or_unresolved_paths',
            'test_it_marks_deferred_semantic_bindings_without_throwing',
            '{{site.name}}',
            '{{route.params.slug}}',
            'unsupported_namespace',
            'invalid_syntax',
            'unresolved_path',
        ] as $needle) {
            $this->assertStringContainsString($needle, $resolverTest);
        }

        foreach ([
            'test_it_collects_binding_warnings_from_section_props',
            'test_it_warns_when_canonical_product_detail_sections_are_missing_route_slug_binding',
            'test_it_accepts_canonical_route_bindings_for_universal_vertical_detail_components',
            'test_it_warns_when_universal_vertical_components_use_missing_or_invalid_route_bindings',
            'invalid_syntax',
            'unsupported_namespace',
            'invalid_route_service_id_binding',
            'missing_route_room_slug_binding',
            'props.primary_cta.url',
        ] as $needle) {
            $this->assertStringContainsString($needle, $validatorTest);
        }

        foreach ([
            'test_it_builds_dynamic_hooks_for_text_image_and_link_fields',
            "'headline']['kind']",
            "'image_url']['kind']",
            "'primary_cta']['kind']",
            "'text'",
            "'image'",
            "'link'",
            'binding_namespaces',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicHooksTest);
        }

        foreach ([
            'test_page_generation_engine_normalizes_section_library_bindings_and_injects_product_route_binding',
            "assertSame('{{content.headline}}'",
            "assertSame('{{ecommerce.product.title}}'",
            "assertSame('{{route.params.slug}}'",
            'test_component_placement_styling_engine_applies_page_type_binding_and_query_rules',
            "assertSame('{{route.params.id}}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiBindingPipelineTest);
        }

        $this->assertStringContainsString('test_p5_f5_03_universal_binding_namespace_compatibility_contract_is_locked', strtolower($universalNamespaceCompatLockTest));
        $this->assertStringContainsString('CMS dynamic bindings and theme UX contracts', $dynamicThemeUxContract);
        $this->assertStringContainsString('CMS universal binding namespace compatibility contracts (P5-F5-03)', $universalNamespaceCompatContract);
    }
}
