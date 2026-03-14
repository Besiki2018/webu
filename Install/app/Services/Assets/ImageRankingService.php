<?php

namespace App\Services\Assets;

class ImageRankingService
{
    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $results, string $query, ?string $orientationPreference = null): array
    {
        $tokens = $this->tokenize($query);

        $scored = array_map(function (array $item) use ($tokens, $orientationPreference): array {
            $score = 0.0;

            $score += $this->queryRelevanceScore($item, $tokens) * 0.35;
            $score += $this->orientationScore($item, $orientationPreference) * 0.2;
            $score += $this->resolutionScore($item) * 0.2;
            $score += $this->brightnessScore($item) * 0.1;
            $score += $this->clarityScore($item) * 0.05;
            $score += min(1.0, max(0.0, (float) ($item['provider_priority'] ?? 0.0))) * 0.1;

            $item['score'] = round($score, 4);

            return $item;
        }, $results);

        usort($scored, function (array $left, array $right): int {
            $scoreComparison = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return ((int) ($right['width'] ?? 0) * (int) ($right['height'] ?? 0))
                <=> ((int) ($left['width'] ?? 0) * (int) ($left['height'] ?? 0));
        });

        return array_values($scored);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $tokens
     */
    private function queryRelevanceScore(array $item, array $tokens): float
    {
        if ($tokens === []) {
            return 0.5;
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) ($item['title'] ?? ''),
            (string) ($item['author'] ?? ''),
            (string) data_get($item, 'metadata.description', ''),
            (string) data_get($item, 'metadata.alt_description', ''),
            (string) data_get($item, 'metadata.alt', ''),
            (string) data_get($item, 'metadata.content_type', ''),
        ]))));

        if ($haystack === '') {
            return 0.2;
        }

        $matches = 0;
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                $matches++;
            }
        }

        return min(1.0, $matches / max(1, count($tokens)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function orientationScore(array $item, ?string $orientationPreference): float
    {
        if ($orientationPreference === null || $orientationPreference === '') {
            return 0.6;
        }

        $orientation = strtolower(trim((string) ($item['orientation'] ?? '')));

        return $orientation === strtolower($orientationPreference) ? 1.0 : 0.25;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolutionScore(array $item): float
    {
        $width = max(0, (int) ($item['width'] ?? 0));
        $height = max(0, (int) ($item['height'] ?? 0));
        $pixels = $width * $height;
        if ($pixels <= 0) {
            return 0.0;
        }

        return min(1.0, $pixels / 2_400_000);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function brightnessScore(array $item): float
    {
        $color = data_get($item, 'metadata.color') ?? data_get($item, 'metadata.avg_color') ?? data_get($item, 'metadata.preview_color');
        if (! is_string($color) || ! preg_match('/^#?([a-f0-9]{6})$/i', trim($color), $matches)) {
            return 0.5;
        }

        $hex = $matches[1];
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
        $normalized = $brightness / 255;

        return 1 - min(1.0, abs($normalized - 0.62) / 0.62);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function clarityScore(array $item): float
    {
        $hasPreview = is_string($item['preview_url'] ?? null) && trim((string) $item['preview_url']) !== '';
        $hasFull = is_string($item['full_url'] ?? null) && trim((string) $item['full_url']) !== '';
        $width = max(0, (int) ($item['width'] ?? 0));
        $height = max(0, (int) ($item['height'] ?? 0));

        $score = 0.0;
        if ($hasPreview) {
            $score += 0.35;
        }
        if ($hasFull) {
            $score += 0.35;
        }
        if (min($width, $height) >= 900) {
            $score += 0.3;
        } elseif (min($width, $height) >= 600) {
            $score += 0.15;
        }

        return min(1.0, $score);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $query): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower(trim($query))) ?: [];

        return array_values(array_filter($parts, fn (string $token): bool => $token !== ''));
    }
}
