<?php

namespace App\Cms\Contracts;

use App\Models\Site;
use App\Models\SiteCustomFont;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface CmsPanelSiteServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function settings(Site $site, ?string $requestedLocale = null): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateSettings(Site $site, array $payload): void;

    /**
     * @return array<string, mixed>
     */
    public function typography(Site $site): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateTypography(Site $site, array $payload): array;

    /**
     * @return array<int, string>
     */
    public function allowedFontKeys(Site $site): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function uploadCustomFont(Site $site, UploadedFile $file, User $user, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteCustomFont(Site $site, SiteCustomFont $font): array;
}
