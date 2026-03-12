<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommercePanelOrderServiceContract;
use App\Http\Controllers\Controller;
use App\Models\EcommerceOrder;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelOrderController extends Controller
{
    public function __construct(
        protected EcommercePanelOrderServiceContract $orders
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->orders->list($site));
    }

    public function show(Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $order);

        return response()->json($this->orders->show($site, $order));
    }

    public function update(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in([
                'pending',
                'paid',
                'processing',
                'shipped',
                'completed',
                'cancelled',
                'failed',
                'refunded',
            ])],
            'payment_status' => ['sometimes', 'string', Rule::in([
                'unpaid',
                'paid',
                'failed',
                'refunded',
                'partially_refunded',
            ])],
            'fulfillment_status' => ['sometimes', 'string', Rule::in([
                'unfulfilled',
                'partial',
                'fulfilled',
                'returned',
                'cancelled',
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $updated = $this->orders->update($site, $order, $validated);

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => $updated,
        ]);
    }

    public function destroy(Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('delete', $order);

        $this->orders->delete($site, $order);

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }
}
