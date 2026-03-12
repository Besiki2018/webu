<?php

namespace App\Ecommerce\Services;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Contracts\CourierPlugin;
use App\Contracts\EcommercePaymentGatewayPlugin;
use App\Ecommerce\Contracts\EcommerceCourierConfigServiceContract;
use App\Ecommerce\Contracts\EcommerceGatewayConfigServiceContract;
use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Contracts\EcommercePublicStorefrontServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Ecommerce\Contracts\EcommerceShipmentServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Services\PluginManager;
use App\Services\UniversalPaymentsAbstractionService;
use App\Services\UsageMeteringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcommercePublicStorefrontService implements EcommercePublicStorefrontServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository,
        protected CmsModuleRegistryServiceContract $moduleRegistry,
        protected EcommerceGatewayConfigServiceContract $gatewayConfig,
        protected EcommerceCourierConfigServiceContract $courierConfig,
        protected EcommerceInventoryServiceContract $inventory,
        protected EcommerceAccountingServiceContract $accounting,
        protected EcommerceShipmentServiceContract $shipmentService,
        protected PluginManager $pluginManager,
        protected UsageMeteringService $usageMetering,
        protected UniversalPaymentsAbstractionService $universalPayments
    ) {}

    public function listProducts(Site $site, array $filters = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);

        $search = trim((string) ($filters['search'] ?? ''));
        $categorySlug = trim((string) ($filters['category_slug'] ?? ''));
        $limit = max(1, min((int) ($filters['limit'] ?? 24), 100));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $products = $this->repository->listPublishedProducts($site);

        if ($categorySlug !== '') {
            $products = $products->filter(function (EcommerceProduct $product) use ($categorySlug): bool {
                return strtolower((string) $product->category?->slug) === strtolower($categorySlug);
            })->values();
        }

        if ($search !== '') {
            $needle = strtolower($search);
            $products = $products->filter(function (EcommerceProduct $product) use ($needle): bool {
                return str_contains(strtolower($product->name), $needle)
                    || str_contains(strtolower((string) $product->short_description), $needle)
                    || str_contains(strtolower((string) $product->description), $needle)
                    || str_contains(strtolower((string) $product->sku), $needle);
            })->values();
        }

        $total = $products->count();
        $items = $products->slice($offset, $limit)
            ->values()
            ->map(fn (EcommerceProduct $product): array => $this->serializeProduct($site, $product, false))
            ->all();

        return [
            'site_id' => $site->id,
            'products' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($items)) < $total,
            ],
        ];
    }

    public function product(Site $site, string $slug, ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);

        $normalizedSlug = trim(strtolower($slug));
        if ($normalizedSlug === '') {
            throw new EcommerceDomainException('Product slug is required.', 422);
        }

        $product = $this->repository->findPublishedProductBySiteAndSlug($site, $normalizedSlug);
        if (! $product) {
            throw new EcommerceDomainException('Published product not found.', 404);
        }

        return [
            'site_id' => $site->id,
            'product' => $this->serializeProduct($site, $product, true),
        ];
    }

    public function createCart(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);

        $identityToken = $this->normalizeCartIdentityToken($payload['cart_identity_token'] ?? null) ?? Str::random(40);
        $reusedExisting = false;

        $existingCart = $this->findOpenCartByIdentityToken($site, $identityToken);
        if ($existingCart) {
            $existingMeta = is_array($existingCart->meta_json) ? $existingCart->meta_json : [];
            $updatedMeta = $this->mergeCartIdentityMeta($existingMeta, $identityToken, [
                'mode' => 'guest',
                'resumed_at' => now()->toISOString(),
                'last_seen_at' => now()->toISOString(),
            ]);

            $existingCart = $this->repository->updateCart($existingCart, [
                'currency' => $this->normalizeCurrency($payload['currency'] ?? $existingCart->currency),
                'customer_email' => $this->nullableString($payload['customer_email'] ?? $existingCart->customer_email),
                'customer_phone' => $this->nullableString($payload['customer_phone'] ?? $existingCart->customer_phone),
                'customer_name' => $this->nullableString($payload['customer_name'] ?? $existingCart->customer_name),
                'meta_json' => $this->mergeCartMetaPreservingIdentity($updatedMeta, is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : []),
            ]);

            $resolvedCart = $this->recalculateCart($site, $existingCart->id);
            $reusedExisting = true;

            return [
                'site_id' => $site->id,
                'cart' => $this->serializeCart($site, $resolvedCart),
                'meta' => [
                    'cart_identity_token' => $identityToken,
                    'cart_identity_reused' => true,
                ],
            ];
        }

        $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
        $meta = $this->mergeCartIdentityMeta($meta, $identityToken, [
            'mode' => 'guest',
            'issued_at' => now()->toISOString(),
            'last_seen_at' => now()->toISOString(),
        ]);

        $cart = $this->repository->createCart($site, [
            'status' => 'open',
            'currency' => $this->normalizeCurrency($payload['currency'] ?? null),
            'customer_email' => $this->nullableString($payload['customer_email'] ?? null),
            'customer_phone' => $this->nullableString($payload['customer_phone'] ?? null),
            'customer_name' => $this->nullableString($payload['customer_name'] ?? null),
            'subtotal' => $this->moneyString(0),
            'tax_total' => $this->moneyString(0),
            'shipping_total' => $this->moneyString(0),
            'discount_total' => $this->moneyString(0),
            'grand_total' => $this->moneyString(0),
            'meta_json' => $meta,
            'expires_at' => now()->addDays(3),
        ]);

        $resolvedCart = $this->repository->findCartBySiteAndId($site, $cart->id) ?? $cart;

        return [
            'site_id' => $site->id,
            'cart' => $this->serializeCart($site, $resolvedCart),
            'meta' => [
                'cart_identity_token' => $identityToken,
                'cart_identity_reused' => $reusedExisting,
            ],
        ];
    }

    public function cart(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $target = $this->assertCartBelongsToSite($site, $cart);
        $target = $this->assertCartIdentityIfProvided($target, $payload);

        return [
            'site_id' => $site->id,
            'cart' => $this->serializeCart($site, $target),
            'meta' => [
                'cart_identity_token' => $this->resolveCartIdentityToken($target),
            ],
        ];
    }

    public function addCartItem(Site $site, EcommerceCart $cart, array $payload, ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);

        $productId = (int) ($payload['product_id'] ?? 0);
        $productSlug = trim((string) ($payload['product_slug'] ?? ''));
        $variantId = isset($payload['variant_id']) ? (int) $payload['variant_id'] : null;
        $quantity = max(1, (int) ($payload['quantity'] ?? ($payload['qty'] ?? 1)));

        $resolvedProductBySlug = null;
        if ($productId <= 0 && $productSlug !== '') {
            $resolvedProductBySlug = $this->repository->findPublishedProductBySiteAndSlug($site, Str::lower($productSlug));
            if ($resolvedProductBySlug) {
                $productId = (int) $resolvedProductBySlug->id;
            }
        }

        if ($productId <= 0) {
            throw new EcommerceDomainException('product_id or product_slug is required.', 422);
        }

        $product = $resolvedProductBySlug ?? $this->repository->findProductBySiteAndId($site, $productId);
        if (! $product || $product->status !== 'active' || ! $product->published_at) {
            throw new EcommerceDomainException('Selected product is not available.', 422);
        }

        $variant = $this->resolveVariantForProduct($site, $product, $variantId);

        $existingItem = $targetCart->items
            ->first(fn ($item) => (int) $item->product_id === $product->id
                && (int) ($item->variant_id ?? 0) === (int) ($variant?->id ?? 0));

        $nextQuantity = $existingItem ? ((int) $existingItem->quantity + $quantity) : $quantity;
        $recalculatedCart = DB::transaction(function () use (
            $site,
            $targetCart,
            $product,
            $variant,
            $existingItem,
            $nextQuantity,
            $payload,
            $viewer
        ): EcommerceCart {
            $unitPrice = $this->resolveUnitPrice($product, $variant);
            $lineTotal = $this->moneyString($unitPrice * $nextQuantity);

            if ($existingItem) {
                $cartItem = $this->repository->updateCartItem($existingItem, [
                    'quantity' => $nextQuantity,
                    'unit_price' => $this->moneyString($unitPrice),
                    'line_total' => $lineTotal,
                    'options_json' => is_array($payload['options_json'] ?? null) ? $payload['options_json'] : ($existingItem->options_json ?? []),
                    'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : ($existingItem->meta_json ?? []),
                ]);
            } else {
                $cartItem = $this->repository->createCartItem($targetCart, [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'name' => $this->buildCartItemName($product, $variant),
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $nextQuantity,
                    'unit_price' => $this->moneyString($unitPrice),
                    'line_total' => $lineTotal,
                    'options_json' => is_array($payload['options_json'] ?? null) ? $payload['options_json'] : [],
                    'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [],
                ]);
            }

            $this->inventory->reserveForCartItem(
                $site,
                $targetCart,
                $cartItem,
                $product,
                $variant,
                $nextQuantity,
                $viewer
            );

            $targetCart = $this->clearCartShippingSelection($targetCart);

            return $this->recalculateCart($site, $targetCart->id);
        });

        return [
            'site_id' => $site->id,
            'cart' => $this->serializeCart($site, $recalculatedCart),
        ];
    }

    public function updateCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item,
        array $payload,
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetItem = $this->assertCartItemBelongsToCart($targetCart, $item);

        $quantity = max(1, (int) ($payload['quantity'] ?? $targetItem->quantity));

        $product = $targetItem->product_id
            ? $this->repository->findProductBySiteAndId($site, (int) $targetItem->product_id)
            : null;
        if (! $product || $product->status !== 'active' || ! $product->published_at) {
            throw new EcommerceDomainException('Cart item product is no longer available.', 422);
        }

        $variant = $targetItem->variant_id
            ? $this->repository->findProductVariantBySiteAndId($site, (int) $targetItem->variant_id)
            : null;
        if ($targetItem->variant_id && ! $variant) {
            throw new EcommerceDomainException('Cart item variant is no longer available.', 422);
        }

        $recalculatedCart = DB::transaction(function () use (
            $site,
            $targetCart,
            $targetItem,
            $product,
            $variant,
            $quantity,
            $payload,
            $viewer
        ): EcommerceCart {
            $unitPrice = $this->resolveUnitPrice($product, $variant);
            $targetItem = $this->repository->updateCartItem($targetItem, [
                'quantity' => $quantity,
                'unit_price' => $this->moneyString($unitPrice),
                'line_total' => $this->moneyString($unitPrice * $quantity),
                'options_json' => is_array($payload['options_json'] ?? null) ? $payload['options_json'] : ($targetItem->options_json ?? []),
                'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : ($targetItem->meta_json ?? []),
            ]);

            $this->inventory->reserveForCartItem(
                $site,
                $targetCart,
                $targetItem,
                $product,
                $variant,
                $quantity,
                $viewer
            );

            $targetCart = $this->clearCartShippingSelection($targetCart);

            return $this->recalculateCart($site, $targetCart->id);
        });

        return [
            'site_id' => $site->id,
            'cart' => $this->serializeCart($site, $recalculatedCart),
        ];
    }

    public function removeCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item,
        array $payload = [],
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetItem = $this->assertCartItemBelongsToCart($targetCart, $item);

        $recalculatedCart = DB::transaction(function () use ($site, $targetCart, $targetItem, $viewer): EcommerceCart {
            $this->inventory->releaseForCartItem($site, $targetCart, $targetItem, $viewer);
            $this->repository->deleteCartItem($targetItem);
            $targetCart = $this->clearCartShippingSelection($targetCart);

            return $this->recalculateCart($site, $targetCart->id);
        });

        return [
            'site_id' => $site->id,
            'cart' => $this->serializeCart($site, $recalculatedCart),
        ];
    }

    public function applyCoupon(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetCart = $this->recalculateCart($site, $targetCart->id);

        if ($targetCart->items->isEmpty()) {
            throw new EcommerceDomainException('Cart is empty.', 422);
        }

        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($code === '') {
            throw new EcommerceDomainException('Coupon code is required.', 422);
        }

        $couponDefinition = $this->resolveCouponDefinitionForCode($site, $code);
        if ($couponDefinition === null) {
            throw new EcommerceDomainException('Coupon code is invalid.', 422);
        }

        $meta = is_array($targetCart->meta_json) ? $targetCart->meta_json : [];
        $meta['coupon'] = [
            'code' => $code,
            'label' => $this->nullableString($couponDefinition['label'] ?? null) ?? $code,
            'type' => $couponDefinition['type'],
            'value' => $this->moneyString($couponDefinition['value']),
            'min_subtotal' => $this->moneyString($couponDefinition['min_subtotal'] ?? 0),
            'scope' => 'subtotal',
            'source' => (string) ($couponDefinition['source'] ?? 'built_in'),
            'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [],
            'applied_at' => now()->toISOString(),
        ];

        $targetCart = $this->repository->updateCart($targetCart, [
            'meta_json' => $meta,
        ]);

        $recalculatedCart = $this->recalculateCart($site, $targetCart->id);
        $couponPayload = $this->serializeCartCoupon($recalculatedCart);

        return [
            'site_id' => $site->id,
            'cart_id' => $recalculatedCart->id,
            'coupon' => $couponPayload,
            'cart' => $this->serializeCart($site, $recalculatedCart),
        ];
    }

    public function removeCoupon(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);

        $meta = is_array($targetCart->meta_json) ? $targetCart->meta_json : [];
        unset($meta['coupon']);

        $targetCart = $this->repository->updateCart($targetCart, [
            'meta_json' => $meta,
            'discount_total' => $this->moneyString(0),
        ]);

        $recalculatedCart = $this->recalculateCart($site, $targetCart->id);

        return [
            'site_id' => $site->id,
            'cart_id' => $recalculatedCart->id,
            'coupon' => null,
            'cart' => $this->serializeCart($site, $recalculatedCart),
        ];
    }

    public function shippingOptions(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetCart = $this->recalculateCart($site, $targetCart->id);
        $shippingPolicy = $this->resolveShippingPlanPolicy($site);

        $shippingAddress = is_array($payload['shipping_address_json'] ?? null) ? $payload['shipping_address_json'] : [];
        $currency = $this->normalizeCurrency($payload['currency'] ?? $targetCart->currency);
        $shipping = $this->buildShippingOptionsPayload(
            site: $site,
            cart: $targetCart,
            shippingAddress: $shippingAddress,
            currency: $currency,
            shippingPolicy: $shippingPolicy
        );

        return [
            'site_id' => $site->id,
            'cart_id' => $targetCart->id,
            'shipping' => $shipping,
            'cart' => $this->serializeCart($site, $targetCart),
        ];
    }

    public function updateShipping(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetCart = $this->recalculateCart($site, $targetCart->id);

        $shippingProvider = $this->nullableString($payload['shipping_provider'] ?? null);
        $shippingRateId = $this->nullableString($payload['shipping_rate_id'] ?? null);
        $shippingAddress = is_array($payload['shipping_address_json'] ?? null) ? $payload['shipping_address_json'] : [];
        $currency = $this->normalizeCurrency($payload['currency'] ?? $targetCart->currency);
        $shippingPolicy = $this->assertShippingProviderAllowedByPlan($site, $shippingProvider);

        $updatedCart = $this->applyShippingSelection(
            site: $site,
            cart: $targetCart,
            shippingAddress: $shippingAddress,
            shippingProvider: $shippingProvider,
            shippingRateId: $shippingRateId,
            currency: $currency
        );

        $shipping = $this->buildShippingOptionsPayload(
            site: $site,
            cart: $updatedCart,
            shippingAddress: $shippingAddress,
            currency: $currency,
            shippingPolicy: $shippingPolicy
        );

        return [
            'site_id' => $site->id,
            'cart_id' => $updatedCart->id,
            'shipping' => $shipping,
            'cart' => $this->serializeCart($site, $updatedCart),
        ];
    }

    public function checkout(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetCart = $this->recalculateCart($site, $targetCart->id);

        if ($targetCart->items->isEmpty()) {
            throw new EcommerceDomainException('Cart is empty.', 422);
        }

        $this->assertMonthlyOrderLimitNotExceeded($site);

        $customerEmail = $this->nullableString($payload['customer_email'] ?? $targetCart->customer_email);
        $customerPhone = $this->nullableString($payload['customer_phone'] ?? $targetCart->customer_phone);
        $customerName = $this->nullableString($payload['customer_name'] ?? $targetCart->customer_name);

        if ($customerEmail === null && $customerPhone === null) {
            throw new EcommerceDomainException('Customer email or phone is required for checkout.', 422);
        }

        $shippingAddress = is_array($payload['shipping_address_json'] ?? null) ? $payload['shipping_address_json'] : [];
        $billingAddress = is_array($payload['billing_address_json'] ?? null) ? $payload['billing_address_json'] : [];
        $shippingProvider = $this->nullableString($payload['shipping_provider'] ?? null);
        $shippingRateId = $this->nullableString($payload['shipping_rate_id'] ?? null);
        $currency = $this->normalizeCurrency($targetCart->currency);
        $shippingPolicy = $this->assertShippingProviderAllowedByPlan($site, $shippingProvider);

        if (! $shippingPolicy['shipping_enabled']) {
            $targetCart = $this->clearCartShippingSelection($targetCart);
            $targetCart = $this->recalculateCart($site, $targetCart->id);
        }

        $targetCart = $this->applyShippingSelection(
            site: $site,
            cart: $targetCart,
            shippingAddress: $shippingAddress,
            shippingProvider: $shippingProvider,
            shippingRateId: $shippingRateId,
            currency: $currency,
            allowNoSelection: true
        );

        $cartMeta = is_array($targetCart->meta_json) ? $targetCart->meta_json : [];
        $selectedShipping = is_array($cartMeta['shipping_selection'] ?? null) ? $cartMeta['shipping_selection'] : null;

        $order = DB::transaction(function () use (
            $site,
            $targetCart,
            $customerEmail,
            $customerPhone,
            $customerName,
            $shippingAddress,
            $billingAddress,
            $selectedShipping,
            $payload,
            $viewer
        ): EcommerceOrder {
            $order = $this->repository->createOrder($site, [
                'order_number' => $this->generateOrderNumber($site),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unfulfilled',
                'currency' => $targetCart->currency ?: 'GEL',
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'customer_name' => $customerName,
                'billing_address_json' => $billingAddress,
                'shipping_address_json' => $shippingAddress,
                'subtotal' => $this->moneyString($targetCart->subtotal),
                'tax_total' => $this->moneyString($targetCart->tax_total),
                'shipping_total' => $this->moneyString($targetCart->shipping_total),
                'discount_total' => $this->moneyString($targetCart->discount_total),
                'grand_total' => $this->moneyString($targetCart->grand_total),
                'paid_total' => $this->moneyString(0),
                'outstanding_total' => $this->moneyString($targetCart->grand_total),
                'placed_at' => now(),
                'notes' => $this->nullableString($payload['notes'] ?? null),
                'meta_json' => [
                    'source' => 'public_storefront',
                    'cart_id' => $targetCart->id,
                    'shipping_selection' => $selectedShipping,
                    'checkout_payload' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [],
                ],
            ]);

            foreach ($targetCart->items as $item) {
                $this->repository->createOrderItem($order, [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $this->moneyString($item->unit_price),
                    'tax_amount' => $this->moneyString(0),
                    'discount_amount' => $this->moneyString(0),
                    'line_total' => $this->moneyString($item->line_total),
                    'options_json' => $item->options_json ?? [],
                    'meta_json' => $item->meta_json ?? [],
                ]);
            }

            $this->accounting->recordOrderPlaced(
                site: $site,
                order: $order,
                eventKey: sprintf('order:%d:placed', $order->id),
                meta: [
                    'source' => 'checkout',
                    'cart_id' => $targetCart->id,
                ],
                actor: $viewer
            );

            $this->inventory->commitCartForOrder($site, $targetCart, $order, $viewer);

            $this->repository->updateCart($targetCart, [
                'status' => 'converted',
                'converted_order_id' => $order->id,
            ]);

            return $order;
        });

        $resolvedOrder = $this->repository->findOrderBySiteAndId($site, $order->id);
        if (! $resolvedOrder) {
            throw new EcommerceDomainException('Failed to load created order.', 500);
        }

        return [
            'site_id' => $site->id,
            'cart_id' => $targetCart->id,
            'order' => $this->serializeOrder($resolvedOrder, true),
            'meta' => [
                'payment_start_endpoint' => route('public.sites.ecommerce.orders.payment.start', [
                    'site' => $site->id,
                    'order' => $resolvedOrder->id,
                ]),
            ],
        ];
    }

    public function checkoutValidate(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetCart = $this->assertOpenCart($site, $cart);
        $targetCart = $this->assertCartIdentityIfProvided($targetCart, $payload);
        $targetCart = $this->recalculateCart($site, $targetCart->id);

        if ($targetCart->items->isEmpty()) {
            throw new EcommerceDomainException('Cart is empty.', 422);
        }

        $shippingPayload = $this->shippingOptions($site, $targetCart, [
            'cart_identity_token' => $payload['cart_identity_token'] ?? null,
            'shipping_address_json' => is_array($payload['shipping_address_json'] ?? null) ? $payload['shipping_address_json'] : [],
            'currency' => $payload['currency'] ?? $targetCart->currency,
        ], $viewer, $allowDraftPreview);

        $paymentPayload = $this->paymentOptions($site, $viewer, $allowDraftPreview);

        return [
            'site_id' => $site->id,
            'cart_id' => $targetCart->id,
            'cart' => $shippingPayload['cart'] ?? $this->serializeCart($site, $targetCart),
            'shipping' => $shippingPayload['shipping'] ?? ['providers' => []],
            'payments' => $paymentPayload,
            'validation' => [
                'valid' => true,
                'messages' => [],
                'checkout_endpoint' => route('public.sites.ecommerce.carts.checkout', [
                    'site' => $site->id,
                    'cart' => $targetCart->id,
                ]),
            ],
        ];
    }

    public function paymentOptions(Site $site, ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $paymentPolicy = $this->resolvePaymentPlanPolicy($site);

        $currency = 'GEL';
        $providers = [
            [
                'slug' => 'manual',
                'name' => 'Manual',
                'description' => 'Manual or offline payment collection.',
                'supports_installment' => false,
                'modes' => ['full'],
            ],
        ];

        if (! $paymentPolicy['online_payments_enabled']) {
            return [
                'site_id' => $site->id,
                'currency' => $currency,
                'providers' => $providers,
                'universal_payment_options' => $this->universalPayments->providerOptionsContract(
                    $site,
                    domain: 'ecommerce',
                    currency: $currency,
                    providers: $providers,
                    options: ['flow' => 'checkout']
                ),
            ];
        }

        foreach ($this->gatewayConfig->enabledGatewaysForStorefront($site, $currency) as $providerState) {
            $slug = $providerState['slug'] ?? null;
            $gateway = $providerState['gateway'] ?? null;
            if (! is_string($slug) || ! $gateway instanceof EcommercePaymentGatewayPlugin) {
                continue;
            }

            if (
                is_array($paymentPolicy['allowed_payment_providers'])
                && ! in_array($slug, $paymentPolicy['allowed_payment_providers'], true)
            ) {
                continue;
            }

            $supportsInstallment = $gateway->supportsInstallments()
                && $paymentPolicy['installments_enabled']
                && (
                    ! is_array($paymentPolicy['allowed_installment_providers'])
                    || in_array($slug, $paymentPolicy['allowed_installment_providers'], true)
                );
            $modes = $supportsInstallment ? ['full', 'installment'] : ['full'];

            $providers[] = [
                'slug' => $slug,
                'name' => $gateway->getName(),
                'description' => $gateway->getDescription(),
                'supports_installment' => $supportsInstallment,
                'modes' => $modes,
            ];
        }

        return [
            'site_id' => $site->id,
            'currency' => $currency,
            'providers' => $providers,
            'universal_payment_options' => $this->universalPayments->providerOptionsContract(
                $site,
                domain: 'ecommerce',
                currency: $currency,
                providers: $providers,
                options: ['flow' => 'checkout']
            ),
        ];
    }

    public function startPayment(Site $site, EcommerceOrder $order, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);
        $paymentPolicy = $this->resolvePaymentPlanPolicy($site);

        if (in_array($targetOrder->status, ['cancelled', 'failed', 'refunded'], true)) {
            throw new EcommerceDomainException('This order cannot accept new payments.', 422);
        }

        $outstandingAmount = $this->moneyFloat($targetOrder->outstanding_total);
        if ($outstandingAmount <= 0) {
            throw new EcommerceDomainException('This order has no outstanding balance.', 422);
        }

        $provider = strtolower($this->nullableString($payload['provider'] ?? null) ?? 'manual');
        $method = $this->nullableString($payload['method'] ?? null);
        $isInstallment = (bool) ($payload['is_installment'] ?? false);
        $gateway = null;

        if ($provider !== 'manual') {
            if (! $paymentPolicy['online_payments_enabled']) {
                throw new EcommerceDomainException(
                    'Online payments are not enabled for your current plan.',
                    422,
                    [
                        'reason' => 'online_payments_not_enabled',
                        ...$this->planPolicyContext($paymentPolicy['plan']),
                    ]
                );
            }

            if (
                is_array($paymentPolicy['allowed_payment_providers'])
                && ! in_array($provider, $paymentPolicy['allowed_payment_providers'], true)
            ) {
                throw new EcommerceDomainException(
                    'Selected payment provider is not allowed for your current plan.',
                    422,
                    [
                        'reason' => 'payment_provider_not_allowed',
                        'provider' => $provider,
                        'allowed_providers' => $paymentPolicy['allowed_payment_providers'],
                        ...$this->planPolicyContext($paymentPolicy['plan']),
                    ]
                );
            }
        }

        if ($provider !== 'manual') {
            $gateway = $this->gatewayConfig->resolveGatewayForStorefront($site, $provider, true);
            if (! $gateway) {
                if ($this->gatewayConfig->isProviderExplicitlyDisabledForSite($site, $provider)) {
                    throw new EcommerceDomainException('Selected payment provider is not available.', 422);
                }

                // Backward-compatible fallback for providers that are active/enabled
                // but do not implement ecommerce-specific payment-session bootstrap.
                $legacyGateway = $this->pluginManager->getGatewayBySlug($provider);
                if (! $legacyGateway) {
                    throw new EcommerceDomainException('Selected payment provider is not available.', 422);
                }

                if ($legacyGateway instanceof EcommercePaymentGatewayPlugin) {
                    $gateway = $legacyGateway;
                }
            }
        }

        if ($isInstallment) {
            if ($provider === 'manual') {
                throw new EcommerceDomainException('Installment payments require an online payment provider.', 422);
            }

            if (! $paymentPolicy['installments_enabled']) {
                throw new EcommerceDomainException(
                    'Installment payments are not enabled for your current plan.',
                    422,
                    [
                        'reason' => 'installments_not_enabled',
                        ...$this->planPolicyContext($paymentPolicy['plan']),
                    ]
                );
            }

            if (
                is_array($paymentPolicy['allowed_installment_providers'])
                && ! in_array($provider, $paymentPolicy['allowed_installment_providers'], true)
            ) {
                throw new EcommerceDomainException(
                    'Selected payment provider is not allowed for installments on your current plan.',
                    422,
                    [
                        'reason' => 'installment_provider_not_allowed',
                        'provider' => $provider,
                        'allowed_providers' => $paymentPolicy['allowed_installment_providers'],
                        ...$this->planPolicyContext($paymentPolicy['plan']),
                    ]
                );
            }

            if (! $gateway instanceof EcommercePaymentGatewayPlugin || ! $gateway->supportsInstallments()) {
                throw new EcommerceDomainException('Selected payment provider does not support installments.', 422);
            }
        }

        $payment = DB::transaction(function () use ($targetOrder, $provider, $method, $isInstallment, $payload, $outstandingAmount): EcommerceOrderPayment {
            return $this->repository->createOrderPayment($targetOrder, [
                'provider' => $provider,
                'status' => 'pending',
                'method' => $method,
                'transaction_reference' => strtoupper((string) Str::uuid()),
                'amount' => $this->moneyString($outstandingAmount),
                'currency' => $targetOrder->currency ?: 'GEL',
                'is_installment' => $isInstallment,
                'installment_plan_json' => is_array($payload['installment_plan_json'] ?? null) ? $payload['installment_plan_json'] : [],
                'raw_payload_json' => is_array($payload['raw_payload_json'] ?? null) ? $payload['raw_payload_json'] : [],
            ]);
        });

        $paymentSession = [
            'provider' => $provider,
            'status' => 'pending',
            'amount' => $this->moneyString($outstandingAmount),
            'currency' => $targetOrder->currency ?: 'GEL',
            'requires_redirect' => false,
            'redirect_url' => null,
            'expires_at' => now()->addMinutes(15)->toISOString(),
        ];

        if ($gateway instanceof EcommercePaymentGatewayPlugin) {
            try {
                $gatewayPayload = $gateway->initEcommercePayment($targetOrder, $payment, [
                    ...$payload,
                    'site_id' => $site->id,
                    'provider' => $provider,
                ]);
            } catch (\Throwable $exception) {
                throw new EcommerceDomainException(
                    'Unable to initialize payment provider session.',
                    422,
                    ['provider' => $provider]
                );
            }

            $paymentPatch = is_array($gatewayPayload['payment'] ?? null) ? $gatewayPayload['payment'] : [];
            if ($paymentPatch !== []) {
                $payment = $this->repository->updateOrderPayment($payment, $paymentPatch);
            }

            $sessionPatch = is_array($gatewayPayload['payment_session'] ?? null) ? $gatewayPayload['payment_session'] : [];
            if ($sessionPatch !== []) {
                $paymentSession = array_merge($paymentSession, $sessionPatch);
            }
        }

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'payment' => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'method' => $payment->method,
                'transaction_reference' => $payment->transaction_reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'is_installment' => (bool) $payment->is_installment,
                'created_at' => $payment->created_at?->toISOString(),
                'universal_payment' => $this->universalPayments->normalizeEcommercePayment($payment, [
                    'meta' => [
                        'resource' => [
                            'order_id' => (int) $targetOrder->id,
                            'order_number' => (string) ($targetOrder->order_number ?? ''),
                        ],
                    ],
                ]),
            ],
            'payment_session' => $paymentSession,
        ];
    }

    public function trackShipment(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        return $this->shipmentService->trackPublic($site, $payload, $viewer, $allowDraftPreview);
    }

    public function customerOrders(Site $site, array $filters = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $customer = $this->assertAuthenticatedCustomerUser($viewer);
        $customerEmail = Str::lower(trim((string) $customer->email));

        $limit = max(1, min((int) ($filters['limit'] ?? ($filters['per_page'] ?? 10)), 50));
        $offset = $filters['offset'] ?? null;
        if ($offset === null && array_key_exists('page', $filters)) {
            $page = max(1, (int) ($filters['page'] ?? 1));
            $offset = ($page - 1) * $limit;
        }
        $offset = max(0, (int) ($offset ?? 0));

        $orders = $this->repository->listOrders($site)
            ->filter(function (EcommerceOrder $order) use ($customerEmail): bool {
                return Str::lower(trim((string) $order->customer_email)) === $customerEmail;
            })
            ->values();

        $total = $orders->count();
        $items = $orders->slice($offset, $limit)
            ->values()
            ->map(fn (EcommerceOrder $order): array => $this->serializeOrder($order, false))
            ->all();

        return [
            'site_id' => $site->id,
            'orders' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($items)) < $total,
            ],
            'meta' => [
                'customer_email' => $customer->email,
            ],
        ];
    }

    public function customerOrder(Site $site, EcommerceOrder $order, ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);
        $customer = $this->assertAuthenticatedCustomerUser($viewer);
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);

        if (Str::lower(trim((string) $targetOrder->customer_email)) !== Str::lower(trim((string) $customer->email))) {
            throw new EcommerceDomainException('Order not found.', 404);
        }

        return [
            'site_id' => $site->id,
            'order' => $this->serializeOrder($targetOrder, true),
        ];
    }

    private function assertStorefrontAccessible(Site $site, ?User $viewer, bool $allowDraftPreview = false): void
    {
        if ($site->status === 'archived') {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        $site->loadMissing(['project.user', 'project.template']);
        $project = $site->project;
        if (! $project) {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        $draftPreviewAllowedForViewer = $allowDraftPreview && $this->canPreviewDraftStorefront($site, $viewer);
        if (! $project->published_at && ! $draftPreviewAllowedForViewer) {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        if ($project->published_visibility === 'private' && ! $draftPreviewAllowedForViewer) {
            $isOwner = $viewer && (string) $viewer->id === (string) $project->user_id;
            if (! $isOwner) {
                throw new EcommerceDomainException('Storefront not found.', 404);
            }
        }

        $modulesPayload = $this->moduleRegistry->modules($site, $project->user);
        $isEnabled = false;
        foreach ($modulesPayload['modules'] ?? [] as $module) {
            if (($module['key'] ?? null) === 'ecommerce') {
                $isEnabled = (bool) ($module['available'] ?? false);
                break;
            }
        }

        if (! $isEnabled) {
            throw new EcommerceDomainException('Ecommerce module is not enabled for this site.', 404);
        }
    }

    private function canPreviewDraftStorefront(Site $site, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (method_exists($viewer, 'hasAdminBypass') && $viewer->hasAdminBypass()) {
            return true;
        }

        $project = $site->relationLoaded('project')
            ? $site->project
            : $site->project()->first();

        if (! $project) {
            return false;
        }

        return (string) $viewer->id === (string) $project->user_id;
    }

    private function assertAuthenticatedCustomerUser(?User $viewer): User
    {
        if (! $viewer) {
            throw new EcommerceDomainException('Authentication required.', 401, [
                'reason' => 'customer_auth_required',
            ]);
        }

        $email = trim((string) ($viewer->email ?? ''));
        if ($email === '') {
            throw new EcommerceDomainException('Authenticated customer email is required.', 422, [
                'reason' => 'customer_email_missing',
            ]);
        }

        return $viewer;
    }

    private function assertMonthlyOrderLimitNotExceeded(Site $site): void
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return;
        }

        $limit = $plan->getMaxMonthlyOrders();
        if ($limit === null) {
            return;
        }

        $ownerId = (int) ($owner?->id ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $currentUsage = $this->usageMetering->countMonthlyOrdersForOwner($ownerId);

        if ($currentUsage >= $limit) {
            throw new EcommerceDomainException(
                'Monthly order limit reached for your current plan.',
                422,
                [
                    'reason' => 'monthly_orders_limit_reached',
                    'limit' => $limit,
                    'current_usage' => $currentUsage,
                    'period' => $this->usageMetering->currentPeriodLabel(),
                ]
            );
        }
    }

    /**
     * @return array{
     *   plan: Plan|null,
     *   online_payments_enabled: bool,
     *   installments_enabled: bool,
     *   allowed_payment_providers: array<int, string>|null,
     *   allowed_installment_providers: array<int, string>|null
     * }
     */
    private function resolvePaymentPlanPolicy(Site $site): array
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return [
                'plan' => null,
                'online_payments_enabled' => true,
                'installments_enabled' => true,
                'allowed_payment_providers' => null,
                'allowed_installment_providers' => null,
            ];
        }

        return [
            'plan' => $plan,
            'online_payments_enabled' => $plan->onlinePaymentsEnabled(),
            'installments_enabled' => $plan->installmentsEnabled(),
            'allowed_payment_providers' => $plan->getAllowedPaymentProviders(),
            'allowed_installment_providers' => $plan->getAllowedInstallmentProviders(),
        ];
    }

    /**
     * @return array{plan_id?: int, plan_slug?: string}
     */
    private function planPolicyContext(?Plan $plan): array
    {
        if (! $plan) {
            return [];
        }

        return [
            'plan_id' => (int) $plan->id,
            'plan_slug' => (string) $plan->slug,
        ];
    }

    /**
     * @return array{
     *   plan: Plan|null,
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
                'plan' => null,
                'shipping_enabled' => true,
                'allowed_courier_providers' => null,
            ];
        }

        return [
            'plan' => $plan,
            'shipping_enabled' => $plan->shippingEnabled(),
            'allowed_courier_providers' => $plan->getAllowedCourierProviders(),
        ];
    }

    /**
     * @return array{
     *   plan: Plan|null,
     *   shipping_enabled: bool,
     *   allowed_courier_providers: array<int, string>|null
     * }
     */
    private function assertShippingProviderAllowedByPlan(Site $site, ?string $provider): array
    {
        $shippingPolicy = $this->resolveShippingPlanPolicy($site);
        if ($provider === null) {
            return $shippingPolicy;
        }

        $normalizedProvider = strtolower(trim($provider));

        if (! $shippingPolicy['shipping_enabled']) {
            throw new EcommerceDomainException(
                'Shipping methods are not enabled for your current plan.',
                422,
                [
                    'reason' => 'shipping_not_enabled',
                    ...$this->planPolicyContext($shippingPolicy['plan']),
                ]
            );
        }

        if (
            is_array($shippingPolicy['allowed_courier_providers'])
            && ! in_array($normalizedProvider, $shippingPolicy['allowed_courier_providers'], true)
        ) {
            throw new EcommerceDomainException(
                'Selected shipping provider is not allowed for your current plan.',
                422,
                [
                    'reason' => 'courier_provider_not_allowed',
                    'provider' => $normalizedProvider,
                    'allowed_providers' => $shippingPolicy['allowed_courier_providers'],
                    ...$this->planPolicyContext($shippingPolicy['plan']),
                ]
            );
        }

        return $shippingPolicy;
    }

    private function assertCartBelongsToSite(Site $site, EcommerceCart $cart): EcommerceCart
    {
        $target = $this->repository->findCartBySiteAndId($site, (string) $cart->id);
        if (! $target) {
            throw new EcommerceDomainException('Cart not found.', 404);
        }

        return $target;
    }

    private function assertCartItemBelongsToCart(EcommerceCart $cart, EcommerceCartItem $item): EcommerceCartItem
    {
        $target = $this->repository->findCartItemByCartAndId($cart, (int) $item->id);
        if (! $target) {
            throw new EcommerceDomainException('Cart item not found.', 404);
        }

        return $target;
    }

    private function assertOrderBelongsToSite(Site $site, EcommerceOrder $order): EcommerceOrder
    {
        $target = $this->repository->findOrderBySiteAndId($site, (int) $order->id);
        if (! $target) {
            throw new EcommerceDomainException('Order not found.', 404);
        }

        return $target;
    }

    private function assertOpenCart(Site $site, EcommerceCart $cart): EcommerceCart
    {
        $target = $this->assertCartBelongsToSite($site, $cart);
        if ($target->status !== 'open') {
            throw new EcommerceDomainException('Cart is not open.', 422);
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCartIdentityIfProvided(EcommerceCart $cart, array $payload = []): EcommerceCart
    {
        $providedToken = $this->normalizeCartIdentityToken($payload['cart_identity_token'] ?? null);
        if ($providedToken === null) {
            return $cart;
        }

        $actualToken = $this->resolveCartIdentityToken($cart);
        if ($actualToken !== null && ! hash_equals($actualToken, $providedToken)) {
            throw new EcommerceDomainException('Cart identity token mismatch.', 409, [
                'code' => 'cart_identity_mismatch',
            ]);
        }

        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $meta = $this->mergeCartIdentityMeta($meta, $providedToken, [
            'mode' => 'guest',
            'last_seen_at' => now()->toISOString(),
        ]);

        return $this->repository->updateCart($cart, [
            'meta_json' => $meta,
        ]);
    }

    private function resolveCartIdentityToken(EcommerceCart $cart): ?string
    {
        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];

        return $this->normalizeCartIdentityToken($identity['token'] ?? null);
    }

    private function normalizeCartIdentityToken(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 191);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $extraIdentity
     * @return array<string, mixed>
     */
    private function mergeCartIdentityMeta(array $meta, string $token, array $extraIdentity = []): array
    {
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];

        $meta['identity'] = [
            ...$identity,
            ...$extraIdentity,
            'token' => $token,
        ];

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $existingMeta
     * @param  array<string, mixed>  $payloadMeta
     * @return array<string, mixed>
     */
    private function mergeCartMetaPreservingIdentity(array $existingMeta, array $payloadMeta): array
    {
        $identity = is_array($existingMeta['identity'] ?? null) ? $existingMeta['identity'] : null;
        $merged = [
            ...$existingMeta,
            ...$payloadMeta,
        ];

        if ($identity !== null) {
            $merged['identity'] = $identity;
        }

        return $merged;
    }

    private function findOpenCartByIdentityToken(Site $site, string $token): ?EcommerceCart
    {
        /** @var \Illuminate\Support\Collection<int, EcommerceCart> $candidates */
        $candidates = EcommerceCart::query()
            ->where('site_id', $site->id)
            ->where('status', 'open')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        foreach ($candidates as $candidate) {
            $candidateToken = $this->resolveCartIdentityToken($candidate);
            if ($candidateToken !== null && hash_equals($candidateToken, $token)) {
                return $candidate;
            }
        }

        return null;
    }

    private function clearCartShippingSelection(EcommerceCart $cart): EcommerceCart
    {
        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $hasSelection = array_key_exists('shipping_selection', $meta);
        $hasQuoteContext = array_key_exists('shipping_quote_context', $meta);
        $hasShippingAmount = $this->moneyFloat($cart->shipping_total) > 0;

        if (! $hasSelection && ! $hasQuoteContext && ! $hasShippingAmount) {
            return $cart;
        }

        unset($meta['shipping_selection'], $meta['shipping_quote_context']);

        return $this->repository->updateCart($cart, [
            'shipping_total' => $this->moneyString(0),
            'meta_json' => $meta,
        ]);
    }

    private function applyShippingSelection(
        Site $site,
        EcommerceCart $cart,
        array $shippingAddress,
        ?string $shippingProvider,
        ?string $shippingRateId,
        string $currency,
        bool $allowNoSelection = false
    ): EcommerceCart {
        if (($shippingProvider === null) xor ($shippingRateId === null)) {
            throw new EcommerceDomainException('shipping_provider and shipping_rate_id must be provided together.', 422);
        }

        if ($shippingProvider === null && $shippingRateId === null) {
            if ($allowNoSelection) {
                return $cart;
            }

            throw new EcommerceDomainException('Shipping provider and rate are required.', 422);
        }

        $shippingPayload = $this->buildShippingOptionsPayload($site, $cart, $shippingAddress, $currency);
        $selected = $this->resolveSelectedShippingRate($shippingPayload['providers'] ?? [], [
            'provider' => $shippingProvider,
            'rate_id' => $shippingRateId,
        ]);

        if (! $selected) {
            throw new EcommerceDomainException('Selected shipping rate is not available.', 422);
        }

        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $meta['shipping_selection'] = [
            'provider' => $selected['provider'],
            'provider_name' => $selected['provider_name'],
            'rate_id' => $selected['rate_id'],
            'service_code' => $selected['service_code'],
            'service_name' => $selected['service_name'],
            'amount' => $selected['amount'],
            'currency' => $selected['currency'],
            'estimated_days' => $selected['estimated_days'],
            'supports_tracking' => $selected['supports_tracking'],
            'selected_at' => now()->toISOString(),
        ];
        $meta['shipping_quote_context'] = [
            'shipping_address_json' => $shippingAddress,
            'currency' => $currency,
            'quoted_at' => now()->toISOString(),
        ];

        $patchedCart = $this->repository->updateCart($cart, [
            'shipping_total' => $this->moneyString($selected['amount']),
            'meta_json' => $meta,
        ]);

        return $this->recalculateCart($site, $patchedCart->id);
    }

    /**
     * @return array{
     *   currency:string,
     *   providers:array<int, array<string, mixed>>,
     *   selected_rate:array<string, mixed>|null
     * }
     */
    private function buildShippingOptionsPayload(
        Site $site,
        EcommerceCart $cart,
        array $shippingAddress,
        string $currency,
        ?array $shippingPolicy = null
    ): array {
        $providers = $this->quoteShippingProviders(
            site: $site,
            cart: $cart,
            shippingAddress: $shippingAddress,
            currency: $currency,
            shippingPolicy: $shippingPolicy
        );

        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $selected = is_array($meta['shipping_selection'] ?? null)
            ? $this->resolveSelectedShippingRate($providers, $meta['shipping_selection'])
            : null;

        return [
            'currency' => $currency,
            'providers' => $providers,
            'selected_rate' => $selected,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quoteShippingProviders(
        Site $site,
        EcommerceCart $cart,
        array $shippingAddress,
        string $currency,
        ?array $shippingPolicy = null
    ): array {
        $shippingPolicy ??= $this->resolveShippingPlanPolicy($site);
        if (! $shippingPolicy['shipping_enabled']) {
            return [];
        }

        $items = $cart->items->map(fn (EcommerceCartItem $item): array => [
            'item_id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => (int) $item->quantity,
            'unit_price' => $this->moneyString($item->unit_price),
            'line_total' => $this->moneyString($item->line_total),
        ])->values()->all();

        $payload = [
            'site_id' => $site->id,
            'cart_id' => $cart->id,
            'currency' => $currency,
            'subtotal' => $this->moneyString($cart->subtotal),
            'items_count' => $this->resolveCartItemsCount($cart),
            'items' => $items,
            'shipping_address_json' => $shippingAddress,
        ];

        $providers = [];

        foreach ($this->courierConfig->enabledCouriersForStorefront($site) as $providerEntry) {
            $courier = $providerEntry['courier'] ?? null;
            if (! $courier instanceof CourierPlugin) {
                continue;
            }

            $courierSlug = $this->nullableString($providerEntry['slug'] ?? null) ?? Str::slug($courier->getName());
            if (
                is_array($shippingPolicy['allowed_courier_providers'])
                && ! in_array($courierSlug, $shippingPolicy['allowed_courier_providers'], true)
            ) {
                continue;
            }

            try {
                $quote = $courier->quote($payload);
            } catch (\Throwable) {
                continue;
            }

            $provider = $this->nullableString($quote['provider'] ?? null) ?? $courierSlug;
            $rawRates = is_array($quote['rates'] ?? null) ? $quote['rates'] : [];
            $rates = [];

            foreach ($rawRates as $index => $rawRate) {
                if (! is_array($rawRate)) {
                    continue;
                }

                $normalized = $this->normalizeQuotedRate(
                    provider: $provider,
                    providerName: $courier->getName(),
                    supportsTracking: $courier->supportsTracking(),
                    rawRate: $rawRate,
                    fallbackCurrency: $currency,
                    fallbackIndex: (int) $index
                );

                if ($normalized !== null) {
                    $rates[] = $normalized;
                }
            }

            if ($rates === []) {
                continue;
            }

            usort(
                $rates,
                fn (array $left, array $right): int => $this->moneyFloat($left['amount'] ?? 0) <=> $this->moneyFloat($right['amount'] ?? 0)
            );

            $providers[] = [
                'provider' => $provider,
                'name' => $courier->getName(),
                'description' => $courier->getDescription(),
                'supports_tracking' => $courier->supportsTracking(),
                'supported_countries' => $courier->getSupportedCountries(),
                'rates' => $rates,
            ];
        }

        usort($providers, fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $rawRate
     * @return array<string, mixed>|null
     */
    private function normalizeQuotedRate(
        string $provider,
        string $providerName,
        bool $supportsTracking,
        array $rawRate,
        string $fallbackCurrency,
        int $fallbackIndex
    ): ?array {
        $serviceCode = $this->nullableString($rawRate['service_code'] ?? null) ?? 'standard';
        $rateId = $this->nullableString($rawRate['rate_id'] ?? null) ?? "{$provider}:{$serviceCode}:{$fallbackIndex}";
        $serviceName = $this->nullableString($rawRate['service_name'] ?? null) ?? $providerName;
        $amount = $this->moneyString($rawRate['amount'] ?? null);
        $currency = $this->normalizeCurrency($rawRate['currency'] ?? $fallbackCurrency);
        $metadata = is_array($rawRate['metadata'] ?? null) ? $rawRate['metadata'] : [];
        $eta = is_array($rawRate['estimated_days'] ?? null) ? $rawRate['estimated_days'] : [];
        $etaMin = max(1, (int) ($eta['min'] ?? 1));
        $etaMax = max($etaMin, (int) ($eta['max'] ?? $etaMin));

        if ($rateId === '' || $serviceName === '') {
            return null;
        }

        return [
            'provider' => $provider,
            'provider_name' => $providerName,
            'supports_tracking' => $supportsTracking,
            'rate_id' => $rateId,
            'service_code' => $serviceCode,
            'service_name' => $serviceName,
            'amount' => $amount,
            'currency' => $currency,
            'estimated_days' => [
                'min' => $etaMin,
                'max' => $etaMax,
            ],
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $providers
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>|null
     */
    private function resolveSelectedShippingRate(array $providers, array $selection): ?array
    {
        $provider = $this->nullableString($selection['provider'] ?? null);
        $rateId = $this->nullableString($selection['rate_id'] ?? null);

        if ($provider === null || $rateId === null) {
            return null;
        }

        foreach ($providers as $providerPayload) {
            if (! is_array($providerPayload)) {
                continue;
            }

            if (($providerPayload['provider'] ?? null) !== $provider) {
                continue;
            }

            $rates = is_array($providerPayload['rates'] ?? null) ? $providerPayload['rates'] : [];
            foreach ($rates as $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                if (($rate['rate_id'] ?? null) !== $rateId) {
                    continue;
                }

                return [
                    'provider' => $provider,
                    'provider_name' => $providerPayload['name'] ?? $provider,
                    'supports_tracking' => (bool) ($providerPayload['supports_tracking'] ?? false),
                    'rate_id' => $rate['rate_id'],
                    'service_code' => $rate['service_code'],
                    'service_name' => $rate['service_name'],
                    'amount' => $rate['amount'],
                    'currency' => $rate['currency'],
                    'estimated_days' => $rate['estimated_days'] ?? ['min' => 1, 'max' => 1],
                    'metadata' => is_array($rate['metadata'] ?? null) ? $rate['metadata'] : [],
                ];
            }
        }

        return null;
    }

    private function resolveCartItemsCount(EcommerceCart $cart): int
    {
        return max(0, (int) $cart->items->sum(fn (EcommerceCartItem $item): int => max(1, (int) $item->quantity)));
    }

    private function resolveVariantForProduct(Site $site, EcommerceProduct $product, ?int $variantId): ?EcommerceProductVariant
    {
        if ($variantId === null || $variantId <= 0) {
            return null;
        }

        $variant = $this->repository->findProductVariantBySiteAndId($site, $variantId);
        if (! $variant || (int) $variant->product_id !== (int) $product->id) {
            throw new EcommerceDomainException('Selected variant is invalid.', 422);
        }

        return $variant;
    }

    private function resolveUnitPrice(EcommerceProduct $product, ?EcommerceProductVariant $variant): float
    {
        $price = $variant && $variant->price !== null ? $variant->price : $product->price;

        return $this->moneyFloat($price);
    }

    private function buildCartItemName(EcommerceProduct $product, ?EcommerceProductVariant $variant): string
    {
        if (! $variant) {
            return $product->name;
        }

        return "{$product->name} - {$variant->name}";
    }

    private function recalculateCart(Site $site, string $cartId): EcommerceCart
    {
        $cart = $this->repository->findCartBySiteAndId($site, $cartId);
        if (! $cart) {
            throw new EcommerceDomainException('Cart not found.', 404);
        }

        $subtotal = $cart->items->sum(fn (EcommerceCartItem $item): float => $this->moneyFloat($item->line_total));
        $taxTotal = $this->moneyFloat($cart->tax_total);
        $shippingTotal = $this->moneyFloat($cart->shipping_total);
        $discountTotal = $this->resolveCouponDiscountForCart($cart, $subtotal);
        $grandTotal = max(0, $subtotal + $taxTotal + $shippingTotal - $discountTotal);

        $updated = $this->repository->updateCart($cart, [
            'subtotal' => $this->moneyString($subtotal),
            'tax_total' => $this->moneyString($taxTotal),
            'shipping_total' => $this->moneyString($shippingTotal),
            'discount_total' => $this->moneyString($discountTotal),
            'grand_total' => $this->moneyString($grandTotal),
        ]);

        $resolved = $this->repository->findCartBySiteAndId($site, $updated->id);
        if (! $resolved) {
            throw new EcommerceDomainException('Failed to load updated cart.', 500);
        }

        return $resolved;
    }

    private function generateOrderNumber(Site $site): string
    {
        $attempts = 0;
        do {
            $candidate = sprintf(
                'ORD-%s-%s',
                now()->format('YmdHis'),
                strtoupper(Str::random(4))
            );

            $exists = EcommerceOrder::query()
                ->where('site_id', $site->id)
                ->where('order_number', $candidate)
                ->exists();

            $attempts++;
        } while ($exists && $attempts < 10);

        return $candidate;
    }

    private function serializeProduct(Site $site, EcommerceProduct $product, bool $withDetails): array
    {
        $images = $product->images
            ->sortBy([
                fn ($item) => $item->is_primary ? 0 : 1,
                fn ($item) => $item->sort_order,
                fn ($item) => $item->id,
            ])
            ->values();

        $payload = [
            'id' => $product->id,
            'site_id' => $product->site_id,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'price' => (string) $product->price,
            'compare_at_price' => $product->compare_at_price !== null ? (string) $product->compare_at_price : null,
            'currency' => $product->currency,
            'stock_tracking' => (bool) $product->stock_tracking,
            'stock_quantity' => (int) $product->stock_quantity,
            'allow_backorder' => (bool) $product->allow_backorder,
            'is_digital' => (bool) $product->is_digital,
            'weight_grams' => $product->weight_grams,
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'published_at' => $product->published_at?->toISOString(),
            'primary_image_url' => $this->resolvePrimaryImageUrl($site, $images->first()),
        ];

        if (! $withDetails) {
            return $payload;
        }

        $payload['description'] = $product->description;
        $payload['attributes_json'] = $product->attributes_json ?? [];
        $payload['images'] = $images->map(function ($image) use ($site): array {
            return [
                'id' => $image->id,
                'path' => $image->path,
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'is_primary' => (bool) $image->is_primary,
                'asset_url' => $image->path
                    ? route('public.sites.assets', ['site' => $site->id, 'path' => $image->path])
                    : null,
            ];
        })->all();

        $payload['variants'] = $product->variants
            ->sortBy('position')
            ->values()
            ->map(fn (EcommerceProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'options_json' => $variant->options_json ?? [],
                'price' => $variant->price !== null ? (string) $variant->price : null,
                'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null,
                'stock_tracking' => (bool) $variant->stock_tracking,
                'stock_quantity' => (int) $variant->stock_quantity,
                'allow_backorder' => (bool) $variant->allow_backorder,
                'is_default' => (bool) $variant->is_default,
                'position' => (int) $variant->position,
            ])
            ->all();

        return $payload;
    }

    private function serializeCart(Site $site, EcommerceCart $cart): array
    {
        $coupon = $this->serializeCartCoupon($cart);

        return [
            'id' => $cart->id,
            'site_id' => $cart->site_id,
            'status' => $cart->status,
            'currency' => $cart->currency,
            'customer_email' => $cart->customer_email,
            'customer_phone' => $cart->customer_phone,
            'customer_name' => $cart->customer_name,
            'subtotal' => (string) $cart->subtotal,
            'tax_total' => (string) $cart->tax_total,
            'shipping_total' => (string) $cart->shipping_total,
            'discount_total' => (string) $cart->discount_total,
            'grand_total' => (string) $cart->grand_total,
            'expires_at' => $cart->expires_at?->toISOString(),
            'meta_json' => $cart->meta_json ?? [],
            'coupon' => $coupon,
            'items' => $cart->items->map(function (EcommerceCartItem $item) use ($site): array {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                    'options_json' => $item->options_json ?? [],
                    'meta_json' => $item->meta_json ?? [],
                    'product_slug' => $item->product?->slug,
                    'product_url' => $item->product?->slug
                        ? route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => $item->product->slug])
                        : null,
                ];
            })->values()->all(),
            'created_at' => $cart->created_at?->toISOString(),
            'updated_at' => $cart->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeCartCoupon(EcommerceCart $cart): ?array
    {
        $coupon = $this->resolveAppliedCouponMeta($cart);
        if ($coupon === null) {
            return null;
        }

        return [
            'code' => (string) ($coupon['code'] ?? ''),
            'label' => (string) ($coupon['label'] ?? ($coupon['code'] ?? '')),
            'type' => (string) ($coupon['type'] ?? 'fixed'),
            'value' => $this->moneyString($coupon['value'] ?? 0),
            'min_subtotal' => $this->moneyString($coupon['min_subtotal'] ?? 0),
            'scope' => (string) ($coupon['scope'] ?? 'subtotal'),
            'source' => (string) ($coupon['source'] ?? 'built_in'),
            'effective_discount_total' => (string) $cart->discount_total,
            'applied_at' => $coupon['applied_at'] ?? null,
            'meta_json' => is_array($coupon['meta_json'] ?? null) ? $coupon['meta_json'] : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAppliedCouponMeta(EcommerceCart $cart): ?array
    {
        $meta = is_array($cart->meta_json) ? $cart->meta_json : [];
        $coupon = $meta['coupon'] ?? null;

        if (! is_array($coupon)) {
            return null;
        }

        $code = strtoupper(trim((string) ($coupon['code'] ?? '')));
        $type = strtolower(trim((string) ($coupon['type'] ?? '')));

        if ($code === '' || ! in_array($type, ['fixed', 'percent'], true)) {
            return null;
        }

        return [
            ...$coupon,
            'code' => $code,
            'type' => $type,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCouponDefinitionForCode(Site $site, string $code): ?array
    {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            return null;
        }

        $siteCoupons = data_get($site->theme_settings, 'ecommerce.coupons');
        if (is_array($siteCoupons)) {
            foreach ($siteCoupons as $rawCoupon) {
                if (! is_array($rawCoupon)) {
                    continue;
                }

                $candidateCode = strtoupper(trim((string) ($rawCoupon['code'] ?? '')));
                $type = strtolower(trim((string) ($rawCoupon['type'] ?? '')));
                $value = $this->moneyFloat($rawCoupon['value'] ?? null);

                if ($candidateCode !== $normalizedCode || ! in_array($type, ['fixed', 'percent'], true) || $value === null || $value <= 0) {
                    continue;
                }

                return [
                    'code' => $candidateCode,
                    'label' => $this->nullableString($rawCoupon['label'] ?? null) ?? $candidateCode,
                    'type' => $type,
                    'value' => $type === 'percent' ? min(100.0, $value) : max(0.0, $value),
                    'min_subtotal' => max(0.0, $this->moneyFloat($rawCoupon['min_subtotal'] ?? null)),
                    'source' => 'site_theme_settings',
                ];
            }
        }

        return match ($normalizedCode) {
            'SAVE10' => [
                'code' => 'SAVE10',
                'label' => 'Save 10%',
                'type' => 'percent',
                'value' => 10.0,
                'min_subtotal' => 0.0,
                'source' => 'built_in',
            ],
            'WELCOME5' => [
                'code' => 'WELCOME5',
                'label' => 'Welcome 5',
                'type' => 'fixed',
                'value' => 5.0,
                'min_subtotal' => 0.0,
                'source' => 'built_in',
            ],
            default => null,
        };
    }

    private function resolveCouponDiscountForCart(EcommerceCart $cart, float $subtotal): float
    {
        $coupon = $this->resolveAppliedCouponMeta($cart);
        if ($coupon === null) {
            return 0.0;
        }

        if ($subtotal <= 0) {
            return 0.0;
        }

        $minSubtotal = max(0.0, $this->moneyFloat($coupon['min_subtotal'] ?? null));
        if ($subtotal < $minSubtotal) {
            return 0.0;
        }

        $value = max(0.0, $this->moneyFloat($coupon['value'] ?? null));
        $type = (string) ($coupon['type'] ?? 'fixed');

        $discount = $type === 'percent'
            ? ($subtotal * min(100.0, $value) / 100.0)
            : $value;

        return min($subtotal, max(0.0, $discount));
    }

    private function serializeOrder(EcommerceOrder $order, bool $withDetails): array
    {
        $payload = [
            'id' => $order->id,
            'site_id' => $order->site_id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'currency' => $order->currency,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'customer_name' => $order->customer_name,
            'subtotal' => (string) $order->subtotal,
            'tax_total' => (string) $order->tax_total,
            'shipping_total' => (string) $order->shipping_total,
            'discount_total' => (string) $order->discount_total,
            'grand_total' => (string) $order->grand_total,
            'paid_total' => (string) $order->paid_total,
            'outstanding_total' => (string) $order->outstanding_total,
            'placed_at' => $order->placed_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];

        if (! $withDetails) {
            return $payload;
        }

        $payload['billing_address_json'] = $order->billing_address_json ?? [];
        $payload['shipping_address_json'] = $order->shipping_address_json ?? [];
        $payload['notes'] = $order->notes;
        $payload['meta_json'] = $order->meta_json ?? [];
        $payload['items'] = $order->items->map(fn ($item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => (int) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'tax_amount' => (string) $item->tax_amount,
            'discount_amount' => (string) $item->discount_amount,
            'line_total' => (string) $item->line_total,
            'options_json' => $item->options_json ?? [],
            'meta_json' => $item->meta_json ?? [],
        ])->values()->all();

        $payload['payments'] = $order->payments->map(fn ($payment): array => [
            'id' => $payment->id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'method' => $payment->method,
            'transaction_reference' => $payment->transaction_reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'is_installment' => (bool) $payment->is_installment,
            'installment_plan_json' => $payment->installment_plan_json ?? [],
            'raw_payload_json' => $payment->raw_payload_json ?? [],
            'processed_at' => $payment->processed_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ])->values()->all();

        return $payload;
    }

    private function resolvePrimaryImageUrl(Site $site, mixed $image): ?string
    {
        if (! $image || ! is_object($image)) {
            return null;
        }

        $path = $image->path ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return route('public.sites.assets', ['site' => $site->id, 'path' => $path]);
    }

    private function moneyFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->moneyFloat($value), 2, '.', '');
    }

    private function normalizeCurrency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'GEL';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
