<?php

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingAdvancedAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_advanced_ops_permissions_and_finance_acceptance_flow(): void
    {
        $owner = User::factory()->create();
        $receptionist = User::factory()->create();
        $staffUser = User::factory()->create();

        [$project, $site] = $this->createPublishedProjectWithSite($owner, true);
        $project->sharedWith()->syncWithoutDetaching([
            $receptionist->id => ['permission' => 'edit'],
            $staffUser->id => ['permission' => 'edit'],
        ]);

        $this->assignRole($site, $receptionist, 'receptionist', $owner);
        $this->assignRole($site, $staffUser, 'staff', $owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Advanced Consultation',
            'slug' => 'advanced-consultation-'.Str::lower(Str::random(6)),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 2,
            'requires_staff' => true,
            'price' => '100.00',
            'currency' => 'GEL',
        ]);

        $resource = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Resource One',
            'slug' => 'resource-one-'.Str::lower(Str::random(6)),
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $createdResponse = $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $resource->id,
                'starts_at' => '2026-11-10 10:00:00',
                'duration_minutes' => 60,
                'timezone' => 'Asia/Tbilisi',
                'customer_name' => 'Advanced Customer',
                'customer_email' => 'advanced@example.com',
                'source' => 'panel',
                'service_fee' => '100.00',
                'grand_total' => '100.00',
                'paid_total' => '0.00',
                'outstanding_total' => '100.00',
            ])
            ->assertCreated()
            ->assertJsonPath('booking.status', Booking::STATUS_PENDING);

        $bookingId = (int) $createdResponse->json('booking.id');
        $this->assertGreaterThan(0, $bookingId);

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $resource->id,
                'starts_at' => '2026-11-10 10:30:00',
                'duration_minutes' => 30,
                'timezone' => 'Asia/Tbilisi',
                'customer_email' => 'collision@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'slot_collision');

        $invoiceId = (int) $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.invoices.store', [
                'site' => $site->id,
                'booking' => $bookingId,
            ]), [])
            ->assertCreated()
            ->json('invoice.id');

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.finance.payments.store', [
                'site' => $site->id,
                'booking' => $bookingId,
            ]), [
                'invoice_id' => $invoiceId,
                'amount' => '60.00',
                'status' => 'paid',
                'method' => 'cash',
            ])
            ->assertForbidden();

        $paymentId = (int) $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.finance.payments.store', [
                'site' => $site->id,
                'booking' => $bookingId,
            ]), [
                'invoice_id' => $invoiceId,
                'amount' => '60.00',
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
                'booking' => $bookingId,
            ]), [
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'amount' => '15.00',
                'status' => 'completed',
                'reason' => 'Adjustment',
                'currency' => 'GEL',
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.finance.reports', [
                'site' => $site->id,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-30',
            ]))
            ->assertOk()
            ->assertJsonPath('summary.bookings_count', 1)
            ->assertJsonPath('summary.revenue_total', '100.00')
            ->assertJsonPath('summary.settled_payments_total', '60.00')
            ->assertJsonPath('summary.refunds_total', '15.00')
            ->assertJsonPath('summary.net_collected_total', '45.00')
            ->assertJsonPath('summary.outstanding_total', '55.00');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.finance.reconciliation', [
                'site' => $site->id,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-30',
            ]))
            ->assertOk()
            ->assertJsonPath('summary.entries_count', 3)
            ->assertJsonPath('summary.is_balanced', true)
            ->assertJsonPath('summary.bookings_outstanding_total', '55.00')
            ->assertJsonPath('summary.invoices_outstanding_total', '55.00')
            ->assertJsonPath('summary.settled_payments_total', '60.00')
            ->assertJsonPath('summary.settled_refunds_total', '15.00')
            ->assertJsonPath('summary.net_collected_total', '45.00');

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.finance.reports', ['site' => $site->id]))
            ->assertForbidden();
    }

    /**
     * @return array{0:Project,1:Site}
     */
    private function createPublishedProjectWithSite(User $owner, bool $enableBooking): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $modules = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $modules['booking'] = $enableBooking;
        $settings['modules'] = $modules;

        $site->update([
            'theme_settings' => $settings,
        ]);

        return [$project, $site];
    }

    private function assignRole(Site $site, User $user, string $roleKey, User $actor): void
    {
        app(BookingAuthorizationServiceContract::class)->assignRole($site, $user, $roleKey, $actor);
    }
}
