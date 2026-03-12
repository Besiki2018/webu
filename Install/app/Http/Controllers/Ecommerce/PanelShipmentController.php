<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceShipmentServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceOrder;
use App\Models\EcommerceShipment;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelShipmentController extends Controller
{
    public function __construct(
        protected EcommerceShipmentServiceContract $shipments
    ) {}

    public function index(Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $order);

        try {
            $payload = $this->shipments->listForOrder($site, $order);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    public function store(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'provider_slug' => ['required', 'string', 'max:120'],
            'shipment_reference' => ['nullable', 'string', 'max:191'],
            'tracking_number' => ['nullable', 'string', 'max:191'],
            'tracking_url' => ['nullable', 'string', 'max:2000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->shipments->createForOrder($site, $order, $validated, $request->user());
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Shipment created successfully.',
            ...$payload,
        ], 201);
    }

    public function refreshTracking(
        Request $request,
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment
    ): JsonResponse {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'status_override' => ['nullable', 'string', Rule::in(EcommerceShipment::allowedStatuses())],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->shipments->refreshTrackingForOrder($site, $order, $shipment, $validated, $request->user());
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Shipment tracking refreshed successfully.',
            ...$payload,
        ]);
    }

    public function cancel(
        Request $request,
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment
    ): JsonResponse {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->shipments->cancelForOrder($site, $order, $shipment, $validated, $request->user());
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Shipment cancelled successfully.',
            ...$payload,
        ]);
    }
}

