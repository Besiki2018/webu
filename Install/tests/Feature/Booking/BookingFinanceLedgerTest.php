<?php

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Models\Booking;
use App\Models\BookingInvoice;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingFinanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_issue_invoice_record_payment_refund_and_keep_ledger_balanced(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);
        $booking = $this->createBooking($site, [
            'service_fee' => '100.00',
            'tax_total' => '18.00',
            'discount_total' => '5.00',
            'grand_total' => '113.00',
            'paid_total' => '20.00',
            'outstanding_total' => '93.00',
        ]);

        $invoiceResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.invoices.store', [
                'site' => $site->id,
                'booking' => $booking->id,
            ]), [])
            ->assertCreated()
            ->assertJsonPath('invoice.booking_id', $booking->id)
            ->assertJsonPath('invoice.grand_total', '113.00');

        $invoiceId = (int) $invoiceResponse->json('invoice.id');
        $this->assertGreaterThan(0, $invoiceId);

        $paymentResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.payments.store', [
                'site' => $site->id,
                'booking' => $booking->id,
            ]), [
                'invoice_id' => $invoiceId,
                'provider' => 'manual',
                'method' => 'cash',
                'status' => 'paid',
                'amount' => '50.00',
                'currency' => 'GEL',
                'transaction_reference' => 'PMT-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.booking_id', $booking->id)
            ->assertJsonPath('payment.amount', '50.00');

        $paymentId = (int) $paymentResponse->json('payment.id');
        $this->assertGreaterThan(0, $paymentId);

        $refundResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.refunds.store', [
                'site' => $site->id,
                'booking' => $booking->id,
            ]), [
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'status' => 'completed',
                'reason' => 'Customer adjustment',
                'amount' => '10.00',
                'currency' => 'GEL',
            ])
            ->assertCreated()
            ->assertJsonPath('refund.booking_id', $booking->id)
            ->assertJsonPath('refund.amount', '10.00');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.finance.ledger.index', [
                'site' => $site->id,
                'booking_id' => $booking->id,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.entries_count', 3)
            ->assertJsonPath('summary.is_balanced', true);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'paid_total' => '60.00',
            'outstanding_total' => '53.00',
        ]);

        $this->assertDatabaseHas('booking_invoices', [
            'id' => $invoiceId,
            'paid_total' => '60.00',
            'outstanding_total' => '53.00',
        ]);

        $entryTotals = DB::table('booking_financial_entries')
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->selectRaw('COALESCE(SUM(total_debit), 0) as debit, COALESCE(SUM(total_credit), 0) as credit')
            ->first();

        $this->assertNotNull($entryTotals);
        $this->assertSame(
            number_format((float) ($entryTotals->debit ?? 0), 2, '.', ''),
            number_format((float) ($entryTotals->credit ?? 0), 2, '.', '')
        );
    }

    public function test_owner_can_view_finance_reports_and_reconciliation_by_service_staff_and_channel(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $servicePublic = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Public Intake',
            'slug' => 'public-intake-'.Str::lower(Str::random(6)),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => true,
            'price' => '120.00',
            'currency' => 'GEL',
        ]);

        $servicePanel = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Panel Intake',
            'slug' => 'panel-intake-'.Str::lower(Str::random(6)),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => true,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $staffOne = $this->createStaffResource($site, 'Doctor One');
        $staffTwo = $this->createStaffResource($site, 'Doctor Two');

        $publicBooking = $this->createBooking($site, [
            'service_id' => $servicePublic->id,
            'staff_resource_id' => $staffOne->id,
            'source' => 'public',
            'starts_at' => '2026-09-11 10:00:00',
            'ends_at' => '2026-09-11 11:00:00',
            'collision_starts_at' => '2026-09-11 10:00:00',
            'collision_ends_at' => '2026-09-11 11:00:00',
            'service_fee' => '120.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '120.00',
            'paid_total' => '0.00',
            'outstanding_total' => '120.00',
        ]);

        $panelBooking = $this->createBooking($site, [
            'service_id' => $servicePanel->id,
            'staff_resource_id' => $staffTwo->id,
            'source' => 'panel',
            'starts_at' => '2026-09-12 13:00:00',
            'ends_at' => '2026-09-12 14:00:00',
            'collision_starts_at' => '2026-09-12 13:00:00',
            'collision_ends_at' => '2026-09-12 14:00:00',
            'service_fee' => '80.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '0.00',
            'outstanding_total' => '80.00',
        ]);

        $publicInvoiceId = (int) $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.invoices.store', [
                'site' => $site->id,
                'booking' => $publicBooking->id,
            ]), [])
            ->assertCreated()
            ->json('invoice.id');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.invoices.store', [
                'site' => $site->id,
                'booking' => $panelBooking->id,
            ]), [])
            ->assertCreated();

        $publicPaymentId = (int) $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.payments.store', [
                'site' => $site->id,
                'booking' => $publicBooking->id,
            ]), [
                'invoice_id' => $publicInvoiceId,
                'amount' => '40.00',
                'status' => 'paid',
                'provider' => 'manual',
                'method' => 'cash',
                'currency' => 'GEL',
            ])
            ->assertCreated()
            ->json('payment.id');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.refunds.store', [
                'site' => $site->id,
                'booking' => $publicBooking->id,
            ]), [
                'invoice_id' => $publicInvoiceId,
                'payment_id' => $publicPaymentId,
                'amount' => '10.00',
                'status' => 'completed',
                'reason' => 'Partial return',
                'currency' => 'GEL',
            ])
            ->assertCreated();

        $reportResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.finance.reports', [
                'site' => $site->id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertJsonPath('summary.bookings_count', 2)
            ->assertJsonPath('summary.revenue_total', '200.00')
            ->assertJsonPath('summary.settled_payments_total', '40.00')
            ->assertJsonPath('summary.refunds_total', '10.00')
            ->assertJsonPath('summary.net_collected_total', '30.00')
            ->assertJsonPath('summary.outstanding_total', '170.00');

        $reportPayload = $reportResponse->json();
        $this->assertTrue(collect($reportPayload['groups']['services'] ?? [])->contains(
            fn (array $row): bool => ($row['label'] ?? null) === $servicePublic->name && ($row['bookings_count'] ?? null) === 1
        ));
        $this->assertTrue(collect($reportPayload['groups']['staff'] ?? [])->contains(
            fn (array $row): bool => ($row['label'] ?? null) === $staffOne->name && ($row['bookings_count'] ?? null) === 1
        ));
        $this->assertTrue(collect($reportPayload['groups']['channels'] ?? [])->contains(
            fn (array $row): bool => ($row['label'] ?? null) === 'public' && ($row['bookings_count'] ?? null) === 1
        ));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.finance.reconciliation', [
                'site' => $site->id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertJsonPath('summary.entries_count', 4)
            ->assertJsonPath('summary.is_balanced', true)
            ->assertJsonPath('summary.bookings_outstanding_total', '170.00')
            ->assertJsonPath('summary.invoices_outstanding_total', '170.00')
            ->assertJsonPath('summary.settled_payments_total', '40.00')
            ->assertJsonPath('summary.settled_refunds_total', '10.00')
            ->assertJsonPath('summary.net_collected_total', '30.00');
    }

    public function test_receptionist_can_view_finance_but_cannot_record_financial_actions(): void
    {
        $owner = User::factory()->create();
        $receptionist = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);
        $project->sharedWith()->syncWithoutDetaching([$receptionist->id => ['permission' => 'edit']]);
        $this->assignRole($site, $receptionist, 'receptionist', $owner);

        $booking = $this->createBooking($site);
        $invoice = BookingInvoice::query()->create([
            'site_id' => $site->id,
            'booking_id' => $booking->id,
            'invoice_number' => 'BKI-TEST-1001',
            'status' => 'issued',
            'currency' => 'GEL',
            'subtotal' => '80.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '0.00',
            'outstanding_total' => '80.00',
            'issued_at' => now(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($receptionist)
            ->getJson(route('panel.sites.booking.finance.invoices.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('invoices.0.id', $invoice->id);

        $this->actingAs($receptionist)
            ->getJson(route('panel.sites.booking.finance.reports', ['site' => $site->id]))
            ->assertOk();

        $this->actingAs($receptionist)
            ->getJson(route('panel.sites.booking.finance.reconciliation', ['site' => $site->id]))
            ->assertOk();

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.finance.payments.store', [
                'site' => $site->id,
                'booking' => $booking->id,
            ]), [
                'invoice_id' => $invoice->id,
                'amount' => '20.00',
                'status' => 'paid',
                'method' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_staff_role_cannot_view_finance_endpoints(): void
    {
        $owner = User::factory()->create();
        $staffUser = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);
        $project->sharedWith()->syncWithoutDetaching([$staffUser->id => ['permission' => 'edit']]);
        $this->assignRole($site, $staffUser, 'staff', $owner);

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.finance.invoices.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.finance.ledger.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.finance.reports', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.finance.reconciliation', ['site' => $site->id]))
            ->assertForbidden();
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createBooking(Site $site, array $overrides = []): Booking
    {
        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation '.Str::lower(Str::random(4)),
            'slug' => 'consultation-'.Str::lower(Str::random(8)),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 2,
            'requires_staff' => false,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        return Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-FIN-'.strtoupper(Str::random(6)),
            'status' => Booking::STATUS_CONFIRMED,
            'source' => 'panel',
            'customer_name' => 'Finance Customer',
            'customer_email' => 'finance@example.com',
            'starts_at' => '2026-09-10 10:00:00',
            'ends_at' => '2026-09-10 11:00:00',
            'collision_starts_at' => '2026-09-10 10:00:00',
            'collision_ends_at' => '2026-09-10 11:00:00',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '80.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '0.00',
            'outstanding_total' => '80.00',
            'currency' => 'GEL',
            ...$overrides,
        ]);
    }

    /**
     * @return array{0:Project,1:Site}
     */
    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function assignRole(Site $site, User $user, string $roleKey, User $actor): void
    {
        app(BookingAuthorizationServiceContract::class)->assignRole($site, $user, $roleKey, $actor);
    }

    private function createStaffResource(Site $site, string $name): BookingStaffResource
    {
        return BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => 'staff',
            'status' => 'active',
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
            'buffer_minutes' => 0,
        ]);
    }
}
