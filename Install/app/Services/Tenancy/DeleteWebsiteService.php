<?php

namespace App\Services\Tenancy;

use App\Models\Website;
use App\Support\TenancyStoragePaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Safe, transactional website deletion. Removes only that website's data and files.
 * Dry run returns counts without deleting.
 */
class DeleteWebsiteService
{
    /**
     * @param  'soft'|'hard'  $mode
     * @return array{ok: bool, dry_run: bool, counts?: array<string, int>, error?: string, report_path?: string}
     */
    public function delete(string $tenantId, string $websiteId, string $mode = 'hard', bool $dryRun = false): array
    {
        $website = Website::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $websiteId)
            ->first();

        if ($website === null) {
            return ['ok' => false, 'dry_run' => $dryRun, 'error' => 'Website not found or does not belong to tenant.'];
        }

        $counts = $this->countRowsForWebsite($tenantId, $websiteId);

        if ($dryRun) {
            return [
                'ok' => true,
                'dry_run' => true,
                'counts' => $counts,
            ];
        }

        if ($mode === 'soft') {
            if (Schema::hasColumn('websites', 'status')) {
                $website->update(['status' => 'deleted']);
            }
            $this->writeReport($tenantId, $websiteId, ['mode' => 'soft', 'counts' => $counts]);

            return ['ok' => true, 'dry_run' => false, 'report_path' => $this->reportPath($websiteId)];
        }

        try {
            DB::beginTransaction();

            DB::table('page_sections')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->delete();
            DB::table('website_seo')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->delete();
            DB::table('website_revisions')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->delete();
            DB::table('website_pages')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->delete();
            if (Schema::hasColumn('media', 'website_id')) {
                DB::table('media')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->delete();
            }
            DB::table('websites')->where('tenant_id', $tenantId)->where('id', $websiteId)->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'dry_run' => false, 'error' => $e->getMessage()];
        }

        $storageBase = TenancyStoragePaths::websiteBase($tenantId, $websiteId);
        $disk = Storage::disk('public');
        if ($disk->exists($storageBase)) {
            $disk->deleteDirectory($storageBase);
        }

        $this->writeReport($tenantId, $websiteId, ['mode' => 'hard', 'counts' => $counts]);

        return ['ok' => true, 'dry_run' => false, 'counts' => $counts, 'report_path' => $this->reportPath($websiteId)];
    }

    /**
     * @return array<string, int>
     */
    public function countRowsForWebsite(string $tenantId, string $websiteId): array
    {
        $counts = [
            'page_sections' => (int) DB::table('page_sections')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->count(),
            'website_seo' => (int) DB::table('website_seo')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->count(),
            'website_revisions' => (int) DB::table('website_revisions')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->count(),
            'website_pages' => (int) DB::table('website_pages')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->count(),
            'websites' => 1,
        ];
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'tenant_id') && Schema::hasColumn('media', 'website_id')) {
            $counts['media'] = (int) DB::table('media')->where('tenant_id', $tenantId)->where('website_id', $websiteId)->count();
        }

        return $counts;
    }

    private function reportPath(string $websiteId): string
    {
        $dir = storage_path('app/audit/tenancy/deletes');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/website-' . $websiteId . '-' . now()->format('Y-m-d-His') . '.json';
    }

    private function writeReport(string $tenantId, string $websiteId, array $data): void
    {
        $path = $this->reportPath($websiteId);
        $payload = array_merge([
            'tenant_id' => $tenantId,
            'website_id' => $websiteId,
            'at' => now()->toIso8601String(),
        ], $data);
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
    }
}
