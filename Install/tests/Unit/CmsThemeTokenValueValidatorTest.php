<?php

namespace Tests\Unit;

use App\Cms\Exceptions\CmsDomainException;
use App\Services\CmsThemeTokenValueValidator;
use Tests\TestCase;

class CmsThemeTokenValueValidatorTest extends TestCase
{
    public function test_it_accepts_valid_canonical_theme_token_groups(): void
    {
        $validator = new CmsThemeTokenValueValidator;

        $result = $validator->validate([
            'layout' => [
                'version' => 1,
            ],
            'theme_tokens' => [
                'version' => 1,
                'colors' => [
                    'primary' => '#111111',
                    'modes' => [
                        'light' => [
                            'background' => '0 0% 100%',
                            'primary' => '266 4% 20.8%',
                        ],
                        'dark' => [
                            'background' => '0 0% 15%',
                        ],
                    ],
                ],
                'radii' => [
                    'base' => '0.5rem',
                    'card' => '12px',
                ],
                'spacing' => [
                    'md' => '16px',
                ],
                'shadows' => [
                    'card' => '0 8px 24px rgba(0,0,0,.12)',
                ],
                'breakpoints' => [
                    'lg' => '1024px',
                ],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function test_it_reports_invalid_token_shapes_and_values(): void
    {
        $validator = new CmsThemeTokenValueValidator;

        $result = $validator->validate([
            'layout' => [
                'version' => 0,
            ],
            'theme_tokens' => [
                'version' => '0',
                'colors' => [
                    'modes' => [
                        'tablet' => [
                            'primary' => 'x',
                        ],
                        'light' => 'bad-shape',
                    ],
                    'accent' => ['nested' => 'bad'],
                ],
                'spacing' => [
                    'md' => ['nested' => 'bad'],
                ],
                'shadows' => 'bad',
            ],
        ]);

        $this->assertFalse($result['valid']);
        $errorPaths = array_column($result['errors'], 'path');
        $errorCodes = array_column($result['errors'], 'error');

        $this->assertContains('layout.version', $errorPaths);
        $this->assertContains('theme_tokens.version', $errorPaths);
        $this->assertContains('theme_tokens.colors.modes.tablet', $errorPaths);
        $this->assertContains('theme_tokens.colors.modes.light', $errorPaths);
        $this->assertContains('theme_tokens.colors.accent', $errorPaths);
        $this->assertContains('theme_tokens.spacing.md', $errorPaths);
        $this->assertContains('theme_tokens.shadows', $errorPaths);
        $this->assertContains('unsupported_mode', $errorCodes);
    }

    public function test_assert_valid_theme_settings_throws_domain_exception_with_context(): void
    {
        $validator = new CmsThemeTokenValueValidator;

        $this->expectException(CmsDomainException::class);

        try {
            $validator->assertValidThemeSettings([
                'theme_tokens' => [
                    'version' => 1,
                    'shadows' => 'bad',
                ],
            ]);
        } catch (CmsDomainException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('theme_token_validation_failed', $exception->context()['code'] ?? null);
            $this->assertFalse((bool) data_get($exception->context(), 'theme_token_validation.valid'));
            throw $exception;
        }
    }
}
