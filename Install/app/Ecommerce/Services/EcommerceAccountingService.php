<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceAccountingEntry;
use App\Models\EcommerceAccountingEntryLine;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EcommerceAccountingService implements EcommerceAccountingServiceContract
{
    public const EVENT_ORDER_PLACED = 'order_placed';

    public const EVENT_PAYMENT_SETTLED = 'payment_settled';

    public const EVENT_REFUND = 'refund';

    public const EVENT_RETURN_ADJUSTMENT = 'return_adjustment';

    private const ACCOUNT_RECEIVABLE = 'asset.accounts_receivable';

    private const ACCOUNT_CASH = 'asset.cash_bank';

    private const ACCOUNT_SALES = 'revenue.sales';

    private const ACCOUNT_SHIPPING = 'revenue.shipping';

    private const ACCOUNT_TAX = 'liability.tax_payable';

    private const ACCOUNT_DISCOUNTS = 'contra_revenue.discounts';

    private const ACCOUNT_REFUNDS = 'contra_revenue.refunds';

    private const ACCOUNT_RETURN_LIABILITY = 'liability.customer_returns';

    public function recordOrderPlaced(
        Site $site,
        EcommerceOrder $order,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry {
        $targetOrder = $this->assertOrderInSite($site, $order);

        $subtotal = $this->moneyFloat($targetOrder->subtotal);
        $shipping = $this->moneyFloat($targetOrder->shipping_total);
        $tax = $this->moneyFloat($targetOrder->tax_total);
        $discount = $this->moneyFloat($targetOrder->discount_total);
        $grandTotal = $this->moneyFloat($targetOrder->grand_total);

        $lines = [
            $this->line(self::ACCOUNT_RECEIVABLE, 'Accounts Receivable', EcommerceAccountingEntryLine::SIDE_DEBIT, $grandTotal),
            $this->line(self::ACCOUNT_SALES, 'Sales Revenue', EcommerceAccountingEntryLine::SIDE_CREDIT, $subtotal),
            $this->line(self::ACCOUNT_SHIPPING, 'Shipping Revenue', EcommerceAccountingEntryLine::SIDE_CREDIT, $shipping),
            $this->line(self::ACCOUNT_TAX, 'Tax Payable', EcommerceAccountingEntryLine::SIDE_CREDIT, $tax),
            $this->line(self::ACCOUNT_DISCOUNTS, 'Discounts', EcommerceAccountingEntryLine::SIDE_DEBIT, $discount),
        ];

        return $this->persistEntry(
            site: $site,
            eventType: self::EVENT_ORDER_PLACED,
            eventKey: $eventKey ?? sprintf('order:%d:placed', $targetOrder->id),
            order: $targetOrder,
            payment: null,
            currency: $targetOrder->currency ?: 'GEL',
            lines: $lines,
            description: sprintf('Order %s placed.', $targetOrder->order_number),
            meta: [
                ...$meta,
                'order_number' => $targetOrder->order_number,
            ],
            occurredAt: $targetOrder->placed_at ?: now(),
            actor: $actor
        );
    }

    public function recordPaymentSettled(
        Site $site,
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry {
        $targetOrder = $this->assertOrderInSite($site, $order);
        $targetPayment = $this->assertPaymentInOrder($site, $targetOrder, $payment);

        $settledAmount = $this->moneyFloat($amount > 0 ? $amount : $targetPayment->amount);
        if ($settledAmount <= 0) {
            throw new EcommerceDomainException('Payment settlement amount must be greater than zero.', 422);
        }

        $lines = [
            $this->line(self::ACCOUNT_CASH, 'Cash / Bank', EcommerceAccountingEntryLine::SIDE_DEBIT, $settledAmount),
            $this->line(self::ACCOUNT_RECEIVABLE, 'Accounts Receivable', EcommerceAccountingEntryLine::SIDE_CREDIT, $settledAmount),
        ];

        return $this->persistEntry(
            site: $site,
            eventType: self::EVENT_PAYMENT_SETTLED,
            eventKey: $eventKey ?? sprintf('payment:%d:settled', $targetPayment->id),
            order: $targetOrder,
            payment: $targetPayment,
            currency: $targetPayment->currency ?: ($targetOrder->currency ?: 'GEL'),
            lines: $lines,
            description: sprintf('Payment settled for order %s.', $targetOrder->order_number),
            meta: [
                ...$meta,
                'provider' => $targetPayment->provider,
                'transaction_reference' => $targetPayment->transaction_reference,
            ],
            occurredAt: $targetPayment->processed_at ?: now(),
            actor: $actor
        );
    }

    public function recordRefund(
        Site $site,
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry {
        $targetOrder = $this->assertOrderInSite($site, $order);
        $targetPayment = $this->assertPaymentInOrder($site, $targetOrder, $payment);

        $refundAmount = $this->moneyFloat($amount);
        if ($refundAmount <= 0) {
            throw new EcommerceDomainException('Refund amount must be greater than zero.', 422);
        }

        $fromReturn = (bool) ($meta['from_return_adjustment'] ?? false);
        $refundDebitAccount = $fromReturn ? self::ACCOUNT_RETURN_LIABILITY : self::ACCOUNT_REFUNDS;
        $refundDebitName = $fromReturn ? 'Customer Return Liability' : 'Refunds and Chargebacks';

        $lines = [
            $this->line($refundDebitAccount, $refundDebitName, EcommerceAccountingEntryLine::SIDE_DEBIT, $refundAmount),
            $this->line(self::ACCOUNT_CASH, 'Cash / Bank', EcommerceAccountingEntryLine::SIDE_CREDIT, $refundAmount),
        ];

        $resolvedEventKey = $eventKey
            ?? sprintf('payment:%d:refund:%s', $targetPayment->id, sha1($this->moneyString($refundAmount).'|'.(string) ($meta['reference'] ?? now()->toISOString())));

        return $this->persistEntry(
            site: $site,
            eventType: self::EVENT_REFUND,
            eventKey: $resolvedEventKey,
            order: $targetOrder,
            payment: $targetPayment,
            currency: $targetPayment->currency ?: ($targetOrder->currency ?: 'GEL'),
            lines: $lines,
            description: sprintf('Refund recorded for order %s.', $targetOrder->order_number),
            meta: [
                ...$meta,
                'provider' => $targetPayment->provider,
                'transaction_reference' => $targetPayment->transaction_reference,
            ],
            occurredAt: now(),
            actor: $actor
        );
    }

    public function recordReturnAdjustment(
        Site $site,
        EcommerceOrder $order,
        float $amount,
        ?string $eventKey = null,
        array $meta = [],
        ?User $actor = null
    ): EcommerceAccountingEntry {
        $targetOrder = $this->assertOrderInSite($site, $order);

        $returnAmount = $this->moneyFloat($amount);
        if ($returnAmount <= 0) {
            throw new EcommerceDomainException('Return adjustment amount must be greater than zero.', 422);
        }

        $lines = [
            $this->line(self::ACCOUNT_REFUNDS, 'Refunds and Returns', EcommerceAccountingEntryLine::SIDE_DEBIT, $returnAmount),
            $this->line(self::ACCOUNT_RETURN_LIABILITY, 'Customer Return Liability', EcommerceAccountingEntryLine::SIDE_CREDIT, $returnAmount),
        ];

        return $this->persistEntry(
            site: $site,
            eventType: self::EVENT_RETURN_ADJUSTMENT,
            eventKey: $eventKey ?? sprintf('order:%d:return:%s', $targetOrder->id, sha1($this->moneyString($returnAmount).'|'.(string) ($meta['reference'] ?? now()->toISOString()))),
            order: $targetOrder,
            payment: null,
            currency: $targetOrder->currency ?: 'GEL',
            lines: $lines,
            description: sprintf('Return adjustment recorded for order %s.', $targetOrder->order_number),
            meta: $meta,
            occurredAt: now(),
            actor: $actor
        );
    }

    public function listEntries(Site $site, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 50), 200));
        $orderId = $this->parsePositiveInt($filters['order_id'] ?? null);
        $eventType = $this->nullableString($filters['event_type'] ?? null);

        $query = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->with([
                'order:id,site_id,order_number,status,payment_status,currency',
                'payment:id,site_id,order_id,provider,status,transaction_reference,amount,currency',
                'lines' => function ($lineQuery): void {
                    $lineQuery->orderBy('line_no')->orderBy('id');
                },
                'creator:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        $entries = $query->limit($limit)->get();

        $totals = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId))
            ->when($eventType !== null, fn ($q) => $q->where('event_type', $eventType))
            ->selectRaw('COUNT(*) as entries_count, COALESCE(SUM(total_debit), 0) as total_debit, COALESCE(SUM(total_credit), 0) as total_credit')
            ->first();

        $totalDebit = $this->moneyFloat($totals?->total_debit ?? 0);
        $totalCredit = $this->moneyFloat($totals?->total_credit ?? 0);

        return [
            'site_id' => $site->id,
            'filters' => [
                'order_id' => $orderId,
                'event_type' => $eventType,
                'limit' => $limit,
            ],
            'summary' => [
                'entries_count' => (int) ($totals?->entries_count ?? 0),
                'total_debit' => $this->moneyString($totalDebit),
                'total_credit' => $this->moneyString($totalCredit),
                'difference' => $this->moneyString($totalDebit - $totalCredit),
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.009,
            ],
            'entries' => $entries
                ->map(fn (EcommerceAccountingEntry $entry): array => $this->serializeEntry($entry))
                ->values()
                ->all(),
        ];
    }

    public function reconciliation(Site $site, array $filters = []): array
    {
        $orderId = $this->parsePositiveInt($filters['order_id'] ?? null);

        $entryBaseQuery = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId));

        $entryTotals = (clone $entryBaseQuery)
            ->selectRaw('COUNT(*) as entries_count, COALESCE(SUM(total_debit), 0) as total_debit, COALESCE(SUM(total_credit), 0) as total_credit')
            ->first();

        $lines = EcommerceAccountingEntryLine::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId))
            ->selectRaw("account_code, account_name, COALESCE(SUM(CASE WHEN side = 'debit' THEN amount ELSE 0 END), 0) as debit_total, COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END), 0) as credit_total")
            ->groupBy('account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        $accounts = $lines->map(function ($row): array {
            $debit = $this->moneyFloat($row->debit_total ?? 0);
            $credit = $this->moneyFloat($row->credit_total ?? 0);

            return [
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'debit_total' => $this->moneyString($debit),
                'credit_total' => $this->moneyString($credit),
                'net' => $this->moneyString($debit - $credit),
            ];
        })->values()->all();

        $totalDebit = $this->moneyFloat($entryTotals?->total_debit ?? 0);
        $totalCredit = $this->moneyFloat($entryTotals?->total_credit ?? 0);

        $receivableLine = collect($accounts)->firstWhere('account_code', self::ACCOUNT_RECEIVABLE);

        return [
            'site_id' => $site->id,
            'filters' => [
                'order_id' => $orderId,
            ],
            'summary' => [
                'entries_count' => (int) ($entryTotals?->entries_count ?? 0),
                'total_debit' => $this->moneyString($totalDebit),
                'total_credit' => $this->moneyString($totalCredit),
                'difference' => $this->moneyString($totalDebit - $totalCredit),
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.009,
                'accounts_receivable_net' => $receivableLine['net'] ?? $this->moneyString(0),
            ],
            'accounts' => $accounts,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $meta
     */
    private function persistEntry(
        Site $site,
        string $eventType,
        string $eventKey,
        ?EcommerceOrder $order,
        ?EcommerceOrderPayment $payment,
        string $currency,
        array $lines,
        ?string $description,
        array $meta,
        mixed $occurredAt,
        ?User $actor
    ): EcommerceAccountingEntry {
        $normalizedCurrency = strtoupper(trim($currency));
        if ($normalizedCurrency === '') {
            $normalizedCurrency = 'GEL';
        }

        $normalizedLines = collect($lines)
            ->map(function (array $line) use ($normalizedCurrency): array {
                $amount = $this->moneyFloat($line['amount'] ?? 0);

                return [
                    'account_code' => (string) ($line['account_code'] ?? ''),
                    'account_name' => (string) ($line['account_name'] ?? ''),
                    'side' => (string) ($line['side'] ?? ''),
                    'amount' => $amount,
                    'currency' => strtoupper(trim((string) ($line['currency'] ?? $normalizedCurrency))) ?: $normalizedCurrency,
                    'description' => $this->nullableString($line['description'] ?? null),
                    'meta_json' => is_array($line['meta_json'] ?? null) ? $line['meta_json'] : [],
                ];
            })
            ->filter(fn (array $line): bool => $line['amount'] > 0)
            ->values();

        if ($normalizedLines->isEmpty()) {
            throw new EcommerceDomainException('Accounting entry requires at least one line with amount greater than zero.', 422);
        }

        $totalDebit = $this->moneyFloat($normalizedLines
            ->where('side', EcommerceAccountingEntryLine::SIDE_DEBIT)
            ->sum('amount'));
        $totalCredit = $this->moneyFloat($normalizedLines
            ->where('side', EcommerceAccountingEntryLine::SIDE_CREDIT)
            ->sum('amount'));

        if (abs($totalDebit - $totalCredit) >= 0.009) {
            throw new EcommerceDomainException(
                'Accounting entry is not balanced.',
                422,
                [
                    'total_debit' => $this->moneyString($totalDebit),
                    'total_credit' => $this->moneyString($totalCredit),
                ]
            );
        }

        try {
            return DB::transaction(function () use (
                $site,
                $eventType,
                $eventKey,
                $order,
                $payment,
                $normalizedCurrency,
                $totalDebit,
                $totalCredit,
                $description,
                $meta,
                $occurredAt,
                $actor,
                $normalizedLines
            ): EcommerceAccountingEntry {
                $existing = EcommerceAccountingEntry::query()
                    ->where('site_id', $site->id)
                    ->where('event_key', $eventKey)
                    ->first();

                if ($existing) {
                    return $existing->load(['lines', 'creator', 'order', 'payment']);
                }

                /** @var EcommerceAccountingEntry $entry */
                $entry = EcommerceAccountingEntry::query()->create([
                    'site_id' => $site->id,
                    'order_id' => $order?->id,
                    'order_payment_id' => $payment?->id,
                    'event_type' => $eventType,
                    'event_key' => $eventKey,
                    'currency' => $normalizedCurrency,
                    'total_debit' => $this->moneyString($totalDebit),
                    'total_credit' => $this->moneyString($totalCredit),
                    'description' => $description,
                    'meta_json' => $meta,
                    'created_by' => $actor?->id,
                    'occurred_at' => $occurredAt,
                ]);

                $lineNo = 1;
                foreach ($normalizedLines as $line) {
                    EcommerceAccountingEntryLine::query()->create([
                        'site_id' => $site->id,
                        'entry_id' => $entry->id,
                        'order_id' => $order?->id,
                        'order_payment_id' => $payment?->id,
                        'line_no' => $lineNo,
                        'account_code' => $line['account_code'],
                        'account_name' => $line['account_name'],
                        'side' => $line['side'],
                        'amount' => $this->moneyString($line['amount']),
                        'currency' => $line['currency'],
                        'description' => $line['description'],
                        'meta_json' => $line['meta_json'],
                    ]);
                    $lineNo++;
                }

                return $entry->fresh(['lines', 'creator', 'order', 'payment']);
            });
        } catch (QueryException $queryException) {
            // Unique event_key race: fetch existing entry idempotently.
            if ((int) $queryException->getCode() === 23000) {
                $existing = EcommerceAccountingEntry::query()
                    ->where('site_id', $site->id)
                    ->where('event_key', $eventKey)
                    ->first();

                if ($existing) {
                    return $existing->load(['lines', 'creator', 'order', 'payment']);
                }
            }

            throw $queryException;
        }
    }

    private function assertOrderInSite(Site $site, EcommerceOrder $order): EcommerceOrder
    {
        if ((string) $order->site_id !== (string) $site->id) {
            throw new EcommerceDomainException('Order not found for accounting operation.', 404);
        }

        return $order;
    }

    private function assertPaymentInOrder(
        Site $site,
        EcommerceOrder $order,
        EcommerceOrderPayment $payment
    ): EcommerceOrderPayment {
        if ((string) $payment->site_id !== (string) $site->id || (int) $payment->order_id !== (int) $order->id) {
            throw new EcommerceDomainException('Payment not found for accounting operation.', 404);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function line(
        string $accountCode,
        string $accountName,
        string $side,
        float $amount,
        ?string $description = null,
        array $meta = []
    ): array {
        return [
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'side' => $side,
            'amount' => $amount,
            'description' => $description,
            'meta_json' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(EcommerceAccountingEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'site_id' => $entry->site_id,
            'order_id' => $entry->order_id,
            'order_number' => $entry->order?->order_number,
            'order_payment_id' => $entry->order_payment_id,
            'payment_provider' => $entry->payment?->provider,
            'event_type' => $entry->event_type,
            'event_key' => $entry->event_key,
            'currency' => $entry->currency,
            'total_debit' => (string) $entry->total_debit,
            'total_credit' => (string) $entry->total_credit,
            'difference' => $this->moneyString($this->moneyFloat($entry->total_debit) - $this->moneyFloat($entry->total_credit)),
            'description' => $entry->description,
            'meta_json' => $entry->meta_json ?? [],
            'created_by' => $entry->created_by,
            'created_by_name' => $entry->creator?->name,
            'occurred_at' => $entry->occurred_at?->toISOString(),
            'created_at' => $entry->created_at?->toISOString(),
            'lines' => $entry->lines
                ->map(fn (EcommerceAccountingEntryLine $line): array => [
                    'id' => $line->id,
                    'line_no' => (int) $line->line_no,
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'side' => $line->side,
                    'amount' => (string) $line->amount,
                    'currency' => $line->currency,
                    'description' => $line->description,
                    'meta_json' => $line->meta_json ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    private function moneyFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->moneyFloat($value), 2, '.', '');
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $resolved = (int) $value;

            return $resolved > 0 ? $resolved : null;
        }

        return null;
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
