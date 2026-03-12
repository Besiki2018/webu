<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceOrder;
use App\Models\Site;
use App\Models\User;

interface EcommercePublicStorefrontServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listProducts(Site $site, array $filters = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @return array<string, mixed>
     */
    public function product(Site $site, string $slug, ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCart(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @return array<string, mixed>
     */
    public function cart(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function addCartItem(Site $site, EcommerceCart $cart, array $payload, ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item,
        array $payload,
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function removeCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $item,
        array $payload = [],
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyCoupon(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @return array<string, mixed>
     */
    public function removeCoupon(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function shippingOptions(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateShipping(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkout(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkoutValidate(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @return array<string, mixed>
     */
    public function paymentOptions(Site $site, ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function startPayment(Site $site, EcommerceOrder $order, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function trackShipment(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function customerOrders(Site $site, array $filters = [], ?User $viewer = null, bool $allowDraftPreview = false): array;

    /**
     * @return array<string, mixed>
     */
    public function customerOrder(Site $site, EcommerceOrder $order, ?User $viewer = null, bool $allowDraftPreview = false): array;
}
