<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommercePanelInventoryServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceInventoryLocation;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelInventoryController extends Controller
{
    public function __construct(
        protected EcommercePanelInventoryServiceContract $inventory
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->inventory->dashboard($site));
    }

    public function storeLocation(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->inventory->createLocation($site, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Inventory location created successfully.',
            ...$payload,
        ], 201);
    }

    public function updateLocation(Request $request, Site $site, EcommerceInventoryLocation $location): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->inventory->updateLocation($site, $location, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Inventory location updated successfully.',
            ...$payload,
        ]);
    }

    public function updateItemSettings(Request $request, Site $site, EcommerceInventoryItem $inventoryItem): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $payload = $this->inventory->updateItemSettings($site, $inventoryItem, $validated, $request->user());
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Inventory item settings updated successfully.',
            ...$payload,
        ]);
    }

    public function adjustItem(Request $request, Site $site, EcommerceInventoryItem $inventoryItem): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $payload = $this->inventory->adjustItem(
                $site,
                $inventoryItem,
                (int) $validated['quantity_delta'],
                $validated['reason'] ?? null,
                $request->user()
            );
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Inventory adjustment applied successfully.',
            ...$payload,
        ]);
    }

    public function stocktakeItem(Request $request, Site $site, EcommerceInventoryItem $inventoryItem): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'counted_quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $payload = $this->inventory->stocktakeItem(
                $site,
                $inventoryItem,
                (int) $validated['counted_quantity'],
                $request->user()
            );
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Inventory stocktake applied successfully.',
            ...$payload,
        ]);
    }
}
