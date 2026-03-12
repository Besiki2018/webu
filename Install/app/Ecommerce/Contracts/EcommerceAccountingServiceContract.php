<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceAccountingEntry;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\Site;
use App\Models\User;

interface EcommerceAccountingServiceContract
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordOrderPlaced(
        Site $site,
        EcommerceOrder $order,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordPaymentSettled(
        Site $site,
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordRefund(
        Site $site,
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordReturnAdjustment(
        Site $site,
        EcommerceOrder $order,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listEntries(Site $site, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function reconciliation(Site $site, array $filters = []): array;
}
