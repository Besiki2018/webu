<?php

namespace App\Services\Assets;

use RuntimeException;

class StockImageProviderConfigurationException extends RuntimeException
{
    public static function missing(string $provider, string $label): self
    {
        return new self(sprintf('%s API key not configured (%s).', ucfirst($provider), $label));
    }
}
