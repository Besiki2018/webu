<?php

namespace Tests\Unit;

use App\Services\CmsThemeTokenValueValidator;
use App\Services\ThemeTokenComposer;
use Tests\TestCase;

/**
 * Token Composer (Director PART 3) — tests for token quality.
 *
 * @see new tasks.txt — AI Design Director System PART 3 Deliverables: tests for token quality
 */
class ThemeTokenComposerTest extends TestCase
{
    public function test_compose_returns_valid_structure(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(['vibe' => 'luxury_minimal', 'layout_density' => 'balanced'], []);

        $this->assertArrayHasKey('valid', $out);
        $this->assertArrayHasKey('theme_tokens', $out);
        $this->assertArrayHasKey('errors', $out);
        $tokens = $out['theme_tokens'];
        $this->assertArrayHasKey('version', $tokens);
        $this->assertArrayHasKey('colors', $tokens);
        $this->assertArrayHasKey('radii', $tokens);
        $this->assertArrayHasKey('spacing', $tokens);
        $this->assertArrayHasKey('typography', $tokens);
    }

    public function test_compose_uses_8px_spacing_grid(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(['vibe' => 'luxury_minimal', 'layout_density' => 'balanced'], []);

        $spacing = $out['theme_tokens']['spacing'];
        $this->assertArrayHasKey('xs', $spacing);
        $this->assertArrayHasKey('sm', $spacing);
        $this->assertArrayHasKey('md', $spacing);
        $this->assertArrayHasKey('lg', $spacing);
        $this->assertStringContainsString('8', $spacing['xs']);
        $this->assertStringContainsString('16', $spacing['sm']);
    }

    public function test_compose_typography_has_hierarchy(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(['vibe' => 'luxury_minimal'], []);

        $typo = $out['theme_tokens']['typography'];
        $this->assertArrayHasKey('h1', $typo);
        $this->assertArrayHasKey('h2', $typo);
        $this->assertArrayHasKey('h3', $typo);
        $this->assertArrayHasKey('body', $typo);
    }

    public function test_compose_accepts_brand_colors(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(
            ['vibe' => 'luxury_minimal'],
            ['primary_color' => '#2563eb', 'secondary_color' => '#7c3aed']
        );

        $this->assertArrayHasKey('primary', $out['theme_tokens']['colors']);
        $this->assertSame('#2563eb', $out['theme_tokens']['colors']['primary']);
        $this->assertArrayHasKey('accent', $out['theme_tokens']['colors']);
        $this->assertSame('#7c3aed', $out['theme_tokens']['colors']['accent']);
    }

    public function test_compose_dark_vibe_sets_light_primary(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(['vibe' => 'dark_modern'], []);

        $colors = $out['theme_tokens']['colors'];
        $this->assertNotEmpty($colors['primary']);
        $this->assertStringStartsWith('#', $colors['primary']);
    }

    public function test_compose_compact_density_sets_section_y(): void
    {
        $composer = new ThemeTokenComposer(app(CmsThemeTokenValueValidator::class));
        $out = $composer->compose(['vibe' => 'luxury_minimal', 'layout_density' => 'compact'], []);

        $spacing = $out['theme_tokens']['spacing'];
        $this->assertArrayHasKey('section_y', $spacing);
    }
}
