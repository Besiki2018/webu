<?php

namespace App\Services;

use App\Models\Website;
use App\Support\TenancyStoragePaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;

/**
 * Universal CMS media: store under tenant/website path when tenant_id is set,
 * otherwise legacy storage/websites/{site_id}/media.
 */
class UniversalCmsMediaService
{
    private const DISK = 'public';
    private const BASE_DIR = 'websites';

    public function basePath(Website $website): string
    {
        if ($website->tenant_id && $website->id) {
            return TenancyStoragePaths::websiteMediaBase($website->tenant_id, $website->id);
        }
        $siteId = $website->site_id ?? $website->id;

        return self::BASE_DIR . '/' . $siteId . '/media';
    }

    /**
     * List all files in the website media folder.
     *
     * @return array<int, array{path: string, url: string, name: string, size: int}>
     */
    public function list(Website $website): array
    {
        $base = $this->basePath($website);
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($base)) {
            return [];
        }

        $files = str_contains($base, 'tenants/') ? $disk->allFiles($base) : $disk->files($base);
        $out = [];

        foreach ($files as $path) {
            $out[] = [
                'path' => $path,
                'url' => $disk->url($path),
                'name' => basename($path),
                'size' => (int) $disk->size($path),
            ];
        }

        return $out;
    }

    /**
     * Upload a file; return path relative to storage for use in settings (e.g. image field).
     * When website has tenant_id, stores under tenants/{tenantId}/websites/{websiteId}/media/{yyyy}/{mm}/...
     */
    public function upload(Website $website, UploadedFile $file): string
    {
        $disk = Storage::disk(self::DISK);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $name = time() . '_' . $safeName;

        if ($website->tenant_id && $website->id) {
            $path = TenancyStoragePaths::mediaPath($website->tenant_id, $website->id, $name);
            $fullPath = $disk->path($path);
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file->move($dir, $name);
        } else {
            $base = $this->basePath($website);
            $path = $file->storeAs($base, $name, ['disk' => self::DISK]);
        }

        if (! is_string($path) || $path === '') {
            throw new UploadException(__('Upload failed.'));
        }

        return $path;
    }

    /**
     * Delete a file by path (must be under this website's media folder).
     * When tenant-scoped, validates path is under tenant/website.
     */
    public function delete(Website $website, string $path): bool
    {
        $base = $this->basePath($website);
        $disk = Storage::disk(self::DISK);

        if ($website->tenant_id && $website->id) {
            if (! TenancyStoragePaths::pathBelongsToWebsite($path, $website->tenant_id, $website->id)) {
                return false;
            }
        } elseif (! str_starts_with($path, $base . '/') && $path !== $base) {
            return false;
        }

        return $disk->delete($path);
    }

    /**
     * Public URL for a stored path (for use in section image src).
     */
    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }
}
