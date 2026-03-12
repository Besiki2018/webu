<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class DomainSettingService
{
    /**
     * Cache key for domain settings.
     */
    protected const CACHE_KEY = 'domain_settings';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Check if subdomain publishing is enabled globally.
     */
    public function isSubdomainsEnabled(): bool
    {
        return (bool) $this->getSetting('domain_enable_subdomains', false);
    }

    /**
     * Check if custom domain publishing is enabled globally.
     */
    public function isCustomDomainsEnabled(): bool
    {
        return (bool) $this->getSetting('domain_enable_custom_domains', false);
    }

    /**
     * Get the base domain for subdomain publishing.
     */
    public function getBaseDomain(): ?string
    {
        $domain = $this->getSetting('domain_base_domain', '');

        return ! empty($domain) ? $domain : null;
    }

    /**
     * Get the SSL provider configuration.
     * Hardcoded to always use Let's Encrypt - no longer configurable.
     */
    public function getSslProvider(): string
    {
        return 'letsencrypt';
    }

    /**
     * Get the domain verification method.
     * Hardcoded to always use CNAME - no longer configurable.
     */
    public function getVerificationMethod(): string
    {
        return 'dns_cname';
    }

    /**
     * Check if Let's Encrypt SSL is configured.
     * Always returns true - Let's Encrypt is the only supported option.
     */
    public function usesLetsEncrypt(): bool
    {
        return true;
    }

    /**
     * Check if CNAME verification is used.
     * Always returns true - CNAME is the only supported option.
     */
    public function usesCnameVerification(): bool
    {
        return true;
    }

    /**
     * Get all domain settings as an array.
     */
    public function getAllSettings(): array
    {
        return [
            'enable_subdomains' => $this->isSubdomainsEnabled(),
            'enable_custom_domains' => $this->isCustomDomainsEnabled(),
            'base_domain' => $this->getBaseDomain(),
        ];
    }

    /**
     * Get a single setting value with caching.
     */
    protected function getSetting(string $key, mixed $default = null): mixed
    {
        return SystemSetting::get($key, $default);
    }

    /**
     * Clear the domain settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
