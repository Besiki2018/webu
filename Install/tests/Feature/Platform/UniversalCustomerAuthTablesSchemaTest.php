<?php

namespace Tests\Feature\Platform;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniversalCustomerAuthTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_universal_customer_auth_tables_exist_with_canonical_columns(): void
    {
        foreach ([
            'customers',
            'customer_sessions',
            'customer_addresses',
            'otp_requests',
            'social_accounts',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('customers', [
            'tenant_id', 'project_id', 'name', 'email', 'phone', 'password_hash', 'status', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('customer_sessions', [
            'customer_id', 'token_hash', 'expires_at', 'ip_hash', 'user_agent', 'last_seen_at', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns('customer_addresses', [
            'customer_id', 'type', 'name', 'phone', 'country', 'city', 'address1', 'address2', 'zip', 'is_default',
        ]));
        $this->assertTrue(Schema::hasColumns('otp_requests', [
            'tenant_id', 'project_id', 'phone', 'code_hash', 'expires_at', 'attempts', 'status', 'ip_hash', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns('social_accounts', [
            'customer_id', 'provider', 'provider_user_id', 'email', 'created_at',
        ]));
    }

    public function test_customer_auth_tables_support_relational_insert_flow(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Customer Auth Tenant',
            'slug' => 'cust-auth-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'booking',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Demo Customer',
            'email' => 'customer@example.test',
            'phone' => '+995555000111',
            'password_hash' => 'hashed-secret',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_sessions')->insert([
            'customer_id' => $customerId,
            'token_hash' => hash('sha256', 'demo-session-token'),
            'expires_at' => now()->addDay(),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_addresses')->insert([
            'customer_id' => $customerId,
            'type' => 'billing',
            'name' => 'Demo Customer',
            'phone' => '+995555000111',
            'country' => 'GE',
            'city' => 'Tbilisi',
            'address1' => 'Rustaveli Ave 1',
            'address2' => null,
            'zip' => '0108',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('otp_requests')->insert([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'phone' => '+995555000111',
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'status' => 'pending',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('social_accounts')->insert([
            'customer_id' => $customerId,
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'email' => 'customer@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'project_id' => (string) $project->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('customer_sessions', [
            'customer_id' => $customerId,
        ]);
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customerId,
            'type' => 'billing',
            'is_default' => 1,
        ]);
        $this->assertDatabaseHas('otp_requests', [
            'project_id' => (string) $project->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'customer_id' => $customerId,
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);
    }
}
