<?php

namespace App\Booking\Services;

use App\Booking\Contracts\BookingFinanceServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Models\Booking;
use App\Models\BookingFinancialEntry;
use App\Models\BookingFinancialEntryLine;
use App\Models\BookingInvoice;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\Site;
use App\Models\User;
use App\Services\UniversalPaymentsAbstractionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingFinanceService implements BookingFinanceServiceContract
{
    public const EVENT_INVOICE_ISSUED = 'invoice_issued';

    public const EVENT_PAYMENT_RECORDED = 'payment_recorded';

    public const EVENT_REFUND_RECORDED = 'refund_recorded';

    private const PAYMENT_SETTLED_STATUSES = ['paid', 'completed', 'captured', 'settled'];

    private const PAYMENT_REPORTABLE_STATUSES = ['paid', 'completed', 'captured', 'settled', 'partially_refunded', 'refunded'];

    private const REFUND_SETTLED_STATUSES = ['completed', 'processed', 'paid'];

    private const ACCOUNT_RECEIVABLE = 'asset.accounts_receivable';

    private const ACCOUNT_CASH = 'asset.cash_bank';

    private const ACCOUNT_BOOKING_REVENUE = 'revenue.booking_services';

    private const ACCOUNT_TAX = 'liability.tax_payable';

    private const ACCOUNT_DISCOUNTS = 'contra_revenue.discounts';

    private const ACCOUNT_REFUNDS = 'contra_revenue.refunds';

    public function __construct(
        protected UniversalPaymentsAbstractionService $universalPayments
    ) {}

    public function listInvoices(Site $site, array $filters = []): array
    {
        $bookingId = $this->parsePositiveInt($filters['booking_id'] ?? null);
        $status = $this->nullableString($filters['status'] ?? null);
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 300));

        $query = BookingInvoice::query()
            ->where('site_id', $site->id)
            ->with([
                'booking:id,site_id,booking_number,status,currency,customer_name,customer_email,starts_at',
                'creator:id,name',
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        if ($bookingId !== null) {
            $query->where('booking_id', $bookingId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $rows = $query->limit($limit)->get();
        $summaryRow = BookingInvoice::query()
            ->where('site_id', $site->id)
            ->when($bookingId !== null, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->selectRaw('COUNT(*) as invoices_count, COALESCE(SUM(grand_total), 0) as grand_total, COALESCE(SUM(paid_total), 0) as paid_total, COALESCE(SUM(outstanding_total), 0) as outstanding_total')
            ->first();

        return [
            'site_id' => $site->id,
            'filters' => [
                'booking_id' => $bookingId,
                'status' => $status,
                'limit' => $limit,
            ],
            'summary' => [
                'invoices_count' => (int) ($summaryRow?->invoices_count ?? 0),
                'grand_total' => $this->moneyString($summaryRow?->grand_total ?? 0),
                'paid_total' => $this->moneyString($summaryRow?->paid_total ?? 0),
                'outstanding_total' => $this->moneyString($summaryRow?->outstanding_total ?? 0),
            ],
            'invoices' => $rows
                ->map(fn (BookingInvoice $invoice): array => $this->serializeInvoice($invoice))
                ->values()
                ->all(),
        ];
    }

    public function issueInvoice(Site $site, Booking $booking, array $payload = [], ?User $actor = null): BookingInvoice
    {
        return DB::transaction(function () use ($site, $booking, $payload, $actor): BookingInvoice {
            $targetBooking = $this->resolveBooking($site, $booking);

            $serviceFee = $this->moneyFloat($payload['service_fee'] ?? $targetBooking->service_fee);
            $taxTotal = $this->moneyFloat($payload['tax_total'] ?? $targetBooking->tax_total);
            $discountTotal = $this->moneyFloat($payload['discount_total'] ?? $targetBooking->discount_total);
            $grandTotal = $this->moneyFloat($payload['grand_total'] ?? ($serviceFee - $discountTotal + $taxTotal));
            if ($grandTotal < 0) {
                throw new BookingDomainException('Invoice grand total cannot be negative.', 422);
            }

            $paidTotal = $this->moneyFloat($payload['paid_total'] ?? $targetBooking->paid_total);
            if ($paidTotal < 0) {
                $paidTotal = 0.0;
            }
            if ($paidTotal > $grandTotal) {
                $paidTotal = $grandTotal;
            }

            $outstandingTotal = $this->moneyFloat($grandTotal - $paidTotal);
            $defaultStatus = $outstandingTotal <= 0
                ? 'paid'
                : ($paidTotal > 0 ? 'partially_paid' : 'issued');
            $status = strtolower((string) ($payload['status'] ?? $defaultStatus));
            $allowedStatuses = ['draft', 'issued', 'partially_paid', 'paid', 'void', 'cancelled'];
            if (! in_array($status, $allowedStatuses, true)) {
                throw new BookingDomainException('Unsupported invoice status.', 422, [
                    'status' => $status,
                ]);
            }

            $currency = $this->normalizeCurrency($payload['currency'] ?? $targetBooking->currency);
            $issuedAt = $payload['issued_at'] ?? now();
            $dueAt = $payload['due_at'] ?? null;
            $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];

            $invoice = BookingInvoice::query()->create([
                'site_id' => $site->id,
                'booking_id' => $targetBooking->id,
                'invoice_number' => $this->generateInvoiceNumber($site),
                'status' => $status,
                'currency' => $currency,
                'subtotal' => $this->moneyString($serviceFee),
                'tax_total' => $this->moneyString($taxTotal),
                'discount_total' => $this->moneyString($discountTotal),
                'grand_total' => $this->moneyString($grandTotal),
                'paid_total' => $this->moneyString($paidTotal),
                'outstanding_total' => $this->moneyString($outstandingTotal),
                'issued_at' => $status === 'draft' ? null : $issuedAt,
                'due_at' => $dueAt,
                'paid_at' => $outstandingTotal <= 0 ? now() : null,
                'meta_json' => $meta,
                'created_by' => $actor?->id,
            ]);

            $this->syncBookingTotals($targetBooking, [
                'paid_total' => $paidTotal,
                'outstanding_total' => $outstandingTotal,
                'service_fee' => $serviceFee,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
            ], $actor);

            if (($serviceFee + $taxTotal + $discountTotal + $grandTotal) > 0) {
                $this->persistEntry(
                    site: $site,
                    booking: $targetBooking,
                    invoice: $invoice,
                    payment: null,
                    refund: null,
                    eventType: self::EVENT_INVOICE_ISSUED,
                    eventKey: sprintf('booking:%d:invoice:%d:issued', $targetBooking->id, $invoice->id),
                    currency: $currency,
                    lines: [
                        $this->line(self::ACCOUNT_RECEIVABLE, 'Accounts Receivable', BookingFinancialEntryLine::SIDE_DEBIT, $grandTotal),
                        $this->line(self::ACCOUNT_BOOKING_REVENUE, 'Booking Services Revenue', BookingFinancialEntryLine::SIDE_CREDIT, $serviceFee),
                        $this->line(self::ACCOUNT_TAX, 'Tax Payable', BookingFinancialEntryLine::SIDE_CREDIT, $taxTotal),
                        $this->line(self::ACCOUNT_DISCOUNTS, 'Discounts', BookingFinancialEntryLine::SIDE_DEBIT, $discountTotal),
                    ],
                    description: sprintf('Invoice %s issued for booking %s.', $invoice->invoice_number, $targetBooking->booking_number),
                    meta: [
                        'booking_number' => $targetBooking->booking_number,
                        ...$meta,
                    ],
                    occurredAt: $invoice->issued_at ?: now(),
                    actor: $actor
                );
            }

            return $invoice->fresh(['booking', 'creator']);
        }, 3);
    }

    public function listPayments(Site $site, array $filters = []): array
    {
        $bookingId = $this->parsePositiveInt($filters['booking_id'] ?? null);
        $invoiceId = $this->parsePositiveInt($filters['invoice_id'] ?? null);
        $status = $this->nullableString($filters['status'] ?? null);
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 300));

        $query = BookingPayment::query()
            ->where('site_id', $site->id)
            ->with([
                'booking:id,site_id,booking_number,status,currency,customer_name,customer_email,starts_at',
                'invoice:id,site_id,booking_id,invoice_number,status,currency,grand_total,paid_total,outstanding_total',
                'creator:id,name',
            ])
            ->orderByDesc('processed_at')
            ->orderByDesc('id');

        if ($bookingId !== null) {
            $query->where('booking_id', $bookingId);
        }

        if ($invoiceId !== null) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $rows = $query->limit($limit)->get();
        $summary = BookingPayment::query()
            ->where('site_id', $site->id)
            ->when($bookingId !== null, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($invoiceId !== null, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(amount), 0) as amount_total')
            ->first();

        return [
            'site_id' => $site->id,
            'filters' => [
                'booking_id' => $bookingId,
                'invoice_id' => $invoiceId,
                'status' => $status,
                'limit' => $limit,
            ],
            'summary' => [
                'payments_count' => (int) ($summary?->payments_count ?? 0),
                'amount_total' => $this->moneyString($summary?->amount_total ?? 0),
            ],
            'payments' => $rows
                ->map(fn (BookingPayment $payment): array => $this->serializePayment($payment))
                ->values()
                ->all(),
        ];
    }

    public function recordPayment(Site $site, Booking $booking, array $payload, ?User $actor = null): BookingPayment
    {
        return DB::transaction(function () use ($site, $booking, $payload, $actor): BookingPayment {
            $targetBooking = $this->resolveBooking($site, $booking);
            $amount = $this->moneyFloat($payload['amount'] ?? 0);
            if ($amount <= 0) {
                throw new BookingDomainException('Payment amount must be greater than zero.', 422);
            }

            $invoice = null;
            $invoiceId = $this->parsePositiveInt($payload['invoice_id'] ?? null);
            if ($invoiceId !== null) {
                $invoice = $this->resolveInvoice($site, $targetBooking, $invoiceId);
            } else {
                $invoice = $this->latestInvoice($site, $targetBooking);
            }

            $status = strtolower(trim((string) ($payload['status'] ?? 'paid')));
            $allowedStatuses = ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'];
            if (! in_array($status, $allowedStatuses, true)) {
                throw new BookingDomainException('Unsupported payment status.', 422, [
                    'status' => $status,
                ]);
            }

            $currency = $this->normalizeCurrency($payload['currency'] ?? $invoice?->currency ?? $targetBooking->currency);
            $processedAt = in_array($status, self::PAYMENT_SETTLED_STATUSES, true) ? now() : null;
            $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
            $rawPayload = is_array($payload['raw_payload_json'] ?? null) ? $payload['raw_payload_json'] : [];

            $payment = BookingPayment::query()->create([
                'site_id' => $site->id,
                'booking_id' => $targetBooking->id,
                'invoice_id' => $invoice?->id,
                'provider' => strtolower(trim((string) ($payload['provider'] ?? 'manual'))),
                'status' => $status,
                'method' => $this->nullableString($payload['method'] ?? null),
                'transaction_reference' => $this->nullableString($payload['transaction_reference'] ?? null),
                'amount' => $this->moneyString($amount),
                'currency' => $currency,
                'is_prepayment' => (bool) ($payload['is_prepayment'] ?? false),
                'processed_at' => $processedAt,
                'raw_payload_json' => $rawPayload,
                'meta_json' => $meta,
                'created_by' => $actor?->id,
            ]);

            if (in_array($status, self::PAYMENT_SETTLED_STATUSES, true)) {
                $this->applySettledPayment($targetBooking, $invoice, $amount, $actor);
                $this->persistEntry(
                    site: $site,
                    booking: $targetBooking,
                    invoice: $invoice,
                    payment: $payment,
                    refund: null,
                    eventType: self::EVENT_PAYMENT_RECORDED,
                    eventKey: $this->buildPaymentEventKey($payment),
                    currency: $currency,
                    lines: [
                        $this->line(self::ACCOUNT_CASH, 'Cash / Bank', BookingFinancialEntryLine::SIDE_DEBIT, $amount),
                        $this->line(self::ACCOUNT_RECEIVABLE, 'Accounts Receivable', BookingFinancialEntryLine::SIDE_CREDIT, $amount),
                    ],
                    description: sprintf('Payment recorded for booking %s.', $targetBooking->booking_number),
                    meta: [
                        ...$meta,
                        'provider' => $payment->provider,
                        'method' => $payment->method,
                        'transaction_reference' => $payment->transaction_reference,
                    ],
                    occurredAt: $payment->processed_at ?: now(),
                    actor: $actor
                );
            }

            return $payment->fresh(['booking', 'invoice', 'creator']);
        }, 3);
    }

    public function listRefunds(Site $site, array $filters = []): array
    {
        $bookingId = $this->parsePositiveInt($filters['booking_id'] ?? null);
        $paymentId = $this->parsePositiveInt($filters['payment_id'] ?? null);
        $invoiceId = $this->parsePositiveInt($filters['invoice_id'] ?? null);
        $status = $this->nullableString($filters['status'] ?? null);
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 300));

        $query = BookingRefund::query()
            ->where('site_id', $site->id)
            ->with([
                'booking:id,site_id,booking_number,status,currency,customer_name,customer_email,starts_at',
                'invoice:id,site_id,booking_id,invoice_number,status,currency,grand_total,paid_total,outstanding_total',
                'payment:id,site_id,booking_id,invoice_id,provider,status,method,transaction_reference,amount,currency',
                'creator:id,name',
            ])
            ->orderByDesc('processed_at')
            ->orderByDesc('id');

        if ($bookingId !== null) {
            $query->where('booking_id', $bookingId);
        }

        if ($paymentId !== null) {
            $query->where('payment_id', $paymentId);
        }

        if ($invoiceId !== null) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $rows = $query->limit($limit)->get();
        $summary = BookingRefund::query()
            ->where('site_id', $site->id)
            ->when($bookingId !== null, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($paymentId !== null, fn ($q) => $q->where('payment_id', $paymentId))
            ->when($invoiceId !== null, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->selectRaw('COUNT(*) as refunds_count, COALESCE(SUM(amount), 0) as amount_total')
            ->first();

        return [
            'site_id' => $site->id,
            'filters' => [
                'booking_id' => $bookingId,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'status' => $status,
                'limit' => $limit,
            ],
            'summary' => [
                'refunds_count' => (int) ($summary?->refunds_count ?? 0),
                'amount_total' => $this->moneyString($summary?->amount_total ?? 0),
            ],
            'refunds' => $rows
                ->map(fn (BookingRefund $refund): array => $this->serializeRefund($refund))
                ->values()
                ->all(),
        ];
    }

    public function recordRefund(Site $site, Booking $booking, array $payload, ?User $actor = null): BookingRefund
    {
        return DB::transaction(function () use ($site, $booking, $payload, $actor): BookingRefund {
            $targetBooking = $this->resolveBooking($site, $booking);
            $amount = $this->moneyFloat($payload['amount'] ?? 0);
            if ($amount <= 0) {
                throw new BookingDomainException('Refund amount must be greater than zero.', 422);
            }

            $payment = null;
            $paymentId = $this->parsePositiveInt($payload['payment_id'] ?? null);
            if ($paymentId !== null) {
                $payment = $this->resolvePayment($site, $targetBooking, $paymentId);
            } else {
                $payment = $this->latestPayment($site, $targetBooking);
            }

            $invoice = null;
            $invoiceId = $this->parsePositiveInt($payload['invoice_id'] ?? null);
            if ($invoiceId !== null) {
                $invoice = $this->resolveInvoice($site, $targetBooking, $invoiceId);
            } elseif ($payment?->invoice_id) {
                $invoice = $this->resolveInvoice($site, $targetBooking, (int) $payment->invoice_id);
            } else {
                $invoice = $this->latestInvoice($site, $targetBooking);
            }

            $status = strtolower(trim((string) ($payload['status'] ?? 'completed')));
            $allowedStatuses = ['pending', 'completed', 'failed', 'cancelled'];
            if (! in_array($status, $allowedStatuses, true)) {
                throw new BookingDomainException('Unsupported refund status.', 422, [
                    'status' => $status,
                ]);
            }

            $currency = $this->normalizeCurrency($payload['currency'] ?? $payment?->currency ?? $invoice?->currency ?? $targetBooking->currency);
            $processedAt = in_array($status, self::REFUND_SETTLED_STATUSES, true) ? now() : null;
            $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
            $rawPayload = is_array($payload['raw_payload_json'] ?? null) ? $payload['raw_payload_json'] : [];

            $refund = BookingRefund::query()->create([
                'site_id' => $site->id,
                'booking_id' => $targetBooking->id,
                'payment_id' => $payment?->id,
                'invoice_id' => $invoice?->id,
                'status' => $status,
                'reason' => $this->nullableString($payload['reason'] ?? null),
                'amount' => $this->moneyString($amount),
                'currency' => $currency,
                'processed_at' => $processedAt,
                'raw_payload_json' => $rawPayload,
                'meta_json' => $meta,
                'created_by' => $actor?->id,
            ]);

            if (in_array($status, self::REFUND_SETTLED_STATUSES, true)) {
                $this->applySettledRefund($targetBooking, $invoice, $payment, $amount, $actor);
                $this->persistEntry(
                    site: $site,
                    booking: $targetBooking,
                    invoice: $invoice,
                    payment: $payment,
                    refund: $refund,
                    eventType: self::EVENT_REFUND_RECORDED,
                    eventKey: $this->buildRefundEventKey($refund, $payment),
                    currency: $currency,
                    lines: [
                        $this->line(self::ACCOUNT_REFUNDS, 'Refunds', BookingFinancialEntryLine::SIDE_DEBIT, $amount),
                        $this->line(self::ACCOUNT_CASH, 'Cash / Bank', BookingFinancialEntryLine::SIDE_CREDIT, $amount),
                    ],
                    description: sprintf('Refund recorded for booking %s.', $targetBooking->booking_number),
                    meta: [
                        ...$meta,
                        'reason' => $refund->reason,
                        'payment_id' => $payment?->id,
                    ],
                    occurredAt: $refund->processed_at ?: now(),
                    actor: $actor
                );
            }

            return $refund->fresh(['booking', 'payment', 'invoice', 'creator']);
        }, 3);
    }

    public function listLedgerEntries(Site $site, array $filters = []): array
    {
        $bookingId = $this->parsePositiveInt($filters['booking_id'] ?? null);
        $eventType = $this->nullableString($filters['event_type'] ?? null);
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 300));

        $query = BookingFinancialEntry::query()
            ->where('site_id', $site->id)
            ->with([
                'booking:id,site_id,booking_number,status,currency,customer_name,customer_email,starts_at',
                'invoice:id,site_id,booking_id,invoice_number,status,currency,grand_total,paid_total,outstanding_total',
                'payment:id,site_id,booking_id,invoice_id,provider,status,method,transaction_reference,amount,currency',
                'refund:id,site_id,booking_id,payment_id,invoice_id,status,amount,currency,reason',
                'creator:id,name',
                'lines' => fn ($lineQuery) => $lineQuery->orderBy('line_no')->orderBy('id'),
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($bookingId !== null) {
            $query->where('booking_id', $bookingId);
        }

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        $entries = $query->limit($limit)->get();
        $summary = BookingFinancialEntry::query()
            ->where('site_id', $site->id)
            ->when($bookingId !== null, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($eventType !== null, fn ($q) => $q->where('event_type', $eventType))
            ->selectRaw('COUNT(*) as entries_count, COALESCE(SUM(total_debit), 0) as total_debit, COALESCE(SUM(total_credit), 0) as total_credit')
            ->first();

        $totalDebit = $this->moneyFloat($summary?->total_debit ?? 0);
        $totalCredit = $this->moneyFloat($summary?->total_credit ?? 0);

        return [
            'site_id' => $site->id,
            'filters' => [
                'booking_id' => $bookingId,
                'event_type' => $eventType,
                'limit' => $limit,
            ],
            'summary' => [
                'entries_count' => (int) ($summary?->entries_count ?? 0),
                'total_debit' => $this->moneyString($totalDebit),
                'total_credit' => $this->moneyString($totalCredit),
                'difference' => $this->moneyString($totalDebit - $totalCredit),
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.009,
            ],
            'entries' => $entries
                ->map(fn (BookingFinancialEntry $entry): array => $this->serializeEntry($entry))
                ->values()
                ->all(),
        ];
    }

    public function reports(Site $site, array $filters = []): array
    {
        $resolvedFilters = $this->resolveReportingFilters($filters);
        $bookingScope = $this->bookingsReportScope($site, $resolvedFilters);
        $filteredBookingIds = (clone $bookingScope)->select('bookings.id');

        $summary = (clone $bookingScope)
            ->selectRaw('COUNT(*) as bookings_count, COALESCE(SUM(bookings.grand_total), 0) as revenue_total, COALESCE(SUM(bookings.paid_total), 0) as paid_total, COALESCE(SUM(bookings.outstanding_total), 0) as outstanding_total, COALESCE(SUM(bookings.discount_total), 0) as discount_total, COALESCE(SUM(bookings.tax_total), 0) as tax_total')
            ->first();

        $settledPaymentsTotal = BookingPayment::query()
            ->where('booking_payments.site_id', $site->id)
            ->whereIn('booking_payments.status', self::PAYMENT_REPORTABLE_STATUSES)
            ->whereIn('booking_payments.booking_id', (clone $filteredBookingIds))
            ->sum('booking_payments.amount');

        $settledRefundsTotal = BookingRefund::query()
            ->where('booking_refunds.site_id', $site->id)
            ->whereIn('booking_refunds.status', self::REFUND_SETTLED_STATUSES)
            ->whereIn('booking_refunds.booking_id', (clone $filteredBookingIds))
            ->sum('booking_refunds.amount');

        $top = (int) $resolvedFilters['top'];

        $serviceRows = (clone $bookingScope)
            ->leftJoin('booking_services as services', 'services.id', '=', 'bookings.service_id')
            ->selectRaw("bookings.service_id as dimension_key, COALESCE(services.name, 'Unassigned service') as dimension_label, COUNT(*) as bookings_count, COALESCE(SUM(bookings.grand_total), 0) as revenue_total, COALESCE(SUM(bookings.paid_total), 0) as paid_total, COALESCE(SUM(bookings.outstanding_total), 0) as outstanding_total")
            ->groupBy('bookings.service_id', 'services.name')
            ->orderByRaw('COALESCE(SUM(bookings.grand_total), 0) DESC')
            ->limit($top)
            ->get();

        $serviceRefundQuery = BookingRefund::query()
            ->where('booking_refunds.site_id', $site->id)
            ->whereIn('booking_refunds.status', self::REFUND_SETTLED_STATUSES)
            ->join('bookings', 'bookings.id', '=', 'booking_refunds.booking_id')
            ->where('bookings.site_id', $site->id);
        $this->applyBookingReportFilters($serviceRefundQuery, $resolvedFilters, 'bookings');

        $serviceRefundMap = $serviceRefundQuery
            ->selectRaw('bookings.service_id as dimension_key, COALESCE(SUM(booking_refunds.amount), 0) as refunds_total')
            ->groupBy('bookings.service_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$this->dimensionMapKey($row->dimension_key ?? null) => $this->moneyFloat($row->refunds_total ?? 0)])
            ->all();

        $staffRows = (clone $bookingScope)
            ->leftJoin('booking_staff_resources as staff_resources', 'staff_resources.id', '=', 'bookings.staff_resource_id')
            ->selectRaw("bookings.staff_resource_id as dimension_key, COALESCE(staff_resources.name, 'Unassigned staff/resource') as dimension_label, COUNT(*) as bookings_count, COALESCE(SUM(bookings.grand_total), 0) as revenue_total, COALESCE(SUM(bookings.paid_total), 0) as paid_total, COALESCE(SUM(bookings.outstanding_total), 0) as outstanding_total")
            ->groupBy('bookings.staff_resource_id', 'staff_resources.name')
            ->orderByRaw('COALESCE(SUM(bookings.grand_total), 0) DESC')
            ->limit($top)
            ->get();

        $staffRefundQuery = BookingRefund::query()
            ->where('booking_refunds.site_id', $site->id)
            ->whereIn('booking_refunds.status', self::REFUND_SETTLED_STATUSES)
            ->join('bookings', 'bookings.id', '=', 'booking_refunds.booking_id')
            ->where('bookings.site_id', $site->id);
        $this->applyBookingReportFilters($staffRefundQuery, $resolvedFilters, 'bookings');

        $staffRefundMap = $staffRefundQuery
            ->selectRaw('bookings.staff_resource_id as dimension_key, COALESCE(SUM(booking_refunds.amount), 0) as refunds_total')
            ->groupBy('bookings.staff_resource_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$this->dimensionMapKey($row->dimension_key ?? null) => $this->moneyFloat($row->refunds_total ?? 0)])
            ->all();

        $channelRows = (clone $bookingScope)
            ->selectRaw("COALESCE(NULLIF(bookings.source, ''), 'unknown') as dimension_key, COALESCE(NULLIF(bookings.source, ''), 'unknown') as dimension_label, COUNT(*) as bookings_count, COALESCE(SUM(bookings.grand_total), 0) as revenue_total, COALESCE(SUM(bookings.paid_total), 0) as paid_total, COALESCE(SUM(bookings.outstanding_total), 0) as outstanding_total")
            ->groupByRaw("COALESCE(NULLIF(bookings.source, ''), 'unknown')")
            ->orderByRaw('COALESCE(SUM(bookings.grand_total), 0) DESC')
            ->limit($top)
            ->get();

        $channelRefundQuery = BookingRefund::query()
            ->where('booking_refunds.site_id', $site->id)
            ->whereIn('booking_refunds.status', self::REFUND_SETTLED_STATUSES)
            ->join('bookings', 'bookings.id', '=', 'booking_refunds.booking_id')
            ->where('bookings.site_id', $site->id);
        $this->applyBookingReportFilters($channelRefundQuery, $resolvedFilters, 'bookings');

        $channelRefundMap = $channelRefundQuery
            ->selectRaw("COALESCE(NULLIF(bookings.source, ''), 'unknown') as dimension_key, COALESCE(SUM(booking_refunds.amount), 0) as refunds_total")
            ->groupByRaw("COALESCE(NULLIF(bookings.source, ''), 'unknown')")
            ->get()
            ->mapWithKeys(fn ($row): array => [$this->dimensionMapKey($row->dimension_key ?? null) => $this->moneyFloat($row->refunds_total ?? 0)])
            ->all();

        $bookingsCount = (int) ($summary?->bookings_count ?? 0);
        $revenueTotal = $this->moneyFloat($summary?->revenue_total ?? 0);
        $paidTotal = $this->moneyFloat($summary?->paid_total ?? 0);
        $outstandingTotal = $this->moneyFloat($summary?->outstanding_total ?? 0);
        $discountTotal = $this->moneyFloat($summary?->discount_total ?? 0);
        $taxTotal = $this->moneyFloat($summary?->tax_total ?? 0);

        return [
            'site_id' => $site->id,
            'filters' => $this->serializeReportingFilters($resolvedFilters),
            'summary' => [
                'bookings_count' => $bookingsCount,
                'revenue_total' => $this->moneyString($revenueTotal),
                'paid_total' => $this->moneyString($paidTotal),
                'outstanding_total' => $this->moneyString($outstandingTotal),
                'discount_total' => $this->moneyString($discountTotal),
                'tax_total' => $this->moneyString($taxTotal),
                'settled_payments_total' => $this->moneyString($settledPaymentsTotal),
                'refunds_total' => $this->moneyString($settledRefundsTotal),
                'net_collected_total' => $this->moneyString($settledPaymentsTotal - $settledRefundsTotal),
                'average_booking_value' => $bookingsCount > 0
                    ? $this->moneyString($revenueTotal / $bookingsCount)
                    : $this->moneyString(0),
            ],
            'groups' => [
                'services' => $this->formatDimensionRows($serviceRows->all(), $serviceRefundMap),
                'staff' => $this->formatDimensionRows($staffRows->all(), $staffRefundMap),
                'channels' => $this->formatDimensionRows($channelRows->all(), $channelRefundMap),
            ],
        ];
    }

    public function reconciliation(Site $site, array $filters = []): array
    {
        $resolvedFilters = $this->resolveReportingFilters($filters);
        $bookingScope = $this->bookingsReportScope($site, $resolvedFilters);
        $filteredBookingIds = (clone $bookingScope)->select('bookings.id');

        $entryTotals = BookingFinancialEntry::query()
            ->where('booking_financial_entries.site_id', $site->id)
            ->whereIn('booking_financial_entries.booking_id', (clone $filteredBookingIds))
            ->selectRaw('COUNT(*) as entries_count, COALESCE(SUM(booking_financial_entries.total_debit), 0) as total_debit, COALESCE(SUM(booking_financial_entries.total_credit), 0) as total_credit')
            ->first();

        $accounts = BookingFinancialEntryLine::query()
            ->where('booking_financial_entry_lines.site_id', $site->id)
            ->whereIn('booking_financial_entry_lines.booking_id', (clone $filteredBookingIds))
            ->selectRaw("booking_financial_entry_lines.account_code, booking_financial_entry_lines.account_name, COALESCE(SUM(CASE WHEN booking_financial_entry_lines.side = 'debit' THEN booking_financial_entry_lines.amount ELSE 0 END), 0) as debit_total, COALESCE(SUM(CASE WHEN booking_financial_entry_lines.side = 'credit' THEN booking_financial_entry_lines.amount ELSE 0 END), 0) as credit_total")
            ->groupBy('booking_financial_entry_lines.account_code', 'booking_financial_entry_lines.account_name')
            ->orderBy('booking_financial_entry_lines.account_code')
            ->get()
            ->map(function ($row): array {
                $debit = $this->moneyFloat($row->debit_total ?? 0);
                $credit = $this->moneyFloat($row->credit_total ?? 0);

                return [
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'debit_total' => $this->moneyString($debit),
                    'credit_total' => $this->moneyString($credit),
                    'net' => $this->moneyString($debit - $credit),
                ];
            })
            ->values()
            ->all();

        $receivableLine = collect($accounts)->firstWhere('account_code', self::ACCOUNT_RECEIVABLE);
        $accountsReceivableNet = $this->moneyFloat(
            is_array($receivableLine) ? ($receivableLine['net'] ?? 0) : 0
        );

        $bookingsOutstandingTotal = $this->moneyFloat(
            (clone $bookingScope)->sum('bookings.outstanding_total')
        );

        $invoicesOutstandingTotal = $this->moneyFloat(
            BookingInvoice::query()
                ->where('booking_invoices.site_id', $site->id)
                ->whereIn('booking_invoices.booking_id', (clone $filteredBookingIds))
                ->sum('booking_invoices.outstanding_total')
        );

        $settledPaymentsTotal = $this->moneyFloat(
            BookingPayment::query()
                ->where('booking_payments.site_id', $site->id)
                ->whereIn('booking_payments.status', self::PAYMENT_REPORTABLE_STATUSES)
                ->whereIn('booking_payments.booking_id', (clone $filteredBookingIds))
                ->sum('booking_payments.amount')
        );

        $settledRefundsTotal = $this->moneyFloat(
            BookingRefund::query()
                ->where('booking_refunds.site_id', $site->id)
                ->whereIn('booking_refunds.status', self::REFUND_SETTLED_STATUSES)
                ->whereIn('booking_refunds.booking_id', (clone $filteredBookingIds))
                ->sum('booking_refunds.amount')
        );

        $uninvoiced = (clone $bookingScope)
            ->whereDoesntHave('invoices')
            ->selectRaw('COUNT(*) as bookings_count, COALESCE(SUM(bookings.grand_total), 0) as revenue_total')
            ->first();

        $totalDebit = $this->moneyFloat($entryTotals?->total_debit ?? 0);
        $totalCredit = $this->moneyFloat($entryTotals?->total_credit ?? 0);

        return [
            'site_id' => $site->id,
            'filters' => $this->serializeReportingFilters($resolvedFilters),
            'summary' => [
                'entries_count' => (int) ($entryTotals?->entries_count ?? 0),
                'total_debit' => $this->moneyString($totalDebit),
                'total_credit' => $this->moneyString($totalCredit),
                'difference' => $this->moneyString($totalDebit - $totalCredit),
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.009,
                'accounts_receivable_net' => $this->moneyString($accountsReceivableNet),
                'bookings_outstanding_total' => $this->moneyString($bookingsOutstandingTotal),
                'invoices_outstanding_total' => $this->moneyString($invoicesOutstandingTotal),
                'outstanding_gap' => $this->moneyString($bookingsOutstandingTotal - $accountsReceivableNet),
                'settled_payments_total' => $this->moneyString($settledPaymentsTotal),
                'settled_refunds_total' => $this->moneyString($settledRefundsTotal),
                'net_collected_total' => $this->moneyString($settledPaymentsTotal - $settledRefundsTotal),
                'uninvoiced_bookings_count' => (int) ($uninvoiced?->bookings_count ?? 0),
                'uninvoiced_revenue_total' => $this->moneyString($uninvoiced?->revenue_total ?? 0),
            ],
            'accounts' => $accounts,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @param  array<string,mixed>  $meta
     */
    private function persistEntry(
        Site $site,
        Booking $booking,
        ?BookingInvoice $invoice,
        ?BookingPayment $payment,
        ?BookingRefund $refund,
        string $eventType,
        string $eventKey,
        string $currency,
        array $lines,
        ?string $description,
        array $meta = [],
        mixed $occurredAt = null,
        ?User $actor = null
    ): BookingFinancialEntry {
        $normalizedCurrency = $this->normalizeCurrency($currency);
        $normalizedLines = collect($lines)
            ->map(function (array $line) use ($normalizedCurrency): array {
                $amount = $this->moneyFloat($line['amount'] ?? 0);

                return [
                    'account_code' => (string) ($line['account_code'] ?? ''),
                    'account_name' => (string) ($line['account_name'] ?? ''),
                    'side' => (string) ($line['side'] ?? ''),
                    'amount' => $amount,
                    'currency' => $this->normalizeCurrency($line['currency'] ?? $normalizedCurrency),
                    'description' => $this->nullableString($line['description'] ?? null),
                    'meta_json' => is_array($line['meta_json'] ?? null) ? $line['meta_json'] : [],
                ];
            })
            ->filter(fn (array $line): bool => $line['amount'] > 0)
            ->values();

        if ($normalizedLines->isEmpty()) {
            throw new BookingDomainException('Financial entry requires at least one non-zero line.', 422);
        }

        $totalDebit = $this->moneyFloat(
            $normalizedLines->where('side', BookingFinancialEntryLine::SIDE_DEBIT)->sum('amount')
        );
        $totalCredit = $this->moneyFloat(
            $normalizedLines->where('side', BookingFinancialEntryLine::SIDE_CREDIT)->sum('amount')
        );

        if (abs($totalDebit - $totalCredit) >= 0.009) {
            throw new BookingDomainException('Financial entry is not balanced.', 422, [
                'total_debit' => $this->moneyString($totalDebit),
                'total_credit' => $this->moneyString($totalCredit),
            ]);
        }

        try {
            return DB::transaction(function () use (
                $site,
                $booking,
                $invoice,
                $payment,
                $refund,
                $eventType,
                $eventKey,
                $normalizedCurrency,
                $totalDebit,
                $totalCredit,
                $description,
                $meta,
                $occurredAt,
                $actor,
                $normalizedLines
            ): BookingFinancialEntry {
                $existing = BookingFinancialEntry::query()
                    ->where('site_id', $site->id)
                    ->where('event_key', $eventKey)
                    ->first();

                if ($existing) {
                    return $existing->load(['booking', 'invoice', 'payment', 'refund', 'creator', 'lines']);
                }

                $entry = BookingFinancialEntry::query()->create([
                    'site_id' => $site->id,
                    'booking_id' => $booking->id,
                    'invoice_id' => $invoice?->id,
                    'payment_id' => $payment?->id,
                    'refund_id' => $refund?->id,
                    'event_type' => $eventType,
                    'event_key' => $eventKey,
                    'currency' => $normalizedCurrency,
                    'total_debit' => $this->moneyString($totalDebit),
                    'total_credit' => $this->moneyString($totalCredit),
                    'description' => $description,
                    'meta_json' => $meta,
                    'created_by' => $actor?->id,
                    'occurred_at' => $occurredAt ?: now(),
                ]);

                foreach ($normalizedLines->values() as $index => $line) {
                    BookingFinancialEntryLine::query()->create([
                        'site_id' => $site->id,
                        'entry_id' => $entry->id,
                        'booking_id' => $booking->id,
                        'invoice_id' => $invoice?->id,
                        'payment_id' => $payment?->id,
                        'refund_id' => $refund?->id,
                        'line_no' => $index + 1,
                        'account_code' => $line['account_code'],
                        'account_name' => $line['account_name'],
                        'side' => $line['side'],
                        'amount' => $this->moneyString($line['amount']),
                        'currency' => $line['currency'],
                        'description' => $line['description'],
                        'meta_json' => $line['meta_json'],
                    ]);
                }

                return $entry->fresh(['booking', 'invoice', 'payment', 'refund', 'creator', 'lines']);
            }, 3);
        } catch (QueryException $exception) {
            $duplicateCode = (string) ($exception->errorInfo[1] ?? '');
            if (in_array($duplicateCode, ['1062', '1555', '2067'], true)) {
                $duplicate = BookingFinancialEntry::query()
                    ->where('site_id', $site->id)
                    ->where('event_key', $eventKey)
                    ->first();

                if ($duplicate) {
                    return $duplicate->load(['booking', 'invoice', 'payment', 'refund', 'creator', 'lines']);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string,mixed>  $totals
     */
    private function syncBookingTotals(Booking $booking, array $totals, ?User $actor): void
    {
        $updates = [
            'service_fee' => $this->moneyString($totals['service_fee'] ?? $booking->service_fee),
            'tax_total' => $this->moneyString($totals['tax_total'] ?? $booking->tax_total),
            'discount_total' => $this->moneyString($totals['discount_total'] ?? $booking->discount_total),
            'grand_total' => $this->moneyString($totals['grand_total'] ?? $booking->grand_total),
            'paid_total' => $this->moneyString(max(0, $this->moneyFloat($totals['paid_total'] ?? $booking->paid_total))),
            'outstanding_total' => $this->moneyString(max(0, $this->moneyFloat($totals['outstanding_total'] ?? $booking->outstanding_total))),
            'updated_by' => $actor?->id,
        ];

        $booking->update($updates);
    }

    private function applySettledPayment(Booking $booking, ?BookingInvoice $invoice, float $amount, ?User $actor): void
    {
        $grandTotal = $this->moneyFloat($booking->grand_total);
        $paidTotal = $this->moneyFloat($booking->paid_total) + $amount;
        $outstanding = max(0, $grandTotal - $paidTotal);

        $this->syncBookingTotals($booking, [
            'service_fee' => $booking->service_fee,
            'tax_total' => $booking->tax_total,
            'discount_total' => $booking->discount_total,
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstanding,
        ], $actor);

        if (! $invoice) {
            return;
        }

        $invoicePaid = $this->moneyFloat($invoice->paid_total) + $amount;
        $invoiceOutstanding = max(0, $this->moneyFloat($invoice->grand_total) - $invoicePaid);

        $invoice->update([
            'paid_total' => $this->moneyString($invoicePaid),
            'outstanding_total' => $this->moneyString($invoiceOutstanding),
            'status' => $invoiceOutstanding <= 0 ? 'paid' : ($invoicePaid > 0 ? 'partially_paid' : 'issued'),
            'paid_at' => $invoiceOutstanding <= 0 ? now() : $invoice->paid_at,
        ]);
    }

    private function applySettledRefund(
        Booking $booking,
        ?BookingInvoice $invoice,
        ?BookingPayment $payment,
        float $amount,
        ?User $actor
    ): void {
        $grandTotal = $this->moneyFloat($booking->grand_total);
        $paidTotal = max(0, $this->moneyFloat($booking->paid_total) - $amount);
        $outstanding = max(0, $grandTotal - $paidTotal);

        $this->syncBookingTotals($booking, [
            'service_fee' => $booking->service_fee,
            'tax_total' => $booking->tax_total,
            'discount_total' => $booking->discount_total,
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstanding,
        ], $actor);

        if ($invoice) {
            $invoicePaid = max(0, $this->moneyFloat($invoice->paid_total) - $amount);
            $invoiceOutstanding = max(0, $this->moneyFloat($invoice->grand_total) - $invoicePaid);

            $invoice->update([
                'paid_total' => $this->moneyString($invoicePaid),
                'outstanding_total' => $this->moneyString($invoiceOutstanding),
                'status' => $invoiceOutstanding <= 0 ? 'paid' : ($invoicePaid > 0 ? 'partially_paid' : 'issued'),
                'paid_at' => $invoiceOutstanding <= 0 ? ($invoice->paid_at ?: now()) : null,
            ]);
        }

        if ($payment) {
            $totalRefunded = BookingRefund::query()
                ->where('site_id', $payment->site_id)
                ->where('payment_id', $payment->id)
                ->whereIn('status', self::REFUND_SETTLED_STATUSES)
                ->sum('amount');

            $paymentAmount = $this->moneyFloat($payment->amount);
            $nextStatus = $payment->status;
            if ($totalRefunded >= $paymentAmount && $paymentAmount > 0) {
                $nextStatus = 'refunded';
            } elseif ($totalRefunded > 0) {
                $nextStatus = 'partially_refunded';
            }

            if ($nextStatus !== $payment->status) {
                $payment->update([
                    'status' => $nextStatus,
                ]);
            }
        }
    }

    private function resolveBooking(Site $site, Booking $booking): Booking
    {
        $target = Booking::query()
            ->where('site_id', $site->id)
            ->whereKey($booking->id)
            ->first();

        if (! $target) {
            throw (new ModelNotFoundException)->setModel(Booking::class, [$booking->id]);
        }

        return $target;
    }

    private function resolveInvoice(Site $site, Booking $booking, int $invoiceId): BookingInvoice
    {
        $invoice = BookingInvoice::query()
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->whereKey($invoiceId)
            ->first();

        if (! $invoice) {
            throw (new ModelNotFoundException)->setModel(BookingInvoice::class, [$invoiceId]);
        }

        return $invoice;
    }

    private function resolvePayment(Site $site, Booking $booking, int $paymentId): BookingPayment
    {
        $payment = BookingPayment::query()
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->whereKey($paymentId)
            ->first();

        if (! $payment) {
            throw (new ModelNotFoundException)->setModel(BookingPayment::class, [$paymentId]);
        }

        return $payment;
    }

    private function latestInvoice(Site $site, Booking $booking): ?BookingInvoice
    {
        return BookingInvoice::query()
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->first();
    }

    private function latestPayment(Site $site, Booking $booking): ?BookingPayment
    {
        return BookingPayment::query()
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['paid', 'completed', 'captured', 'settled', 'partially_refunded', 'refunded'])
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function generateInvoiceNumber(Site $site): string
    {
        do {
            $candidate = 'BKI-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
            $exists = BookingInvoice::query()
                ->where('site_id', $site->id)
                ->where('invoice_number', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }

    private function buildPaymentEventKey(BookingPayment $payment): string
    {
        $reference = $payment->transaction_reference
            ? preg_replace('/[^a-zA-Z0-9\-_:.]/', '_', $payment->transaction_reference)
            : null;

        if ($reference) {
            return sprintf('booking:%d:payment:%s', $payment->booking_id, $reference);
        }

        return sprintf('booking:%d:payment:%d', $payment->booking_id, $payment->id);
    }

    private function buildRefundEventKey(BookingRefund $refund, ?BookingPayment $payment): string
    {
        $base = sprintf(
            'booking:%d:refund:%s:%s',
            $refund->booking_id,
            $payment?->id ?: 'none',
            sha1($this->moneyString($refund->amount).'|'.($refund->reason ?? '').'|'.($refund->created_at?->toISOString() ?? now()->toISOString()))
        );

        return $base;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeInvoice(BookingInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'site_id' => $invoice->site_id,
            'booking_id' => $invoice->booking_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'subtotal' => (string) $invoice->subtotal,
            'tax_total' => (string) $invoice->tax_total,
            'discount_total' => (string) $invoice->discount_total,
            'grand_total' => (string) $invoice->grand_total,
            'paid_total' => (string) $invoice->paid_total,
            'outstanding_total' => (string) $invoice->outstanding_total,
            'issued_at' => $invoice->issued_at?->toISOString(),
            'due_at' => $invoice->due_at?->toISOString(),
            'paid_at' => $invoice->paid_at?->toISOString(),
            'voided_at' => $invoice->voided_at?->toISOString(),
            'meta_json' => $invoice->meta_json ?? [],
            'booking' => [
                'id' => $invoice->booking?->id,
                'booking_number' => $invoice->booking?->booking_number,
                'status' => $invoice->booking?->status,
                'customer_name' => $invoice->booking?->customer_name,
                'customer_email' => $invoice->booking?->customer_email,
                'starts_at' => $invoice->booking?->starts_at?->toISOString(),
            ],
            'created_by' => $invoice->created_by,
            'creator' => $invoice->creator ? [
                'id' => $invoice->creator->id,
                'name' => $invoice->creator->name,
            ] : null,
            'created_at' => $invoice->created_at?->toISOString(),
            'updated_at' => $invoice->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializePayment(BookingPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'site_id' => $payment->site_id,
            'booking_id' => $payment->booking_id,
            'invoice_id' => $payment->invoice_id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'method' => $payment->method,
            'transaction_reference' => $payment->transaction_reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'is_prepayment' => (bool) $payment->is_prepayment,
            'processed_at' => $payment->processed_at?->toISOString(),
            'meta_json' => $payment->meta_json ?? [],
            'universal_payment' => $this->universalPayments->normalizeBookingPayment($payment),
            'booking' => [
                'id' => $payment->booking?->id,
                'booking_number' => $payment->booking?->booking_number,
                'status' => $payment->booking?->status,
                'customer_name' => $payment->booking?->customer_name,
                'customer_email' => $payment->booking?->customer_email,
                'starts_at' => $payment->booking?->starts_at?->toISOString(),
            ],
            'invoice' => $payment->invoice ? [
                'id' => $payment->invoice->id,
                'invoice_number' => $payment->invoice->invoice_number,
                'status' => $payment->invoice->status,
                'grand_total' => (string) $payment->invoice->grand_total,
                'paid_total' => (string) $payment->invoice->paid_total,
                'outstanding_total' => (string) $payment->invoice->outstanding_total,
            ] : null,
            'created_by' => $payment->created_by,
            'creator' => $payment->creator ? [
                'id' => $payment->creator->id,
                'name' => $payment->creator->name,
            ] : null,
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeRefund(BookingRefund $refund): array
    {
        return [
            'id' => $refund->id,
            'site_id' => $refund->site_id,
            'booking_id' => $refund->booking_id,
            'payment_id' => $refund->payment_id,
            'invoice_id' => $refund->invoice_id,
            'status' => $refund->status,
            'reason' => $refund->reason,
            'amount' => (string) $refund->amount,
            'currency' => $refund->currency,
            'processed_at' => $refund->processed_at?->toISOString(),
            'meta_json' => $refund->meta_json ?? [],
            'booking' => [
                'id' => $refund->booking?->id,
                'booking_number' => $refund->booking?->booking_number,
                'status' => $refund->booking?->status,
                'customer_name' => $refund->booking?->customer_name,
                'customer_email' => $refund->booking?->customer_email,
                'starts_at' => $refund->booking?->starts_at?->toISOString(),
            ],
            'invoice' => $refund->invoice ? [
                'id' => $refund->invoice->id,
                'invoice_number' => $refund->invoice->invoice_number,
                'status' => $refund->invoice->status,
                'grand_total' => (string) $refund->invoice->grand_total,
                'paid_total' => (string) $refund->invoice->paid_total,
                'outstanding_total' => (string) $refund->invoice->outstanding_total,
            ] : null,
            'payment' => $refund->payment ? [
                'id' => $refund->payment->id,
                'provider' => $refund->payment->provider,
                'status' => $refund->payment->status,
                'method' => $refund->payment->method,
                'transaction_reference' => $refund->payment->transaction_reference,
                'amount' => (string) $refund->payment->amount,
            ] : null,
            'created_by' => $refund->created_by,
            'creator' => $refund->creator ? [
                'id' => $refund->creator->id,
                'name' => $refund->creator->name,
            ] : null,
            'created_at' => $refund->created_at?->toISOString(),
            'updated_at' => $refund->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeEntry(BookingFinancialEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'site_id' => $entry->site_id,
            'booking_id' => $entry->booking_id,
            'invoice_id' => $entry->invoice_id,
            'payment_id' => $entry->payment_id,
            'refund_id' => $entry->refund_id,
            'event_type' => $entry->event_type,
            'event_key' => $entry->event_key,
            'currency' => $entry->currency,
            'total_debit' => (string) $entry->total_debit,
            'total_credit' => (string) $entry->total_credit,
            'description' => $entry->description,
            'meta_json' => $entry->meta_json ?? [],
            'occurred_at' => $entry->occurred_at?->toISOString(),
            'booking' => [
                'id' => $entry->booking?->id,
                'booking_number' => $entry->booking?->booking_number,
                'status' => $entry->booking?->status,
                'customer_name' => $entry->booking?->customer_name,
                'customer_email' => $entry->booking?->customer_email,
                'starts_at' => $entry->booking?->starts_at?->toISOString(),
            ],
            'invoice' => $entry->invoice ? [
                'id' => $entry->invoice->id,
                'invoice_number' => $entry->invoice->invoice_number,
                'status' => $entry->invoice->status,
            ] : null,
            'payment' => $entry->payment ? [
                'id' => $entry->payment->id,
                'provider' => $entry->payment->provider,
                'status' => $entry->payment->status,
                'method' => $entry->payment->method,
                'transaction_reference' => $entry->payment->transaction_reference,
            ] : null,
            'refund' => $entry->refund ? [
                'id' => $entry->refund->id,
                'status' => $entry->refund->status,
                'reason' => $entry->refund->reason,
                'amount' => (string) $entry->refund->amount,
            ] : null,
            'created_by' => $entry->created_by,
            'creator' => $entry->creator ? [
                'id' => $entry->creator->id,
                'name' => $entry->creator->name,
            ] : null,
            'lines' => $entry->lines
                ->map(fn (BookingFinancialEntryLine $line): array => [
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
            'created_at' => $entry->created_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
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
            'amount' => $this->moneyFloat($amount),
            'description' => $description,
            'meta_json' => $meta,
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{date_from:?CarbonImmutable,date_to:?CarbonImmutable,service_id:?int,staff_resource_id:?int,source:?string,top:int}
     */
    private function resolveReportingFilters(array $filters): array
    {
        $dateFrom = $this->parseDateBoundary($filters['date_from'] ?? null, false);
        $dateTo = $this->parseDateBoundary($filters['date_to'] ?? null, true);
        $source = $this->nullableString($filters['source'] ?? null);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'service_id' => $this->parsePositiveInt($filters['service_id'] ?? null),
            'staff_resource_id' => $this->parsePositiveInt($filters['staff_resource_id'] ?? null),
            'source' => $source !== null ? strtolower($source) : null,
            'top' => max(1, min((int) ($filters['top'] ?? 10), 50)),
        ];
    }

    /**
     * @param  array{date_from:?CarbonImmutable,date_to:?CarbonImmutable,service_id:?int,staff_resource_id:?int,source:?string,top:int}  $filters
     * @return Builder<Booking>
     */
    private function bookingsReportScope(Site $site, array $filters): Builder
    {
        $query = Booking::query()->where('bookings.site_id', $site->id);
        $this->applyBookingReportFilters($query, $filters, 'bookings');

        return $query;
    }

    /**
     * @param  array{date_from:?CarbonImmutable,date_to:?CarbonImmutable,service_id:?int,staff_resource_id:?int,source:?string,top:int}  $filters
     */
    private function applyBookingReportFilters(mixed $query, array $filters, string $table = 'bookings'): void
    {
        if ($filters['service_id'] !== null) {
            $query->where($table.'.service_id', $filters['service_id']);
        }

        if ($filters['staff_resource_id'] !== null) {
            $query->where($table.'.staff_resource_id', $filters['staff_resource_id']);
        }

        if ($filters['source'] !== null) {
            $query->where($table.'.source', $filters['source']);
        }

        if ($filters['date_from'] !== null) {
            $query->where($table.'.starts_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->where($table.'.starts_at', '<=', $filters['date_to']);
        }
    }

    private function parseDateBoundary(mixed $value, bool $endOfDay): ?CarbonImmutable
    {
        $resolved = $this->nullableString($value);
        if ($resolved === null) {
            return null;
        }

        $date = CarbonImmutable::parse($resolved);

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function serializeReportingFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from']?->toDateString(),
            'date_to' => $filters['date_to']?->toDateString(),
            'service_id' => $filters['service_id'],
            'staff_resource_id' => $filters['staff_resource_id'],
            'source' => $filters['source'],
            'top' => $filters['top'],
        ];
    }

    /**
     * @param  array<int,mixed>  $rows
     * @param  array<string,float>  $refundMap
     * @return array<int,array<string,mixed>>
     */
    private function formatDimensionRows(array $rows, array $refundMap): array
    {
        return collect($rows)
            ->map(function ($row) use ($refundMap): array {
                $dimensionKey = $this->dimensionMapKey($row->dimension_key ?? null);
                $revenue = $this->moneyFloat($row->revenue_total ?? 0);
                $paid = $this->moneyFloat($row->paid_total ?? 0);
                $outstanding = $this->moneyFloat($row->outstanding_total ?? 0);
                $refunds = $this->moneyFloat($refundMap[$dimensionKey] ?? 0);

                return [
                    'key' => $dimensionKey,
                    'label' => (string) ($row->dimension_label ?? 'Unknown'),
                    'bookings_count' => (int) ($row->bookings_count ?? 0),
                    'revenue_total' => $this->moneyString($revenue),
                    'paid_total' => $this->moneyString($paid),
                    'outstanding_total' => $this->moneyString($outstanding),
                    'refunds_total' => $this->moneyString($refunds),
                    'net_collected_total' => $this->moneyString($paid - $refunds),
                ];
            })
            ->values()
            ->all();
    }

    private function dimensionMapKey(mixed $value): string
    {
        if ($value === null) {
            return '__unknown__';
        }

        if (is_string($value) && trim($value) === '') {
            return '__unknown__';
        }

        return (string) $value;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_numeric($value)) {
            $parsed = (int) $value;

            return $parsed > 0 ? $parsed : null;
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

    private function normalizeCurrency(mixed $currency): string
    {
        $resolved = strtoupper(trim((string) ($currency ?? '')));

        return $resolved !== '' ? $resolved : 'GEL';
    }

    private function moneyFloat(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->moneyFloat($value), 2, '.', '');
    }
}
