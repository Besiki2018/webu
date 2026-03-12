<?php

namespace Tests\Feature\Platform;

use App\Models\Project;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniversalPaymentsShippingNormalizationTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_shipping_normalization_tables_exist_with_canonical_columns(): void
    {
        foreach ([
            'payment_methods',
            'payments',
            'payment_webhooks',
            'shipping_methods',
            'shipping_zones',
            'shipping_zone_regions',
            'shipping_rates',
            'coupons',
            'coupon_redemptions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('payment_methods', [
            'tenant_id', 'project_id', 'code', 'name', 'status', 'config_json',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'tenant_id', 'project_id', 'payable_type', 'payable_id', 'method_code', 'amount', 'currency', 'status', 'transaction_id', 'provider_payload',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_webhooks', [
            'provider', 'event_id', 'payload_json', 'status', 'processed_at', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns('shipping_methods', [
            'tenant_id', 'project_id', 'code', 'name', 'status', 'sort_order',
        ]));
        $this->assertTrue(Schema::hasColumns('shipping_zones', [
            'tenant_id', 'project_id', 'name',
        ]));
        $this->assertTrue(Schema::hasColumns('shipping_zone_regions', [
            'zone_id', 'country', 'city', 'zip_from', 'zip_to',
        ]));
        $this->assertTrue(Schema::hasColumns('shipping_rates', [
            'method_id', 'zone_id', 'rule_type', 'min_value', 'max_value', 'price', 'eta_days',
        ]));
        $this->assertTrue(Schema::hasColumns('coupons', [
            'tenant_id', 'project_id', 'code', 'type', 'value', 'min_order', 'usage_limit', 'used_count', 'expires_at', 'status', 'meta_json',
        ]));
        $this->assertTrue(Schema::hasColumns('coupon_redemptions', [
            'coupon_id', 'customer_id', 'order_id', 'created_at',
        ]));
    }

    public function test_payments_shipping_normalization_tables_support_relational_insert_flow(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Payments Shipping Tenant',
            'slug' => 'payship-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'ecommerce',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $site = Site::query()->where('project_id', (string) $project->id)->first();
        $this->assertNotNull($site, 'Project observer/provisioning should create a site in test baseline.');

        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Checkout Customer',
            'email' => 'checkout@example.test',
            'phone' => '+995555000222',
            'password_hash' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentMethodId = DB::table('payment_methods')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'stripe',
            'name' => 'Stripe',
            'status' => 'active',
            'config_json' => json_encode(['mode' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shippingMethodId = DB::table('shipping_methods')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'courier',
            'name' => 'Courier',
            'status' => 'active',
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zoneId = DB::table('shipping_zones')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Tbilisi Zone',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shipping_zone_regions')->insert([
            'zone_id' => $zoneId,
            'country' => 'GE',
            'city' => 'Tbilisi',
            'zip_from' => '0100',
            'zip_to' => '0199',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shipping_rates')->insert([
            'method_id' => $shippingMethodId,
            'zone_id' => $zoneId,
            'rule_type' => 'flat',
            'min_value' => null,
            'max_value' => null,
            'price' => 5.00,
            'eta_days' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $couponId = DB::table('coupons')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'SAVE10',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 25,
            'usage_limit' => 100,
            'used_count' => 0,
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'meta_json' => json_encode(['source' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('ecommerce_orders')->insertGetId([
            'site_id' => (string) $site->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'USD',
            'customer_email' => 'checkout@example.test',
            'customer_phone' => '+995555000222',
            'customer_name' => 'Checkout Customer',
            'subtotal' => 100,
            'tax_total' => 0,
            'shipping_total' => 5,
            'discount_total' => 10,
            'grand_total' => 95,
            'paid_total' => 0,
            'outstanding_total' => 95,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'payable_type' => 'order',
            'payable_id' => $orderId,
            'method_code' => 'stripe',
            'amount' => 95,
            'currency' => 'USD',
            'status' => 'initiated',
            'transaction_id' => 'txn_demo_123',
            'provider_payload' => json_encode(['intent' => 'pi_test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_webhooks')->insert([
            'provider' => 'stripe',
            'event_id' => 'evt_test_123',
            'payload_json' => json_encode(['type' => 'payment_intent.succeeded']),
            'status' => 'received',
            'processed_at' => null,
            'created_at' => now(),
        ]);

        DB::table('coupon_redemptions')->insert([
            'coupon_id' => $couponId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethodId,
            'project_id' => (string) $project->id,
            'code' => 'stripe',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'project_id' => (string) $project->id,
            'payable_type' => 'order',
            'method_code' => 'stripe',
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'provider' => 'stripe',
            'event_id' => 'evt_test_123',
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('shipping_methods', [
            'id' => $shippingMethodId,
            'code' => 'courier',
        ]);
        $this->assertDatabaseHas('shipping_zones', [
            'id' => $zoneId,
            'name' => 'Tbilisi Zone',
        ]);
        $this->assertDatabaseHas('shipping_rates', [
            'method_id' => $shippingMethodId,
            'zone_id' => $zoneId,
            'rule_type' => 'flat',
        ]);
        $this->assertDatabaseHas('coupons', [
            'id' => $couponId,
            'code' => 'SAVE10',
            'project_id' => (string) $project->id,
        ]);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $couponId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
        ]);
    }
}
