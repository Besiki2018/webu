<?php

namespace App\Support;

/**
 * Mandatory storage layout for tenant-isolated media:
 * storage/tenants/{tenantId}/websites/{websiteId}/media/{yyyy}/{mm}/<filename>.<ext>
 * No global media path for tenant content; no filename collisions across tenants.
 */
class TenancyStoragePaths
{
    private const BASE = 'tenants';

    public static function mediaPath(string $tenantId, string $websiteId, string $filename): string
    {
        $now = now();
        $yyyy = $now->format('Y');
        $mm = $now->format('m');

        return self::BASE . '/' . $tenantId . '/websites/' . $websiteId . '/media/' . $yyyy . '/' . $mm . '/' . $filename;
    }

    /**
     * Base directory for a website's media (for listing/deletion).
     */
    public static function websiteMediaBase(string $tenantId, string $websiteId): string
    {
        return self::BASE . '/' . $tenantId . '/websites/' . $websiteId . '/media';
    }

    /**
     * Base directory for a tenant's storage (for tenant delete).
     */
    public static function tenantBase(string $tenantId): string
    {
        return self::BASE . '/' . $tenantId;
    }

    /**
     * Base directory for a website's storage (for website delete).
     */
    public static function websiteBase(string $tenantId, string $websiteId): string
    {
        return self::BASE . '/' . $tenantId . '/websites/' . $websiteId;
    }

    /**
     * Public URL for a path (relative to storage disk root).
     */
    public static function mediaUrl(string $path, string $disk = 'public'): string
    {
        return \Illuminate\Support\Facades\Storage::disk($disk)->url($path);
    }

    /**
     * Ensure path is under tenant/website so no cross-tenant access.
     */
    public static function pathBelongsToWebsite(string $path, string $tenantId, string $websiteId): bool
    {
        $prefix = self::websiteMediaBase($tenantId, $websiteId);

        return str_starts_with($path, $prefix . '/') || $path === $prefix;
    }
}
