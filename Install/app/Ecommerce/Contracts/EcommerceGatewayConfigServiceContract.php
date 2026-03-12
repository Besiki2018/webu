<?php

namespace App\Ecommerce\Contracts;

use App\Contracts\EcommercePaymentGatewayPlugin;
use App\Contracts\PaymentGatewayPlugin;
use App\Models\Site;

interface EcommerceGatewayConfigServiceContract
{
    /**
     * Return payment gateway configuration payload for merchant panel.
     *
     * @return array{site_id:string,providers:array<int,array<string,mixed>>}
     */
    public function listForSite(Site $site): array;

    /**
     * Update one site-level payment provider configuration.
     *
     * @param  array<string,mixed>  $payload
     * @return array{site_id:string,provider:array<string,mixed>}
     */
    public function updateForSite(Site $site, string $providerSlug, array $payload = []): array;

    /**
     * Resolve one provider instance for storefront payment flow.
     */
    public function resolveGatewayForStorefront(Site $site, string $providerSlug, bool $requireEnabled = true): ?EcommercePaymentGatewayPlugin;

    /**
     * Check whether a provider can be used for this site.
     */
    public function isProviderEnabledForSite(Site $site, string $providerSlug): bool;

    /**
     * Check whether a provider is explicitly disabled on site-level.
     */
    public function isProviderExplicitlyDisabledForSite(Site $site, string $providerSlug): bool;

    /**
     * Resolve active storefront-ready providers for a specific currency.
     *
     * @return array<int,array{slug:string,gateway:EcommercePaymentGatewayPlugin}>
     */
    public function enabledGatewaysForStorefront(Site $site, ?string $currency = null): array;

    /**
     * Resolve best matching provider configuration for webhook verification.
     */
    public function resolveGatewayForWebhook(string $providerSlug, array $payload = []): ?PaymentGatewayPlugin;
}
