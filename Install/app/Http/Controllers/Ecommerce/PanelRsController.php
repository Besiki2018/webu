<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceRsReadinessServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceOrder;
use App\Models\EcommerceRsExport;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelRsController extends Controller
{
    public function __construct(
        protected EcommerceRsReadinessServiceContract $rsReadiness
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in([EcommerceRsExport::STATUS_VALID, EcommerceRsExport::STATUS_INVALID])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->rsReadiness->listExports($site, $validated));
    }

    public function summary(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->rsReadiness->readinessSummary($site, $validated));
    }

    public function show(Site $site, EcommerceRsExport $export): JsonResponse
    {
        Gate::authorize('view', $site->project);

        try {
            return response()->json($this->rsReadiness->showExport($site, $export));
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }
    }

    public function generate(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $result = $this->rsReadiness->generateOrderExport($site, $order, $request->user());
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($result, 201);
    }
}
