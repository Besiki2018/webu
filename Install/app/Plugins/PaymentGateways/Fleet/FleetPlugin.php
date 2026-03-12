<?php

namespace App\Plugins\PaymentGateways\Fleet;

use App\Contracts\EcommercePaymentGatewayPlugin;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FleetPlugin implements EcommercePaymentGatewayPlugin
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    public function getName(): string
    {
        return 'Flitt';
    }

    public function getDescription(): string
    {
        return 'Flitt redirect checkout gateway with card and installment support.';
    }

    public function getType(): string
    {
        return 'payment_gateway';
    }

    public function getIcon(): string
    {
        return 'plugins/fleet/icon.svg';
    }

    public function getVersion(): string
    {
        return '1.1.0';
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
        return ! empty($this->config['merchant_id']) && ! empty($this->config['merchant_secret']);
    }

    public function validateConfig(array $config): void
    {
        foreach (['merchant_id', 'merchant_secret'] as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Flitt {$field} is required.");
            }
        }
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Flitt merchant identifier',
            ],
            [
                'name' => 'merchant_secret',
                'label' => 'Merchant Secret',
                'type' => 'password',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'Flitt merchant secret used for SHA1 signature',
            ],
            [
                'name' => 'callback_secret',
                'label' => 'Callback Secret',
                'type' => 'password',
                'required' => false,
                'sensitive' => true,
                'placeholder' => 'Optional callback signature secret (defaults to Merchant Secret)',
            ],
            [
                'name' => 'default_payment_systems',
                'label' => 'Default Payment Systems',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'cards',
            ],
            [
                'name' => 'default_installment_method',
                'label' => 'Default Installment Method',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'tbc_installment ან bog_installment',
            ],
            [
                'name' => 'sandbox',
                'label' => 'Sandbox Mode',
                'type' => 'toggle',
                'default' => true,
            ],
            [
                'name' => 'sandbox_base_url',
                'label' => 'Sandbox Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://sandbox.pay.flitt.dev',
            ],
            [
                'name' => 'production_base_url',
                'label' => 'Production Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://pay.flitt.com',
            ],
            [
                'name' => 'checkout_path',
                'label' => 'Checkout Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/api/checkout/url',
            ],
        ];
    }

    /**
     * Platform subscriptions are handled by dedicated recurring gateways.
     */
    public function initPayment(Plan $plan, User $user): RedirectResponse|string|array
    {
        throw new \Exception('Flitt plugin is configured for ecommerce checkout only.');
    }

    public function initEcommercePayment(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload = []
    ): array {
        $isInstallment = (bool) ($payload['is_installment'] ?? $payment->is_installment);
        $installmentPlan = is_array($payload['installment_plan_json'] ?? null)
            ? $payload['installment_plan_json']
            : ((array) ($payment->installment_plan_json ?? []));
        $method = $this->resolveStorageMethod($payload, $isInstallment, $installmentPlan);

        $transactionReference = $payment->transaction_reference ?: strtoupper((string) Str::uuid());
        $requestPayload = $this->buildRequestPayload(
            order: $order,
            payment: $payment,
            payload: $payload,
            isInstallment: $isInstallment,
            installmentPlan: $installmentPlan
        );
        $requestSignature = $this->generateSignature($requestPayload, $this->resolveRequestSignatureSecret());

        $wrappedPayload = [
            'request' => $requestPayload,
            'signature' => $requestSignature,
        ];

        $response = Http::acceptJson()
            ->asJson()
            ->post($this->resolveCheckoutUrl(), $wrappedPayload)
            ->throw();

        $responsePayload = is_array($response->json()) ? $response->json() : [];
        $responseStatus = strtolower((string) ($responsePayload['status'] ?? ''));

        if ($responseStatus !== '' && ! in_array($responseStatus, ['success', 'ok', 'created'], true)) {
            $message = $this->nullableString($responsePayload['description'] ?? null)
                ?? $this->nullableString($responsePayload['message'] ?? null)
                ?? 'Flitt failed to create checkout order.';

            throw new \Exception($message);
        }

        $providerReference = $this->nullableString(
            ($responsePayload['payment_id'] ?? null)
                ?? ($responsePayload['order_hash'] ?? null)
                ?? ($responsePayload['id'] ?? null)
        ) ?? $transactionReference;

        $redirectUrl = $this->nullableString(
            ($responsePayload['checkout_url'] ?? null)
                ?? data_get($responsePayload, 'response_url')
                ?? ($responsePayload['redirect_url'] ?? null)
        );

        $expiresAt = $this->nullableString(
            $responsePayload['expires_at'] ?? null
        ) ?? now()->addMinutes(20)->toISOString();

        $sessionStatus = $this->normalizeSessionStatus(
            ($responsePayload['order_status'] ?? null)
                ?? ($responsePayload['status'] ?? null)
        );

        $existingRawPayload = is_array($payment->raw_payload_json) ? $payment->raw_payload_json : [];

        return [
            'payment' => [
                'method' => $method,
                'transaction_reference' => $providerReference,
                'raw_payload_json' => array_merge($existingRawPayload, [
                    'fleet' => [
                        'request' => $wrappedPayload,
                        'response' => $responsePayload,
                    ],
                ]),
            ],
            'payment_session' => [
                'provider' => 'fleet',
                'status' => $sessionStatus,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => strtoupper((string) ($payment->currency ?: 'GEL')),
                'requires_redirect' => $redirectUrl !== null,
                'redirect_url' => $redirectUrl,
                'provider_reference' => $providerReference,
                'expires_at' => $expiresAt,
                'installment' => [
                    'enabled' => $isInstallment,
                    'plan' => $installmentPlan,
                ],
            ],
        ];
    }

    public function supportsInstallments(): bool
    {
        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $this->resolveCallbackPayload($request->all());
        if (! $this->isCallbackSignatureValid($payload)) {
            return response('Invalid signature', 400);
        }

        $status = $this->mapCallbackStatus(
            $this->nullableString($payload['order_status'] ?? null)
                ?? $this->nullableString($payload['status'] ?? null)
        );

        $request->merge([
            'provider' => 'fleet',
            'event_type' => 'flitt.callback',
            'ecommerce' => [
                'provider' => 'fleet',
                'order_id' => $this->parsePositiveInt($payload['order_id'] ?? null),
                'transaction_reference' => $this->nullableString($payload['payment_id'] ?? null)
                    ?? $this->nullableString($payload['order_hash'] ?? null)
                    ?? $this->nullableString($payload['order_id'] ?? null),
                'status' => $status,
                'amount' => $this->normalizeCallbackAmount($payload['amount'] ?? null),
                'refund_amount' => $this->normalizeCallbackAmount($payload['refund_amount'] ?? null),
            ],
        ]);

        return response('Webhook handled', 200);
    }

    public function callback(Request $request): RedirectResponse
    {
        $payload = $this->resolveCallbackPayload($request->all());
        $signature = $this->nullableString($payload['signature'] ?? null);

        if ($signature !== null && ! $this->isCallbackSignatureValid($payload)) {
            return redirect()->route('create')
                ->with('error', 'Payment callback signature is invalid.');
        }

        $status = strtolower((string) (
            $payload['order_status']
                ?? $payload['status']
                ?? $request->query('status', 'processing')
        ));

        if (in_array($status, ['declined', 'failed', 'cancelled', 'canceled', 'expired'], true)) {
            return redirect()->route('create')
                ->with('error', 'Payment was not completed.');
        }

        return redirect()->route('create')
            ->with('info', 'Payment is being verified. You will receive an update shortly.');
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);
    }

    public function getSubscriptionStatus(string $subscriptionId): array
    {
        $subscription = Subscription::query()
            ->where('external_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            throw new \Exception("Subscription not found: {$subscriptionId}");
        }

        return [
            'status' => $subscription->status,
            'renewal_at' => $subscription->renewal_at?->toISOString(),
            'amount' => $subscription->amount,
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['GEL'];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    public function requiresManualApproval(): bool
    {
        return false;
    }

    private function buildRequestPayload(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload,
        bool $isInstallment,
        array $installmentPlan
    ): array {
        $requestPayload = [
            'merchant_id' => (string) $this->config['merchant_id'],
            'order_id' => (string) $order->id,
            'amount' => $this->toMinorUnits($payment->amount),
            'currency' => strtoupper((string) ($payment->currency ?: 'GEL')),
            'description' => $this->buildOrderDescription($order, $payload),
            'response_url' => route('payment.callback', ['gateway' => 'fleet']),
            'server_callback_url' => url('/payment-gateways/fleet/webhook'),
            'lang' => $this->normalizeLocale($payload['locale'] ?? null),
        ];

        $userEmail = $this->nullableString($order->customer_email);
        if ($userEmail !== null) {
            $requestPayload['user_email'] = $userEmail;
        }

        $userPhone = $this->nullableString($order->customer_phone);
        if ($userPhone !== null) {
            $requestPayload['user_phone'] = $userPhone;
        }

        $userName = $this->nullableString($order->customer_name);
        if ($userName !== null) {
            $requestPayload['user_name'] = $userName;
        }

        if ($isInstallment) {
            $installmentMethod = $this->resolveInstallmentMethod($payload, $installmentPlan);
            $requestPayload['payment_systems'] = 'installments';
            $requestPayload['payment_method'] = $installmentMethod;

            $period = $this->resolveInstallmentPeriod($payload, $installmentPlan);
            if ($period !== null) {
                $requestPayload['period'] = $period;
            }
        } else {
            $requestPayload['payment_systems'] = $this->resolvePaymentSystems($payload);
        }

        $merchantData = json_encode([
            'site_id' => $order->site_id,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($merchantData !== false) {
            $requestPayload['merchant_data'] = $merchantData;
        }

        return $requestPayload;
    }

    private function resolveCheckoutUrl(): string
    {
        $explicit = $this->nullableString($this->config['checkout_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->resolveBaseUrl().$this->normalizePath((string) ($this->config['checkout_path'] ?? '/api/checkout/url'));
    }

    private function resolveBaseUrl(): string
    {
        $sandbox = (bool) ($this->config['sandbox'] ?? true);
        $fallback = $sandbox ? 'https://sandbox.pay.flitt.dev' : 'https://pay.flitt.com';

        $configured = $sandbox
            ? ($this->config['sandbox_base_url'] ?? null)
            : ($this->config['production_base_url'] ?? null);

        return rtrim((string) ($configured ?: $fallback), '/');
    }

    private function normalizePath(string $path): string
    {
        $value = trim($path);
        if ($value === '') {
            return '';
        }

        return str_starts_with($value, '/') ? $value : "/{$value}";
    }

    private function resolveStorageMethod(array $payload, bool $isInstallment, array $installmentPlan): string
    {
        if ($isInstallment) {
            return $this->resolveInstallmentMethod($payload, $installmentPlan);
        }

        return $this->nullableString($payload['method'] ?? null) ?? 'card';
    }

    private function resolvePaymentSystems(array $payload): string
    {
        $candidate = $payload['payment_systems'] ?? $this->config['default_payment_systems'] ?? 'cards';

        if (is_array($candidate)) {
            $normalized = array_values(array_filter(array_map(
                fn (mixed $value): ?string => $this->nullableString($value),
                $candidate
            )));

            if ($normalized !== []) {
                return implode(',', $normalized);
            }
        }

        $value = $this->nullableString($candidate);

        return $value ?? 'cards';
    }

    private function resolveInstallmentMethod(array $payload, array $installmentPlan): string
    {
        $candidate = $this->nullableString($payload['payment_method'] ?? null)
            ?? $this->nullableString($installmentPlan['payment_method'] ?? null)
            ?? $this->nullableString($installmentPlan['method'] ?? null)
            ?? $this->nullableString($payload['method'] ?? null)
            ?? $this->nullableString($this->config['default_installment_method'] ?? null);

        if ($candidate === null || $candidate === 'installment' || $candidate === 'installments') {
            throw new \Exception('Flitt installment payment_method is required (tbc_installment ან bog_installment).');
        }

        if (! in_array($candidate, ['tbc_installment', 'bog_installment'], true)) {
            throw new \Exception('Unsupported Flitt installment payment_method. Allowed: tbc_installment, bog_installment.');
        }

        return $candidate;
    }

    private function resolveInstallmentPeriod(array $payload, array $installmentPlan): ?int
    {
        $raw = $payload['period']
            ?? $installmentPlan['period']
            ?? $installmentPlan['months']
            ?? null;

        if (! is_numeric($raw)) {
            return null;
        }

        $period = (int) $raw;

        return $period > 0 ? $period : null;
    }

    private function buildOrderDescription(EcommerceOrder $order, array $payload): string
    {
        $description = $this->nullableString($payload['description'] ?? null)
            ?? $this->nullableString($order->order_number)
            ?? ('Order '.$order->id);

        if ($description === null) {
            return 'Order';
        }

        return Str::limit($description, 255, '');
    }

    private function toMinorUnits(mixed $amount): int
    {
        $floatAmount = (float) $amount;

        return max(1, (int) round($floatAmount * 100));
    }

    private function normalizeLocale(mixed $locale): string
    {
        $candidate = strtolower((string) $locale);

        if (in_array($candidate, ['ka', 'en', 'ru'], true)) {
            return $candidate;
        }

        return 'ka';
    }

    private function resolveCallbackPayload(array $payload): array
    {
        if (is_array($payload['response'] ?? null)) {
            return $payload['response'];
        }

        $responseString = $this->nullableString($payload['response'] ?? null);
        if ($responseString !== null) {
            $decoded = json_decode($responseString, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $payload;
    }

    private function isCallbackSignatureValid(array $payload): bool
    {
        $secret = $this->resolveCallbackSignatureSecret();
        if ($secret === null) {
            return false;
        }

        $signature = $this->nullableString($payload['signature'] ?? null);

        if ($signature === null) {
            return false;
        }

        $expected = $this->generateSignature($payload, $secret);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    private function generateSignature(array $params, string $secret): string
    {
        unset($params['signature'], $params['response_signature_string']);

        $prepared = [];
        foreach ($params as $key => $value) {
            $normalized = $this->normalizeSignatureValue($value);
            if ($normalized === null || $normalized === '') {
                continue;
            }

            $prepared[(string) $key] = $normalized;
        }

        ksort($prepared, SORT_STRING);

        $signatureParts = [$secret];
        foreach ($prepared as $value) {
            $signatureParts[] = $value;
        }

        return sha1(implode('|', $signatureParts));
    }

    private function normalizeSignatureValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? null : trim($encoded);
        }

        if (! is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function resolveRequestSignatureSecret(): string
    {
        $secret = $this->nullableString($this->config['merchant_secret'] ?? null);
        if ($secret === null) {
            throw new \Exception('Flitt merchant_secret is not configured.');
        }

        return $secret;
    }

    private function resolveCallbackSignatureSecret(): ?string
    {
        return $this->nullableString($this->config['callback_secret'] ?? null)
            ?? $this->nullableString($this->config['merchant_secret'] ?? null);
    }

    private function mapCallbackStatus(?string $status): string
    {
        $candidate = strtolower(trim((string) $status));

        if (in_array($candidate, ['approved', 'success', 'paid', 'completed'], true)) {
            return 'paid';
        }

        if (in_array($candidate, ['declined', 'expired', 'failed', 'cancelled', 'canceled'], true)) {
            return 'failed';
        }

        if (in_array($candidate, ['reversed', 'refunded'], true)) {
            return 'refunded';
        }

        if (in_array($candidate, ['created', 'processing', 'pending'], true)) {
            return 'pending';
        }

        return $candidate === '' ? 'pending' : $candidate;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    private function normalizeCallbackAmount(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $raw = (string) $value;
        if (str_contains($raw, '.')) {
            return number_format((float) $raw, 2, '.', '');
        }

        return number_format(((float) $raw) / 100, 2, '.', '');
    }

    private function normalizeSessionStatus(mixed $status): string
    {
        $candidate = strtolower(trim((string) $status));

        if (in_array($candidate, ['success', 'ok', 'created', 'pending', 'processing', 'authorized'], true)) {
            return 'pending';
        }

        if (in_array($candidate, ['approved', 'paid', 'captured', 'succeeded', 'completed'], true)) {
            return 'paid';
        }

        if (in_array($candidate, ['failed', 'declined', 'cancelled', 'canceled', 'expired', 'reversed'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
