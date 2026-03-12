<?php

namespace App\Contracts;

interface CourierPlugin extends Plugin
{
    /**
     * List of ISO country codes this courier supports.
     * Empty array means "all countries".
     *
     * @return array<int, string>
     */
    public function getSupportedCountries(): array;

    /**
     * Whether this courier exposes real-time tracking status.
     */
    public function supportsTracking(): bool;

    /**
     * Quote shipping rates for the provided checkout context.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     rates: array<int, array{
     *         rate_id: string,
     *         service_code: string,
     *         service_name: string,
     *         amount: string,
     *         currency: string,
     *         estimated_days: array{min:int, max:int},
     *         metadata?: array<string, mixed>
     *     }>
     * }
     */
    public function quote(array $payload = []): array;

    /**
     * Create shipment on courier side for an order.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     shipment_reference: string,
     *     tracking_number: string|null,
     *     tracking_url: string|null,
     *     status: string,
     *     metadata?: array<string, mixed>
     * }
     */
    public function createShipment(array $payload = []): array;

    /**
     * Resolve shipment tracking status.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     shipment_reference: string,
     *     tracking_number: string|null,
     *     status: string,
     *     updated_at: string,
     *     metadata?: array<string, mixed>
     * }
     */
    public function track(array $payload = []): array;

    /**
     * Cancel an existing shipment.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     shipment_reference: string,
     *     status: string
     * }
     */
    public function cancelShipment(array $payload = []): array;
}
