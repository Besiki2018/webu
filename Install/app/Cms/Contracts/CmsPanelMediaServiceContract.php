<?php

namespace App\Cms\Contracts;

use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface CmsPanelMediaServiceContract
{
    /**
     * @return array{
     *   site_id: string,
     *   media: array<int, array<string, mixed>>
     * }
     */
    public function listMedia(Site $site): array;

    public function maxUploadKilobytes(User $user): int;

    public function upload(Site $site, UploadedFile $file, User $user): Media;

    /**
     * @param  array{alt?: string|null, description?: string|null}  $attributes
     */
    public function updateMetadata(Site $site, Media $media, array $attributes): Media;

    public function delete(Site $site, Media $media): void;
}
