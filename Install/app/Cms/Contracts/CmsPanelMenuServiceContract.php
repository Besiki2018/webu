<?php

namespace App\Cms\Contracts;

use App\Models\Site;

interface CmsPanelMenuServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function index(Site $site, ?string $requestedLocale = null): array;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function store(Site $site, string $key, array $items = [], ?string $locale = null): array;

    /**
     * @return array<string, mixed>
     */
    public function show(Site $site, string $key, ?string $requestedLocale = null): array;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function update(Site $site, string $key, array $items, ?string $locale = null): array;

    /**
     * @return array<string, mixed>
     */
    public function destroy(Site $site, string $key): array;
}
