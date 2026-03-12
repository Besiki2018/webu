<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\DeleteTenantService;
use App\Services\Tenancy\DeleteWebsiteService;
use App\Services\Tenancy\ExportTenantService;
use App\Services\Tenancy\TenantStorageUsageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin: Tenants dashboard, detail, export, delete (with dry run).
 */
class AdminTenantController extends Controller
{
    public function __construct(
        protected DeleteWebsiteService $deleteWebsite,
        protected DeleteTenantService $deleteTenant,
        protected ExportTenantService $exportTenant,
        protected TenantStorageUsageService $storageUsage
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->withCount('websites')
            ->with('owner:id,name,email')
            ->orderBy('name')
            ->paginate(20);

        $storageEstimates = [];
        foreach ($tenants->items() as $tenant) {
            $est = $this->storageUsage->estimateForTenant($tenant->id);
            $storageEstimates[$tenant->id] = [
                'bytes' => $est['bytes'],
                'files' => $est['files'],
                'human' => $this->storageUsage->humanSize($est['bytes']),
            ];
        }

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'storageEstimates' => $storageEstimates,
            'title' => __('Tenants'),
        ]);
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        $tenant->loadCount('websites')->load('owner:id,name,email');
        $query = $tenant->websites()->with('site:id,primary_domain')->orderBy('name');
        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('domain', 'like', $term)
                    ->orWhere('id', 'like', $term);
            });
        }
        $websites = $query->get(['id', 'name', 'domain', 'site_id', 'created_at'])->map(function ($w) {
            $arr = $w->toArray();
            $arr['view_site_url'] = null;
            if ($w->domain) {
                $arr['view_site_url'] = (str_starts_with($w->domain, 'http') ? '' : 'https://') . $w->domain;
            } elseif ($w->site) {
                $arr['view_site_url'] = $w->site->primary_domain
                ? (str_starts_with($w->site->primary_domain, 'http') ? $w->site->primary_domain : 'https://' . $w->site->primary_domain)
                : url('/public/sites/' . $w->site->id . '/pages/home');
            }

            return $arr;
        });

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'websites' => $websites,
            'search' => $search ?? '',
            'title' => __('Tenant') . ' — ' . $tenant->name,
        ]);
    }

    /**
     * Suspend or unsuspend tenant (admin only).
     */
    public function updateStatus(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'status' => 'required|string|in:active,suspended',
        ]);
        $tenant = Tenant::query()->findOrFail($request->input('tenant_id'));
        $tenant->update(['status' => $request->input('status')]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $tenant->status]);
        }

        return back()->with('success', $tenant->status === 'suspended' ? __('Tenant suspended.') : __('Tenant activated.'));
    }

    /**
     * Dry run delete website: return counts only.
     */
    public function deleteWebsiteDryRun(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $websiteId = $request->input('website_id');
        if (! $tenantId || ! $websiteId) {
            return response()->json(['error' => 'tenant_id and website_id required'], 422);
        }
        $result = $this->deleteWebsite->delete($tenantId, $websiteId, 'hard', true);

        return response()->json($result);
    }

    /**
     * Hard delete website (admin only).
     */
    public function deleteWebsite(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $websiteId = $request->input('website_id');
        if (! $tenantId || ! $websiteId) {
            return response()->json(['error' => 'tenant_id and website_id required'], 422);
        }
        $result = $this->deleteWebsite->delete($tenantId, $websiteId, 'hard', false);

        return response()->json($result);
    }

    /**
     * Dry run delete tenant.
     */
    public function deleteTenantDryRun(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'tenant_id required'], 422);
        }
        $result = $this->deleteTenant->delete($tenantId, true);

        return response()->json($result);
    }

    /**
     * Delete tenant (admin only).
     */
    public function deleteTenant(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'tenant_id required'], 422);
        }
        $result = $this->deleteTenant->delete($tenantId, false);

        return response()->json($result);
    }

    /**
     * Export tenant bundle (zip).
     */
    public function exportTenant(Tenant $tenant): BinaryFileResponse|array
    {
        try {
            $path = $this->exportTenant->exportTenant($tenant->id);

            return response()->download($path, basename($path), ['Content-Type' => 'application/zip']);
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Export website bundle (zip).
     */
    public function exportWebsite(Request $request): BinaryFileResponse|array
    {
        $tenantId = $request->input('tenant_id');
        $websiteId = $request->input('website_id');
        if (! $tenantId || ! $websiteId) {
            return response()->json(['error' => 'tenant_id and website_id required'], 422);
        }
        try {
            $path = $this->exportTenant->exportWebsite($tenantId, $websiteId);

            return response()->download($path, basename($path), ['Content-Type' => 'application/zip']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}
