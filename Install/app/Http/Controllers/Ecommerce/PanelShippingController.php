<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceCourierConfigServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelShippingController extends Controller
{
    public function __construct(
        protected EcommerceCourierConfigServiceContract $couriers
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $shippingPolicy = $this->resolveShippingPlanPolicy($site);
        if ($blocked = $this->shippingPolicyBlockedResponse($site, $shippingPolicy)) {
            return $blocked;
        }

        $payload = $this->couriers->listForSite($site);
        $allowed = $shippingPolicy['allowed_courier_providers'];

        if (is_array($allowed)) {
            $payload['couriers'] = collect($payload['couriers'] ?? [])
                ->filter(fn ($courier): bool => is_array($courier) && in_array((string) ($courier['slug'] ?? ''), $allowed, true))
                ->values()
                ->all();
        }

        return response()->json($payload);
    }

    public function update(Request $request, Site $site, string $courier): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $shippingPolicy = $this->resolveShippingPlanPolicy($site);
        if ($blocked = $this->shippingPolicyBlockedResponse($site, $shippingPolicy, $courier)) {
            return $blocked;
        }

        $validated = $request->validate([
            'availability' => ['nullable', 'string', Rule::in(['inherit', 'enabled', 'disabled'])],
            'config' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->couriers->updateForSite($site, $courier, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Shipping courier settings updated successfully.',
            ...$payload,
        ]);
    }

    /**
     * @return array{
     *   plan_slug: string|null,
     *   shipping_enabled: bool,
     *   allowed_courier_providers: array<int, string>|null
     * }
     */
    private function resolveShippingPlanPolicy(Site $site): array
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return [
                'plan_slug' => null,
                'shipping_enabled' => true,
                'allowed_courier_providers' => null,
            ];
        }

        return [
            'plan_slug' => (string) ($plan->slug ?? null),
            'shipping_enabled' => $plan->shippingEnabled(),
            'allowed_courier_providers' => $plan->getAllowedCourierProviders(),
        ];
    }

    /**
     * @param  array{
     *   plan_slug: string|null,
     *   shipping_enabled: bool,
     *   allowed_courier_providers: array<int, string>|null
     * }  $shippingPolicy
     */
    private function shippingPolicyBlockedResponse(Site $site, array $shippingPolicy, ?string $courierSlug = null): ?JsonResponse
    {
        $normalizedCourierSlug = $courierSlug !== null ? strtolower(trim($courierSlug)) : null;

        if ($shippingPolicy['shipping_enabled']) {
            if (
                $normalizedCourierSlug !== null
                && is_array($shippingPolicy['allowed_courier_providers'])
                && ! in_array($normalizedCourierSlug, $shippingPolicy['allowed_courier_providers'], true)
            ) {
                return response()->json([
                    'error' => 'Selected courier provider is not allowed for your current plan.',
                    'feature' => 'shipping',
                    'site_id' => $site->id,
                    'code' => 'site_entitlement_required',
                    'reason' => 'courier_provider_not_allowed',
                    'provider' => $normalizedCourierSlug,
                    'allowed_providers' => $shippingPolicy['allowed_courier_providers'],
                    'plan_slug' => $shippingPolicy['plan_slug'],
                ], 403);
            }

            return null;
        }

        return response()->json([
            'error' => 'Your plan does not include shipping provider management.',
            'feature' => 'shipping',
            'site_id' => $site->id,
            'code' => 'site_entitlement_required',
            'reason' => 'shipping_not_enabled',
            'plan_slug' => $shippingPolicy['plan_slug'],
        ], 403);
    }
}
