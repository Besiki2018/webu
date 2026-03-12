<?php

namespace App\Plugins\Couriers\ManualCourier;

use App\Contracts\CourierPlugin;
use Illuminate\Support\Str;

class ManualCourierPlugin implements CourierPlugin
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    public function getName(): string
    {
        return 'Manual Courier';
    }

    public function getDescription(): string
    {
        return 'Base courier plugin with flat-rate quotes and manual shipment lifecycle.';
    }

    public function getType(): string
    {
        return 'courier';
    }

    public function getIcon(): string
    {
        return 'plugins/manual-courier/icon.svg';
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
        return true;
    }

    public function validateConfig(array $config): void
    {
        if (isset($config['base_rate']) && (! is_numeric($config['base_rate']) || (float) $config['base_rate'] < 0)) {
            throw new \Exception('Manual courier base_rate must be a non-negative number.');
        }

        if (isset($config['per_item_rate']) && (! is_numeric($config['per_item_rate']) || (float) $config['per_item_rate'] < 0)) {
            throw new \Exception('Manual courier per_item_rate must be a non-negative number.');
        }

        if (isset($config['free_shipping_over']) && (! is_numeric($config['free_shipping_over']) || (float) $config['free_shipping_over'] < 0)) {
            throw new \Exception('Manual courier free_shipping_over must be a non-negative number.');
        }
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'service_name',
                'label' => 'Service Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Standard Delivery',
                'help' => 'Label shown during checkout.',
            ],
            [
                'name' => 'base_rate',
                'label' => 'Base Rate',
                'type' => 'text',
                'required' => false,
                'placeholder' => '7.00',
                'help' => 'Flat base shipping amount.',
            ],
            [
                'name' => 'per_item_rate',
                'label' => 'Per Item Rate',
                'type' => 'text',
                'required' => false,
                'placeholder' => '0.00',
                'help' => 'Optional amount added per item.',
            ],
            [
                'name' => 'free_shipping_over',
                'label' => 'Free Shipping Over',
                'type' => 'text',
                'required' => false,
                'placeholder' => '100.00',
                'help' => 'Order subtotal threshold for free shipping.',
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
                'placeholder' => '3',
            ],
            [
                'name' => 'tracking_base_url',
                'label' => 'Tracking Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://example.com/tracking/',
            ],
        ];
    }

    public function getSupportedCountries(): array
    {
        $countries = $this->config['supported_countries'] ?? [];

        if (! is_array($countries)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($code) => is_string($code) ? strtoupper(trim($code)) : null,
            $countries
        )));
    }

    public function supportsTracking(): bool
    {
        return true;
    }

    public function quote(array $payload = []): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? $this->config['currency'] ?? 'GEL'));
        $subtotal = $this->moneyFloat($payload['subtotal'] ?? null);
        $itemsCount = $this->resolveItemsCount($payload);
        $baseRate = $this->moneyFloat($this->config['base_rate'] ?? 7);
        $perItemRate = $this->moneyFloat($this->config['per_item_rate'] ?? 0);
        $freeShippingOver = $this->moneyFloat($this->config['free_shipping_over'] ?? null);

        $amount = $baseRate + ($itemsCount * $perItemRate);
        if ($freeShippingOver > 0 && $subtotal >= $freeShippingOver) {
            $amount = 0.0;
        }

        $minDays = max(1, $this->intConfig('eta_min_days', 1));
        $maxDays = max($minDays, $this->intConfig('eta_max_days', 3));
        $serviceCode = 'manual-standard';
        $rateId = 'manual-courier:'.$serviceCode;

        return [
            'provider' => 'manual-courier',
            'rates' => [
                [
                    'rate_id' => $rateId,
                    'service_code' => $serviceCode,
                    'service_name' => (string) ($this->config['service_name'] ?? 'Standard Delivery'),
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                    'estimated_days' => [
                        'min' => $minDays,
                        'max' => $maxDays,
                    ],
                    'metadata' => [
                        'items_count' => $itemsCount,
                        'free_shipping_applied' => $amount <= 0.0,
                    ],
                ],
            ],
        ];
    }

    public function createShipment(array $payload = []): array
    {
        $shipmentReference = strtoupper((string) ($payload['shipment_reference'] ?? ('MAN-'.Str::random(10))));
        $trackingNumber = strtoupper((string) ($payload['tracking_number'] ?? ('TRK-'.Str::random(12))));
        $trackingUrl = $this->buildTrackingUrl($trackingNumber);

        return [
            'provider' => 'manual-courier',
            'shipment_reference' => $shipmentReference,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'status' => 'created',
            'metadata' => [
                'mode' => 'manual',
                'order_id' => $payload['order_id'] ?? null,
            ],
        ];
    }

    public function track(array $payload = []): array
    {
        $shipmentReference = (string) ($payload['shipment_reference'] ?? '');
        if ($shipmentReference === '') {
            throw new \Exception('shipment_reference is required for tracking.');
        }

        $trackingNumber = isset($payload['tracking_number']) ? (string) $payload['tracking_number'] : null;
        $status = strtolower(trim((string) ($payload['status'] ?? 'in_transit')));
        if ($status === '') {
            $status = 'in_transit';
        }

        return [
            'provider' => 'manual-courier',
            'shipment_reference' => $shipmentReference,
            'tracking_number' => $trackingNumber,
            'status' => $status,
            'updated_at' => now()->toIso8601String(),
            'metadata' => [
                'mode' => 'manual',
            ],
        ];
    }

    public function cancelShipment(array $payload = []): array
    {
        $shipmentReference = (string) ($payload['shipment_reference'] ?? '');
        if ($shipmentReference === '') {
            throw new \Exception('shipment_reference is required for shipment cancellation.');
        }

        return [
            'provider' => 'manual-courier',
            'shipment_reference' => $shipmentReference,
            'status' => 'cancelled',
        ];
    }

    private function resolveItemsCount(array $payload): int
    {
        if (isset($payload['items_count']) && is_numeric($payload['items_count'])) {
            return max(0, (int) $payload['items_count']);
        }

        $items = $payload['items'] ?? [];
        if (! is_array($items)) {
            return 0;
        }

        $count = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $count += max(1, (int) $quantity);
        }

        return max(0, $count);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        if (! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private function moneyFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, round((float) $value, 2));
    }

    private function buildTrackingUrl(string $trackingNumber): ?string
    {
        $baseUrl = trim((string) ($this->config['tracking_base_url'] ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.rawurlencode($trackingNumber);
    }
}
