<?php

namespace App\Services\Tenancy;

use App\Support\TenancyStoragePaths;
use Illuminate\Support\Facades\Storage;

/**
 * Estimate storage usage per tenant (files under storage/tenants/{id}).
 */
class TenantStorageUsageService
{
    /**
     * @return array{bytes: int, files: int}
     */
    public function estimateForTenant(string $tenantId): array
    {
        $base = TenancyStoragePaths::tenantBase($tenantId);
        $disk = Storage::disk('public');

        if (! $disk->exists($base)) {
            return ['bytes' => 0, 'files' => 0];
        }

        $files = $disk->allFiles($base);
        $bytes = 0;
        foreach ($files as $path) {
            $bytes += (int) $disk->size($path);
        }

        return ['bytes' => $bytes, 'files' => count($files)];
    }

    /**
     * Human-readable size (e.g. "12.5 MB").
     */
    public function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 1) . ' GB';
    }
}
