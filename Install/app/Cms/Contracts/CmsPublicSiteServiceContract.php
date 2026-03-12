<?php

namespace App\Cms\Contracts;

use App\Models\Media;
use App\Models\Site;
use App\Models\User;

interface CmsPublicSiteServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $domain, ?string $requestedLocale = null, ?User $viewer = null): array;

    /**
     * @return array<string, mixed>
     */
    public function settings(Site $site, ?string $requestedLocale = null, ?User $viewer = null): array;

    /**
     * @return array<string, mixed>
     */
    public function typography(Site $site, ?string $requestedLocale = null, ?User $viewer = null): array;

    /**
     * @return array<string, mixed>
     */
    public function menu(Site $site, string $key, ?string $requestedLocale = null, ?User $viewer = null): array;

    /**
     * @return array<string, mixed>
     */
    public function page(
        Site $site,
        string $slug,
        ?string $requestedLocale = null,
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array;

    public function asset(Site $site, string $path, ?User $viewer = null): Media;
}
