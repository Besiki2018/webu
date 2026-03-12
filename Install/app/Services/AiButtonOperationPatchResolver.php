<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AiButtonOperationPatchResolver
{
    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolvePatch(array $operation, array $props = []): array
    {
        $patch = is_array($operation['patch'] ?? null) ? $operation['patch'] : [];
        if ($patch !== []) {
            return $patch;
        }

        $resolved = [];

        if (isset($operation['label']) && is_string($operation['label'])) {
            Arr::set($resolved, $this->resolveLabelPath($props), $operation['label']);
        }

        if (isset($operation['href']) && is_string($operation['href'])) {
            Arr::set($resolved, $this->resolveLinkPath($props), $operation['href']);
        }

        if (isset($operation['variant']) && is_string($operation['variant'])) {
            Arr::set($resolved, $this->resolveVariantPath($props), $operation['variant']);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function resolveLabelPath(array $props): string
    {
        $preferred = $this->firstExistingPath($props, [
            'primary_cta.label',
            'cta.label',
            'buttonLabel',
            'buttonText',
            'hero_cta_label',
            'cta_label',
            'button_label',
            'announcement_cta_label',
            'top_strip_cta_label',
            'secondary_cta.label',
            'hero_cta_secondary_label',
        ]);

        return $preferred ?? $this->firstCandidatePath($props, 'label') ?? 'buttonText';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function resolveLinkPath(array $props): string
    {
        $preferred = $this->firstExistingPath($props, [
            'primary_cta.url',
            'primary_cta.href',
            'primary_cta.link',
            'cta.url',
            'cta.href',
            'cta.link',
            'buttonLink',
            'buttonUrl',
            'buttonHref',
            'hero_cta_url',
            'cta_url',
            'button_url',
            'announcement_cta_url',
            'top_strip_cta_url',
            'secondary_cta.url',
            'secondary_cta.href',
            'secondary_cta.link',
            'hero_cta_secondary_url',
        ]);

        return $preferred ?? $this->firstCandidatePath($props, 'link') ?? 'buttonLink';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function resolveVariantPath(array $props): string
    {
        $preferred = $this->firstExistingPath($props, [
            'primary_cta.variant',
            'cta.variant',
            'buttonVariant',
            'button_variant',
            'cta_variant',
            'secondary_cta.variant',
        ]);

        return $preferred ?? $this->firstCandidatePath($props, 'variant') ?? 'buttonVariant';
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $paths
     */
    private function firstExistingPath(array $props, array $paths): ?string
    {
        foreach ($paths as $path) {
            if (Arr::has($props, $path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function firstCandidatePath(array $props, string $kind): ?string
    {
        $candidates = $this->collectCandidatePaths($props, $kind);
        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (string $left, string $right): int => $this->scorePath($left, $kind) <=> $this->scorePath($right, $kind));

        return $candidates[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, string>
     */
    private function collectCandidatePaths(array $props, string $kind, string $prefix = ''): array
    {
        $paths = [];

        foreach ($props as $key => $value) {
            $segment = trim((string) $key);
            if ($segment === '') {
                continue;
            }

            $path = $prefix !== '' ? $prefix.'.'.$segment : $segment;
            if (is_array($value) && ! array_is_list($value)) {
                $paths = [...$paths, ...$this->collectCandidatePaths($value, $kind, $path)];
            }

            if ($this->matchesCandidateKind($path, $kind)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    private function matchesCandidateKind(string $path, string $kind): bool
    {
        $normalized = Str::lower($path);

        return match ($kind) {
            'label' => preg_match('/(^|\.|_)(label|text|buttonlabel|buttontext|cta_label|cta_text)$/', $normalized) === 1
                || (
                    preg_match('/(button|cta|action)/', $normalized) === 1
                    && preg_match('/(label|text)/', $normalized) === 1
                ),
            'link' => preg_match('/(^|\.|_)(url|href|link|buttonurl|buttonhref|buttonlink|cta_url|cta_href|cta_link)$/', $normalized) === 1
                || (
                    preg_match('/(button|cta|action)/', $normalized) === 1
                    && preg_match('/(url|href|link)/', $normalized) === 1
                ),
            'variant' => preg_match('/(^|\.|_)(variant|style|buttonvariant|cta_variant)$/', $normalized) === 1
                || (
                    preg_match('/(button|cta|action)/', $normalized) === 1
                    && preg_match('/(variant|style)/', $normalized) === 1
                ),
            default => false,
        };
    }

    private function scorePath(string $path, string $kind): int
    {
        $normalized = Str::lower($path);
        $score = 100;

        if (Str::contains($normalized, 'primary_cta')) {
            $score -= 40;
        }
        if (Str::contains($normalized, 'secondary_cta')) {
            $score += 25;
        }
        if (Str::contains($normalized, 'announcement')) {
            $score += 10;
        }
        if (Str::contains($normalized, 'top_strip')) {
            $score += 12;
        }
        if (Str::contains($normalized, ['buttonlabel', 'button_label', 'buttontext', 'button_text', 'buttonurl', 'button_url', 'buttonlink', 'button_link'])) {
            $score -= 12;
        }
        if (Str::contains($normalized, ['cta.label', 'cta_label', 'cta.url', 'cta_url', 'cta.link', 'cta_link'])) {
            $score -= 8;
        }

        if ($kind === 'variant' && Str::contains($normalized, 'variant')) {
            $score -= 10;
        }

        return $score + substr_count($normalized, '.');
    }
}
