<?php

/**
 * Idempotent backfill of tenant_id (and website_id on page_sections) for existing rows.
 * Run: php scripts/tenancy/backfill-tenant-ids.php
 * Or: php artisan tenancy:backfill
 *
 * Outputs: storage/app/audit/tenancy/backfill-report.json
 */

$base = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';
require_once $base . '/vendor/autoload.php';

$app = require_once $base . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$report = [
    'started_at' => now()->toIso8601String(),
    'websites_processed' => 0,
    'websites_created_tenant' => 0,
    'tables' => [],
    'errors' => [],
    'finished_at' => null,
];

function ensureAuditDir(): void
{
    $dir = storage_path('app/audit/tenancy');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function writeReport(array $report): void
{
    ensureAuditDir();
    $path = storage_path('app/audit/tenancy/backfill-report.json');
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

try {
    if (! Schema::hasTable('websites') || ! Schema::hasTable('tenants')) {
        $report['errors'][] = 'websites or tenants table missing';
        writeReport($report);
        exit(1);
    }
    if (! Schema::hasColumn('websites', 'tenant_id')) {
        $report['errors'][] = 'websites.tenant_id column missing. Run migrations first.';
        writeReport($report);
        exit(1);
    }

    // 1) Websites: assign tenant (create tenant per user if needed)
    $websitesNull = DB::table('websites')->whereNull('tenant_id')->get();
    foreach ($websitesNull as $website) {
        $userId = $website->user_id;
        if ($userId === null) {
            $report['errors'][] = "Website {$website->id} has no user_id.";
            continue;
        }
        $tenant = DB::table('tenants')->where('owner_user_id', $userId)->orWhere('created_by_user_id', $userId)->first();
        if ($tenant === null) {
            $tenantId = (string) \Illuminate\Support\Str::uuid();
            $name = DB::table('users')->where('id', $userId)->value('name') ?: "Tenant {$userId}";
            $slug = \Illuminate\Support\Str::slug($name).'-'.substr($tenantId, 0, 8);
            if (DB::table('tenants')->where('slug', $slug)->exists()) {
                $slug .= '-'.time();
            }
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'status' => 'active',
                'owner_user_id' => $userId,
                'created_by_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $tenant = (object) ['id' => $tenantId];
            $report['websites_created_tenant']++;
        }
        $tenantId = $tenant->id;
        DB::table('websites')->where('id', $website->id)->update(['tenant_id' => $tenantId, 'updated_at' => now()]);
        $report['websites_processed']++;
    }

    // 2) website_pages: set tenant_id from websites
    if (Schema::hasTable('website_pages') && Schema::hasColumn('website_pages', 'tenant_id')) {
        $updated = DB::table('website_pages')
            ->join('websites', 'websites.id', '=', 'website_pages.website_id')
            ->whereNull('website_pages.tenant_id')
            ->whereNotNull('websites.tenant_id')
            ->update(['website_pages.tenant_id' => DB::raw('websites.tenant_id'), 'website_pages.updated_at' => now()]);
        $stillNull = (int) DB::table('website_pages')->whereNull('tenant_id')->count();
        $report['tables']['website_pages'] = ['updated' => $updated, 'still_null' => $stillNull];
    }

    // 3) page_sections: set tenant_id and website_id from website_pages -> websites
    if (Schema::hasTable('page_sections') && Schema::hasColumn('page_sections', 'tenant_id')) {
        $updated = 0;
        $rows = DB::table('page_sections')
            ->join('website_pages', 'website_pages.id', '=', 'page_sections.page_id')
            ->join('websites', 'websites.id', '=', 'website_pages.website_id')
            ->whereNull('page_sections.tenant_id')
            ->whereNotNull('websites.tenant_id')
            ->select('page_sections.id', 'websites.tenant_id', 'website_pages.website_id')
            ->get();
        foreach ($rows as $r) {
            DB::table('page_sections')->where('id', $r->id)->update([
                'tenant_id' => $r->tenant_id,
                'website_id' => $r->website_id,
                'updated_at' => now(),
            ]);
            $updated++;
        }
        $stillNull = (int) DB::table('page_sections')->whereNull('tenant_id')->count();
        $report['tables']['page_sections'] = ['updated' => $updated, 'still_null' => $stillNull];
    }

    // 4) website_seo
    if (Schema::hasTable('website_seo') && Schema::hasColumn('website_seo', 'tenant_id')) {
        $updated = DB::table('website_seo')
            ->join('websites', 'websites.id', '=', 'website_seo.website_id')
            ->whereNull('website_seo.tenant_id')
            ->whereNotNull('websites.tenant_id')
            ->update(['website_seo.tenant_id' => DB::raw('websites.tenant_id'), 'website_seo.updated_at' => now()]);
        $stillNull = (int) DB::table('website_seo')->whereNull('tenant_id')->count();
        $report['tables']['website_seo'] = ['updated' => $updated, 'still_null' => $stillNull];
    }

    // 5) website_revisions
    if (Schema::hasTable('website_revisions') && Schema::hasColumn('website_revisions', 'tenant_id')) {
        $updated = DB::table('website_revisions')
            ->join('websites', 'websites.id', '=', 'website_revisions.website_id')
            ->whereNull('website_revisions.tenant_id')
            ->whereNotNull('websites.tenant_id')
            ->update(['website_revisions.tenant_id' => DB::raw('websites.tenant_id'), 'website_revisions.updated_at' => now()]);
        $stillNull = (int) DB::table('website_revisions')->whereNull('tenant_id')->count();
        $report['tables']['website_revisions'] = ['updated' => $updated, 'still_null' => $stillNull];
    }

    $report['finished_at'] = now()->toIso8601String();
    ensureAuditDir();
    writeReport($report);

    echo "Backfill complete. Websites processed: {$report['websites_processed']}\n";
    foreach ($report['tables'] as $t => $v) {
        echo "  {$t}: updated=".($v['updated'] ?? 0).", still_null=".($v['still_null'] ?? 0)."\n";
    }
    if (! empty($report['errors'])) {
        echo "Errors: ".implode('; ', $report['errors'])."\n";
        exit(1);
    }
    exit(0);
} catch (\Throwable $e) {
    $report['errors'][] = $e->getMessage();
    $report['finished_at'] = now()->toIso8601String();
    writeReport($report);
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}
