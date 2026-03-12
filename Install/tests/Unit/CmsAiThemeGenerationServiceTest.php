<?php

namespace Tests\Unit;

use App\Services\CmsAiThemeGenerationService;
use Tests\TestCase;

class CmsAiThemeGenerationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('theme-presets', [
            'default' => ['name' => 'Default'],
            'arctic' => ['name' => 'Arctic'],
            'summer' => ['name' => 'Summer'],
            'fragrant' => ['name' => 'Fragrant'],
        ]);
    }

    public function test_it_generates_builder_native_theme_fragment_from_prompt_signals(): void
    {
        $service = app(CmsAiThemeGenerationService::class);

        $result = $service->generateThemeFragment($this->validInputPayload([
            'request' => [
                'prompt' => 'Build a modern electronics ecommerce store for premium gadgets with professional look.',
                'user_context' => [
                    'brand_tone' => 'professional',
                ],
            ],
            'platform_context' => [
                'template_blueprint' => [
                    'template_slug' => 'webu-shop-01',
                ],
                'module_registry' => [
                    'modules' => [[
                        'key' => 'ecommerce',
                        'enabled' => true,
                        'available' => true,
                    ]],
                ],
            ],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertSame('rule_based_theme_generation', data_get($result, 'engine.kind'));
        $this->assertSame('webu-shop-01', data_get($result, 'template_choice.slug'));
        $this->assertSame('arctic', data_get($result, 'preset_choice.resolved'));
        $this->assertSame('generated', data_get($result, 'theme.meta.source'));
        $this->assertSame('arctic', data_get($result, 'theme.theme_settings_patch.preset'));
        $this->assertSame(1, data_get($result, 'theme.theme_settings_patch.theme_tokens.version'));
        $this->assertSame('#0ea5e9', data_get($result, 'theme.theme_settings_patch.theme_tokens.colors.primary'));
        $this->assertSame('0.375rem', data_get($result, 'theme.theme_settings_patch.theme_tokens.radii.base'));
        $this->assertTrue((bool) data_get($result, 'validation.theme_token_patch.valid'));
    }

    public function test_it_keeps_existing_theme_preset_when_preserve_theme_settings_constraint_is_requested(): void
    {
        $service = app(CmsAiThemeGenerationService::class);

        $result = $service->generateThemeFragment($this->validInputPayload([
            'request' => [
                'prompt' => 'Redesign the boutique homepage with a luxury feel.',
                'constraints' => [
                    'preserve_theme_settings' => true,
                ],
            ],
            'platform_context' => [
                'site' => [
                    'theme_settings' => [
                        'preset' => 'fragrant',
                    ],
                ],
            ],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertSame('keep_existing', data_get($result, 'theme.meta.source'));
        $this->assertSame('fragrant', data_get($result, 'preset_choice.requested'));
        $this->assertSame('fragrant', data_get($result, 'preset_choice.resolved'));
        $this->assertTrue((bool) data_get($result, 'preset_choice.keep_existing'));
        $this->assertSame(['preset' => 'fragrant'], data_get($result, 'theme.theme_settings_patch'));
    }

    public function test_it_returns_input_validation_errors_when_ai_input_payload_is_invalid(): void
    {
        $service = app(CmsAiThemeGenerationService::class);

        $result = $service->generateThemeFragment([
            'schema_version' => 1,
            'request' => [
                'prompt' => 'missing required fields',
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['theme']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('missing_required_key', array_column($result['errors'], 'code'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInputPayload(array $overrides = []): array
    {
        $base = [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate ecommerce storefront',
                'locale' => 'en',
                'target' => [
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
                    'theme_settings' => [
                        'preset' => 'default',
                    ],
                ],
                'template_blueprint' => [
                    'template_id' => 1,
                    'template_slug' => 'webu-shop-01',
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
                    'summary' => [
                        'total' => 1,
                        'available' => 1,
                        'disabled' => 0,
                        'not_entitled' => 0,
                    ],
                ],
                'module_entitlements' => [
                    'site_id' => '1',
                    'project_id' => '1',
                    'features' => [],
                    'modules' => [
                        'ecommerce' => true,
                    ],
                    'reasons' => [],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req-theme-1',
                'created_at' => '2026-02-24T12:00:00Z',
                'source' => 'internal_tool',
            ],
        ];

        return $this->mergeRecursiveDistinct($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeRecursiveDistinct(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                /** @var array<string, mixed> $baseValue */
                $baseValue = $base[$key];
                /** @var array<string, mixed> $overrideValue */
                $overrideValue = $value;
                $base[$key] = $this->mergeRecursiveDistinct($baseValue, $overrideValue);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
