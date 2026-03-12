<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceOrder;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelAccountingController extends Controller
{
    public function __construct(
        protected EcommerceAccountingServiceContract $accounting
    ) {}

    public function entries(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1'],
            'event_type' => ['nullable', 'string', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json($this->accounting->listEntries($site, $validated));
    }

    public function reconciliation(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->accounting->reconciliation($site, $validated));
    }

    public function recordReturn(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $entry = $this->accounting->recordReturnAdjustment(
                site: $site,
                order: $order,
                amount: (float) $validated['amount'],
                eventKey: null,
                meta: [
                    'reason' => $validated['reason'] ?? null,
                    'reference' => $validated['reference'] ?? null,
                    'source' => 'panel_manual_return',
                ],
                actor: $request->user()
            );
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Return accounting entry recorded successfully.',
            'site_id' => $site->id,
            'order_id' => $order->id,
            'entry_id' => $entry->id,
        ], 201);
    }
}
