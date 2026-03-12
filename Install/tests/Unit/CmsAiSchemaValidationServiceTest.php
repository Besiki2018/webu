<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiSchemaValidationServiceTest extends TestCase
{
    public function test_it_validates_minimal_ai_input_payload_and_reports_success(): void
    {
        $service = app(CmsAiSchemaValidationService::class);

        $result = $service->validateInputPayload($this->validInputPayload());

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame('docs/architecture/schemas/cms-ai-generation-input.v1.schema.json', $result['schema']);
        $this->assertSame([], $result['errors']);
    }

    public function test_it_reports_structured_errors_for_invalid_ai_input_payload(): void
    {
        $service = app(CmsAiSchemaValidationService::class);
        $payload = $this->validInputPayload();

        unset($payload['meta']);
        $payload['schema_version'] = 2;
        $payload['request']['mode'] = 'generate_everything';
        $payload['unexpected'] = true;

        $result = $service->validateInputPayload($payload);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(4, $result['error_count']);
        $this->assertIsArray($result['errors']);

        $paths = array_column($result['errors'], 'path');
        $codes = array_column($result['errors'], 'code');

        $this->assertContains('$.meta', $paths);
        $this->assertContains('$.schema_version', $paths);
        $this->assertContains('$.request.mode', $paths);
        $this->assertContains('$.unexpected', $paths);
        $this->assertContains('missing_required_key', $codes);
        $this->assertContains('const_mismatch', $codes);
        $this->assertContains('enum_mismatch', $codes);
        $this->assertContains('unexpected_key', $codes);
    }

    public function test_it_validates_minimal_ai_output_payload_and_reports_success(): void
    {
        $service = app(CmsAiSchemaValidationService::class);

        $result = $service->validateOutputPayload($this->validOutputPayload());

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json', $result['schema']);
    }

    public function test_it_reports_structured_errors_for_invalid_ai_output_payload(): void
    {
        $service = app(CmsAiSchemaValidationService::class);
        $payload = $this->validOutputPayload();

        $payload['extra_top_level'] = 'nope';
        $payload['pages'][0]['status'] = 'archived';
        $payload['meta']['contracts']['ai_input_schema'] = 'wrong/path.json';
        $payload['meta']['validation_expectations']['no_parallel_storage'] = false;
        unset($payload['header']['props']);

        $result = $service->validateOutputPayload($payload);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(5, $result['error_count']);

        $paths = array_column($result['errors'], 'path');
        $codes = array_column($result['errors'], 'code');

        $this->assertContains('$.extra_top_level', $paths);
        $this->assertContains('$.pages[0].status', $paths);
        $this->assertContains('$.meta.contracts.ai_input_schema', $paths);
        $this->assertContains('$.meta.validation_expectations.no_parallel_storage', $paths);
        $this->assertContains('$.header.props', $paths);
        $this->assertContains('unexpected_key', $codes);
        $this->assertContains('enum_mismatch', $codes);
        $this->assertContains('const_mismatch', $codes);
        $this->assertContains('missing_required_key', $codes);
    }

    public function test_it_reports_invalid_json_errors_for_string_entrypoints_and_documents_validator_contract(): void
    {
        $service = app(CmsAiSchemaValidationService::class);

        $result = $service->validateOutputJsonString('{"broken":');

        $this->assertFalse($result['valid']);
        $this->assertSame(1, $result['error_count']);
        $this->assertSame('invalid_json', $result['errors'][0]['code'] ?? null);
        $this->assertSame('$', $result['errors'][0]['path'] ?? null);

        $docPath = base_path('docs/architecture/CMS_AI_SCHEMA_VALIDATORS_V1.md');
        $this->assertFileExists($docPath);
        $doc = File::get($docPath);
        $this->assertStringContainsString('# CMS AI Schema Validators V1', $doc);
        $this->assertStringContainsString('P4-E1-03', $doc);
        $this->assertStringContainsString('CmsAiSchemaValidationService', $doc);
        $this->assertStringContainsString('code', $doc);
        $this->assertStringContainsString('path', $doc);
        $this->assertStringContainsString('message', $doc);
        $this->assertStringContainsString('developer-facing first', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function validInputPayload(): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a modern ecommerce homepage.',
                'locale' => 'en',
                'target' => [
                    'page_slugs' => ['home'],
                    'route_scope' => 'site',
                ],
            ],
            'platform_context' => [
                'project' => [
                    'id' => '1',
                    'name' => 'Demo Project',
                ],
                'site' => [
                    'id' => '1',
                    'name' => 'Demo Site',
                    'status' => 'draft',
                    'locale' => 'en',
                    'theme_settings' => [],
                ],
                'template_blueprint' => [
                    'template_id' => null,
                    'template_slug' => null,
                    'default_pages' => [],
                    'default_sections' => [],
                ],
                'site_settings_snapshot' => [
                    'site' => [
                        'id' => '1',
                        'project_id' => '1',
                        'name' => 'Demo Site',
                        'status' => 'draft',
                        'locale' => 'en',
                        'theme_settings' => [],
                    ],
                    'typography' => [],
                    'global_settings' => [
                        'logo_media_id' => null,
                        'logo_asset_url' => null,
                        'contact_json' => [],
                        'social_links_json' => [],
                        'analytics_ids_json' => [],
                    ],
                ],
                'section_library' => [],
                'module_registry' => [
                    'site_id' => '1',
                    'project_id' => '1',
                    'modules' => [],
                    'summary' => ['total' => 0, 'available' => 0, 'disabled' => 0, 'not_entitled' => 0],
                ],
                'module_entitlements' => [
                    'site_id' => '1',
                    'project_id' => '1',
                    'features' => [],
                    'modules' => [],
                    'reasons' => [],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validOutputPayload(): array
    {
        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => [
                    'preset' => 'commerce',
                ],
            ],
            'pages' => [[
                'slug' => 'home',
                'title' => 'Home',
                'status' => 'draft',
                'builder_nodes' => [],
                'page_css' => '/* scoped css */',
                'seo' => [
                    'seo_title' => 'Home',
                    'seo_description' => 'Demo home page',
                ],
            ]],
            'header' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'footer' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => '2026-02-24T12:00:00Z',
                'contracts' => [
                    'ai_input_schema' => 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    'canonical_page_node_schema' => 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    'canonical_component_registry_schema' => 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                ],
                'validation_expectations' => [
                    'strict_top_level' => true,
                    'no_parallel_storage' => true,
                    'builder_native_pages' => true,
                    'component_availability_check_required' => true,
                    'binding_validation_required' => true,
                ],
            ],
        ];
    }
}
