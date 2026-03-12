<?php

namespace Tests\Feature\Billing;

use App\Events\Builder\BuilderCompleteEvent;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BuildCreditUsage;
use App\Models\EcommerceOrder;
use App\Models\OperationLog;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingUsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_usage_stats_endpoint_returns_unified_monthly_metering(): void
    {
        $plan = Plan::factory()->create([
            'monthly_build_credits' => 10000,
            'max_monthly_orders' => 5,
            'max_monthly_bookings' => 3,
        ]);

        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published('metering-owner')->create();
        $site = $project->site()->firstOrFail();

        BuildCreditUsage::query()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'model' => 'gpt-5.2',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'estimated_cost' => 0.0025,
            'action' => 'generate',
            'used_own_api_key' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Legacy action should still count as "generate".
        BuildCreditUsage::query()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'model' => 'gpt-5.2',
            'prompt_tokens' => 80,
            'completion_tokens' => 40,
            'total_tokens' => 120,
            'estimated_cost' => 0.0019,
            'action' => 'build',
            'used_own_api_key' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BuildCreditUsage::query()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'model' => 'gpt-5.2',
            'prompt_tokens' => 50,
            'completion_tokens' => 30,
            'total_tokens' => 80,
            'estimated_cost' => 0.0012,
            'action' => 'edit',
            'used_own_api_key' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OperationLog::query()->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'channel' => OperationLog::CHANNEL_BUILD,
            'event' => 'preview_build_completed',
            'status' => OperationLog::STATUS_SUCCESS,
            'message' => 'Preview build completed.',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-H2-001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '30.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '30.00',
            'paid_total' => '0.00',
            'outstanding_total' => '30.00',
            'placed_at' => now(),
        ]);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 30,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '50.00',
            'currency' => 'GEL',
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-H2-001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'panel',
            'customer_email' => 'customer@example.com',
            'starts_at' => now()->addHours(1),
            'ends_at' => now()->addHours(2),
            'collision_starts_at' => now()->addHours(1),
            'collision_ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '50.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '50.00',
            'paid_total' => '0.00',
            'outstanding_total' => '50.00',
            'currency' => 'GEL',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('billing.usage.stats'))
            ->assertOk()
            ->assertJsonPath('metering.ai_operations.generate', 2)
            ->assertJsonPath('metering.ai_operations.edit', 1)
            ->assertJsonPath('metering.ai_operations.rebuild', 1)
            ->assertJsonPath('metering.ai_operations.total', 4);
        $orders = (int) ($response->json('metering.commerce.orders') ?? 0);
        $bookings = (int) ($response->json('metering.booking.bookings') ?? 0);
        $this->assertGreaterThanOrEqual(1, $orders, 'Metering should include at least the test order (demo may add more)');
        $this->assertGreaterThanOrEqual(1, $bookings, 'Metering should include at least the test booking (demo may add more)');
        $this->assertSame(5, (int) ($response->json('metering.commerce.orders_limit')));
        $this->assertSame(3, (int) ($response->json('metering.booking.bookings_limit')));
    }

    public function test_builder_complete_usage_is_classified_generate_then_edit(): void
    {
        $plan = Plan::factory()->create([
            'monthly_build_credits' => 50000,
        ]);

        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->create([
            'build_session_id' => 'sess-generate',
            'conversation_history' => [
                [
                    'role' => 'user',
                    'content' => 'Create my first site',
                    'timestamp' => now()->toISOString(),
                ],
            ],
        ]);

        BuilderCompleteEvent::dispatch(
            'sess-generate',
            'evt-generate-001',
            2,
            400,
            true,
            240,
            160,
            'gpt-5.2',
            'completed',
            'ok',
            true
        );

        $project->update([
            'build_session_id' => 'sess-edit',
            'conversation_history' => [
                [
                    'role' => 'user',
                    'content' => 'Create my first site',
                    'timestamp' => now()->subMinute()->toISOString(),
                ],
                [
                    'role' => 'assistant',
                    'content' => 'Initial version ready',
                    'timestamp' => now()->subSeconds(40)->toISOString(),
                ],
                [
                    'role' => 'user',
                    'content' => 'Change header to blue',
                    'timestamp' => now()->toISOString(),
                ],
            ],
        ]);

        BuilderCompleteEvent::dispatch(
            'sess-edit',
            'evt-edit-001',
            1,
            220,
            true,
            120,
            100,
            'gpt-5.2',
            'completed',
            'ok',
            true
        );

        $actions = BuildCreditUsage::query()
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame(['generate', 'edit'], $actions);
    }
}
