<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceRsReadinessServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceAccountingEntry;
use App\Models\EcommerceAccountingEntryLine;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceRsExport;
use App\Models\GlobalSetting;
use App\Models\Site;
use App\Models\User;

class EcommerceRsReadinessService implements EcommerceRsReadinessServiceContract
{
    private const SCHEMA_VERSION = 'rs.v1';

    public function generateOrderExport(
        Site $site,
        EcommerceOrder $order,
        ?User $actor = null
    ): array {
        $targetOrder = $this->assertOrderInSite($site, $order);
        $site->loadMissing(['project:id,name,user_id', 'globalSettings']);

        $payload = $this->buildPayload($site, $targetOrder);
        $validation = $this->validatePayload($payload);
        $status = empty($validation['errors']) ? EcommerceRsExport::STATUS_VALID : EcommerceRsExport::STATUS_INVALID;

        /** @var EcommerceRsExport $export */
        $export = EcommerceRsExport::query()->create([
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'schema_version' => self::SCHEMA_VERSION,
            'status' => $status,
            'export_hash' => sha1($this->encodePayload($payload)),
            'payload_json' => $payload,
            'validation_errors_json' => $validation['errors'],
            'validation_warnings_json' => $validation['warnings'],
            'totals_json' => [
                ...($payload['order']['totals'] ?? []),
                'accounting_total_debit' => $payload['accounting']['summary']['total_debit'] ?? $this->moneyString(0),
                'accounting_total_credit' => $payload['accounting']['summary']['total_credit'] ?? $this->moneyString(0),
            ],
            'generated_by' => $actor?->id,
            'generated_at' => now(),
        ]);

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'export' => $this->serializeExport(
                $export->fresh(['order:id,site_id,order_number,status,payment_status,currency', 'generatedBy:id,name']),
                includePayload: true
            ),
        ];
    }

    public function listExports(Site $site, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 25), 100));
        $orderId = $this->parsePositiveInt($filters['order_id'] ?? null);
        $status = $this->normalizeStatus($filters['status'] ?? null);

        $query = EcommerceRsExport::query()
            ->where('site_id', $site->id)
            ->with(['order:id,site_id,order_number,status,payment_status,currency', 'generatedBy:id,name'])
            ->orderByDesc('generated_at')
            ->orderByDesc('id');

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $exports = $query->limit($limit)->get();

        $summaryBase = EcommerceRsExport::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId));

        $total = (clone $summaryBase)->count();
        $valid = (clone $summaryBase)->where('status', EcommerceRsExport::STATUS_VALID)->count();
        $invalid = (clone $summaryBase)->where('status', EcommerceRsExport::STATUS_INVALID)->count();

        return [
            'site_id' => $site->id,
            'filters' => [
                'order_id' => $orderId,
                'status' => $status,
                'limit' => $limit,
            ],
            'summary' => [
                'total_exports' => $total,
                'valid_exports' => $valid,
                'invalid_exports' => $invalid,
            ],
            'exports' => $exports
                ->map(fn (EcommerceRsExport $export): array => $this->serializeExport($export, includePayload: false))
                ->values()
                ->all(),
        ];
    }

    public function readinessSummary(Site $site, array $filters = []): array
    {
        $orderId = $this->parsePositiveInt($filters['order_id'] ?? null);

        $baseExports = EcommerceRsExport::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId));

        $latest = (clone $baseExports)
            ->with(['order:id,site_id,order_number,status,payment_status,currency', 'generatedBy:id,name'])
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        $ordersInScope = EcommerceOrder::query()
            ->where('site_id', $site->id)
            ->when($orderId !== null, fn ($q) => $q->where('id', $orderId))
            ->count();

        $ordersWithExport = (clone $baseExports)
            ->distinct('order_id')
            ->count('order_id');

        $valid = (clone $baseExports)->where('status', EcommerceRsExport::STATUS_VALID)->count();
        $invalid = (clone $baseExports)->where('status', EcommerceRsExport::STATUS_INVALID)->count();
        $total = (clone $baseExports)->count();

        return [
            'site_id' => $site->id,
            'filters' => [
                'order_id' => $orderId,
            ],
            'summary' => [
                'orders_in_scope' => $ordersInScope,
                'orders_with_export' => $ordersWithExport,
                'orders_without_export' => max(0, $ordersInScope - $ordersWithExport),
                'total_exports' => $total,
                'valid_exports' => $valid,
                'invalid_exports' => $invalid,
                'is_ready' => $ordersInScope > 0 && $invalid === 0 && $ordersWithExport >= $ordersInScope,
            ],
            'latest_export' => $latest ? $this->serializeExport($latest, includePayload: false) : null,
        ];
    }

    public function showExport(Site $site, EcommerceRsExport $export): array
    {
        $target = $this->assertExportInSite($site, $export);
        $target->loadMissing(['order:id,site_id,order_number,status,payment_status,currency', 'generatedBy:id,name']);

        return [
            'site_id' => $site->id,
            'export' => $this->serializeExport($target, includePayload: true),
        ];
    }

    private function assertOrderInSite(Site $site, EcommerceOrder $order): EcommerceOrder
    {
        if ((string) $order->site_id !== (string) $site->id) {
            throw new EcommerceDomainException('Order not found for RS export.', 404);
        }

        $resolved = EcommerceOrder::query()
            ->where('site_id', $site->id)
            ->whereKey($order->id)
            ->with([
                'items',
                'payments',
                'accountingEntries' => function ($query): void {
                    $query->with(['lines' => function ($lineQuery): void {
                        $lineQuery->orderBy('line_no')->orderBy('id');
                    }])->orderBy('occurred_at')->orderBy('id');
                },
            ])
            ->first();

        if (! $resolved) {
            throw new EcommerceDomainException('Order not found for RS export.', 404);
        }

        return $resolved;
    }

    private function assertExportInSite(Site $site, EcommerceRsExport $export): EcommerceRsExport
    {
        if ((string) $export->site_id !== (string) $site->id) {
            throw new EcommerceDomainException('RS export not found for this site.', 404);
        }

        return $export;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Site $site, EcommerceOrder $order): array
    {
        $settings = $site->globalSettings;
        $contact = $this->contactPayload($settings);
        $currency = strtoupper(trim((string) $order->currency)) ?: 'GEL';

        $items = $order->items
            ->map(fn (EcommerceOrderItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => $this->moneyString($item->unit_price),
                'tax_amount' => $this->moneyString($item->tax_amount),
                'discount_amount' => $this->moneyString($item->discount_amount),
                'line_total' => $this->moneyString($item->line_total),
                'options_json' => $item->options_json ?? [],
                'meta_json' => $item->meta_json ?? [],
            ])
            ->values()
            ->all();

        $payments = $order->payments
            ->map(fn (EcommerceOrderPayment $payment): array => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'method' => $payment->method,
                'transaction_reference' => $payment->transaction_reference,
                'amount' => $this->moneyString($payment->amount),
                'currency' => strtoupper(trim((string) $payment->currency)) ?: $currency,
                'is_installment' => (bool) $payment->is_installment,
                'installment_plan_json' => $payment->installment_plan_json ?? [],
                'processed_at' => $payment->processed_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        $accountingEntries = $order->accountingEntries
            ->map(fn (EcommerceAccountingEntry $entry): array => [
                'id' => $entry->id,
                'event_type' => $entry->event_type,
                'event_key' => $entry->event_key,
                'currency' => $entry->currency,
                'total_debit' => $this->moneyString($entry->total_debit),
                'total_credit' => $this->moneyString($entry->total_credit),
                'description' => $entry->description,
                'occurred_at' => $entry->occurred_at?->toISOString(),
                'lines' => $entry->lines
                    ->map(fn (EcommerceAccountingEntryLine $line): array => [
                        'line_no' => (int) $line->line_no,
                        'account_code' => $line->account_code,
                        'account_name' => $line->account_name,
                        'side' => $line->side,
                        'amount' => $this->moneyString($line->amount),
                        'currency' => strtoupper(trim((string) $line->currency)) ?: $currency,
                        'description' => $line->description,
                        'meta_json' => $line->meta_json ?? [],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $accountingTotalDebit = $this->moneyFloat($order->accountingEntries->sum('total_debit'));
        $accountingTotalCredit = $this->moneyFloat($order->accountingEntries->sum('total_credit'));

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'export_reference' => sprintf('RSV1-%s-%d-%s', $site->id, $order->id, now()->format('YmdHis')),
            'generated_at' => now()->toISOString(),
            'site' => [
                'site_id' => $site->id,
                'project_id' => $site->project_id,
                'site_name' => $site->name,
                'primary_domain' => $site->primary_domain,
                'subdomain' => $site->subdomain,
            ],
            'seller' => [
                'legal_name' => $this->nullableString($contact['business_name'] ?? $contact['company_name'] ?? $site->name),
                'tax_id' => $this->nullableString($contact['tax_id'] ?? $contact['tin'] ?? $contact['vat_number'] ?? null),
                'address' => $this->nullableString($contact['address'] ?? null),
                'city' => $this->nullableString($contact['city'] ?? null),
                'country_code' => strtoupper($this->nullableString($contact['country_code'] ?? null) ?? 'GE'),
                'email' => $this->nullableString($contact['email'] ?? null),
                'phone' => $this->nullableString($contact['phone'] ?? null),
            ],
            'buyer' => [
                'name' => $this->nullableString($order->customer_name),
                'email' => $this->nullableString($order->customer_email),
                'phone' => $this->nullableString($order->customer_phone),
                'billing_address' => is_array($order->billing_address_json) ? $order->billing_address_json : [],
                'shipping_address' => is_array($order->shipping_address_json) ? $order->shipping_address_json : [],
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_status' => $order->fulfillment_status,
                'currency' => $currency,
                'placed_at' => $order->placed_at?->toISOString(),
                'paid_at' => $order->paid_at?->toISOString(),
                'totals' => [
                    'subtotal' => $this->moneyString($order->subtotal),
                    'tax_total' => $this->moneyString($order->tax_total),
                    'shipping_total' => $this->moneyString($order->shipping_total),
                    'discount_total' => $this->moneyString($order->discount_total),
                    'grand_total' => $this->moneyString($order->grand_total),
                    'paid_total' => $this->moneyString($order->paid_total),
                    'outstanding_total' => $this->moneyString($order->outstanding_total),
                ],
                'items' => $items,
                'meta_json' => $order->meta_json ?? [],
            ],
            'payments' => $payments,
            'accounting' => [
                'summary' => [
                    'entries_count' => count($accountingEntries),
                    'total_debit' => $this->moneyString($accountingTotalDebit),
                    'total_credit' => $this->moneyString($accountingTotalCredit),
                    'difference' => $this->moneyString($accountingTotalDebit - $accountingTotalCredit),
                    'is_balanced' => abs($accountingTotalDebit - $accountingTotalCredit) < 0.009,
                ],
                'entries' => $accountingEntries,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];
        $warnings = [];

        $seller = is_array($payload['seller'] ?? null) ? $payload['seller'] : [];
        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $totals = is_array($order['totals'] ?? null) ? $order['totals'] : [];
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $payments = is_array($payload['payments'] ?? null) ? $payload['payments'] : [];
        $accounting = is_array($payload['accounting'] ?? null) ? $payload['accounting'] : [];
        $accountingSummary = is_array($accounting['summary'] ?? null) ? $accounting['summary'] : [];
        $accountingEntries = is_array($accounting['entries'] ?? null) ? $accounting['entries'] : [];

        if ($this->nullableString($seller['legal_name'] ?? null) === null) {
            $errors[] = $this->issue('error', 'missing_seller_legal_name', 'seller.legal_name', 'Seller legal name is required for RS export.');
        }

        if ($this->nullableString($seller['tax_id'] ?? null) === null) {
            $errors[] = $this->issue('error', 'missing_seller_tax_id', 'seller.tax_id', 'Seller tax ID is required for RS export.');
        }

        if ($this->nullableString($seller['address'] ?? null) === null) {
            $warnings[] = $this->issue('warning', 'missing_seller_address', 'seller.address', 'Seller legal address is not set.');
        }

        if ($this->nullableString($buyer['name'] ?? null) === null) {
            $warnings[] = $this->issue('warning', 'missing_buyer_name', 'buyer.name', 'Buyer name is empty.');
        }

        if ($this->nullableString($order['order_number'] ?? null) === null) {
            $errors[] = $this->issue('error', 'missing_order_number', 'order.order_number', 'Order number is required.');
        }

        if ($this->nullableString($order['placed_at'] ?? null) === null) {
            $errors[] = $this->issue('error', 'missing_order_placed_at', 'order.placed_at', 'Order placed date is required.');
        }

        $currency = strtoupper($this->nullableString($order['currency'] ?? null) ?? '');
        if ($currency === '' || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors[] = $this->issue('error', 'invalid_order_currency', 'order.currency', 'Order currency must be a 3-letter ISO code.');
        }

        if (count($items) === 0) {
            $errors[] = $this->issue('error', 'missing_order_items', 'order.items', 'RS export requires at least one order item.');
        }

        $computedSubtotal = 0.0;
        $computedTax = 0.0;
        foreach ($items as $index => $item) {
            $lineTotal = $this->moneyFloat($item['line_total'] ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $unitPrice = $this->moneyFloat($item['unit_price'] ?? 0);
            $taxAmount = $this->moneyFloat($item['tax_amount'] ?? 0);

            $computedSubtotal += $lineTotal;
            $computedTax += $taxAmount;

            if ($quantity <= 0) {
                $errors[] = $this->issue('error', 'invalid_item_quantity', "order.items.{$index}.quantity", 'Order item quantity must be greater than zero.', [
                    'line_index' => $index,
                ]);
            }

            if ($lineTotal < 0 || $unitPrice < 0 || $taxAmount < 0) {
                $errors[] = $this->issue('error', 'invalid_item_totals', "order.items.{$index}", 'Order item totals cannot be negative.', [
                    'line_index' => $index,
                ]);
            }
        }

        $subtotal = $this->moneyFloat($totals['subtotal'] ?? 0);
        $taxTotal = $this->moneyFloat($totals['tax_total'] ?? 0);
        $shippingTotal = $this->moneyFloat($totals['shipping_total'] ?? 0);
        $discountTotal = $this->moneyFloat($totals['discount_total'] ?? 0);
        $grandTotal = $this->moneyFloat($totals['grand_total'] ?? 0);
        $paidTotal = $this->moneyFloat($totals['paid_total'] ?? 0);
        $outstandingTotal = $this->moneyFloat($totals['outstanding_total'] ?? 0);

        if (abs($computedSubtotal - $subtotal) >= 0.009) {
            $errors[] = $this->issue('error', 'subtotal_mismatch', 'order.totals.subtotal', 'Order subtotal does not match sum of line totals.', [
                'expected' => $this->moneyString($computedSubtotal),
                'actual' => $this->moneyString($subtotal),
            ]);
        }

        if (abs($computedTax - $taxTotal) >= 0.009) {
            $warnings[] = $this->issue('warning', 'tax_mismatch', 'order.totals.tax_total', 'Order tax total differs from sum of item tax amounts.', [
                'expected' => $this->moneyString($computedTax),
                'actual' => $this->moneyString($taxTotal),
            ]);
        }

        $computedGrand = $this->moneyFloat($subtotal + $taxTotal + $shippingTotal - $discountTotal);
        if (abs($computedGrand - $grandTotal) >= 0.009) {
            $errors[] = $this->issue('error', 'grand_total_mismatch', 'order.totals.grand_total', 'Grand total does not match subtotal + tax + shipping - discount.', [
                'expected' => $this->moneyString($computedGrand),
                'actual' => $this->moneyString($grandTotal),
            ]);
        }

        $computedDueTotal = $this->moneyFloat($paidTotal + $outstandingTotal);
        if (abs($computedDueTotal - $grandTotal) >= 0.009) {
            $errors[] = $this->issue('error', 'paid_outstanding_mismatch', 'order.totals', 'Paid + outstanding totals must equal grand total.', [
                'expected' => $this->moneyString($grandTotal),
                'actual' => $this->moneyString($computedDueTotal),
            ]);
        }

        $paidBySettledPayments = 0.0;
        foreach ($payments as $index => $payment) {
            $paymentCurrency = strtoupper($this->nullableString($payment['currency'] ?? null) ?? '');
            $amount = $this->moneyFloat($payment['amount'] ?? 0);
            $status = strtolower($this->nullableString($payment['status'] ?? null) ?? '');

            if ($paymentCurrency !== '' && $currency !== '' && $paymentCurrency !== $currency) {
                $errors[] = $this->issue('error', 'payment_currency_mismatch', "payments.{$index}.currency", 'Payment currency must match order currency.', [
                    'order_currency' => $currency,
                    'payment_currency' => $paymentCurrency,
                ]);
            }

            if ($status === 'paid' || $status === 'partially_refunded' || $status === 'refunded') {
                $paidBySettledPayments += $amount;
            }
        }

        if ($paidTotal > 0 && count($payments) === 0) {
            $errors[] = $this->issue('error', 'missing_payments', 'payments', 'Paid orders must include payment records.');
        }

        if ($paidTotal > 0 && $paidBySettledPayments <= 0) {
            $warnings[] = $this->issue('warning', 'payment_status_gap', 'payments', 'Paid total is greater than zero but no settled payments were found.');
        }

        if (count($accountingEntries) === 0) {
            $errors[] = $this->issue('error', 'missing_accounting_entries', 'accounting.entries', 'Accounting ledger entries are required for RS readiness.');
        }

        $accountingDebit = 0.0;
        $accountingCredit = 0.0;
        foreach ($accountingEntries as $index => $entry) {
            $entryDebit = $this->moneyFloat($entry['total_debit'] ?? 0);
            $entryCredit = $this->moneyFloat($entry['total_credit'] ?? 0);
            $entryLines = is_array($entry['lines'] ?? null) ? $entry['lines'] : [];

            $accountingDebit += $entryDebit;
            $accountingCredit += $entryCredit;

            if (abs($entryDebit - $entryCredit) >= 0.009) {
                $errors[] = $this->issue('error', 'unbalanced_accounting_entry', "accounting.entries.{$index}", 'Accounting entry is not balanced.', [
                    'entry_id' => $entry['id'] ?? null,
                    'total_debit' => $this->moneyString($entryDebit),
                    'total_credit' => $this->moneyString($entryCredit),
                ]);
            }

            if (count($entryLines) === 0) {
                $errors[] = $this->issue('error', 'missing_accounting_lines', "accounting.entries.{$index}.lines", 'Accounting entry requires at least one line.', [
                    'entry_id' => $entry['id'] ?? null,
                ]);
            }
        }

        $summaryDebit = $this->moneyFloat($accountingSummary['total_debit'] ?? 0);
        $summaryCredit = $this->moneyFloat($accountingSummary['total_credit'] ?? 0);

        if (abs($summaryDebit - $accountingDebit) >= 0.009) {
            $errors[] = $this->issue('error', 'accounting_summary_debit_mismatch', 'accounting.summary.total_debit', 'Accounting summary debit total does not match entry totals.', [
                'expected' => $this->moneyString($accountingDebit),
                'actual' => $this->moneyString($summaryDebit),
            ]);
        }

        if (abs($summaryCredit - $accountingCredit) >= 0.009) {
            $errors[] = $this->issue('error', 'accounting_summary_credit_mismatch', 'accounting.summary.total_credit', 'Accounting summary credit total does not match entry totals.', [
                'expected' => $this->moneyString($accountingCredit),
                'actual' => $this->moneyString($summaryCredit),
            ]);
        }

        if (abs($summaryDebit - $summaryCredit) >= 0.009) {
            $errors[] = $this->issue('error', 'accounting_summary_unbalanced', 'accounting.summary', 'Accounting summary is not balanced.', [
                'total_debit' => $this->moneyString($summaryDebit),
                'total_credit' => $this->moneyString($summaryCredit),
            ]);
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExport(EcommerceRsExport $export, bool $includePayload): array
    {
        return [
            'id' => $export->id,
            'site_id' => $export->site_id,
            'order_id' => $export->order_id,
            'order_number' => $export->order?->order_number,
            'order_status' => $export->order?->status,
            'payment_status' => $export->order?->payment_status,
            'schema_version' => $export->schema_version,
            'status' => $export->status,
            'is_valid' => $export->status === EcommerceRsExport::STATUS_VALID,
            'export_hash' => $export->export_hash,
            'generated_by' => $export->generated_by,
            'generated_by_name' => $export->generatedBy?->name,
            'generated_at' => $export->generated_at?->toISOString(),
            'created_at' => $export->created_at?->toISOString(),
            'updated_at' => $export->updated_at?->toISOString(),
            'validation' => [
                'errors' => $export->validation_errors_json ?? [],
                'warnings' => $export->validation_warnings_json ?? [],
                'errors_count' => count($export->validation_errors_json ?? []),
                'warnings_count' => count($export->validation_warnings_json ?? []),
            ],
            'totals_json' => $export->totals_json ?? [],
            'payload_json' => $includePayload ? ($export->payload_json ?? []) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        string $level,
        string $code,
        string $field,
        string $message,
        array $meta = []
    ): array {
        return [
            'level' => $level,
            'code' => $code,
            'field' => $field,
            'message' => $message,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(?GlobalSetting $settings): array
    {
        if (! $settings) {
            return [];
        }

        return is_array($settings->contact_json) ? $settings->contact_json : [];
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

    private function normalizeStatus(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));
        if ($trimmed === EcommerceRsExport::STATUS_VALID || $trimmed === EcommerceRsExport::STATUS_INVALID) {
            return $trimmed;
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

    private function moneyFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->moneyFloat($value), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return is_string($encoded) ? $encoded : '{}';
    }
}
