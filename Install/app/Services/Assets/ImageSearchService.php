<?php

namespace App\Services\Assets;

class ImageSearchService
{
    /**
     * @var array<int, StockImageProviderInterface>
     */
    private array $providers;

    public function __construct(
        UnsplashProvider $unsplash,
        PexelsProvider $pexels,
        FreepikProvider $freepik,
        protected ImageRankingService $ranking
    ) {
        $this->providers = [$unsplash, $pexels, $freepik];
    }

    /**
     * @param  array{orientation?: string|null}  $options
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 12, array $options = []): array
    {
        $normalizedQuery = trim($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $providerLimit = max($limit, 6);
        $merged = [];
        foreach ($this->providers as $provider) {
            $merged = [...$merged, ...$provider->search($normalizedQuery, $providerLimit)];
        }

        $deduped = $this->dedupe($merged);
        $ranked = $this->ranking->rank(
            $deduped,
            $normalizedQuery,
            isset($options['orientation']) && is_string($options['orientation']) ? $options['orientation'] : null
        );

        return array_slice($ranked, 0, max(1, min($limit, 30)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $results): array
    {
        $unique = [];
        $seen = [];

        foreach ($results as $item) {
            $key = $this->dedupeKey($item);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function dedupeKey(array $item): string
    {
        $fullUrl = is_string($item['full_url'] ?? null) ? trim((string) $item['full_url']) : '';
        if ($fullUrl !== '') {
            $parsed = parse_url($fullUrl);
            $host = strtolower((string) ($parsed['host'] ?? ''));
            $path = strtolower((string) ($parsed['path'] ?? ''));

            return trim($host.$path);
        }

        return strtolower(trim((string) ($item['provider'] ?? '').':'.(string) ($item['id'] ?? '')));
    }
}
