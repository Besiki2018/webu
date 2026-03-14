<?php

namespace App\Services\Assets;

interface StockImageProviderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array;

    public function providerKey(): string;
}
