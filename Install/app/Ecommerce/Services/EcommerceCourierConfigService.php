<?php

namespace App\Ecommerce\Services;

use App\Contracts\CourierPlugin;
use App\Ecommerce\Contracts\EcommerceCourierConfigServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\Plugin;
use App\Models\Site;
use App\Models\SiteCourierSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EcommerceCourierConfigService implements EcommerceCourierConfigServiceContract
{
    public function listForSite(Site $site): array
    {
        $siteSettings = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->get()
            ->keyBy('courier_slug');

        $couriers = $this->courierPlugins()
            ->map(function (Plugin $plugin) use ($site, $siteSettings): ?array {
                return $this->buildCourierPayload(
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
            'couriers' => $couriers,
        ];
    }

    public function updateForSite(Site $site, string $courierSlug, array $payload = []): array
    {
        $plugin = $this->findCourierPlugin($courierSlug);
        if (! $plugin) {
            throw new EcommerceDomainException('Courier provider not found.', 404);
        }

        $globalInstance = $this->instantiateCourier($plugin, $this->pluginConfig($plugin));
        if (! $globalInstance instanceof CourierPlugin) {
            throw new EcommerceDomainException('Selected courier is not available.', 422);
        }

        /** @var SiteCourierSetting|null $setting */
        $setting = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->where('courier_slug', $plugin->slug)
            ->first();

        $availability = $this->normalizeAvailability(
            $payload['availability'] ?? $setting?->availability ?? SiteCourierSetting::AVAILABILITY_INHERIT
        );

        $schema = $this->normalizedSchema($globalInstance->getConfigSchema());
        $existingSiteConfig = $this->siteConfig($setting?->config ?? null);
        $incomingConfig = $this->siteConfig($payload['config'] ?? null);
        $nextSiteConfig = $this->applyConfigPatch($existingSiteConfig, $incomingConfig, $schema);

        $resolvedConfig = $this->mergeCourierConfig($this->pluginConfig($plugin), $nextSiteConfig);
        $resolvedInstance = $this->instantiateCourier($plugin, $resolvedConfig);

        if (! $resolvedInstance instanceof CourierPlugin) {
            throw new EcommerceDomainException('Selected courier is not available.', 422);
        }

        if ($availability === SiteCourierSetting::AVAILABILITY_ENABLED) {
            if (! $resolvedInstance->isConfigured()) {
                throw new EcommerceDomainException(
                    'Courier configuration is incomplete. Add required fields or switch availability to inherit/disabled.',
                    422
                );
            }

            try {
                $resolvedInstance->validateConfig($resolvedConfig);
            } catch (\Throwable $exception) {
                throw new EcommerceDomainException(
                    'Courier configuration validation failed: '.$exception->getMessage(),
                    422
                );
            }
        }

        if (
            $availability === SiteCourierSetting::AVAILABILITY_INHERIT
            && $nextSiteConfig === []
        ) {
            if ($setting) {
                $setting->delete();
            }
            $setting = null;
        } else {
            $setting = SiteCourierSetting::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'courier_slug' => $plugin->slug,
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
            'courier' => $this->buildCourierPayload(
                plugin: $plugin,
                site: $site,
                setting: $setting
            ),
        ];
    }

    public function enabledCouriersForStorefront(Site $site): array
    {
        $siteSettings = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->get()
            ->keyBy('courier_slug');

        $providers = [];

        foreach ($this->courierPlugins() as $plugin) {
            $state = $this->resolveCourierState($plugin, $siteSettings->get($plugin->slug));
            if (! $state || ! ($state['is_enabled'] ?? false)) {
                continue;
            }

            $courier = $state['resolved_instance'] ?? null;
            if (! $courier instanceof CourierPlugin) {
                continue;
            }

            $providers[] = [
                'slug' => $plugin->slug,
                'courier' => $courier,
            ];
        }

        return $providers;
    }

    public function resolveCourierForSite(Site $site, string $courierSlug, bool $requireEnabled = true): ?CourierPlugin
    {
        $plugin = $this->findCourierPlugin($courierSlug);
        if (! $plugin) {
            return null;
        }

        /** @var SiteCourierSetting|null $setting */
        $setting = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->where('courier_slug', $plugin->slug)
            ->first();

        $state = $this->resolveCourierState($plugin, $setting);
        if (! $state) {
            return null;
        }

        $courier = $state['resolved_instance'] ?? null;
        if (! $courier instanceof CourierPlugin) {
            return null;
        }

        if ($requireEnabled && ! ($state['is_enabled'] ?? false)) {
            return null;
        }

        return $courier;
    }

    /**
     * @return Collection<int, Plugin>
     */
    private function courierPlugins(): Collection
    {
        return Plugin::query()
            ->active()
            ->byType('courier')
            ->orderBy('name')
            ->get();
    }

    private function findCourierPlugin(string $courierSlug): ?Plugin
    {
        return Plugin::query()
            ->active()
            ->byType('courier')
            ->where('slug', $courierSlug)
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
     * @return array{
     *   availability:string,
     *   is_enabled:bool,
     *   is_configured:bool,
     *   admin_default_configured:bool,
     *   site_config:array<string,mixed>,
     *   resolved_config:array<string,mixed>,
     *   resolved_instance:CourierPlugin|null,
     *   supports_tracking:bool,
     *   supported_countries:array<int,string>,
     *   mode:string|null
     * }|null
     */
    private function resolveCourierState(Plugin $plugin, ?SiteCourierSetting $setting): ?array
    {
        $globalConfig = $this->pluginConfig($plugin);
        $globalInstance = $this->instantiateCourier($plugin, $globalConfig);
        if (! $globalInstance instanceof CourierPlugin) {
            return null;
        }

        $siteConfig = $this->siteConfig($setting?->config ?? null);
        $resolvedConfig = $this->mergeCourierConfig($globalConfig, $siteConfig);
        $resolvedInstance = $this->instantiateCourier($plugin, $resolvedConfig);
        if (! $resolvedInstance instanceof CourierPlugin) {
            return null;
        }

        $availability = $this->normalizeAvailability($setting?->availability ?? SiteCourierSetting::AVAILABILITY_INHERIT);
        $configured = $resolvedInstance->isConfigured();
        $isEnabled = $availability !== SiteCourierSetting::AVAILABILITY_DISABLED && $configured;

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
            'supports_tracking' => $resolvedInstance->supportsTracking(),
            'supported_countries' => $resolvedInstance->getSupportedCountries(),
            'mode' => $mode,
        ];
    }

    private function buildCourierPayload(Plugin $plugin, Site $site, ?SiteCourierSetting $setting): ?array
    {
        $state = $this->resolveCourierState($plugin, $setting);
        if (! $state) {
            return null;
        }

        $courier = $state['resolved_instance'];
        if (! $courier instanceof CourierPlugin) {
            return null;
        }

        return [
            'slug' => $plugin->slug,
            'name' => $courier->getName(),
            'description' => $courier->getDescription(),
            'icon' => $courier->getIcon(),
            'availability' => $state['availability'],
            'is_active' => $plugin->isActive(),
            'is_enabled' => $state['is_enabled'],
            'is_configured' => $state['is_configured'],
            'admin_default_configured' => $state['admin_default_configured'],
            'supports_tracking' => $state['supports_tracking'],
            'supported_countries' => $state['supported_countries'],
            'mode' => $state['mode'],
            'config_schema' => $courier->getConfigSchema(),
            'site_config' => $state['site_config'],
            'updated_at' => $setting?->updated_at?->toISOString(),
            'updated_by' => $setting?->updated_by,
            'site_id' => $site->id,
        ];
    }

    private function instantiateCourier(Plugin $plugin, array $config): ?CourierPlugin
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

        return $instance instanceof CourierPlugin ? $instance : null;
    }

    /**
     * @param  array<string,mixed>  $global
     * @param  array<string,mixed>  $site
     * @return array<string,mixed>
     */
    private function mergeCourierConfig(array $global, array $site): array
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
            SiteCourierSetting::AVAILABILITY_ENABLED => SiteCourierSetting::AVAILABILITY_ENABLED,
            SiteCourierSetting::AVAILABILITY_DISABLED => SiteCourierSetting::AVAILABILITY_DISABLED,
            default => SiteCourierSetting::AVAILABILITY_INHERIT,
        };
    }
}
