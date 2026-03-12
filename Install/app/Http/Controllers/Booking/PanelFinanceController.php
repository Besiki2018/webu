<?php

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Contracts\BookingFinanceServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Booking\Support\BookingPermissions;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelFinanceController extends Controller
{
    public function __construct(
        protected BookingFinanceServiceContract $finance,
        protected BookingAuthorizationServiceContract $bookingAuthorization
    ) {}

    public function invoices(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'booking_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:30'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        return response()->json($this->finance->listInvoices($site, $validated));
    }

    public function issueInvoice(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_MANAGE);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['draft', 'issued', 'partially_paid', 'paid', 'void', 'cancelled'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'paid_total' => ['nullable', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $invoice = $this->finance->issueInvoice($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking invoice issued successfully.',
            'invoice' => $invoice,
        ], 201);
    }

    public function payments(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'booking_id' => ['nullable', 'integer', 'min:1'],
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:30'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        return response()->json($this->finance->listPayments($site, $validated));
    }

    public function recordPayment(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_MANAGE);

        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'])],
            'method' => ['nullable', 'string', Rule::in(['cash', 'card', 'online', 'bank_transfer', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_prepayment' => ['nullable', 'boolean'],
            'raw_payload_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payment = $this->finance->recordPayment($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking payment recorded successfully.',
            'payment' => $payment,
        ], 201);
    }

    public function refunds(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'booking_id' => ['nullable', 'integer', 'min:1'],
            'payment_id' => ['nullable', 'integer', 'min:1'],
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:30'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        return response()->json($this->finance->listRefunds($site, $validated));
    }

    public function recordRefund(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_MANAGE);

        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'payment_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'completed', 'failed', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'raw_payload_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $refund = $this->finance->recordRefund($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking refund recorded successfully.',
            'refund' => $refund,
        ], 201);
    }

    public function ledger(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'booking_id' => ['nullable', 'integer', 'min:1'],
            'event_type' => ['nullable', 'string', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        return response()->json($this->finance->listLedgerEntries($site, $validated));
    }

    public function reports(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:30'],
            'top' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->finance->reports($site, $validated));
    }

    public function reconciliation(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::FINANCE_VIEW);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($this->finance->reconciliation($site, $validated));
    }
}
