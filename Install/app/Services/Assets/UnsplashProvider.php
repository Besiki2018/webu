<?php

namespace App\Services\Assets;

use Illuminate\Support\Facades\Http;

class UnsplashProvider implements StockImageProviderInterface
{
    public function __construct(
        protected StockImageProviderConfig $config
    ) {}

    public function providerKey(): string
    {
        return 'unsplash';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $accessKey = $this->config->requireValue('unsplash', 'access_key');

        $response = Http::acceptJson()
            ->timeout(10)
            ->withHeaders([
                'Authorization' => "Client-ID {$accessKey}",
            ])
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'page' => 1,
                'per_page' => max(1, min($limit, 30)),
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('results', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $title = trim((string) ($item['alt_description'] ?? $item['description'] ?? ''));
                $previewUrl = $item['urls']['small'] ?? $item['urls']['regular'] ?? $item['urls']['thumb'] ?? null;
                $fullUrl = $item['urls']['full'] ?? $item['urls']['raw'] ?? $item['urls']['regular'] ?? $previewUrl;
                $color = is_string($item['color'] ?? null) ? (string) $item['color'] : null;

                return [
                    'provider' => $this->providerKey(),
                    'id' => (string) ($item['id'] ?? ''),
                    'title' => $title !== '' ? $title : (string) ($item['user']['name'] ?? 'Unsplash photo'),
                    'preview_url' => is_string($previewUrl) ? $previewUrl : '',
                    'full_url' => is_string($fullUrl) ? $fullUrl : '',
                    'download_url' => is_string($fullUrl) ? $fullUrl : '',
                    'width' => (int) ($item['width'] ?? 0),
                    'height' => (int) ($item['height'] ?? 0),
                    'author' => (string) ($item['user']['name'] ?? ''),
                    'license' => 'Unsplash License',
                    'orientation' => $this->resolveOrientation((int) ($item['width'] ?? 0), (int) ($item['height'] ?? 0)),
                    'provider_priority' => 1.0,
                    'metadata' => [
                        'description' => $item['description'] ?? null,
                        'alt_description' => $item['alt_description'] ?? null,
                        'color' => $color,
                        'download_tracking_url' => $item['links']['download_location'] ?? null,
                        'author_url' => $item['user']['links']['html'] ?? null,
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
