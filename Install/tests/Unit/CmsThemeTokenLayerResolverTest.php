<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use App\Services\CmsThemeTokenLayerResolver;
use Tests\TestCase;

class CmsThemeTokenLayerResolverTest extends TestCase
{
    public function test_it_builds_canonical_layers_with_template_preset_and_site_precedence(): void
    {
        config()->set('theme-presets.qa-base', [
            'name' => 'QA Base',
            'description' => 'Test preset',
            'light' => [
                'primary' => '111 100% 50%',
                'secondary' => '222 50% 50%',
                'radius' => '0.25rem',
            ],
            'dark' => [
                'primary' => '111 90% 60%',
            ],
        ]);
        config()->set('theme-presets.qa-alt', [
            'name' => 'QA Alt',
            'description' => 'Test preset alt',
            'light' => [
                'primary' => '10 80% 40%',
                'radius' => '0.75rem',
            ],
            'dark' => [
                'primary' => '10 70% 50%',
            ],
        ]);

        $template = new Template([
            'id' => 99,
            'slug' => 'webu-shop-01',
            'name' => 'Webu Shop 01',
            'metadata' => [
                'typography_tokens' => [
                    'heading' => 'tbc_contractica',
                    'body' => 'tbc_contractica',
                    'button' => 'tbc_contractica',
                ],
                'theme_tokens' => [
                    'version' => 1,
                    'colors' => [
                        'primary' => '#111111',
                        'accent' => '#aaaaaa',
                    ],
                    'spacing' => [
                        'md' => '16px',
                    ],
                ],
                'layout_defaults' => [
                    'header_menu_key' => 'header',
                    'popup_modal' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $project = new Project([
            'id' => 'proj_test_1',
            'theme_preset' => 'qa-base',
        ]);
        $project->setRelation('template', $template);

        $site = new Site([
            'id' => 'site_test_1',
            'project_id' => 'proj_test_1',
            'locale' => 'ka',
            'theme_settings' => [
                'preset' => 'qa-alt',
                'colors' => [
                    'primary' => '#ff0000',
                    'text' => '#121212',
                ],
                'layout' => [
                    'header_menu_key' => 'main',
                ],
                'theme_tokens' => [
                    'version' => 1,
                    'radii' => [
                        'card' => '12px',
                    ],
                ],
                'typography' => [
                    'font_key' => 'tbc-contractica',
                    'heading_font_key' => 'tbc-contractica',
                ],
            ],
        ]);
        $site->setRelation('project', $project);

        $resolver = app(CmsThemeTokenLayerResolver::class);
        $result = $resolver->resolveForSite($site, $project);

        $this->assertSame(['template_defaults', 'preset_defaults', 'site_overrides'], $result['layer_order']);
        $this->assertSame('qa-alt', data_get($result, 'theme_preset.requested_key'));
        $this->assertSame('qa-alt', data_get($result, 'theme_preset.resolved_key'));
        $this->assertFalse((bool) data_get($result, 'theme_preset.fallback_applied'));

        $this->assertSame(
            'tbc-contractica',
            data_get($result, 'layers.template_defaults.canonical.theme_tokens.typography.heading_font_key')
        );
        $this->assertSame(
            '10 80% 40%',
            data_get($result, 'layers.preset_defaults.canonical.theme_tokens.colors.modes.light.primary')
        );
        $this->assertSame(
            '#ff0000',
            data_get($result, 'layers.site_overrides.canonical.theme_tokens.colors.primary')
        );

        // Site overrides win over template defaults for flat color tokens.
        $this->assertSame('#ff0000', data_get($result, 'effective.theme_tokens.colors.primary'));
        // Preset mode palette remains available in effective canonical tokens.
        $this->assertSame('10 80% 40%', data_get($result, 'effective.theme_tokens.colors.modes.light.primary'));
        // Site layout overrides win over template layout defaults.
        $this->assertSame('main', data_get($result, 'effective.layout.header_menu_key'));
        // Template layout defaults still survive if not overridden.
        $this->assertTrue((bool) data_get($result, 'effective.layout.popup_modal.enabled'));

        $this->assertSame(1, (int) data_get($result, 'effective.theme_tokens.typography.version'));
        $this->assertSame('tbc-contractica', data_get($result, 'effective.theme_tokens.typography.font_key'));
        $this->assertSame('qa-alt', data_get($result, 'effective_theme_settings.preset'));
    }

    public function test_it_falls_back_to_default_preset_when_requested_preset_is_invalid(): void
    {
        $project = new Project([
            'id' => 'proj_test_2',
            'theme_preset' => 'missing-preset',
        ]);
        $project->setRelation('template', new Template([
            'id' => 100,
            'slug' => 'blank',
            'name' => 'Blank',
            'metadata' => [],
        ]));

        $site = new Site([
            'id' => 'site_test_2',
            'project_id' => 'proj_test_2',
            'theme_settings' => [
                'preset' => 'also-missing',
            ],
        ]);
        $site->setRelation('project', $project);

        $resolver = app(CmsThemeTokenLayerResolver::class);
        $result = $resolver->resolveForSite($site, $project);

        $this->assertSame('also-missing', data_get($result, 'theme_preset.requested_key'));
        $this->assertSame('default', data_get($result, 'theme_preset.resolved_key'));
        $this->assertTrue((bool) data_get($result, 'theme_preset.fallback_applied'));
        $this->assertTrue((bool) data_get($result, 'layers.preset_defaults.exists'));
        $this->assertSame('default', data_get($result, 'effective.theme_preset.key'));
        $this->assertSame('default', data_get($result, 'effective_theme_settings.preset'));
    }
}
