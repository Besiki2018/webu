<?php

namespace App\Cms\Contracts;

use App\Models\Site;
use App\Models\User;

interface CmsModuleRegistryServiceContract
{
    /**
     * @return array{
     *   site_id: string,
     *   project_id: string,
     *   modules: array<int, array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public function modules(Site $site, ?User $user = null): array;

    /**
     * @return array{
     *   site_id: string,
     *   project_id: string,
     *   features: array<string, bool>,
     *   modules: array<string, bool>,
     *   reasons: array<string, string|null>,
     *   plan: array<string, mixed>|null
     * }
     */
    public function entitlements(Site $site, ?User $user = null): array;
}
