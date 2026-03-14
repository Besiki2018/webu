<?php

namespace App\Services\Assets;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Models\Media;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Website;
use App\Services\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const DOWNLOAD_HOSTS = [
        'unsplash' => ['images.unsplash.com'],
        'pexels' => ['images.pexels.com'],
        'freepik' => ['api.freepik.com', 'img.freepik.com'],
    ];

    public function __construct(
        protected CmsRepositoryContract $repository,
        protected UploadSecurityService $uploadSecurity,
        protected StockImageProviderConfig $providerConfig
    ) {}

    /**
     * @param  array{
     *   provider: string,
     *   image_id: string,
     *   download_url: string,
     *   title?: string|null,
     *   author?: string|null,
     *   license?: string|null,
     *   imported_by?: string|null,
     *   section_local_id?: string|null,
     *   component_key?: string|null,
     *   page_slug?: string|null,
     *   page_id?: string|null,
     *   query?: string|null
     * }  $payload
     */
    public function import(Project $project, User $user, array $payload): Media
    {
        $site = $project->site;
        if (! $site instanceof Site) {
            throw new CmsDomainException('Project site is not ready for media imports.', 422);
        }

        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $imageId = trim((string) ($payload['image_id'] ?? ''));
        $downloadUrl = trim((string) ($payload['download_url'] ?? ''));

        if ($provider === '' || $imageId === '' || $downloadUrl === '') {
            throw new CmsDomainException('Provider, image_id, and download_url are required.', 422);
        }

        $this->assertProviderDownloadAllowed($provider, $downloadUrl);

        $resolved = $this->downloadImageBinary($provider, $downloadUrl, $imageId);
        $optimized = $this->optimizeImage(
            $resolved['binary'],
            $resolved['mime'],
            $resolved['extension']
        );

        $fileSize = strlen($optimized['binary']);
        if ($fileSize <= 0) {
            throw new CmsDomainException('Downloaded image is empty.', 422);
        }

        $this->assertStorageAllowed($user, $fileSize);

        $filename = $this->buildFilename($provider, $imageId, $optimized['extension']);
        $path = sprintf('projects/%s/assets/images/%s', $project->id, $filename);
        Storage::disk('public')->put($path, $optimized['binary']);

        $websiteId = Website::query()
            ->where('site_id', $site->id)
            ->latest('created_at')
            ->value('id');

        $media = $this->repository->createMedia($site, [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'website_id' => $websiteId,
            'path' => $path,
            'url' => route('public.sites.assets', ['site' => $site->id, 'path' => $path]),
            'file_name' => $filename,
            'mime' => $optimized['mime'],
            'mime_type' => $optimized['mime'],
            'size' => $fileSize,
            'width' => $optimized['width'],
            'height' => $optimized['height'],
            'alt' => is_string($payload['title'] ?? null) ? trim((string) $payload['title']) : null,
            'meta_json' => [
                'original_name' => $filename,
                'uploaded_by' => $user->id,
                'extension' => $optimized['extension'],
                'asset_origin' => 'stock_provider',
                'stock_provider' => $provider,
                'stock_image_id' => $imageId,
                'stock_author' => is_string($payload['author'] ?? null) ? trim((string) $payload['author']) : null,
                'stock_license' => is_string($payload['license'] ?? null) ? trim((string) $payload['license']) : null,
                'stock_query' => is_string($payload['query'] ?? null) ? trim((string) $payload['query']) : null,
                'imported_by' => is_string($payload['imported_by'] ?? null) ? trim((string) $payload['imported_by']) : 'user',
                'project_id' => (string) $project->id,
                'section_local_id' => is_string($payload['section_local_id'] ?? null) ? trim((string) $payload['section_local_id']) : null,
                'component_key' => is_string($payload['component_key'] ?? null) ? trim((string) $payload['component_key']) : null,
                'page_slug' => is_string($payload['page_slug'] ?? null) ? trim((string) $payload['page_slug']) : null,
                'page_id' => is_string($payload['page_id'] ?? null) ? trim((string) $payload['page_id']) : null,
                'imported_at' => now()->toIso8601String(),
            ],
        ]);

        $project->incrementStorageUsed($fileSize);

        return $media;
    }

    private function assertStorageAllowed(User $user, int $fileSize): void
    {
        if ($user->hasAdminBypass()) {
            return;
        }

        $plan = $user->getCurrentPlan();
        if (! $plan || ! $plan->fileStorageEnabled()) {
            throw new CmsDomainException('File storage is not enabled for your plan.', 403);
        }

        $remaining = $user->getRemainingStorageBytes();
        if ($remaining !== -1 && $fileSize > $remaining) {
            throw new CmsDomainException('Storage quota exceeded. Upgrade your plan or remove files.', 422);
        }
    }

    private function assertProviderDownloadAllowed(string $provider, string $downloadUrl): void
    {
        if (! filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            throw new CmsDomainException('Invalid download URL.', 422);
        }

        $host = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
        $allowedHosts = self::DOWNLOAD_HOSTS[$provider] ?? [];
        if ($host === '' || $allowedHosts === []) {
            throw new CmsDomainException('Unsupported stock image provider.', 422);
        }

        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return;
            }
        }

        throw new CmsDomainException('Download host is not allowed for the selected provider.', 422);
    }

    /**
     * @return array{binary: string, mime: string, extension: string}
     */
    private function downloadImageBinary(string $provider, string $downloadUrl, string $imageId): array
    {
        if ($provider === 'freepik' && str_contains($downloadUrl, 'api.freepik.com/v1/resources/')) {
            $downloadUrl = $this->resolveFreepikDownloadUrl($downloadUrl, $imageId);
        }

        $response = Http::timeout(20)
            ->withHeaders([
                'Accept' => 'image/*',
                'User-Agent' => 'Webu Stock Image Importer/1.0',
            ])
            ->get($downloadUrl);

        if (! $response->successful()) {
            throw new CmsDomainException('Failed to download stock image.', 502);
        }

        $binary = $response->body();
        if (! is_string($binary) || $binary === '') {
            throw new CmsDomainException('Downloaded stock image is empty.', 422);
        }

        $mime = strtolower(trim((string) ($response->header('Content-Type') ?: 'application/octet-stream')));
        $extension = $this->extensionFromMime($mime);

        $tempPath = tempnam(sys_get_temp_dir(), 'webu-stock-');
        if ($tempPath === false) {
            throw new CmsDomainException('Failed to prepare temporary image file.', 500);
        }

        file_put_contents($tempPath, $binary);
        $uploadedFile = new UploadedFile(
            $tempPath,
            'stock-import.'.$extension,
            $mime !== '' ? $mime : null,
            null,
            true
        );

        try {
            $securityMeta = $this->uploadSecurity->assertSafeUpload($uploadedFile, ['image/*']);
        } finally {
            @unlink($tempPath);
        }

        return [
            'binary' => $binary,
            'mime' => $securityMeta['mime'] ?? ($mime !== '' ? $mime : 'image/jpeg'),
            'extension' => $securityMeta['extension'] !== '' ? $securityMeta['extension'] : $extension,
        ];
    }

    private function resolveFreepikDownloadUrl(string $apiDownloadUrl, string $imageId): string
    {
        $apiKey = $this->providerConfig->requireValue('freepik', 'key');

        $response = Http::acceptJson()
            ->timeout(12)
            ->withHeaders([
                'x-freepik-api-key' => $apiKey,
            ])
            ->get($apiDownloadUrl, [
                'resource_id' => $imageId,
            ]);

        if (! $response->successful()) {
            throw new CmsDomainException('Failed to resolve Freepik download URL.', 502);
        }

        $resolved = $response->json('data.url')
            ?? $response->json('url')
            ?? $response->json('data.download_url')
            ?? $response->json('download_url');

        if (! is_string($resolved) || trim($resolved) === '') {
            throw new CmsDomainException('Freepik download URL is missing from the provider response.', 502);
        }

        return trim($resolved);
    }

    /**
     * @return array{binary: string, mime: string, extension: string, width: int|null, height: int|null}
     */
    private function optimizeImage(string $binary, string $mime, string $extension): array
    {
        $imageInfo = @getimagesizefromstring($binary) ?: null;
        $width = is_array($imageInfo) ? (int) ($imageInfo[0] ?? 0) : 0;
        $height = is_array($imageInfo) ? (int) ($imageInfo[1] ?? 0) : 0;

        if (! function_exists('imagecreatefromstring')) {
            return [
                'binary' => $binary,
                'mime' => $mime,
                'extension' => $extension,
                'width' => $width > 0 ? $width : null,
                'height' => $height > 0 ? $height : null,
            ];
        }

        $resource = @imagecreatefromstring($binary);
        if ($resource === false) {
            return [
                'binary' => $binary,
                'mime' => $mime,
                'extension' => $extension,
                'width' => $width > 0 ? $width : null,
                'height' => $height > 0 ? $height : null,
            ];
        }

        ob_start();
        $outputMime = $mime;
        $outputExtension = $extension;

        if ($mime === 'image/png' && function_exists('imagepng')) {
            imagesavealpha($resource, true);
            imagepng($resource, null, 7);
        } elseif (function_exists('imagewebp')) {
            imagewebp($resource, null, 82);
            $outputMime = 'image/webp';
            $outputExtension = 'webp';
        } elseif (function_exists('imagejpeg')) {
            imagejpeg($resource, null, 85);
            $outputMime = 'image/jpeg';
            $outputExtension = 'jpg';
        }

        $optimized = ob_get_clean();
        imagedestroy($resource);

        if (! is_string($optimized) || $optimized === '') {
            $optimized = $binary;
        }

        return [
            'binary' => $optimized,
            'mime' => $outputMime,
            'extension' => $outputExtension,
            'width' => $width > 0 ? $width : null,
            'height' => $height > 0 ? $height : null,
        ];
    }

    private function buildFilename(string $provider, string $imageId, string $extension): string
    {
        $safeProvider = Str::slug($provider);
        $safeImageId = Str::slug($imageId);
        if ($safeProvider !== '' && $safeImageId !== '') {
            $safeImageId = preg_replace('/^'.preg_quote($safeProvider, '/').'[-_]?/', '', $safeImageId) ?: $safeImageId;
        }
        $normalizedExtension = trim($extension) !== '' ? trim($extension) : 'jpg';

        return sprintf(
            'stock-%s-%s-%s.%s',
            $safeProvider,
            $safeImageId !== '' ? $safeImageId : Str::random(8),
            now()->format('YmdHis'),
            $normalizedExtension
        );
    }

    private function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            default => 'jpg',
        };
    }
}
