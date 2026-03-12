<?php

namespace App\Services;

use App\Cms\Exceptions\CmsDomainException;

/**
 * Validates canonical theme token values (structure and types).
 * Used by CmsAiThemeGenerationEngine (PART 6): AI theme output is restricted to
 * allowed keys only (primary, secondary, font, button radius, spacing) before validation.
 */
class CmsThemeTokenValueValidator
{
    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array{
     *   valid: bool,
     *   errors: array<int, array{path:string, error:string}>
     * }
     */
    public function validate(array $themeSettings): array
    {
        $errors = [];

        $layout = is_array($themeSettings['layout'] ?? null) ? $themeSettings['layout'] : [];
        if ($layout !== [] && array_key_exists('version', $layout)) {
            $this->validatePositiveInt($errors, 'layout.version', $layout['version']);
        }

        $themeTokens = is_array($themeSettings['theme_tokens'] ?? null)
            ? $themeSettings['theme_tokens']
            : (is_array($themeSettings['tokens'] ?? null) ? $themeSettings['tokens'] : []);

        if ($themeTokens !== []) {
            if (array_key_exists('version', $themeTokens)) {
                $this->validatePositiveInt($errors, 'theme_tokens.version', $themeTokens['version']);
            }

            $this->validateColors($errors, 'theme_tokens.colors', $themeTokens['colors'] ?? null);
            $this->validateStringTokenGroup($errors, 'theme_tokens.radii', $themeTokens['radii'] ?? null, 64);
            $this->validateStringTokenGroup($errors, 'theme_tokens.spacing', $themeTokens['spacing'] ?? null, 64);
            $this->validateStringTokenGroup($errors, 'theme_tokens.breakpoints', $themeTokens['breakpoints'] ?? null, 64);
            $this->validateStringTokenGroup($errors, 'theme_tokens.shadows', $themeTokens['shadows'] ?? null, 512);
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     */
    public function assertValidThemeSettings(array $themeSettings): void
    {
        $validation = $this->validate($themeSettings);
        if ($validation['valid']) {
            return;
        }

        throw new CmsDomainException(
            'Invalid canonical theme token values.',
            422,
            [
                'code' => 'theme_token_validation_failed',
                'theme_token_validation' => $validation,
            ]
        );
    }

    /**
     * @param  array<int, array{path:string, error:string}>  $errors
     */
    private function validatePositiveInt(array &$errors, string $path, mixed $value): void
    {
        if (is_int($value) && $value > 0) {
            return;
        }

        if (is_numeric($value) && (int) $value > 0 && (string) (int) $value === trim((string) $value)) {
            return;
        }

        $errors[] = [
            'path' => $path,
            'error' => 'must_be_positive_integer',
        ];
    }

    /**
     * @param  array<int, array{path:string, error:string}>  $errors
     */
    private function validateColors(array &$errors, string $path, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $errors[] = ['path' => $path, 'error' => 'must_be_object'];
            return;
        }

        foreach ($value as $key => $item) {
            $itemPath = sprintf('%s.%s', $path, (string) $key);
            if ($key === 'modes') {
                if (! is_array($item)) {
                    $errors[] = ['path' => $itemPath, 'error' => 'must_be_object'];
                    continue;
                }

                foreach ($item as $mode => $modeValues) {
                    $modePath = sprintf('%s.%s', $itemPath, (string) $mode);
                    if (! in_array((string) $mode, ['light', 'dark'], true)) {
                        $errors[] = ['path' => $modePath, 'error' => 'unsupported_mode'];
                        continue;
                    }
                    $this->validateStringTokenGroup($errors, $modePath, $modeValues, 128);
                }
                continue;
            }

            if (! $this->isScalarOrNull($item)) {
                $errors[] = ['path' => $itemPath, 'error' => 'must_be_scalar_or_null'];
                continue;
            }

            if (is_string($item) && mb_strlen(trim($item)) > 128) {
                $errors[] = ['path' => $itemPath, 'error' => 'string_too_long'];
            }
        }
    }

    /**
     * @param  array<int, array{path:string, error:string}>  $errors
     */
    private function validateStringTokenGroup(array &$errors, string $path, mixed $value, int $maxLength): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $errors[] = ['path' => $path, 'error' => 'must_be_object'];
            return;
        }

        foreach ($value as $key => $item) {
            $itemPath = sprintf('%s.%s', $path, (string) $key);

            if (is_array($item)) {
                $errors[] = ['path' => $itemPath, 'error' => 'nested_objects_not_supported'];
                continue;
            }

            if ($item === null) {
                continue;
            }

            if (! is_scalar($item)) {
                $errors[] = ['path' => $itemPath, 'error' => 'must_be_scalar_or_null'];
                continue;
            }

            $stringValue = trim((string) $item);
            if ($stringValue === '') {
                continue;
            }

            if (mb_strlen($stringValue) > $maxLength) {
                $errors[] = ['path' => $itemPath, 'error' => 'string_too_long'];
            }
        }
    }

    private function isScalarOrNull(mixed $value): bool
    {
        return $value === null || is_scalar($value);
    }
}
