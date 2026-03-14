<?php

namespace App\Services\Assets;

use Illuminate\Support\Facades\Http;

class PexelsProvider implements StockImageProviderInterface
{
    public function __construct(
        protected StockImageProviderConfig $config
    ) {}

    public function providerKey(): string
    {
        return 'pexels';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $apiKey = $this->config->requireValue('pexels', 'key');

        $response = Http::acceptJson()
            ->timeout(10)
            ->withHeaders([
                'Authorization' => $apiKey,
            ])
            ->get('https://api.pexels.com/v1/search', [
                'query' => $query,
                'page' => 1,
                'per_page' => max(1, min($limit, 80)),
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('photos', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $previewUrl = $item['src']['large'] ?? $item['src']['medium'] ?? $item['src']['tiny'] ?? null;
                $fullUrl = $item['src']['original'] ?? $item['src']['large2x'] ?? $item['src']['large'] ?? $previewUrl;

                return [
                    'provider' => $this->providerKey(),
                    'id' => (string) ($item['id'] ?? ''),
                    'title' => trim((string) ($item['alt'] ?? '')) ?: (string) ($item['photographer'] ?? 'Pexels photo'),
                    'preview_url' => is_string($previewUrl) ? $previewUrl : '',
                    'full_url' => is_string($fullUrl) ? $fullUrl : '',
                    'download_url' => is_string($fullUrl) ? $fullUrl : '',
                    'width' => (int) ($item['width'] ?? 0),
                    'height' => (int) ($item['height'] ?? 0),
                    'author' => (string) ($item['photographer'] ?? ''),
                    'license' => 'Pexels License',
                    'orientation' => $this->resolveOrientation((int) ($item['width'] ?? 0), (int) ($item['height'] ?? 0)),
                    'provider_priority' => 0.92,
                    'metadata' => [
                        'alt' => $item['alt'] ?? null,
                        'avg_color' => $item['avg_color'] ?? null,
                        'photographer_url' => $item['photographer_url'] ?? null,
                        'pexels_url' => $item['url'] ?? null,
                    ],
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '' && $item['preview_url'] !== '' && $item['full_url'] !== '')
            ->values()
            ->all();
    }

    private function resolveOrientation(int $width, int $height): string
    {
        if ($width > 0 && $height > 0) {
            if ($width > $height) {
                return 'landscape';
            }

            if ($width < $height) {
                return 'portrait';
            }
        }

        return 'square';
    }
}
