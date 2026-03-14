<?php

namespace Tests\Unit\Assets;

use App\Services\Assets\StockImageProviderConfig;
use App\Services\Assets\StockImageProviderConfigurationException;
use Tests\TestCase;

class StockImageProviderConfigTest extends TestCase
{
    public function test_it_reads_configured_provider_keys_from_services_config(): void
    {
        config()->set('services.unsplash.access_key', 'unsplash-access');
        config()->set('services.unsplash.secret_key', 'unsplash-secret');
        config()->set('services.pexels.key', 'pexels-key');
        config()->set('services.freepik.key', 'freepik-key');

        $config = app(StockImageProviderConfig::class);

        $this->assertSame('unsplash-access', config('services.unsplash.access_key'));
        $this->assertSame('pexels-key', config('services.pexels.key'));
        $this->assertSame('freepik-key', config('services.freepik.key'));
        $this->assertSame('unsplash-access', $config->requireValue('unsplash', 'access_key'));
        $this->assertSame('unsplash-secret', $config->requireValue('unsplash', 'secret_key'));
        $this->assertSame('pexels-key', $config->requireValue('pexels', 'key'));
        $this->assertSame('freepik-key', $config->requireValue('freepik', 'key'));
    }

    public function test_it_throws_a_clear_error_when_unsplash_access_key_is_missing(): void
    {
        config()->set('services.unsplash.access_key', null);
        config()->set('services.unsplash.secret_key', 'unsplash-secret');

        $this->expectException(StockImageProviderConfigurationException::class);
        $this->expectExceptionMessage('Unsplash API key not configured');

        app(StockImageProviderConfig::class)->requireValue('unsplash', 'access_key');
    }

    public function test_it_throws_a_clear_error_when_pexels_key_is_missing(): void
    {
        config()->set('services.pexels.key', null);

        $this->expectException(StockImageProviderConfigurationException::class);
        $this->expectExceptionMessage('Pexels API key not configured');

        app(StockImageProviderConfig::class)->requireValue('pexels', 'key');
    }

    public function test_it_throws_a_clear_error_when_freepik_key_is_missing(): void
    {
        config()->set('services.freepik.key', null);

        $this->expectException(StockImageProviderConfigurationException::class);
        $this->expectExceptionMessage('Freepik API key not configured');

        app(StockImageProviderConfig::class)->requireValue('freepik', 'key');
    }
}
