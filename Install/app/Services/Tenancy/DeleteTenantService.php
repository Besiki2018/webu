<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Support\TenancyStoragePaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-only tenant deletion. Deletes all websites for the tenant, then the tenant row and storage.
 */
class DeleteTenantService
{
    public function __construct(
        protected DeleteWebsiteService $deleteWebsite
    ) {}

    /**
     * @return array{ok: bool, dry_run?: bool, websites_deleted?: int, error?: string, report_path?: string}
     */
    public function delete(string $tenantId, bool $dryRun = false): array
    {
        $tenant = Tenant::query()->where('id', $tenantId)->first();
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Tenant not found.'];
        }

        $websiteIds = DB::table('websites')->where('tenant_id', $tenantId)->pluck('id')->all();

        if ($dryRun) {
            $counts = [];
            foreach ($websiteIds as $wid) {
                $counts[$wid] = $this->deleteWebsite->countRowsForWebsite($tenantId, $wid);
            }

            return [
                'ok' => true,
                'dry_run' => true,
                'websites_count' => count($websiteIds),
                'counts_per_website' => $counts,
            ];
        }

        $deleted = 0;
        foreach ($websiteIds as $websiteId) {
            $result = $this->deleteWebsite->delete($tenantId, $websiteId, 'hard', false);
            if ($result['ok'] ?? false) {
                $deleted++;
            }
        }

        DB::table('tenants')->where('id', $tenantId)->delete();

        $storageBase = TenancyStoragePaths::tenantBase($tenantId);
        $disk = Storage::disk('public');
        if ($disk->exists($storageBase)) {
            $disk->deleteDirectory($storageBase);
        }

        $reportPath = $this->writeReport($tenantId, $deleted, count($websiteIds));

        return ['ok' => true, 'websites_deleted' => $deleted, 'report_path' => $reportPath];
    }

    private function writeReport(string $tenantId, int $websitesDeleted, int $websitesTotal): string
    {
        $dir = storage_path('app/audit/tenancy/deletes');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/tenant-' . $tenantId . '-' . now()->format('Y-m-d-His') . '.json';
        $payload = [
            'tenant_id' => $tenantId,
            'at' => now()->toIso8601String(),
            'websites_deleted' => $websitesDeleted,
            'websites_total' => $websitesTotal,
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));

        return $path;
    }
}
