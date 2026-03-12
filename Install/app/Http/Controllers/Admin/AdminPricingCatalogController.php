<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ChecksDemoMode;
use App\Models\ModuleAddon;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\PriceRule;
use App\Services\PricingCatalogService;
use App\Services\PricingQuoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPricingCatalogController extends Controller
{
    use ChecksDemoMode;

    public function __construct(
        protected PricingCatalogService $catalog,
        protected PricingQuoteService $pricingQuote
    ) {}

    public function show(Plan $plan): JsonResponse
    {
        return response()->json($this->catalog->catalogForPlan($plan));
    }

    public function storeVersion(Request $request, Plan $plan): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'source_version_id' => ['nullable', 'integer', 'exists:plan_versions,id'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'billing_period' => ['nullable', Rule::in(['monthly', 'yearly', 'lifetime'])],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $version = $this->catalog->createDraftVersion($plan, $validated, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Source version was not found for this plan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Pricing version created successfully.',
            'version' => $version,
        ], 201);
    }

    public function activateVersion(Request $request, Plan $plan, PlanVersion $version): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $version = $this->catalog->activateVersion($plan, $version, $request->user(), $validated['reason'] ?? null);
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Pricing version was not found for this plan.',
            ], 404);
        }

        $plan->refresh();

        return response()->json([
            'message' => 'Pricing version activated successfully.',
            'plan' => [
                'id' => $plan->id,
                'price' => (float) $plan->price,
                'billing_period' => $plan->billing_period,
            ],
            'version' => $version,
        ]);
    }

    public function upsertAddon(Request $request, Plan $plan, PlanVersion $version): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:140'],
            'addon_group' => ['nullable', 'string', 'max:64'],
            'pricing_mode' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $addon = $this->catalog->upsertAddon($plan, $version, $validated, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Pricing version was not found for this plan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Module add-on saved successfully.',
            'addon' => $addon,
        ]);
    }

    public function destroyAddon(Plan $plan, PlanVersion $version, ModuleAddon $addon, Request $request): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        try {
            $this->catalog->deleteAddon($plan, $version, $addon, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Module add-on was not found for this plan version.',
            ], 404);
        }

        return response()->json([
            'message' => 'Module add-on deleted successfully.',
        ]);
    }

    public function upsertRule(Request $request, Plan $plan, PlanVersion $version): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:140'],
            'rule_type' => ['nullable', 'string', 'max:64'],
            'adjustment_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'amount' => ['nullable', 'numeric'],
            'conditions_json' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $rule = $this->catalog->upsertPriceRule($plan, $version, $validated, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Pricing version was not found for this plan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Price rule saved successfully.',
            'rule' => $rule,
        ]);
    }

    public function destroyRule(Plan $plan, PlanVersion $version, PriceRule $rule, Request $request): JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => 'Action is not available in demo mode.',
            ], 403);
        }

        try {
            $this->catalog->deletePriceRule($plan, $version, $rule, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Price rule was not found for this plan version.',
            ], 404);
        }

        return response()->json([
            'message' => 'Price rule deleted successfully.',
        ]);
    }

    public function preview(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'version_id' => ['nullable', 'integer', 'exists:plan_versions,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'usage' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $quote = $this->pricingQuote->compose($plan, $validated);
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Pricing version was not found for this plan.',
            ], 404);
        }

        return response()->json([
            'quote' => $quote,
        ]);
    }
}
