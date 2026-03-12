<?php

namespace App\Services;

use App\Models\BookingPayment;
use App\Models\EcommerceOrderPayment;
use App\Models\Site;
use App\Models\SitePaymentGatewaySetting;

class UniversalPaymentsAbstractionService
{
    public const SCHEMA_NAME = 'universal_payments_abstraction';

    public const SCHEMA_VERSION = 1;

    /**
     * Build a canonical provider options contract over domain-specific provider rows.
     *
     * @param  array<int, array<string, mixed>>  $providers
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function providerOptionsContract(Site $site, string $domain, string $currency, array $providers, array $options = []): array
    {
        $flow = $this->normalizeIdentifier((string) ($options['flow'] ?? 'checkout')) ?: 'checkout';
        $includeGatewaySettings = (bool) ($options['include_gateway_settings'] ?? true);

        $normalizedProviders = collect($providers)
            ->filter(fn ($provider): bool => is_array($provider) && is_string($provider['slug'] ?? null))
            ->map(function (array $provider) use ($domain, $flow): array {
                $slug = strtolower(trim((string) ($provider['slug'] ?? '')));
                $modes = collect(is_array($provider['modes'] ?? null) ? $provider['modes'] : [])
                    ->map(fn ($mode): string => $this->normalizeIdentifier((string) $mode))
                    ->filter()
                    ->values()
                    ->all();
                if ($modes === []) {
                    $modes = ['full'];
                }

                $supportsInstallment = (bool) ($provider['supports_installment'] ?? in_array('installment', $modes, true));
                if ($supportsInstallment && ! in_array('installment', $modes, true)) {
                    $modes[] = 'installment';
                }
                $modes = array_values(array_unique($modes));

                return [
                    ...$provider,
                    'slug' => $slug,
                    'name' => trim((string) ($provider['name'] ?? ucfirst($slug))),
                    'description' => trim((string) ($provider['description'] ?? '')),
                    'supports_installment' => $supportsInstallment,
                    'modes' => $modes,
                    'universal_provider' => [
                        'provider_key' => $slug,
                        'domain' => $domain,
                        'flow' => $flow,
                        'availability' => 'enabled',
                        'capabilities' => [
                            'full_payment' => in_array('full', $modes, true) || $modes !== [],
                            'installment' => $supportsInstallment,
                        ],
                    ],
                ];
            })
            ->sortBy(function (array $provider): string {
                return (($provider['slug'] ?? null) === 'manual' ? '0' : '1').'|'.(string) ($provider['slug'] ?? '');
            })
            ->values()
            ->all();

        return [
            'schema' => $this->schema(task: 'P5-F2-04'),
            'site' => [
                'id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
            ],
            'context' => [
                'domain' => $this->normalizeDomain($domain),
                'flow' => $flow,
            ],
            'currency' => strtoupper(trim($currency)) ?: 'GEL',
            'providers' => $normalizedProviders,
            'provider_count' => count($normalizedProviders),
            'gateway_settings' => $includeGatewaySettings ? $this->gatewaySettingsSnapshot($site, ['include_config' => false])['gateways'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function gatewaySettingsSnapshot(Site $site, array $options = []): array
    {
        $includeConfig = (bool) ($options['include_config'] ?? false);

        $site = $site->fresh() ?? $site;
        $site->loadMissing('paymentGatewaySettings');

        $gateways = $site->paymentGatewaySettings
            ->sortBy(fn (SitePaymentGatewaySetting $setting): string => sprintf('%s|%020d', (string) $setting->provider_slug, (int) $setting->id))
            ->values()
            ->map(fn (SitePaymentGatewaySetting $setting): array => $this->normalizeGatewaySetting($setting, $includeConfig))
            ->all();

        return [
            'schema' => $this->schema(task: 'P5-F2-04'),
            'site' => [
                'id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
            ],
            'gateways' => $gateways,
            'counts' => [
                'gateways' => count($gateways),
                'enabled' => count(array_filter($gateways, fn (array $row): bool => ($row['availability'] ?? null) === SitePaymentGatewaySetting::AVAILABILITY_ENABLED)),
                'disabled' => count(array_filter($gateways, fn (array $row): bool => ($row['availability'] ?? null) === SitePaymentGatewaySetting::AVAILABILITY_DISABLED)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function normalizeEcommercePayment(EcommerceOrderPayment $payment, array $options = []): array
    {
        return $this->normalizePaymentRecord(
            domain: 'ecommerce',
            sourceTable: 'ecommerce_order_payments',
            sourceId: $payment->id,
            provider: (string) ($payment->provider ?? ''),
            status: (string) ($payment->status ?? ''),
            method: $payment->method,
            transactionReference: $payment->transaction_reference,
            amount: $this->moneyString($payment->amount),
            currency: (string) ($payment->currency ?? 'GEL'),
            flags: [
                'is_installment' => (bool) ($payment->is_installment ?? false),
                'is_prepayment' => false,
            ],
            processedAt: $payment->processed_at,
            createdAt: $payment->created_at,
            updatedAt: $payment->updated_at,
            rawPayload: is_array($payment->raw_payload_json ?? null) ? $payment->raw_payload_json : [],
            meta: [
                'domain_resource' => [
                    'order_id' => (int) ($payment->order_id ?? 0),
                    'order_number' => (string) ($payment->order?->order_number ?? ''),
                    'payment_status' => (string) ($payment->order?->payment_status ?? ''),
                ],
                'installment_plan_json' => is_array($payment->installment_plan_json ?? null) ? $payment->installment_plan_json : [],
                ...$this->normalizeMetaArray($options['meta'] ?? null),
            ],
            options: $options,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function normalizeBookingPayment(BookingPayment $payment, array $options = []): array
    {
        return $this->normalizePaymentRecord(
            domain: 'booking',
            sourceTable: 'booking_payments',
            sourceId: $payment->id,
            provider: (string) ($payment->provider ?? ''),
            status: (string) ($payment->status ?? ''),
            method: $payment->method,
            transactionReference: $payment->transaction_reference,
            amount: $this->moneyString($payment->amount),
            currency: (string) ($payment->currency ?? 'GEL'),
            flags: [
                'is_installment' => false,
                'is_prepayment' => (bool) ($payment->is_prepayment ?? false),
            ],
            processedAt: $payment->processed_at,
            createdAt: $payment->created_at,
            updatedAt: $payment->updated_at,
            rawPayload: is_array($payment->raw_payload_json ?? null) ? $payment->raw_payload_json : [],
            meta: [
                'domain_resource' => [
                    'booking_id' => (int) ($payment->booking_id ?? 0),
                    'booking_number' => (string) ($payment->booking?->booking_number ?? ''),
                    'invoice_id' => $payment->invoice_id ? (int) $payment->invoice_id : null,
                    'invoice_number' => (string) ($payment->invoice?->invoice_number ?? ''),
                ],
                'booking_meta_json' => is_array($payment->meta_json ?? null) ? $payment->meta_json : [],
                ...$this->normalizeMetaArray($options['meta'] ?? null),
            ],
            options: $options,
        );
    }

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizePaymentRecord(
        string $domain,
        string $sourceTable,
        mixed $sourceId,
        string $provider,
        string $status,
        mixed $method,
        mixed $transactionReference,
        string $amount,
        string $currency,
        array $flags,
        mixed $processedAt,
        mixed $createdAt,
        mixed $updatedAt,
        array $rawPayload,
        array $meta,
        array $options = []
    ): array {
        $includeRawPayload = (bool) ($options['include_raw_payload'] ?? false);
        $normalizedDomain = $this->normalizeDomain($domain);
        $normalizedStatus = strtolower(trim($status));
        $providerKey = $this->normalizeIdentifier($provider) ?: 'manual';
        $methodKey = $this->normalizeIdentifier((string) ($method ?? ''));

        return [
            'schema' => $this->schema(task: 'P5-F2-04'),
            'domain' => $normalizedDomain,
            'kind' => 'payment',
            'source' => [
                'table' => $sourceTable,
                'id' => is_numeric($sourceId) ? (int) $sourceId : null,
            ],
            'provider' => $providerKey,
            'status' => $normalizedStatus !== '' ? $normalizedStatus : 'unknown',
            'lifecycle_state' => $this->mapLifecycleState($normalizedStatus),
            'method' => $methodKey !== '' ? $methodKey : null,
            'transaction_reference' => $this->nullableString($transactionReference, 255),
            'amount' => $amount,
            'currency' => strtoupper(trim($currency)) ?: 'GEL',
            'flags' => [
                'is_installment' => (bool) ($flags['is_installment'] ?? false),
                'is_prepayment' => (bool) ($flags['is_prepayment'] ?? false),
            ],
            'raw_payload_json' => $includeRawPayload ? $rawPayload : null,
            'meta_json' => $meta,
            'processed_at' => $this->iso($processedAt),
            'created_at' => $this->iso($createdAt),
            'updated_at' => $this->iso($updatedAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeGatewaySetting(SitePaymentGatewaySetting $setting, bool $includeConfig): array
    {
        return [
            'id' => (int) $setting->id,
            'provider_slug' => (string) $setting->provider_slug,
            'availability' => (string) $setting->availability,
            'config' => $includeConfig ? (is_array($setting->config ?? null) ? $setting->config : []) : null,
            'updated_by' => $setting->updated_by ? (int) $setting->updated_by : null,
            'updated_at' => $setting->updated_at?->toISOString(),
            'created_at' => $setting->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $task): array
    {
        return [
            'name' => self::SCHEMA_NAME,
            'version' => self::SCHEMA_VERSION,
            'task' => $task,
        ];
    }

    private function mapLifecycleState(string $status): string
    {
        return match (true) {
            $status === '', $status === 'pending', $status === 'created', $status === 'processing' => 'pending',
            $status === 'authorized', $status === 'authorised' => 'authorized',
            in_array($status, ['paid', 'completed', 'captured', 'settled'], true) => 'captured',
            $status === 'partially_refunded' => 'partially_refunded',
            $status === 'refunded' => 'refunded',
            in_array($status, ['failed', 'declined', 'error'], true) => 'failed',
            in_array($status, ['cancelled', 'canceled', 'voided'], true) => 'cancelled',
            default => 'unknown',
        };
    }

    private function normalizeDomain(string $domain): string
    {
        $normalized = $this->normalizeIdentifier($domain);

        return in_array($normalized, ['ecommerce', 'booking'], true) ? $normalized : 'custom';
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?: '';
        $normalized = trim($normalized, '-');

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetaArray(mixed $meta): array
    {
        return is_array($meta) ? $meta : [];
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $maxLength);
    }

    private function moneyString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return null;
    }
}
