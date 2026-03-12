<?php

namespace App\Plugins\PaymentGateways\BankOfGeorgia;

use App\Contracts\EcommercePaymentGatewayPlugin;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BankOfGeorgiaPlugin implements EcommercePaymentGatewayPlugin
{
    private const DEFAULT_CALLBACK_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu4RUyAw3+CdkS3ZNILQh\nzHI9Hemo+vKB9U2BSabppkKjzjjkf+0Sm76hSMiu/HFtYhqWOESryoCDJoqffY0Q\n1VNt25aTxbj068QNUtnxQ7KQVLA+pG0smf+EBWlS1vBEAFbIas9d8c9b9sSEkTrr\nTYQ90WIM8bGB6S/KLVoT1a7SnzabjoLc5Qf/SLDG5fu8dH8zckyeYKdRKSBJKvhx\ntcBuHV4f7qsynQT+f2UYbESX/TLHwT5qFWZDHZ0YUOUIvb8n7JujVSGZO9/+ll/g\n4ZIWhC1MlJgPObDwRkRd8NFOopgxMcMsDIZIoLbWKhHVq67hdbwpAq9K9WMmEhPn\nPwIDAQAB\n-----END PUBLIC KEY-----";

    private array $config;

    private ?string $paymentsAccessToken = null;

    private ?string $installmentAccessToken = null;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    public function getName(): string
    {
        return 'Bank of Georgia';
    }

    public function getDescription(): string
    {
        return 'Bank of Georgia gateway using official Payment Manager and Installment API flows.';
    }

    public function getType(): string
    {
        return 'payment_gateway';
    }

    public function getIcon(): string
    {
        return 'plugins/bank-of-georgia/icon.svg';
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
        return ! empty($this->config['client_id'])
            && ! empty($this->config['client_secret'])
            && ! empty($this->config['merchant_id']);
    }

    public function validateConfig(array $config): void
    {
        foreach (['client_id', 'client_secret', 'merchant_id'] as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Bank of Georgia {$field} is required.");
            }
        }
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'client_id',
                'label' => 'Payment Client ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'BoG payment API client_id',
            ],
            [
                'name' => 'client_secret',
                'label' => 'Payment Client Secret',
                'type' => 'password',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'BoG payment API client_secret',
            ],
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Merchant identifier used in install­ment calculator/order flows',
            ],
            [
                'name' => 'installment_client_id',
                'label' => 'Installment Client ID',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Optional installment client_id (defaults to Payment Client ID)',
            ],
            [
                'name' => 'installment_secret_key',
                'label' => 'Installment Secret Key',
                'type' => 'password',
                'required' => false,
                'sensitive' => true,
                'placeholder' => 'Optional installment secret_key (defaults to Payment Client Secret)',
            ],
            [
                'name' => 'callback_public_key',
                'label' => 'Payment Callback Public Key',
                'type' => 'textarea',
                'required' => false,
                'rows' => 8,
                'placeholder' => 'PEM public key used for Callback-Signature verification',
                'help' => 'If empty, Bank of Georgia default public key from official docs is used.',
            ],
            [
                'name' => 'sandbox',
                'label' => 'Sandbox Mode',
                'type' => 'toggle',
                'default' => true,
            ],
            [
                'name' => 'payment_token_url',
                'label' => 'Payment Token URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
            ],
            [
                'name' => 'sandbox_payment_base_url',
                'label' => 'Sandbox Payment Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://api.bog.ge',
            ],
            [
                'name' => 'production_payment_base_url',
                'label' => 'Production Payment Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://api.bog.ge',
            ],
            [
                'name' => 'payment_order_path',
                'label' => 'Payment Order Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/payments/v1/ecommerce/orders',
            ],
            [
                'name' => 'sandbox_installment_base_url',
                'label' => 'Sandbox Installment Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://installment-test.bog.ge',
            ],
            [
                'name' => 'production_installment_base_url',
                'label' => 'Production Installment Base URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://installment.bog.ge',
            ],
            [
                'name' => 'installment_token_path',
                'label' => 'Installment Token Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/v1/oauth2/token',
            ],
            [
                'name' => 'installment_calculator_path',
                'label' => 'Installment Calculator Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/v1/services/installment/calculate',
            ],
            [
                'name' => 'installment_order_path',
                'label' => 'Installment Order Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/v1/installment/checkout',
            ],
        ];
    }

    /**
     * Platform subscriptions are handled by dedicated recurring gateways.
     */
    public function initPayment(Plan $plan, User $user): RedirectResponse|string|array
    {
        throw new \Exception('Bank of Georgia plugin is configured for ecommerce checkout only.');
    }

    public function initEcommercePayment(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload = []
    ): array {
        $isInstallment = (bool) ($payload['is_installment'] ?? $payment->is_installment);

        if ($isInstallment) {
            return $this->initInstallmentPayment($order, $payment, $payload);
        }

        return $this->initStandardPayment($order, $payment, $payload);
    }

    public function supportsInstallments(): bool
    {
        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $request->all();

        if ($this->isPaymentManagerCallback($payload)) {
            if (! $this->verifyPaymentCallbackSignature($request)) {
                return response('Invalid signature', 400);
            }

            $status = $this->nullableString(data_get($payload, 'body.order_status.key'));
            $mappedStatus = $this->mapPaymentManagerStatus($status);

            $request->merge([
                'provider' => 'bank-of-georgia',
                'event_type' => $payload['event'] ?? 'order_payment',
                'ecommerce' => [
                    'provider' => 'bank-of-georgia',
                    'transaction_reference' => data_get($payload, 'body.order_id') ?? data_get($payload, 'body.external_order_id'),
                    'status' => $mappedStatus ?? $status,
                    'amount' => data_get($payload, 'body.purchase_units.transfer_amount') ?? data_get($payload, 'body.purchase_units.request_amount'),
                    'refund_amount' => data_get($payload, 'body.purchase_units.refund_amount'),
                ],
            ]);

            return response('Webhook handled', 200);
        }

        if ($this->isInstallmentCallback($payload)) {
            $status = $this->nullableString($payload['status'] ?? null);
            $mappedStatus = $this->mapInstallmentCallbackStatus($status);

            $request->merge([
                'provider' => 'bank-of-georgia',
                'event_type' => 'installment.callback',
                'ecommerce' => [
                    'provider' => 'bank-of-georgia',
                    'transaction_reference' => $payload['order_id'] ?? $payload['shop_order_id'] ?? null,
                    'status' => $mappedStatus ?? $status,
                ],
            ]);

            return response('Webhook handled', 200);
        }

        return response('Webhook handled', 200);
    }

    public function callback(Request $request): RedirectResponse
    {
        $status = strtolower((string) $request->query('status', 'completed'));

        if (in_array($status, ['failed', 'fail', 'error', 'cancelled', 'canceled', 'reject'], true)) {
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
        return ['GEL', 'USD', 'EUR', 'GBP'];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    public function requiresManualApproval(): bool
    {
        return false;
    }

    private function initStandardPayment(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload
    ): array {
        $token = $this->authenticatePayments();
        $method = $this->normalizePaymentMethod($payload['method'] ?? null, false);
        $transactionReference = $payment->transaction_reference ?: strtoupper((string) Str::uuid());
        $locale = $this->normalizeLocale($payload['locale'] ?? null);

        $requestPayload = [
            'callback_url' => url('/payment-gateways/bank-of-georgia/webhook'),
            'external_order_id' => $order->order_number ?: (string) $order->id,
            'purchase_units' => [
                'currency' => strtoupper((string) ($payment->currency ?: 'GEL')),
                'total_amount' => (float) number_format((float) $payment->amount, 2, '.', ''),
                'basket' => $this->buildPaymentBasket($order),
            ],
            'redirect_urls' => [
                'success' => $this->buildRedirectUrl('success'),
                'fail' => $this->buildRedirectUrl('fail'),
            ],
        ];

        if ($method !== null) {
            $requestPayload['payment_method'] = [$method];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Accept-Language' => $locale,
                'Idempotency-Key' => $transactionReference,
            ])
            ->post($this->resolvePaymentOrderUrl(), $requestPayload)
            ->throw();

        $responsePayload = $response->json();

        $providerReference = $this->nullableString(
            $responsePayload['id']
                ?? data_get($responsePayload, 'order_id')
                ?? data_get($responsePayload, '_links.details.href')
        ) ?? $transactionReference;

        $redirectUrl = $this->nullableString(data_get($responsePayload, '_links.redirect.href'));
        $detailsUrl = $this->nullableString(data_get($responsePayload, '_links.details.href'));

        $existingRawPayload = is_array($payment->raw_payload_json) ? $payment->raw_payload_json : [];

        return [
            'payment' => [
                'method' => $method ?? 'card',
                'transaction_reference' => $providerReference,
                'raw_payload_json' => array_merge($existingRawPayload, [
                    'bog' => [
                        'flow' => 'payments',
                        'request' => $requestPayload,
                        'response' => $responsePayload,
                    ],
                ]),
            ],
            'payment_session' => [
                'provider' => 'bank-of-georgia',
                'status' => 'pending',
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => strtoupper((string) ($payment->currency ?: 'GEL')),
                'requires_redirect' => $redirectUrl !== null,
                'redirect_url' => $redirectUrl,
                'provider_reference' => $providerReference,
                'details_url' => $detailsUrl,
                'expires_at' => now()->addMinutes(15)->toISOString(),
                'installment' => [
                    'enabled' => false,
                    'plan' => [],
                ],
            ],
        ];
    }

    private function initInstallmentPayment(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload
    ): array {
        $token = $this->authenticateInstallment();
        $locale = $this->normalizeLocale($payload['locale'] ?? null);
        $plan = $this->resolveInstallmentPlan($payment, $payload);
        $transactionReference = $payment->transaction_reference ?: strtoupper((string) Str::uuid());

        $requestPayload = [
            'intent' => 'LOAN',
            'installment_month' => $plan['month'],
            'installment_type' => $plan['type'],
            'shop_order_id' => $order->order_number ?: (string) $order->id,
            'success_redirect_url' => $this->buildRedirectUrl('success'),
            'fail_redirect_url' => $this->buildRedirectUrl('fail'),
            'reject_redirect_url' => $this->buildRedirectUrl('reject'),
            'validate_items' => true,
            'locale' => $locale,
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper((string) ($payment->currency ?: 'GEL')),
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ],
            ],
            'cart_items' => $this->buildInstallmentCartItems($order),
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->resolveInstallmentOrderUrl(), $requestPayload)
            ->throw();

        $responsePayload = $response->json();
        $providerReference = $this->nullableString($responsePayload['order_id'] ?? null) ?? $transactionReference;
        $redirectUrl = $this->extractInstallmentLink($responsePayload, 'target');
        $detailsUrl = $this->extractInstallmentLink($responsePayload, 'self');

        $existingRawPayload = is_array($payment->raw_payload_json) ? $payment->raw_payload_json : [];

        return [
            'payment' => [
                'method' => 'installment',
                'transaction_reference' => $providerReference,
                'raw_payload_json' => array_merge($existingRawPayload, [
                    'bog' => [
                        'flow' => 'installment',
                        'plan' => $plan,
                        'request' => $requestPayload,
                        'response' => $responsePayload,
                    ],
                ]),
            ],
            'payment_session' => [
                'provider' => 'bank-of-georgia',
                'status' => $this->normalizeSessionStatus($responsePayload['status'] ?? null),
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => strtoupper((string) ($payment->currency ?: 'GEL')),
                'requires_redirect' => $redirectUrl !== null,
                'redirect_url' => $redirectUrl,
                'provider_reference' => $providerReference,
                'details_url' => $detailsUrl,
                'expires_at' => now()->addMinutes(45)->toISOString(),
                'installment' => [
                    'enabled' => true,
                    'plan' => $plan,
                ],
            ],
        ];
    }

    private function authenticatePayments(): string
    {
        if ($this->paymentsAccessToken !== null) {
            return $this->paymentsAccessToken;
        }

        $cacheKey = sprintf(
            'bog:payments:access-token:%s:%s',
            sha1((string) ($this->config['client_id'] ?? '')),
            sha1($this->resolvePaymentTokenUrl())
        );

        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && trim($cachedToken) !== '') {
            $this->paymentsAccessToken = $cachedToken;

            return $cachedToken;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth((string) $this->config['client_id'], (string) $this->config['client_secret'])
            ->post($this->resolvePaymentTokenUrl(), [
                'grant_type' => 'client_credentials',
            ])
            ->throw();

        $body = $response->json();
        $token = $this->nullableString($body['access_token'] ?? null);
        if ($token === null) {
            throw new \Exception('Bank of Georgia payment token was not returned.');
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        $this->paymentsAccessToken = $token;

        return $token;
    }

    private function authenticateInstallment(): string
    {
        if ($this->installmentAccessToken !== null) {
            return $this->installmentAccessToken;
        }

        $installmentClientId = $this->resolveInstallmentClientId();
        $installmentSecret = $this->resolveInstallmentSecretKey();

        $cacheKey = sprintf(
            'bog:installment:access-token:%s:%s',
            sha1($installmentClientId),
            sha1($this->resolveInstallmentTokenUrl())
        );

        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && trim($cachedToken) !== '') {
            $this->installmentAccessToken = $cachedToken;

            return $cachedToken;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($installmentClientId, $installmentSecret)
            ->post($this->resolveInstallmentTokenUrl(), [
                'grant_type' => 'client_credentials',
            ])
            ->throw();

        $body = $response->json();
        $token = $this->nullableString($body['access_token'] ?? null);
        if ($token === null) {
            throw new \Exception('Bank of Georgia installment token was not returned.');
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        $this->installmentAccessToken = $token;

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{month: int, type: string, source: string}
     */
    private function resolveInstallmentPlan(EcommerceOrderPayment $payment, array $payload): array
    {
        $raw = is_array($payload['installment_plan_json'] ?? null)
            ? $payload['installment_plan_json']
            : ((array) ($payment->installment_plan_json ?? []));

        $month = $this->parsePositiveInt($raw['month'] ?? $raw['months'] ?? $raw['installment_month'] ?? null);
        $type = $this->nullableString($raw['type'] ?? $raw['installment_type'] ?? $raw['discount_code'] ?? null);

        if ($month !== null && $type !== null) {
            return [
                'month' => $month,
                'type' => strtoupper($type),
                'source' => 'request',
            ];
        }

        $options = $this->fetchInstallmentOptions((float) $payment->amount);
        if ($options === []) {
            throw new \Exception('Installment options could not be resolved from Bank of Georgia calculator API.');
        }

        $selected = null;
        if ($month !== null) {
            foreach ($options as $option) {
                if (($option['month'] ?? null) === $month) {
                    $selected = $option;
                    break;
                }
            }
        }

        if ($selected === null) {
            $selected = $options[0];
        }

        return [
            'month' => (int) $selected['month'],
            'type' => strtoupper((string) $selected['type']),
            'source' => 'calculator',
        ];
    }

    /**
     * @return array<int, array{month: int, type: string}>
     */
    private function fetchInstallmentOptions(float $amount): array
    {
        $token = $this->authenticateInstallment();

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->resolveInstallmentCalculatorUrl(), [
                'amount' => number_format($amount, 2, '.', ''),
                'client_id' => $this->resolveInstallmentClientId(),
            ])
            ->throw();

        $body = $response->json();
        $rows = is_array($body) ? $body : (is_array($body['discounts'] ?? null) ? $body['discounts'] : []);

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $month = $this->parsePositiveInt($row['month'] ?? null);
            $type = $this->nullableString($row['discount_code'] ?? $row['type'] ?? null);

            if ($month === null || $type === null) {
                continue;
            }

            $options[] = [
                'month' => $month,
                'type' => strtoupper($type),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function buildPaymentBasket(EcommerceOrder $order): array
    {
        $order->loadMissing('items');

        $basket = [];
        foreach ($order->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $unitPrice = (float) $item->unit_price;

            $basket[] = [
                'product_id' => (string) ($item->product_id ?: ($item->sku ?: $item->id)),
                'description' => (string) $item->name,
                'quantity' => $quantity,
                'unit_price' => (float) number_format($unitPrice, 2, '.', ''),
                'total_price' => (float) number_format($unitPrice * $quantity, 2, '.', ''),
            ];
        }

        return $basket;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildInstallmentCartItems(EcommerceOrder $order): array
    {
        $order->loadMissing('items');

        $items = [];
        foreach ($order->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $total = (float) $item->line_total;

            $items[] = [
                'total_item_amount' => number_format($total, 2, '.', ''),
                'item_description' => (string) $item->name,
                'total_item_qty' => (string) $quantity,
                'item_vendor_code' => (string) ($item->product_id ?: ($item->sku ?: $item->id)),
                'product_image_url' => '',
                'item_site_detail_url' => '',
            ];
        }

        if ($items !== []) {
            return $items;
        }

        return [
            [
                'total_item_amount' => number_format((float) $order->grand_total, 2, '.', ''),
                'item_description' => 'Order #'.(string) $order->id,
                'total_item_qty' => '1',
                'item_vendor_code' => (string) $order->id,
                'product_image_url' => '',
                'item_site_detail_url' => '',
            ],
        ];
    }

    private function verifyPaymentCallbackSignature(Request $request): bool
    {
        $signature = $this->nullableString($request->header('Callback-Signature'));
        if ($signature === null) {
            return true;
        }

        $pem = $this->normalizePublicKeyPem($this->config['callback_public_key'] ?? self::DEFAULT_CALLBACK_PUBLIC_KEY);
        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            return false;
        }

        $body = $request->getContent();
        $decoded = base64_decode($signature, true);
        $signatureBytes = $decoded !== false ? $decoded : $signature;

        $verified = openssl_verify($body, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256) === 1;

        if (is_resource($publicKey)) {
            openssl_free_key($publicKey);
        }

        return $verified;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isPaymentManagerCallback(array $payload): bool
    {
        return is_array($payload['body'] ?? null)
            && $this->nullableString($payload['event'] ?? null) === 'order_payment';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isInstallmentCallback(array $payload): bool
    {
        return $this->nullableString($payload['order_id'] ?? null) !== null
            && $this->nullableString($payload['status'] ?? null) !== null
            && $this->nullableString($payload['payment_method'] ?? null) !== null;
    }

    private function mapPaymentManagerStatus(?string $status): ?string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'completed' => 'paid',
            'rejected' => 'failed',
            'refunded' => 'refunded',
            'refunded_partially' => 'partially_refunded',
            default => $status,
        };
    }

    private function mapInstallmentCallbackStatus(?string $status): ?string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'success' => 'paid',
            'error', 'reject', 'fail' => 'failed',
            'reverse_success' => 'refunded',
            default => $status,
        };
    }

    private function normalizeSessionStatus(mixed $status): string
    {
        $value = strtolower(trim((string) $status));

        if (in_array($value, ['created', 'in_progress', 'processing', 'pending'], true)) {
            return 'pending';
        }

        if (in_array($value, ['success', 'completed', 'paid'], true)) {
            return 'paid';
        }

        if (in_array($value, ['error', 'failed', 'rejected', 'fail'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function normalizePaymentMethod(mixed $method, bool $installment): ?string
    {
        if ($installment) {
            return 'bog_loan';
        }

        $value = strtolower(trim((string) $method));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'card', 'google_pay', 'apple_pay', 'bog_p2p', 'bog_loyalty', 'bnpl', 'bog_loan', 'gift_card' => $value,
            'installment' => 'bog_loan',
            default => null,
        };
    }

    private function buildRedirectUrl(string $status): string
    {
        return route('payment.callback', [
            'gateway' => 'bank-of-georgia',
            'status' => $status,
        ]);
    }

    private function resolvePaymentTokenUrl(): string
    {
        $explicit = $this->nullableString($this->config['payment_token_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token';
    }

    private function resolvePaymentOrderUrl(): string
    {
        $explicit = $this->nullableString($this->config['payment_order_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->resolvePaymentBaseUrl().$this->normalizePath((string) ($this->config['payment_order_path'] ?? '/payments/v1/ecommerce/orders'));
    }

    private function resolveInstallmentTokenUrl(): string
    {
        $explicit = $this->nullableString($this->config['installment_token_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->resolveInstallmentBaseUrl().$this->normalizePath((string) ($this->config['installment_token_path'] ?? '/v1/oauth2/token'));
    }

    private function resolveInstallmentCalculatorUrl(): string
    {
        $explicit = $this->nullableString($this->config['installment_calculator_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->resolveInstallmentBaseUrl().$this->normalizePath((string) ($this->config['installment_calculator_path'] ?? '/v1/services/installment/calculate'));
    }

    private function resolveInstallmentOrderUrl(): string
    {
        $explicit = $this->nullableString($this->config['installment_order_url'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->resolveInstallmentBaseUrl().$this->normalizePath((string) ($this->config['installment_order_path'] ?? '/v1/installment/checkout'));
    }

    private function resolvePaymentBaseUrl(): string
    {
        $sandbox = (bool) ($this->config['sandbox'] ?? true);
        $configured = $sandbox
            ? ($this->config['sandbox_payment_base_url'] ?? null)
            : ($this->config['production_payment_base_url'] ?? null);

        return rtrim((string) ($configured ?: 'https://api.bog.ge'), '/');
    }

    private function resolveInstallmentBaseUrl(): string
    {
        $sandbox = (bool) ($this->config['sandbox'] ?? true);
        $configured = $sandbox
            ? ($this->config['sandbox_installment_base_url'] ?? null)
            : ($this->config['production_installment_base_url'] ?? null);

        $fallback = $sandbox ? 'https://installment-test.bog.ge' : 'https://installment.bog.ge';

        return rtrim((string) ($configured ?: $fallback), '/');
    }

    private function resolveInstallmentClientId(): string
    {
        $value = $this->nullableString($this->config['installment_client_id'] ?? null)
            ?? $this->nullableString($this->config['client_id'] ?? null);

        if ($value === null) {
            throw new \Exception('Installment client ID is not configured.');
        }

        return $value;
    }

    private function resolveInstallmentSecretKey(): string
    {
        $value = $this->nullableString($this->config['installment_secret_key'] ?? null)
            ?? $this->nullableString($this->config['client_secret'] ?? null);

        if ($value === null) {
            throw new \Exception('Installment secret key is not configured.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function extractInstallmentLink(array $responsePayload, string $rel): ?string
    {
        $links = $responsePayload['links'] ?? [];
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            if (strtolower((string) ($link['rel'] ?? '')) !== strtolower($rel)) {
                continue;
            }

            return $this->nullableString($link['href'] ?? null);
        }

        return null;
    }

    private function normalizeLocale(mixed $locale): string
    {
        $value = strtolower(trim((string) $locale));

        return in_array($value, ['ka', 'en'], true) ? $value : 'ka';
    }

    private function normalizePath(string $path): string
    {
        $value = trim($path);
        if ($value === '') {
            return '';
        }

        return str_starts_with($value, '/') ? $value : "/{$value}";
    }

    private function normalizePublicKeyPem(mixed $value): string
    {
        $pem = trim((string) $value);
        if ($pem === '') {
            return self::DEFAULT_CALLBACK_PUBLIC_KEY;
        }

        if (str_contains($pem, 'BEGIN PUBLIC KEY')) {
            return $pem;
        }

        return "-----BEGIN PUBLIC KEY-----\n{$pem}\n-----END PUBLIC KEY-----";
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
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
