<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPanelMediaServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use App\Services\UploadSecurityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CmsPanelMediaService implements CmsPanelMediaServiceContract
{
    /**
     * @var array<int, string>
     */
    private const DEFAULT_ALLOWED_MEDIA_MIME_PATTERNS = [
        'image/*',
        'application/pdf',
        'video/*',
        'audio/*',
        'text/plain',
        'application/json',
    ];

    public function __construct(
        protected CmsRepositoryContract $repository,
        protected UploadSecurityService $uploadSecurity
    ) {}

    public function listMedia(Site $site): array
    {
        $items = $this->repository->listMedia($site)
            ->map(fn (Media $item): array => [
                'id' => $item->id,
                'site_id' => $item->site_id,
                'path' => $item->path,
                'mime' => $item->mime,
                'size' => (int) $item->size,
                'meta_json' => $item->meta_json ?? [],
                'asset_url' => route('public.sites.assets', ['site' => $site->id, 'path' => $item->path]),
                'created_at' => $item->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'media' => $items,
        ];
    }

    public function maxUploadKilobytes(User $user): int
    {
        if ($user->hasAdminBypass()) {
            // Keep local/dev uploads practical while still bounded for safety.
            return 50 * 1024;
        }

        $plan = $user->getCurrentPlan();
        if (! $plan || ! $plan->fileStorageEnabled()) {
            throw new CmsDomainException('File storage is not enabled for your plan.', 403);
        }

        return max(1, $plan->getMaxFileSizeMb()) * 1024;
    }

    public function upload(Site $site, UploadedFile $file, User $user): Media
    {
        $maxKb = $this->maxUploadKilobytes($user);
        $fileSize = (int) $file->getSize();

        if ($fileSize > ($maxKb * 1024)) {
            throw new CmsDomainException('Uploaded file exceeds plan max file size.', 422);
        }

        $remaining = $user->getRemainingStorageBytes();
        if ($remaining !== -1 && $fileSize > $remaining) {
            throw new CmsDomainException('Storage quota exceeded. Upgrade your plan or remove files.', 422);
        }

        $plan = $user->getCurrentPlan();
        $allowedMimePatterns = is_array($plan?->getAllowedFileTypes()) && $plan?->getAllowedFileTypes() !== []
            ? array_values(array_filter($plan->getAllowedFileTypes(), fn (mixed $value): bool => is_string($value) && trim($value) !== ''))
            : self::DEFAULT_ALLOWED_MEDIA_MIME_PATTERNS;

        try {
            $securityMeta = $this->uploadSecurity->assertSafeUpload($file, $allowedMimePatterns);
        } catch (\RuntimeException $exception) {
            throw new CmsDomainException($exception->getMessage(), 422);
        }

        $path = $file->store("site-media/{$site->id}", 'public');

        $media = $this->repository->createMedia($site, [
            'path' => $path,
            'mime' => $securityMeta['mime'] ?? ($file->getMimeType() ?: 'application/octet-stream'),
            'size' => $fileSize,
            'meta_json' => [
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $user->id,
                'extension' => $securityMeta['extension'] ?? $file->getClientOriginalExtension(),
            ],
        ]);

        $site->project?->incrementStorageUsed($fileSize);

        return $media;
    }

    public function updateMetadata(Site $site, Media $media, array $attributes): Media
    {
        $target = $this->repository->findMediaById($site, $media->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(Media::class, [(string) $media->id]);
        }

        $meta = is_array($target->meta_json) ? $target->meta_json : [];

        if (array_key_exists('alt', $attributes)) {
            $alt = is_string($attributes['alt']) ? trim($attributes['alt']) : '';
            $meta['alt'] = $alt !== '' ? $alt : null;
        }

        if (array_key_exists('description', $attributes)) {
            $description = is_string($attributes['description']) ? trim($attributes['description']) : '';
            $meta['description'] = $description !== '' ? $description : null;
        }

        return $this->repository->updateMedia($target, [
            'meta_json' => $meta,
        ]);
    }

    public function delete(Site $site, Media $media): void
    {
        if (! $this->repository->findMediaById($site, $media->id)) {
            throw (new ModelNotFoundException)->setModel(Media::class, [(string) $media->id]);
        }

        if (Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }

        $size = (int) $media->size;
        $this->repository->deleteMedia($media);

        if ($size > 0) {
            $site->project?->decrementStorageUsed($size);
        }
    }
}
