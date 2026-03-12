<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommerceGatewayConfigServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelGatewayController extends Controller
{
    public function __construct(
        protected EcommerceGatewayConfigServiceContract $gateways
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->gateways->listForSite($site));
    }

    public function update(Request $request, Site $site, string $provider): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'availability' => ['nullable', 'string', Rule::in(['inherit', 'enabled', 'disabled'])],
            'config' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->gateways->updateForSite($site, $provider, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Payment provider settings updated successfully.',
            ...$payload,
        ]);
    }
}
