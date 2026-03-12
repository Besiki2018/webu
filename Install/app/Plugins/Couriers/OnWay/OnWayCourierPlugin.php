<?php

namespace App\Plugins\Couriers\OnWay;

use App\Contracts\CourierPlugin;
use Illuminate\Support\Str;

class OnWayCourierPlugin implements CourierPlugin
{
    /**
     * @param  array<string,mixed>  $config
     */
    public function __construct(
        private array $config = []
    ) {}

    public function getName(): string
    {
        return 'OnWay';
    }

    public function getDescription(): string
    {
        return 'OnWay courier integration (configure merchant credentials, then enable for storefront shipping).';
    }

    public function getType(): string
    {
        return 'courier';
    }

    public function getIcon(): string
    {
        return 'plugins/onway/icon.svg';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'System';
    }

    public function getAuthorUrl(): string
    {
        return '';
    }

    public function isConfigured(): bool
    {
        return $this->nullableString($this->config['merchant_id'] ?? null) !== null
            && $this->nullableString($this->config['api_key'] ?? null) !== null;
    }

    public function validateConfig(array $config): void
    {
        $merchantId = $this->nullableString($config['merchant_id'] ?? null);
        $apiKey = $this->nullableString($config['api_key'] ?? null);

        if ($merchantId === null || $apiKey === null) {
            throw new \Exception('OnWay configuration requires merchant_id and api_key.');
        }

        if (isset($config['fallback_rate']) && (! is_numeric($config['fallback_rate']) || (float) $config['fallback_rate'] < 0)) {
            throw new \Exception('OnWay fallback_rate must be a non-negative number.');
        }
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'sandbox',
                'label' => 'Sandbox Mode',
                'type' => 'toggle',
                'required' => false,
                'help' => 'Use sandbox/test mode if OnWay credentials support it.',
                'default' => true,
            ],
            [
                'name' => 'api_base_url',
                'label' => 'API Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://onway.ge/api',
            ],
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Your OnWay merchant ID',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'OnWay API key',
            ],
            [
                'name' => 'default_service_name',
                'label' => 'Default Service Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'OnWay Delivery',
            ],
            [
                'name' => 'fallback_rate',
                'label' => 'Fallback Rate',
                'type' => 'text',
                'required' => false,
                'placeholder' => '0.00',
                'help' => 'Displayed quote while API rate integration is not configured.',
            ],
            [
                'name' => 'currency',
                'label' => 'Currency',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'GEL',
            ],
            [
                'name' => 'eta_min_days',
                'label' => 'ETA Min Days',
                'type' => 'text',
                'required' => false,
                'placeholder' => '1',
            ],
            [
                'name' => 'eta_max_days',
                'label' => 'ETA Max Days',
                'type' => 'text',
                'required' => false,
                'placeholder' => '2',
            ],
            [
                'name' => 'tracking_base_url',
                'label' => 'Tracking URL Base',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://onway.ge/',
            ],
        ];
    }

    public function getSupportedCountries(): array
    {
        $countries = $this->config['supported_countries'] ?? ['GE'];
        if (! is_array($countries)) {
            return ['GE'];
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($value) => is_string($value) ? strtoupper(trim($value)) : null,
            $countries
        )));

        return $normalized === [] ? ['GE'] : $normalized;
    }

    public function supportsTracking(): bool
    {
        return true;
    }

    public function quote(array $payload = []): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? $this->config['currency'] ?? 'GEL'));
        $amount = $this->moneyFloat($this->config['fallback_rate'] ?? 0);
        $minDays = max(1, $this->intConfig('eta_min_days', 1));
        $maxDays = max($minDays, $this->intConfig('eta_max_days', 2));
        $serviceName = (string) ($this->config['default_service_name'] ?? 'OnWay Delivery');
        $serviceCode = 'onway-standard';

        return [
            'provider' => 'onway',
            'rates' => [
                [
                    'rate_id' => 'onway:'.$serviceCode,
                    'service_code' => $serviceCode,
                    'service_name' => $serviceName,
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                    'estimated_days' => [
                        'min' => $minDays,
                        'max' => $maxDays,
                    ],
                    'metadata' => [
                        'integration' => 'onway',
                        'quote_mode' => 'fallback',
                    ],
                ],
            ],
        ];
    }

    public function createShipment(array $payload = []): array
    {
        $shipmentReference = strtoupper((string) ($payload['shipment_reference'] ?? ('ONW-'.Str::random(10))));
        $trackingNumber = strtoupper((string) ($payload['tracking_number'] ?? ('ONW-TRK-'.Str::random(10))));

        return [
            'provider' => 'onway',
            'shipment_reference' => $shipmentReference,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $this->buildTrackingUrl($trackingNumber),
            'status' => 'created',
            'metadata' => [
                'integration' => 'onway',
                'mode' => 'configured_stub',
                'order_id' => $payload['order_id'] ?? null,
            ],
        ];
    }

    public function track(array $payload = []): array
    {
        $shipmentReference = $this->nullableString($payload['shipment_reference'] ?? null);
        if ($shipmentReference === null) {
            throw new \Exception('shipment_reference is required for tracking.');
        }

        $trackingNumber = $this->nullableString($payload['tracking_number'] ?? null);
        $status = strtolower(trim((string) ($payload['status'] ?? 'in_transit')));
        if ($status === '') {
            $status = 'in_transit';
        }

        return [
            'provider' => 'onway',
            'shipment_reference' => $shipmentReference,
            'tracking_number' => $trackingNumber,
            'status' => $status,
            'updated_at' => now()->toIso8601String(),
            'metadata' => [
                'integration' => 'onway',
                'mode' => 'configured_stub',
            ],
        ];
    }

    public function cancelShipment(array $payload = []): array
    {
        $shipmentReference = $this->nullableString($payload['shipment_reference'] ?? null);
        if ($shipmentReference === null) {
            throw new \Exception('shipment_reference is required for shipment cancellation.');
        }

        return [
            'provider' => 'onway',
            'shipment_reference' => $shipmentReference,
            'status' => 'cancelled',
        ];
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    private function moneyFloat(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, round((float) $value, 2));
    }

    private function buildTrackingUrl(?string $trackingNumber): ?string
    {
        if ($trackingNumber === null || trim($trackingNumber) === '') {
            return null;
        }

        $base = trim((string) ($this->config['tracking_base_url'] ?? ''));
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/').'/'.rawurlencode($trackingNumber);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}

