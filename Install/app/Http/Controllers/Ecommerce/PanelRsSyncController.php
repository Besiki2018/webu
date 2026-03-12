<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelRsSyncController extends Controller
{
    public function __construct(
        protected EcommerceRsSyncServiceContract $syncs
    ) {}

    public function queue(Request $request, Site $site, EcommerceRsExport $export): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $result = $this->syncs->queueExportSync(
                site: $site,
                export: $export,
                actor: $request->user(),
                meta: [
                    'source' => 'panel_manual_queue',
                ]
            );
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($result, $result['queued'] ? 202 : 200);
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                EcommerceRsSync::STATUS_QUEUED,
                EcommerceRsSync::STATUS_PROCESSING,
                EcommerceRsSync::STATUS_SUCCEEDED,
                EcommerceRsSync::STATUS_FAILED,
            ])],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'export_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->syncs->listSyncs($site, $validated));
    }

    public function show(Site $site, EcommerceRsSync $sync): JsonResponse
    {
        Gate::authorize('view', $site->project);

        try {
            return response()->json($this->syncs->showSync($site, $sync));
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }
    }

    public function retry(Request $request, Site $site, EcommerceRsSync $sync): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $result = $this->syncs->retrySync(
                site: $site,
                sync: $sync,
                actor: $request->user(),
                meta: [
                    'source' => 'panel_manual_retry',
                ]
            );
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($result, $result['queued'] ? 202 : 200);
    }
}
