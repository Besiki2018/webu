<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class CmsCanonicalSchemaContractsTest extends TestCase
{
    public function test_component_registry_entry_v1_schema_exists_and_contains_required_contract_keys(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertSame(
            [
                'type',
                'category',
                'props_schema',
                'default_props',
                'renderer',
                'controls_config',
            ],
            $schema['required'] ?? null
        );

        $renderer = data_get($schema, 'properties.renderer.properties.kind.enum');
        $this->assertIsArray($renderer);
        $this->assertContains('template_marker', $renderer);
        $this->assertContains('frontend_builtin', $renderer);
    }

    public function test_page_node_v1_schema_exists_and_requires_canonical_prop_groups(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertSame(
            ['type', 'props', 'bindings', 'meta'],
            $schema['required'] ?? null
        );

        $propGroups = data_get($schema, 'properties.props.required');
        $this->assertSame(
            ['content', 'data', 'style', 'advanced', 'responsive', 'states'],
            $propGroups
        );

        $this->assertSame('integer', data_get($schema, 'properties.meta.properties.schema_version.type'));
    }

    public function test_ai_component_feature_spec_v1_schema_exists_and_requires_endpoints_ui_intent_and_generator_hints(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertFalse((bool) ($schema['additionalProperties'] ?? true));
        $this->assertSame(
            [
                'schema_version',
                'feature_key',
                'title',
                'category',
                'description',
                'context',
                'permissions',
                'entities',
                'endpoints',
                'events',
                'ui_intent',
                'generator_hints',
                'meta',
            ],
            $schema['required'] ?? null
        );

        $this->assertSame(1, data_get($schema, 'properties.schema_version.const'));
        $this->assertSame(
            ['public', 'customer', 'admin'],
            data_get($schema, 'properties.context.enum')
        );
        $this->assertSame(1, data_get($schema, 'properties.endpoints.minItems'));
        $this->assertSame(
            ['primary_component', 'secondary_components', 'component_set'],
            data_get($schema, 'properties.ui_intent.required')
        );
        $this->assertSame(
            ['namespace', 'builder_sidebar_category', 'component_types', 'feature_dir'],
            data_get($schema, 'properties.generator_hints.required')
        );
        $this->assertSame(
            'CmsAiComponentFeatureSpecParser',
            data_get($schema, 'properties.meta.properties.parser.const')
        );
        $this->assertSame(
            'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
            data_get($schema, 'properties.meta.properties.contracts.properties.canonical_component_registry_schema.const')
        );
    }

    public function test_ai_generation_input_v1_schema_exists_and_aligns_to_current_platform_payload_model(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-ai-generation-input.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertFalse((bool) ($schema['additionalProperties'] ?? true));
        $this->assertSame(
            ['schema_version', 'request', 'platform_context', 'meta'],
            $schema['required'] ?? null
        );

        $this->assertSame(1, data_get($schema, 'properties.schema_version.const'));
        $this->assertSame(
            ['mode', 'prompt', 'locale', 'target'],
            data_get($schema, 'properties.request.required')
        );
        $this->assertSame(
            ['generate_site', 'generate_pages', 'generate_theme', 'edit_page', 'edit_site'],
            data_get($schema, 'properties.request.properties.mode.enum')
        );

        $this->assertSame(
            [
                'project',
                'site',
                'template_blueprint',
                'site_settings_snapshot',
                'section_library',
                'module_registry',
                'module_entitlements',
            ],
            data_get($schema, 'properties.platform_context.required')
        );

        $this->assertSame(
            ['template_id', 'template_slug', 'default_pages', 'default_sections'],
            data_get($schema, 'properties.platform_context.properties.template_blueprint.required')
        );
        $this->assertSame(
            ['site', 'typography', 'global_settings'],
            data_get($schema, 'properties.platform_context.properties.site_settings_snapshot.required')
        );
        $this->assertSame(
            ['logo_media_id', 'logo_asset_url', 'contact_json', 'social_links_json', 'analytics_ids_json'],
            data_get($schema, 'properties.platform_context.properties.site_settings_snapshot.properties.global_settings.required')
        );

        $compatibilityRefs = data_get($schema, 'properties.platform_context.properties.compatibility_refs.properties');
        $this->assertIsArray($compatibilityRefs);
        $this->assertArrayHasKey('canonical_component_registry_schema', $compatibilityRefs);
        $this->assertArrayHasKey('canonical_page_node_schema', $compatibilityRefs);
        $this->assertArrayHasKey('binding_resolver_contract', $compatibilityRefs);
        $this->assertArrayHasKey('control_metadata_contract', $compatibilityRefs);

        $this->assertSame(
            ['request_id', 'created_at', 'source'],
            data_get($schema, 'properties.meta.required')
        );
        $this->assertSame(
            ['builder_chat', 'builder_action', 'internal_tool', 'api'],
            data_get($schema, 'properties.meta.properties.source.enum')
        );
    }

    public function test_ai_generation_input_v1_architecture_doc_documents_current_payload_alignment(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATION_INPUT_SCHEMA_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generation Input Schema v1', $doc);
        $this->assertStringContainsString('P4-E1-01', $doc);
        $this->assertStringContainsString('current Webu platform payload shapes', $doc);
        $this->assertStringContainsString('ProjectCmsController', $doc);
        $this->assertStringContainsString('platform_context.project', $doc);
        $this->assertStringContainsString('platform_context.site', $doc);
        $this->assertStringContainsString('platform_context.template_blueprint', $doc);
        $this->assertStringContainsString('platform_context.site_settings_snapshot', $doc);
        $this->assertStringContainsString('theme_settings', $doc);
        $this->assertStringContainsString('default_pages', $doc);
        $this->assertStringContainsString('default_sections', $doc);
        $this->assertStringContainsString('seo_title', $doc);
        $this->assertStringContainsString('items_json', $doc);
    }

    public function test_ai_generation_output_v1_schema_exists_and_requires_strict_builder_native_artifacts(): void
    {
        $schema = $this->readJson(base_path('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json'));

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertFalse((bool) ($schema['additionalProperties'] ?? true));
        $this->assertSame(
            ['schema_version', 'theme', 'pages', 'header', 'footer', 'meta'],
            $schema['required'] ?? null
        );

        $this->assertSame(1, data_get($schema, 'properties.schema_version.const'));

        $this->assertSame(
            ['theme_settings_patch'],
            data_get($schema, '$defs.themeOutput.required')
        );

        $this->assertSame(
            ['slug', 'title', 'status', 'builder_nodes'],
            data_get($schema, '$defs.pageOutput.required')
        );

        $this->assertSame(
            ['draft', 'published'],
            data_get($schema, '$defs.pageOutput.properties.status.enum')
        );

        $this->assertSame(
            'cms-canonical-page-node.v1.schema.json',
            data_get($schema, '$defs.pageOutput.properties.builder_nodes.items.$ref')
        );

        $this->assertSame(
            ['enabled', 'section_type', 'props'],
            data_get($schema, '$defs.fixedSectionOutput.required')
        );

        $this->assertSame(
            ['generator', 'created_at', 'contracts', 'validation_expectations'],
            data_get($schema, 'properties.meta.required')
        );

        $this->assertSame(
            ['ai_input_schema', 'canonical_page_node_schema', 'canonical_component_registry_schema'],
            data_get($schema, 'properties.meta.properties.contracts.required')
        );
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            data_get($schema, 'properties.meta.properties.contracts.properties.ai_input_schema.const')
        );
        $this->assertSame(
            [
                'strict_top_level',
                'no_parallel_storage',
                'builder_native_pages',
                'component_availability_check_required',
                'binding_validation_required',
            ],
            data_get($schema, 'properties.meta.properties.validation_expectations.required')
        );
        $this->assertTrue((bool) data_get($schema, 'properties.meta.properties.validation_expectations.properties.strict_top_level.const'));
        $this->assertTrue((bool) data_get($schema, 'properties.meta.properties.validation_expectations.properties.no_parallel_storage.const'));
        $this->assertTrue((bool) data_get($schema, 'properties.meta.properties.validation_expectations.properties.builder_native_pages.const'));
        $this->assertSame(
            ['generated', 'template_derived', 'keep_existing'],
            data_get($schema, '$defs.fixedSectionOutput.properties.meta.properties.source.enum')
        );
        $this->assertSame(
            'object',
            data_get($schema, '$defs.pageOutput.properties.meta.properties.reproducibility.type')
        );
        $this->assertFalse((bool) data_get($schema, '$defs.pageOutput.properties.meta.properties.reproducibility.additionalProperties', true));
        $this->assertSame(
            1,
            data_get($schema, '$defs.pageOutput.properties.meta.properties.reproducibility.properties.schema_version.const')
        );
        $this->assertSame(
            ['string', 'null'],
            data_get($schema, '$defs.pageOutput.properties.meta.properties.reproducibility.properties.learned_rule_set_version.type')
        );
    }

    public function test_ai_generation_output_v1_architecture_doc_documents_page_json_page_css_and_revision_model_mapping(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATION_OUTPUT_SCHEMA_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generation Output Schema v1', $doc);
        $this->assertStringContainsString('P4-E1-02', $doc);
        $this->assertStringContainsString('strict AI output payload contract', $doc);
        $this->assertStringContainsString('builder-native artifacts', $doc);
        $this->assertStringContainsString('builder_nodes[]', $doc);
        $this->assertStringContainsString('page_json', $doc);
        $this->assertStringContainsString('page_css', $doc);
        $this->assertStringContainsString('current page revision/content model', $doc);
        $this->assertStringContainsString('page_revisions.content_json', $doc);
        $this->assertStringContainsString('meta.validation_expectations', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1.schema.json', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1.schema.json', $doc);
    }

    public function test_ai_generation_compatibility_policy_v1_documents_accept_warn_reject_rules_for_schema_versions(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATION_COMPATIBILITY_POLICY_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generation Compatibility Policy v1', $doc);
        $this->assertStringContainsString('P4-E1-04', $doc);
        $this->assertStringContainsString('compatible', $doc);
        $this->assertStringContainsString('compatible_with_warnings', $doc);
        $this->assertStringContainsString('incompatible', $doc);
        $this->assertStringContainsString('cms-ai-generation-input.v1.schema.json', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1.schema.json', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1.schema.json', $doc);
        $this->assertStringContainsString('cms-canonical-component-registry-entry.v1.schema.json', $doc);
        $this->assertStringContainsString('meta.contracts.*', $doc);
        $this->assertStringContainsString('meta.validation_expectations.*', $doc);
        $this->assertStringContainsString('page_json', $doc);
        $this->assertStringContainsString('page_css', $doc);
        $this->assertStringContainsString('current page revision/content model', $doc);
        $this->assertStringContainsString('CmsAiSchemaValidationService', $doc);
    }

    public function test_canonical_builder_configs_load_and_have_expected_contract_structure(): void
    {
        $meta = config('cms-builder-meta', []);
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('canonical_keys', $meta);
        $this->assertIsArray($meta['canonical_keys']);
        $this->assertContains('label', $meta['canonical_keys']);
        $this->assertContains('schema_version', $meta['canonical_keys']);
        $this->assertArrayHasKey('defaults', $meta);
        $this->assertArrayHasKey('registry_only_keys', $meta);

        $propsGroups = config('cms-builder-props-groups', []);
        $this->assertIsArray($propsGroups);
        $this->assertArrayHasKey('groups', $propsGroups);
        $this->assertIsArray($propsGroups['groups']);
        $this->assertContains('content', $propsGroups['groups']);
        $this->assertContains('responsive', $propsGroups['groups']);
        $this->assertArrayHasKey('labels', $propsGroups);

        $namespaces = config('cms-binding-namespaces', []);
        $this->assertIsArray($namespaces);
        $this->assertArrayHasKey('text', $namespaces);
        $this->assertArrayHasKey('link', $namespaces);
        $this->assertArrayHasKey('image', $namespaces);
        foreach (['text', 'link', 'image'] as $kind) {
            $this->assertArrayHasKey('binding_namespaces', $namespaces[$kind]);
            $this->assertArrayHasKey('examples', $namespaces[$kind]);
        }

        $styleCaps = config('cms-builder-style-capabilities', []);
        $this->assertIsArray($styleCaps);
        $this->assertArrayHasKey('flags', $styleCaps);
        $this->assertIsArray($styleCaps['flags']);
        $this->assertContains('responsive', $styleCaps['flags']);
        $this->assertContains('inherits_theme', $styleCaps['flags']);
        $this->assertArrayHasKey('layer_order', $styleCaps);
        $this->assertSame(['base', 'responsive', 'state'], $styleCaps['layer_order']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        $this->assertIsArray($decoded, "Invalid JSON schema: {$path}");

        return $decoded;
    }
}
