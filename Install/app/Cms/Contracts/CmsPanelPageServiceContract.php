<?php

namespace App\Cms\Contracts;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;

interface CmsPanelPageServiceContract
{
    /**
     * @return array{
     *   site_id: string,
     *   pages: array<int, array<string, mixed>>
     * }
     */
    public function listPages(Site $site): array;

    /**
     * @return array{
     *   site_id: string,
     *   page: array<string, mixed>,
     *   latest_revision: array<string, mixed>|null,
     *   published_revision: array<string, mixed>|null
     * }
     */
    public function getPageDetails(Site $site, Page $page, ?string $requestedLocale = null): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createPage(Site $site, array $payload, ?int $actorId): Page;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePage(Site $site, Page $page, array $payload): Page;

    public function deletePage(Site $site, Page $page): void;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRevision(Site $site, Page $page, array $payload, ?int $actorId, ?string $locale = null): PageRevision;

    public function publish(Site $site, Page $page, ?int $revisionId = null): PageRevision;
}
