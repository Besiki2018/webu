<?php

namespace App\Services\Assets;

class StockImageProviderConfig
{
    /**
     * @var array<string, array<string, string>>
     */
    private const REQUIRED_KEYS = [
        'unsplash' => [
            'access_key' => 'services.unsplash.access_key',
            'secret_key' => 'services.unsplash.secret_key',
        ],
        'pexels' => [
            'key' => 'services.pexels.key',
        ],
        'freepik' => [
            'key' => 'services.freepik.key',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public function requireProvider(string $provider): array
    {
        $normalized = strtolower(trim($provider));
        $required = self::REQUIRED_KEYS[$normalized] ?? null;

        if ($required === null) {
            throw new StockImageProviderConfigurationException(sprintf('Unsupported stock image provider [%s].', $provider));
        }

        $resolved = [];

        foreach ($required as $key => $configPath) {
            $value = trim((string) config($configPath));
            if ($value === '') {
                throw StockImageProviderConfigurationException::missing($normalized, $configPath);
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    public function requireValue(string $provider, string $key): string
    {
        $config = $this->requireProvider($provider);

        if (! array_key_exists($key, $config)) {
            throw new StockImageProviderConfigurationException(sprintf(
                'Stock image provider [%s] does not define required key [%s].',
                $provider,
                $key
            ));
        }

        return $config[$key];
    }
}
