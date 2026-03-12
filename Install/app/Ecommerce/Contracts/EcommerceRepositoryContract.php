<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceCategory;
use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\EcommerceShipment;
use App\Models\EcommerceShipmentEvent;
use App\Models\Site;
use Illuminate\Support\Collection;

interface EcommerceRepositoryContract
{
    /**
     * @return Collection<int, EcommerceCategory>
     */
    public function listCategories(Site $site): Collection;

    public function findCategoryBySiteAndId(Site $site, int $categoryId): ?EcommerceCategory;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCategory(Site $site, array $payload): EcommerceCategory;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateCategory(EcommerceCategory $category, array $payload): EcommerceCategory;

    public function deleteCategory(EcommerceCategory $category): bool;

    /**
     * @return Collection<int, EcommerceProduct>
     */
    public function listProducts(Site $site): Collection;

    public function findProductBySiteAndId(Site $site, int $productId): ?EcommerceProduct;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createProduct(Site $site, array $payload): EcommerceProduct;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProduct(EcommerceProduct $product, array $payload): EcommerceProduct;

    public function deleteProduct(EcommerceProduct $product): bool;

    /**
     * @return Collection<int, EcommerceProduct>
     */
    public function listPublishedProducts(Site $site): Collection;

    public function findPublishedProductBySiteAndSlug(Site $site, string $slug): ?EcommerceProduct;

    public function findProductVariantBySiteAndId(Site $site, int $variantId): ?EcommerceProductVariant;

    /**
     * @return Collection<int, EcommerceOrder>
     */
    public function listOrders(Site $site): Collection;

    public function findOrderBySiteAndId(Site $site, int $orderId): ?EcommerceOrder;

    public function findOrderById(int $orderId): ?EcommerceOrder;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrder(Site $site, array $payload): EcommerceOrder;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOrder(EcommerceOrder $order, array $payload): EcommerceOrder;

    public function deleteOrder(EcommerceOrder $order): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrderItem(EcommerceOrder $order, array $payload): EcommerceOrderItem;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrderPayment(EcommerceOrder $order, array $payload): EcommerceOrderPayment;

    public function findOrderPaymentById(int $paymentId): ?EcommerceOrderPayment;

    public function findOrderPaymentByTransactionReference(string $transactionReference, ?string $provider = null): ?EcommerceOrderPayment;

    public function findLatestOrderPaymentForOrder(EcommerceOrder $order, ?string $provider = null): ?EcommerceOrderPayment;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOrderPayment(EcommerceOrderPayment $payment, array $payload): EcommerceOrderPayment;

    /**
     * @return Collection<int, EcommerceShipment>
     */
    public function listShipmentsByOrder(Site $site, EcommerceOrder $order): Collection;

    public function findShipmentByOrderAndId(Site $site, EcommerceOrder $order, int $shipmentId): ?EcommerceShipment;

    public function findShipmentBySiteAndReference(Site $site, string $shipmentReference): ?EcommerceShipment;

    public function findShipmentBySiteAndTrackingNumber(Site $site, string $trackingNumber): ?EcommerceShipment;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createShipment(EcommerceOrder $order, array $payload): EcommerceShipment;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateShipment(EcommerceShipment $shipment, array $payload): EcommerceShipment;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createShipmentEvent(EcommerceShipment $shipment, array $payload): EcommerceShipmentEvent;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCart(Site $site, array $payload): EcommerceCart;

    public function findCartBySiteAndId(Site $site, string $cartId): ?EcommerceCart;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateCart(EcommerceCart $cart, array $payload): EcommerceCart;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCartItem(EcommerceCart $cart, array $payload): EcommerceCartItem;

    public function findCartItemByCartAndId(EcommerceCart $cart, int $itemId): ?EcommerceCartItem;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateCartItem(EcommerceCartItem $item, array $payload): EcommerceCartItem;

    public function deleteCartItem(EcommerceCartItem $item): bool;
}
