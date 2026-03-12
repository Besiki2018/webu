<?php

namespace App\Booking\Contracts;

use App\Models\Booking;
use App\Models\BookingInvoice;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\Site;
use App\Models\User;

interface BookingFinanceServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listInvoices(Site $site, array $filters = []): array;

    public function issueInvoice(Site $site, Booking $booking, array $payload = [], ?User $actor = null): BookingInvoice;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listPayments(Site $site, array $filters = []): array;

    public function recordPayment(Site $site, Booking $booking, array $payload, ?User $actor = null): BookingPayment;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listRefunds(Site $site, array $filters = []): array;

    public function recordRefund(Site $site, Booking $booking, array $payload, ?User $actor = null): BookingRefund;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listLedgerEntries(Site $site, array $filters = []): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function reports(Site $site, array $filters = []): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function reconciliation(Site $site, array $filters = []): array;
}
