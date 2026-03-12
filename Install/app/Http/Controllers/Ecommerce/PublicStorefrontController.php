<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommercePublicStorefrontServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceOrder;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStorefrontController extends Controller
{
    public function __construct(
        protected EcommercePublicStorefrontServiceContract $storefront
    ) {}

    /**
     * @param  string  $site  Site UUID from route (resolved manually so missing site returns 200 + empty list instead of 404 for builder preview)
     */
    public function products(Request $request, string $site): JsonResponse
    {
        $siteModel = Site::find($site);
        if (! $siteModel instanceof Site) {
            $perPage = max(1, (int) ($request->query('per_page') ?? $request->query('limit') ?? 24));
            $page = max(1, (int) $request->query('page', 1));

            return $this->corsJson([
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'page' => $page,
            ]);
        }

        try {
            $search = $request->query('search');
            if (($search === null || $search === '') && $request->query->has('q')) {
                $search = $request->query('q');
            }

            $categorySlug = $request->query('category_slug');
            if (($categorySlug === null || $categorySlug === '') && $request->query->has('category')) {
                $categorySlug = $request->query('category');
            }

            $limit = $request->query('limit');
            if ($limit === null && $request->query->has('per_page')) {
                $limit = $request->query('per_page');
            }

            $offset = $request->query('offset');
            if ($offset === null && $request->query->has('page')) {
                $resolvedLimit = max(1, (int) ($limit ?? 24));
                $resolvedPage = max(1, (int) $request->query('page', 1));
                $offset = ($resolvedPage - 1) * $resolvedLimit;
            }

            $payload = $this->storefront->listProducts($siteModel, [
                'search' => $search,
                'category_slug' => $categorySlug,
                'limit' => $limit,
                'offset' => $offset,
            ], $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function product(Request $request, Site $site, string $slug): JsonResponse
    {
        try {
            $payload = $this->storefront->product($site, $slug, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function paymentOptions(Request $request, Site $site): JsonResponse
    {
        try {
            $payload = $this->storefront->paymentOptions($site, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function createCart(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->storefront->createCart($site, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload, 201);
    }

    public function cart(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $payload = $this->storefront->cart($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function addCartItem(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_slug'],
            'product_slug' => ['nullable', 'string', 'max:191', 'required_without:product_id'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'required_without:qty'],
            'qty' => ['nullable', 'integer', 'min:1', 'required_without:quantity'],
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'options_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        if (! array_key_exists('quantity', $validated) && array_key_exists('qty', $validated)) {
            $validated['quantity'] = (int) $validated['qty'];
        }

        try {
            $payload = $this->storefront->addCartItem($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function updateCartItem(
        Request $request,
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item
    ): JsonResponse {
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'options_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->storefront->updateCartItem($site, $cart, $item, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function removeCartItem(
        Request $request,
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item
    ): JsonResponse {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $payload = $this->storefront->removeCartItem($site, $cart, $item, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function applyCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->storefront->applyCoupon($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function removeCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $payload = $this->storefront->removeCoupon($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function shippingOptions(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'shipping_address_json' => ['nullable', 'array'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $payload = $this->storefront->shippingOptions($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function updateShipping(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'shipping_provider' => ['required', 'string', 'max:100'],
            'shipping_rate_id' => ['required', 'string', 'max:191'],
            'shipping_address_json' => ['nullable', 'array'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $payload = $this->storefront->updateShipping($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function checkout(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'shipping_address_json' => ['nullable', 'array'],
            'shipping_provider' => ['nullable', 'string', 'max:100'],
            'shipping_rate_id' => ['nullable', 'string', 'max:191'],
            'billing_address_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->storefront->checkout($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload, 201);
    }

    public function checkoutValidate(Request $request, Site $site, EcommerceCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'cart_identity_token' => ['nullable', 'string', 'max:191'],
            'shipping_address_json' => ['nullable', 'array'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $payload = $this->storefront->checkoutValidate($site, $cart, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function customerOrders(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $payload = $this->storefront->customerOrders($site, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function customerOrder(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        try {
            $payload = $this->storefront->customerOrder($site, $order, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function startPayment(Request $request, Site $site, EcommerceOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:100'],
            'is_installment' => ['nullable', 'boolean'],
            'installment_plan_json' => ['nullable', 'array'],
            'raw_payload_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->storefront->startPayment($site, $order, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function trackShipment(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:120'],
            'shipment_reference' => ['nullable', 'string', 'max:191'],
            'tracking_number' => ['nullable', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        if (
            ! array_key_exists('shipment_reference', $validated)
            && ! array_key_exists('tracking_number', $validated)
        ) {
            return $this->corsJson([
                'error' => 'shipment_reference or tracking_number is required.',
            ], 422);
        }

        try {
            $payload = $this->storefront->trackShipment($site, $validated, $request->user(), $this->allowDraftPreview($request));
        } catch (EcommerceDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    private function corsJson(array $payload, int $status = 200): JsonResponse
    {
        $response = response()
            ->json($payload, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');

        return $response->header('Cache-Control', $this->cacheControlForCurrentRequest());
    }

    private function allowDraftPreview(Request $request): bool
    {
        return $request->boolean('draft');
    }

    private function cacheControlForCurrentRequest(): string
    {
        $request = request();
        $routeName = (string) ($request->route()?->getName() ?? '');
        $method = strtoupper((string) $request->method());

        // Catalog and payment options are read-mostly storefront endpoints and can
        // use a short public TTL. Stateful and customer-specific endpoints stay no-store.
        if (
            $method === 'GET'
            && in_array($routeName, [
                'public.sites.ecommerce.payment.options',
                'public.sites.ecommerce.products.index',
                'public.sites.ecommerce.products.show',
            ], true)
        ) {
            return 'public, max-age=60';
        }

        return 'no-store';
    }
}
