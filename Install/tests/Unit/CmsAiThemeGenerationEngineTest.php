<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiThemeGenerationEngineTest extends TestCase
{
    public function test_it_generates_theme_output_fragment_using_existing_theme_settings_patch_contract(): void
    {
        $engine = app(CmsAiThemeGenerationEngine::class);
        $schemaValidator = app(CmsAiSchemaValidationService::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'generate_theme';
        $input['request']['prompt'] = 'Generate an organic farm produce shop theme with soft rounded cards and primary color #2E7D32.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
        ];
        $input['request']['user_context'] = [
            'industry' => 'organic food',
            'brand_tone' => 'friendly premium',
        ];
        $input['platform_context']['template_blueprint']['template_slug'] = 'webu-shop-01';
        $input['platform_context']['site']['theme_settings'] = [
            'preset' => 'default',
            'typography' => [
                'version' => 1,
                'font_key' => 'tbc-contractica',
                'heading_font_key' => 'tbc-contractica',
                'body_font_key' => 'tbc-contractica',
                'button_font_key' => 'tbc-contractica',
            ],
        ];

        $result = $engine->generateFromAiInput($input);

        $this->assertTrue($result['ok']);
        $this->assertSame('ecommerce', data_get($result, 'decisions.template_choice.family'));
        $this->assertSame('webu-shop-01', data_get($result, 'decisions.template_choice.recommended_slug'));
        $this->assertSame('forest', data_get($result, 'decisions.preset_choice.resolved_key'));
        $this->assertSame('generated', data_get($result, 'theme_output.meta.source'));
        $this->assertSame('forest', data_get($result, 'theme_output.theme_settings_patch.preset'));
        $this->assertSame(1, data_get($result, 'theme_output.theme_settings_patch.theme_tokens.version'));
        $this->assertSame('rounded', data_get($result, 'decisions.token_choices.radius_profile'));
        $this->assertSame('#2e7d32', data_get($result, 'theme_output.theme_settings_patch.colors.primary'));
        $this->assertSame('#2e7d32', data_get($result, 'theme_output.theme_settings_patch.theme_tokens.colors.primary'));
        $this->assertTrue((bool) data_get($result, 'validation.theme_tokens.valid'));

        $outputValidation = $schemaValidator->validateOutputPayload($this->minimalOutputEnvelope($result['theme_output']));
        $this->assertTrue($outputValidation['valid']);
    }

    public function test_it_respects_preserve_theme_settings_constraint_and_returns_keep_existing_source(): void
    {
        $engine = app(CmsAiThemeGenerationEngine::class);

        $input = $this->validAiInput();
        $input['request']['mode'] = 'generate_theme';
        $input['request']['prompt'] = 'Use midnight preset for premium jewelry store and dark mode.';
        $input['request']['constraints'] = [
            'allow_ecommerce' => true,
            'preserve_theme_settings' => true,
        ];

        $result = $engine->generateFromAiInput($input);

        $this->assertTrue($result['ok']);
        $this->assertSame('midnight', data_get($result, 'decisions.preset_choice.resolved_key'));
        $this->assertSame('keep_existing', data_get($result, 'theme_output.meta.source'));
        $this->assertSame([], data_get($result, 'theme_output.theme_settings_patch'));
        $this->assertContains(
            'preserve_theme_settings=true; returned keep_existing theme output with no patch.',
            $result['warnings']
        );
    }

    public function test_it_reports_invalid_ai_input_payloads_using_phase_e1_validator_contract(): void
    {
        $engine = app(CmsAiThemeGenerationEngine::class);

        $result = $engine->generateFromAiInput([
            'schema_version' => 1,
            'request' => ['mode' => 'generate_theme'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_ai_input', $result['code']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
            data_get($result, 'validation.input.schema')
        );
    }

    public function test_architecture_doc_documents_non_duplicate_runtime_contract_and_output_fragment_shape(): void
    {
        $path = base_path('docs/architecture/CMS_AI_THEME_GENERATION_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Theme Generation Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-01', $doc);
        $this->assertStringContainsString('theme_settings_patch', $doc);
        $this->assertStringContainsString('CmsThemeTokenLayerResolver', $doc);
        $this->assertStringContainsString('CmsThemeTokenValueValidator', $doc);
        $this->assertStringContainsString('without duplicating runtime contract', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiInput(): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a modern site theme.',
                'locale' => 'en',
                'target' => [
                    'route_scope' => 'theme',
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
                'request_id' => 'req-theme-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'builder_chat',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $themeOutput
     * @return array<string, mixed>
     */
    private function minimalOutputEnvelope(array $themeOutput): array
    {
        return [
            'schema_version' => 1,
            'theme' => $themeOutput,
            'pages' => [],
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
