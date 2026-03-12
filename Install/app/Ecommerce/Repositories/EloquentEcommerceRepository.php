<?php

namespace App\Ecommerce\Repositories;

use App\Ecommerce\Contracts\EcommerceRepositoryContract;
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

class EloquentEcommerceRepository implements EcommerceRepositoryContract
{
    public function listCategories(Site $site): Collection
    {
        return EcommerceCategory::query()
            ->where('site_id', $site->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findCategoryBySiteAndId(Site $site, int $categoryId): ?EcommerceCategory
    {
        return EcommerceCategory::query()
            ->where('site_id', $site->id)
            ->where('id', $categoryId)
            ->first();
    }

    public function createCategory(Site $site, array $payload): EcommerceCategory
    {
        $payload['site_id'] = $site->id;

        /** @var EcommerceCategory $category */
        $category = EcommerceCategory::query()->create($payload);

        return $category;
    }

    public function updateCategory(EcommerceCategory $category, array $payload): EcommerceCategory
    {
        $category->update($payload);

        return $category->fresh();
    }

    public function deleteCategory(EcommerceCategory $category): bool
    {
        return (bool) $category->delete();
    }

    public function listProducts(Site $site): Collection
    {
        return EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->with([
                'category:id,name,site_id',
                'images:id,site_id,product_id,media_id,path,alt_text,sort_order,is_primary',
            ])
            ->latest('id')
            ->get();
    }

    public function findProductBySiteAndId(Site $site, int $productId): ?EcommerceProduct
    {
        return EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('id', $productId)
            ->with([
                'category:id,name,site_id',
                'images:id,site_id,product_id,media_id,path,alt_text,sort_order,is_primary',
                'variants:id,site_id,product_id,name,sku,options_json,price,compare_at_price,stock_tracking,stock_quantity,allow_backorder,is_default,position',
            ])
            ->first();
    }

    public function createProduct(Site $site, array $payload): EcommerceProduct
    {
        $payload['site_id'] = $site->id;

        /** @var EcommerceProduct $product */
        $product = EcommerceProduct::query()->create($payload);

        return $product;
    }

    public function updateProduct(EcommerceProduct $product, array $payload): EcommerceProduct
    {
        $product->update($payload);

        return $product->fresh();
    }

    public function deleteProduct(EcommerceProduct $product): bool
    {
        return (bool) $product->delete();
    }

    public function listPublishedProducts(Site $site): Collection
    {
        return EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->with([
                'category:id,site_id,name,slug',
                'images:id,site_id,product_id,path,alt_text,sort_order,is_primary',
                'variants:id,site_id,product_id,name,sku,price,stock_tracking,stock_quantity,allow_backorder,is_default,position',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findPublishedProductBySiteAndSlug(Site $site, string $slug): ?EcommerceProduct
    {
        return EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->with([
                'category:id,site_id,name,slug',
                'images:id,site_id,product_id,path,alt_text,sort_order,is_primary',
                'variants:id,site_id,product_id,name,sku,options_json,price,compare_at_price,stock_tracking,stock_quantity,allow_backorder,is_default,position',
            ])
            ->first();
    }

    public function findProductVariantBySiteAndId(Site $site, int $variantId): ?EcommerceProductVariant
    {
        return EcommerceProductVariant::query()
            ->where('site_id', $site->id)
            ->where('id', $variantId)
            ->with('product:id,site_id,status,published_at')
            ->first();
    }

    public function listOrders(Site $site): Collection
    {
        return EcommerceOrder::query()
            ->where('site_id', $site->id)
            ->withCount('items')
            ->withCount('shipments')
            ->with([
                'payments:id,site_id,order_id,provider,status,amount,currency,is_installment,created_at',
            ])
            ->latest('id')
            ->get();
    }

    public function findOrderBySiteAndId(Site $site, int $orderId): ?EcommerceOrder
    {
        return EcommerceOrder::query()
            ->where('site_id', $site->id)
            ->where('id', $orderId)
            ->with([
                'items',
                'payments',
                'shipments.events',
            ])
            ->first();
    }

    public function findOrderById(int $orderId): ?EcommerceOrder
    {
        return EcommerceOrder::query()
            ->where('id', $orderId)
            ->with([
                'site:id,project_id',
                'items',
                'payments',
                'shipments.events',
            ])
            ->first();
    }

    public function createOrder(Site $site, array $payload): EcommerceOrder
    {
        $payload['site_id'] = $site->id;

        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create($payload);

        return $order;
    }

    public function updateOrder(EcommerceOrder $order, array $payload): EcommerceOrder
    {
        $order->update($payload);

        return $order->fresh();
    }

    public function deleteOrder(EcommerceOrder $order): bool
    {
        return (bool) $order->delete();
    }

    public function createOrderItem(EcommerceOrder $order, array $payload): EcommerceOrderItem
    {
        $payload['site_id'] = $order->site_id;

        /** @var EcommerceOrderItem $item */
        $item = $order->items()->create($payload);

        return $item;
    }

    public function createOrderPayment(EcommerceOrder $order, array $payload): EcommerceOrderPayment
    {
        $payload['site_id'] = $order->site_id;

        /** @var EcommerceOrderPayment $payment */
        $payment = $order->payments()->create($payload);

        return $payment;
    }

    public function findOrderPaymentById(int $paymentId): ?EcommerceOrderPayment
    {
        return EcommerceOrderPayment::query()
            ->where('id', $paymentId)
            ->with(['order.site:id,project_id'])
            ->first();
    }

    public function findOrderPaymentByTransactionReference(string $transactionReference, ?string $provider = null): ?EcommerceOrderPayment
    {
        $query = EcommerceOrderPayment::query()
            ->where('transaction_reference', $transactionReference);

        if (is_string($provider) && trim($provider) !== '') {
            $query->where('provider', $provider);
        }

        return $query
            ->with(['order.site:id,project_id'])
            ->orderByDesc('id')
            ->first();
    }

    public function findLatestOrderPaymentForOrder(EcommerceOrder $order, ?string $provider = null): ?EcommerceOrderPayment
    {
        $query = EcommerceOrderPayment::query()
            ->where('site_id', $order->site_id)
            ->where('order_id', $order->id);

        if (is_string($provider) && trim($provider) !== '') {
            $query->where('provider', $provider);
        }

        return $query
            ->with(['order.site:id,project_id'])
            ->latest('id')
            ->first();
    }

    public function updateOrderPayment(EcommerceOrderPayment $payment, array $payload): EcommerceOrderPayment
    {
        $payment->update($payload);

        return $payment->fresh(['order.site']);
    }

    public function listShipmentsByOrder(Site $site, EcommerceOrder $order): Collection
    {
        return EcommerceShipment::query()
            ->where('site_id', $site->id)
            ->where('order_id', $order->id)
            ->with([
                'events' => function ($query): void {
                    $query->latest('occurred_at')->latest('id');
                },
            ])
            ->latest('id')
            ->get();
    }

    public function findShipmentByOrderAndId(Site $site, EcommerceOrder $order, int $shipmentId): ?EcommerceShipment
    {
        return EcommerceShipment::query()
            ->where('site_id', $site->id)
            ->where('order_id', $order->id)
            ->where('id', $shipmentId)
            ->with([
                'events' => function ($query): void {
                    $query->latest('occurred_at')->latest('id');
                },
            ])
            ->first();
    }

    public function findShipmentBySiteAndReference(Site $site, string $shipmentReference): ?EcommerceShipment
    {
        return EcommerceShipment::query()
            ->where('site_id', $site->id)
            ->where('shipment_reference', $shipmentReference)
            ->with([
                'order:id,site_id,order_number,customer_email,status,fulfillment_status',
                'events' => function ($query): void {
                    $query->latest('occurred_at')->latest('id');
                },
            ])
            ->first();
    }

    public function findShipmentBySiteAndTrackingNumber(Site $site, string $trackingNumber): ?EcommerceShipment
    {
        return EcommerceShipment::query()
            ->where('site_id', $site->id)
            ->where('tracking_number', $trackingNumber)
            ->with([
                'order:id,site_id,order_number,customer_email,status,fulfillment_status',
                'events' => function ($query): void {
                    $query->latest('occurred_at')->latest('id');
                },
            ])
            ->latest('id')
            ->first();
    }

    public function createShipment(EcommerceOrder $order, array $payload): EcommerceShipment
    {
        $payload['site_id'] = $order->site_id;
        $payload['order_id'] = $order->id;

        /** @var EcommerceShipment $shipment */
        $shipment = EcommerceShipment::query()->create($payload);

        return $shipment;
    }

    public function updateShipment(EcommerceShipment $shipment, array $payload): EcommerceShipment
    {
        $shipment->update($payload);

        return $shipment->fresh(['events']);
    }

    public function createShipmentEvent(EcommerceShipment $shipment, array $payload): EcommerceShipmentEvent
    {
        $payload['site_id'] = $shipment->site_id;
        $payload['shipment_id'] = $shipment->id;

        /** @var EcommerceShipmentEvent $event */
        $event = $shipment->events()->create($payload);

        return $event;
    }

    public function createCart(Site $site, array $payload): EcommerceCart
    {
        $payload['site_id'] = $site->id;

        /** @var EcommerceCart $cart */
        $cart = EcommerceCart::query()->create($payload);

        return $cart;
    }

    public function findCartBySiteAndId(Site $site, string $cartId): ?EcommerceCart
    {
        return EcommerceCart::query()
            ->where('site_id', $site->id)
            ->where('id', $cartId)
            ->with([
                'items:id,site_id,cart_id,product_id,variant_id,name,sku,quantity,unit_price,line_total,options_json,meta_json,created_at,updated_at',
                'items.product:id,site_id,name,slug,status,published_at,stock_tracking,stock_quantity,allow_backorder,price,currency',
                'items.variant:id,site_id,product_id,name,sku,price,stock_tracking,stock_quantity,allow_backorder',
            ])
            ->first();
    }

    public function updateCart(EcommerceCart $cart, array $payload): EcommerceCart
    {
        $cart->update($payload);

        return $cart->fresh([
            'items',
        ]);
    }

    public function createCartItem(EcommerceCart $cart, array $payload): EcommerceCartItem
    {
        $payload['site_id'] = $cart->site_id;

        /** @var EcommerceCartItem $item */
        $item = $cart->items()->create($payload);

        return $item;
    }

    public function findCartItemByCartAndId(EcommerceCart $cart, int $itemId): ?EcommerceCartItem
    {
        return EcommerceCartItem::query()
            ->where('site_id', $cart->site_id)
            ->where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->first();
    }

    public function updateCartItem(EcommerceCartItem $item, array $payload): EcommerceCartItem
    {
        $item->update($payload);

        return $item->fresh();
    }

    public function deleteCartItem(EcommerceCartItem $item): bool
    {
        return (bool) $item->delete();
    }
}
