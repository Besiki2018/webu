<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Validates AI output (blueprint or patch) for security and schema:
 * - No raw HTML/CSS in content (blocking).
 * - Only allowed section keys when allowed_section_keys is configured.
 */
class AiOutputSchemaValidator
{
    /** @var array<int, string> */
    protected array $allowedSectionKeys;

    /** @param  array<int, string>  $allowedSectionKeys  Empty = do not enforce; non-empty = section key must be in list */
    public function __construct(array $allowedSectionKeys = [], protected ?CmsSectionBindingService $sectionBindings = null)
    {
        $this->allowedSectionKeys = array_values(array_filter(array_map('strval', $allowedSectionKeys)));
    }

    /**
     * Validate AI output (pages_output, theme_output, or content_json with sections).
     * Returns list of error messages; empty if valid.
     *
     * @param  array<string, mixed>  $aiOutput
     * @return array<int, string>
     */
    public function validate(array $aiOutput): array
    {
        $errors = [];

        $this->rejectRawHtmlCss($aiOutput, '', $errors);
        if ($this->allowedSectionKeys !== []) {
            $this->rejectUnknownSectionKeys($aiOutput, $errors);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return void
     */
    protected function rejectRawHtmlCss(array $data, string $path, array &$errors): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path === '' ? $key : $path.'.'.$key;

            if (is_string($value)) {
                if ($this->looksLikeRawHtml($value)) {
                    $errors[] = "Raw HTML is not allowed at {$currentPath}. Use only structured content and component props.";
                }
                if ($this->looksLikeRawCss($value)) {
                    $errors[] = "Raw CSS is not allowed at {$currentPath}. Use theme tokens and style_variant only.";
                }
                continue;
            }

            if (is_array($value)) {
                $this->rejectRawHtmlCss($value, $currentPath, $errors);
            }
        }
    }

    protected function looksLikeRawHtml(string $value): bool
    {
        $trimmed = Str::trim($value);
        if ($trimmed === '') {
            return false;
        }
        return preg_match('/<\s*(script|iframe|object|embed|form|input|button|style)\b/i', $trimmed) === 1
            || (preg_match('/<[a-z][a-z0-9]*[\s>]/i', $trimmed) === 1 && Str::length($trimmed) > 50);
    }

    protected function looksLikeRawCss(string $value): bool
    {
        $trimmed = Str::trim($value);
        if ($trimmed === '') {
            return false;
        }
        return str_contains($trimmed, '{}') && (str_contains($trimmed, 'px') || str_contains($trimmed, 'rem') || str_contains($trimmed, ':'))
            && Str::length($trimmed) > 30;
    }

    /**
     * @param  array<string, mixed>  $aiOutput
     * @param  array<int, string>  $errors
     */
    protected function rejectUnknownSectionKeys(array $aiOutput, array &$errors): void
    {
        $allowed = array_map(static fn (string $k): string => Str::lower(Str::trim($k)), $this->allowedSectionKeys);
        $sections = Arr::get($aiOutput, 'sections', Arr::get($aiOutput, 'pages_output.0.sections', []));
        if (! is_array($sections)) {
            return;
        }

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = $section['key'] ?? $section['type'] ?? null;
            if ($key === null) {
                continue;
            }
            $normalized = Str::lower(Str::trim((string) $key));
            if ($normalized !== '' && ! $this->isAllowedSection($normalized, $section, $allowed)) {
                $errors[] = "Section key \"{$key}\" is not in the allowed component list. Use only registered Webu components.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<int, string>  $allowed
     */
    protected function isAllowedSection(string $normalizedKey, array $section, array $allowed): bool
    {
        if (in_array($normalizedKey, $allowed, true)) {
            return true;
        }

        $bindingSectionKey = Str::lower(Str::trim((string) data_get($section, 'binding.section_key')));
        if ($bindingSectionKey !== '' && in_array($bindingSectionKey, $allowed, true)) {
            return true;
        }

        if (! $this->sectionBindings) {
            return false;
        }

        foreach (array_values(array_unique(array_filter([$normalizedKey, $bindingSectionKey]))) as $candidate) {
            $resolved = $this->sectionBindings->resolveBinding($candidate);
            if (($resolved['source'] ?? null) !== 'sections_library') {
                continue;
            }

            $resolvedKey = Str::lower(Str::trim((string) ($resolved['section_key'] ?? '')));
            if ($resolvedKey !== '') {
                return true;
            }
        }

        return false;
    }
}
