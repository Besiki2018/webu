<?php

namespace App\Services\Tenancy;

use App\Support\TenancyStoragePaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Export tenant or website bundle (JSON/zip) for support or backup.
 * Includes media manifest (file paths under tenant/website storage).
 */
class ExportTenantService
{
    /**
     * Export full tenant bundle to audit/tenancy/exports/tenant-{id}-{ts}.zip
     */
    public function exportTenant(string $tenantId): string
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            throw new \InvalidArgumentException('Tenant not found.');
        }

        $data = [
            'tenant' => (array) $tenant,
            'websites' => DB::table('websites')->where('tenant_id', $tenantId)->get()->map(fn ($r) => (array) $r)->all(),
            'website_pages' => DB::table('website_pages')->where('tenant_id', $tenantId)->get()->map(fn ($r) => (array) $r)->all(),
            'page_sections' => DB::table('page_sections')->where('tenant_id', $tenantId)->get()->map(fn ($r) => (array) $r)->all(),
            'website_seo' => DB::table('website_seo')->where('tenant_id', $tenantId)->get()->map(fn ($r) => (array) $r)->all(),
            'website_revisions' => DB::table('website_revisions')->where('tenant_id', $tenantId)->get()->map(fn ($r) => (array) $r)->all(),
            'media_manifest' => $this->mediaManifestForTenant($tenantId),
        ];

        return $this->writeZip('tenant-' . $tenantId . '-' . now()->format('Y-m-d-His'), $data);
    }

    /**
     * Export single website bundle.
     */
    public function exportWebsite(string $tenantId, string $websiteId): string
    {
        $website = DB::table('websites')->where('tenant_id', $tenantId)->where('id', $websiteId)->first();
        if (! $website) {
            throw new \InvalidArgumentException('Website not found.');
        }

        $data = [
            'website' => (array) $website,
            'website_pages' => DB::table('website_pages')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->get()->map(fn ($r) => (array) $r)->all(),
            'page_sections' => DB::table('page_sections')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->get()->map(fn ($r) => (array) $r)->all(),
            'website_seo' => DB::table('website_seo')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->get()->map(fn ($r) => (array) $r)->all(),
            'website_revisions' => DB::table('website_revisions')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->get()->map(fn ($r) => (array) $r)->all(),
            'media_manifest' => $this->mediaManifestForWebsite($tenantId, $websiteId),
        ];

        return $this->writeZip('website-' . $websiteId . '-' . now()->format('Y-m-d-His'), $data);
    }

    /**
     * List file paths under tenant storage (all websites).
     * @return array<int, string>
     */
    private function mediaManifestForTenant(string $tenantId): array
    {
        $base = TenancyStoragePaths::tenantBase($tenantId);
        $disk = Storage::disk('public');

        return $disk->exists($base) ? $disk->allFiles($base) : [];
    }

    /**
     * List file paths under website media folder.
     * @return array<int, string>
     */
    private function mediaManifestForWebsite(string $tenantId, string $websiteId): array
    {
        $base = TenancyStoragePaths::websiteMediaBase($tenantId, $websiteId);
        $disk = Storage::disk('public');

        return $disk->exists($base) ? $disk->allFiles($base) : [];
    }

    private function writeZip(string $baseName, array $data): string
    {
        $dir = storage_path('app/audit/tenancy/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $baseName . '.zip';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create zip file.');
        }
        foreach ($data as $key => $payload) {
            $zip->addFromString($key . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $zip->close();

        return $path;
    }
}
