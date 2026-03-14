<?php

namespace App\Services\Assets;

use Illuminate\Support\Facades\Http;

class FreepikProvider implements StockImageProviderInterface
{
    public function __construct(
        protected StockImageProviderConfig $config
    ) {}

    public function providerKey(): string
    {
        return 'freepik';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $apiKey = $this->config->requireValue('freepik', 'key');

        $response = Http::acceptJson()
            ->timeout(12)
            ->withHeaders([
                'x-freepik-api-key' => $apiKey,
            ])
            ->get('https://api.freepik.com/v1/resources', [
                'term' => $query,
                'page' => 1,
                'limit' => max(1, min($limit, 50)),
            ]);

        if (! $response->successful()) {
            return [];
        }

        $items = $response->json('data', []);
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $image = is_array($item['image'] ?? null) ? $item['image'] : [];
                $source = is_array($image['source'] ?? null) ? $image['source'] : [];
                $previewUrl = $source['url'] ?? $image['url'] ?? null;
                $fullUrl = $source['url'] ?? $image['url'] ?? $previewUrl;
                $width = (int) ($source['width'] ?? $image['width'] ?? 0);
                $height = (int) ($source['height'] ?? $image['height'] ?? 0);
                $identifier = $item['id'] ?? $item['uuid'] ?? null;
                $downloadUrl = null;

                if ($identifier !== null) {
                    $downloadUrl = 'https://api.freepik.com/v1/resources/'.urlencode((string) $identifier).'/download';
                }

                return [
                    'provider' => $this->providerKey(),
                    'id' => (string) ($identifier ?? ''),
                    'title' => trim((string) ($item['title'] ?? $item['slug'] ?? '')) ?: 'Freepik asset',
                    'preview_url' => is_string($previewUrl) ? $previewUrl : '',
                    'full_url' => is_string($fullUrl) ? $fullUrl : '',
                    'download_url' => is_string($downloadUrl) ? $downloadUrl : (is_string($fullUrl) ? $fullUrl : ''),
                    'width' => $width,
                    'height' => $height,
                    'author' => (string) ($item['author']['name'] ?? $item['author_name'] ?? ''),
                    'license' => 'Freepik License',
                    'orientation' => $this->resolveOrientation($width, $height),
                    'provider_priority' => 0.75,
                    'metadata' => [
                        'content_type' => $item['content_type'] ?? null,
                        'license' => $item['license'] ?? null,
                        'url' => $item['url'] ?? null,
                        'preview_color' => $image['color'] ?? null,
                    ],
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '' && $item['preview_url'] !== '' && $item['download_url'] !== '')
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
