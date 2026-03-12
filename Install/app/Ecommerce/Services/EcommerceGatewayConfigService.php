<?php

namespace App\Ecommerce\Services;

use App\Contracts\EcommercePaymentGatewayPlugin;
use App\Contracts\PaymentGatewayPlugin;
use App\Ecommerce\Contracts\EcommerceGatewayConfigServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceOrderPayment;
use App\Models\Plugin;
use App\Models\Site;
use App\Models\SitePaymentGatewaySetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EcommerceGatewayConfigService implements EcommerceGatewayConfigServiceContract
{
    public function listForSite(Site $site): array
    {
        $siteSettings = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->get()
            ->keyBy('provider_slug');

        $providers = $this->paymentGatewayPlugins()
            ->map(function (Plugin $plugin) use ($site, $siteSettings): ?array {
                return $this->buildProviderPayload(
                    plugin: $plugin,
                    site: $site,
                    setting: $siteSettings->get($plugin->slug)
                );
            })
            ->filter()
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'providers' => $providers,
        ];
    }

    public function updateForSite(Site $site, string $providerSlug, array $payload = []): array
    {
        $plugin = $this->findPaymentGatewayPlugin($providerSlug);
        if (! $plugin) {
            throw new EcommerceDomainException('Payment provider not found.', 404);
        }

        $globalInstance = $this->instantiateGateway($plugin, $this->pluginConfig($plugin));
        if (! $globalInstance instanceof EcommercePaymentGatewayPlugin) {
            throw new EcommerceDomainException('Selected provider is not available for ecommerce checkout.', 422);
        }

        /** @var SitePaymentGatewaySetting|null $setting */
        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->where('provider_slug', $plugin->slug)
            ->first();

        $availability = $this->normalizeAvailability(
            $payload['availability'] ?? $setting?->availability ?? SitePaymentGatewaySetting::AVAILABILITY_INHERIT
        );

        $schema = $this->normalizedSchema($globalInstance->getConfigSchema());
        $existingSiteConfig = $this->siteConfig($setting?->config ?? null);
        $incomingConfig = $this->siteConfig($payload['config'] ?? null);
        $nextSiteConfig = $this->applyConfigPatch($existingSiteConfig, $incomingConfig, $schema);

        $resolvedConfig = $this->mergeGatewayConfig($this->pluginConfig($plugin), $nextSiteConfig);
        $resolvedInstance = $this->instantiateGateway($plugin, $resolvedConfig);

        if (! $resolvedInstance instanceof EcommercePaymentGatewayPlugin) {
            throw new EcommerceDomainException('Selected provider is not available for ecommerce checkout.', 422);
        }

        if ($availability === SitePaymentGatewaySetting::AVAILABILITY_ENABLED) {
            if (! $resolvedInstance->isConfigured()) {
                throw new EcommerceDomainException(
                    'Provider configuration is incomplete. Add required credentials or switch availability to inherit/disabled.',
                    422
                );
            }

            try {
                $resolvedInstance->validateConfig($resolvedConfig);
            } catch (\Throwable $exception) {
                throw new EcommerceDomainException(
                    'Provider configuration validation failed: '.$exception->getMessage(),
                    422
                );
            }
        }

        if (
            $availability === SitePaymentGatewaySetting::AVAILABILITY_INHERIT
            && $nextSiteConfig === []
        ) {
            if ($setting) {
                $setting->delete();
            }
            $setting = null;
        } else {
            $setting = SitePaymentGatewaySetting::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'provider_slug' => $plugin->slug,
                ],
                [
                    'availability' => $availability,
                    'config' => $nextSiteConfig,
                    'updated_by' => Auth::id(),
                ]
            );
        }

        return [
            'site_id' => $site->id,
            'provider' => $this->buildProviderPayload(
                plugin: $plugin,
                site: $site,
                setting: $setting
            ),
        ];
    }

    public function resolveGatewayForStorefront(Site $site, string $providerSlug, bool $requireEnabled = true): ?EcommercePaymentGatewayPlugin
    {
        $plugin = $this->findPaymentGatewayPlugin($providerSlug);
        if (! $plugin) {
            return null;
        }

        /** @var SitePaymentGatewaySetting|null $setting */
        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->where('provider_slug', $plugin->slug)
            ->first();

        $state = $this->resolveGatewayState($plugin, $setting);
        if (! $state) {
            return null;
        }

        $gateway = $state['resolved_instance'];
        if (! $gateway instanceof EcommercePaymentGatewayPlugin) {
            return null;
        }

        if ($requireEnabled && ! $state['is_enabled']) {
            return null;
        }

        return $gateway;
    }

    public function isProviderEnabledForSite(Site $site, string $providerSlug): bool
    {
        $plugin = $this->findPaymentGatewayPlugin($providerSlug);
        if (! $plugin) {
            return false;
        }

        /** @var SitePaymentGatewaySetting|null $setting */
        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->where('provider_slug', $plugin->slug)
            ->first();

        $state = $this->resolveGatewayState($plugin, $setting);

        return (bool) ($state['is_enabled'] ?? false);
    }

    public function isProviderExplicitlyDisabledForSite(Site $site, string $providerSlug): bool
    {
        /** @var SitePaymentGatewaySetting|null $setting */
        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->where('provider_slug', $providerSlug)
            ->first();

        if (! $setting) {
            return false;
        }

        return $this->normalizeAvailability($setting->availability) === SitePaymentGatewaySetting::AVAILABILITY_DISABLED;
    }

    public function enabledGatewaysForStorefront(Site $site, ?string $currency = null): array
    {
        $targetCurrency = strtoupper(trim((string) ($currency ?? 'GEL')));
        if ($targetCurrency === '') {
            $targetCurrency = 'GEL';
        }

        $siteSettings = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->get()
            ->keyBy('provider_slug');

        $providers = [];

        foreach ($this->paymentGatewayPlugins() as $plugin) {
            $state = $this->resolveGatewayState($plugin, $siteSettings->get($plugin->slug));
            if (! $state || ! $state['is_enabled']) {
                continue;
            }

            $gateway = $state['resolved_instance'];
            if (! $gateway instanceof EcommercePaymentGatewayPlugin) {
                continue;
            }

            $supportedCurrencies = array_map(
                static fn ($code): string => strtoupper((string) $code),
                $gateway->getSupportedCurrencies()
            );

            if ($supportedCurrencies !== [] && ! in_array($targetCurrency, $supportedCurrencies, true)) {
                continue;
            }

            $providers[] = [
                'slug' => $plugin->slug,
                'gateway' => $gateway,
            ];
        }

        return $providers;
    }

    public function resolveGatewayForWebhook(string $providerSlug, array $payload = []): ?PaymentGatewayPlugin
    {
        $plugin = $this->findPaymentGatewayPlugin($providerSlug);
        if (! $plugin) {
            return null;
        }

        $references = $this->extractWebhookReferences($payload);
        if ($references === []) {
            return $this->instantiateGateway($plugin, $this->pluginConfig($plugin));
        }

        /** @var EcommerceOrderPayment|null $payment */
        $payment = EcommerceOrderPayment::query()
            ->where('provider', $plugin->slug)
            ->whereIn('transaction_reference', $references)
            ->with('site:id')
            ->latest('id')
            ->first();

        if (! $payment || ! $payment->site) {
            return $this->instantiateGateway($plugin, $this->pluginConfig($plugin));
        }

        /** @var SitePaymentGatewaySetting|null $setting */
        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $payment->site_id)
            ->where('provider_slug', $plugin->slug)
            ->first();

        $state = $this->resolveGatewayState($plugin, $setting);
        if (! $state || ! ($state['resolved_instance'] instanceof PaymentGatewayPlugin)) {
            return $this->instantiateGateway($plugin, $this->pluginConfig($plugin));
        }

        return $state['resolved_instance'];
    }

    /**
     * @return Collection<int, Plugin>
     */
    private function paymentGatewayPlugins(): Collection
    {
        return Plugin::query()
            ->active()
            ->byType('payment_gateway')
            ->orderBy('name')
            ->get();
    }

    private function findPaymentGatewayPlugin(string $providerSlug): ?Plugin
    {
        return Plugin::query()
            ->active()
            ->byType('payment_gateway')
            ->where('slug', $providerSlug)
            ->first();
    }

    /**
     * @param  array<string,mixed>  $existing
     * @param  array<string,mixed>  $patch
     * @param  array<string,array<string,mixed>>  $schema
     * @return array<string,mixed>
     */
    private function applyConfigPatch(array $existing, array $patch, array $schema): array
    {
        $next = $existing;

        foreach ($schema as $fieldName => $field) {
            if (! array_key_exists($fieldName, $patch)) {
                continue;
            }

            $type = strtolower((string) ($field['type'] ?? 'text'));
            $value = $patch[$fieldName];

            if ($type === 'toggle') {
                $next[$fieldName] = (bool) $value;
                continue;
            }

            if ($value === null) {
                unset($next[$fieldName]);
                continue;
            }

            if (! is_scalar($value)) {
                unset($next[$fieldName]);
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                unset($next[$fieldName]);
                continue;
            }

            $next[$fieldName] = $normalized;
        }

        return $next;
    }

    /**
     * @param  array<int,array<string,mixed>>  $schema
     * @return array<string,array<string,mixed>>
     */
    private function normalizedSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalized[$name] = $field;
        }

        return $normalized;
    }

    /**
     * @return array{availability:string,is_enabled:bool,is_configured:bool,admin_default_configured:bool,site_config:array<string,mixed>,resolved_config:array<string,mixed>,resolved_instance:PaymentGatewayPlugin|null,is_ecommerce:bool,supports_installment:bool,mode:string|null}
     */
    private function resolveGatewayState(Plugin $plugin, ?SitePaymentGatewaySetting $setting): ?array
    {
        $globalConfig = $this->pluginConfig($plugin);
        $globalInstance = $this->instantiateGateway($plugin, $globalConfig);

        if (! $globalInstance instanceof PaymentGatewayPlugin) {
            return null;
        }

        $siteConfig = $this->siteConfig($setting?->config ?? null);
        $resolvedConfig = $this->mergeGatewayConfig($globalConfig, $siteConfig);
        $resolvedInstance = $this->instantiateGateway($plugin, $resolvedConfig);

        if (! $resolvedInstance instanceof PaymentGatewayPlugin) {
            return null;
        }

        $availability = $this->normalizeAvailability($setting?->availability ?? SitePaymentGatewaySetting::AVAILABILITY_INHERIT);
        $configured = $resolvedInstance->isConfigured();
        $isEnabled = $availability !== SitePaymentGatewaySetting::AVAILABILITY_DISABLED && $configured;
        $isEcommerce = $resolvedInstance instanceof EcommercePaymentGatewayPlugin;

        $mode = null;
        if (array_key_exists('sandbox', $resolvedConfig)) {
            $mode = (bool) $resolvedConfig['sandbox'] ? 'sandbox' : 'live';
        }

        return [
            'availability' => $availability,
            'is_enabled' => $isEnabled,
            'is_configured' => $configured,
            'admin_default_configured' => $globalInstance->isConfigured(),
            'site_config' => $siteConfig,
            'resolved_config' => $resolvedConfig,
            'resolved_instance' => $resolvedInstance,
            'is_ecommerce' => $isEcommerce,
            'supports_installment' => $isEcommerce ? $resolvedInstance->supportsInstallments() : false,
            'mode' => $mode,
        ];
    }

    private function buildProviderPayload(Plugin $plugin, Site $site, ?SitePaymentGatewaySetting $setting): ?array
    {
        $state = $this->resolveGatewayState($plugin, $setting);
        if (! $state) {
            return null;
        }

        if (! $state['is_ecommerce']) {
            return null;
        }

        $gateway = $state['resolved_instance'];
        if (! $gateway instanceof PaymentGatewayPlugin) {
            return null;
        }

        return [
            'slug' => $plugin->slug,
            'name' => $gateway->getName(),
            'description' => $gateway->getDescription(),
            'icon' => $gateway->getIcon(),
            'availability' => $state['availability'],
            'is_active' => $plugin->isActive(),
            'is_enabled' => $state['is_enabled'],
            'is_configured' => $state['is_configured'],
            'admin_default_configured' => $state['admin_default_configured'],
            'supports_installment' => $state['supports_installment'],
            'mode' => $state['mode'],
            'config_schema' => $gateway->getConfigSchema(),
            'site_config' => $state['site_config'],
            'callbacks' => [
                'webhook_url' => route('payment.webhook', ['plugin' => $plugin->slug]),
                'callback_url' => url('/payment-gateways/callback?gateway='.$plugin->slug),
            ],
            'updated_at' => $setting?->updated_at?->toISOString(),
            'updated_by' => $setting?->updated_by,
            'site_id' => $site->id,
        ];
    }

    private function instantiateGateway(Plugin $plugin, array $config): ?PaymentGatewayPlugin
    {
        $class = $plugin->class;

        if (! class_exists($class)) {
            return null;
        }

        try {
            $instance = new $class($config);
        } catch (\Throwable) {
            return null;
        }

        return $instance instanceof PaymentGatewayPlugin ? $instance : null;
    }

    /**
     * @param  array<string,mixed>  $global
     * @param  array<string,mixed>  $site
     * @return array<string,mixed>
     */
    private function mergeGatewayConfig(array $global, array $site): array
    {
        return array_replace($global, $site);
    }

    /**
     * @param  mixed  $config
     * @return array<string,mixed>
     */
    private function siteConfig(mixed $config): array
    {
        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function pluginConfig(Plugin $plugin): array
    {
        return is_array($plugin->config) ? $plugin->config : [];
    }

    private function normalizeAvailability(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            SitePaymentGatewaySetting::AVAILABILITY_ENABLED => SitePaymentGatewaySetting::AVAILABILITY_ENABLED,
            SitePaymentGatewaySetting::AVAILABILITY_DISABLED => SitePaymentGatewaySetting::AVAILABILITY_DISABLED,
            default => SitePaymentGatewaySetting::AVAILABILITY_INHERIT,
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<int,string>
     */
    private function extractWebhookReferences(array $payload): array
    {
        $paths = [
            'ecommerce.transaction_reference',
            'transaction_reference',
            'body.order_id',
            'body.external_order_id',
            'body.payment_detail.transaction_id',
            'order_id',
            'payment_id',
            'order_hash',
            'id',
        ];

        $values = [];

        foreach ($paths as $path) {
            $candidate = data_get($payload, $path);
            if (! is_scalar($candidate)) {
                continue;
            }

            $normalized = trim((string) $candidate);
            if ($normalized === '') {
                continue;
            }

            $values[] = $normalized;
        }

        return array_values(array_unique($values));
    }
}
