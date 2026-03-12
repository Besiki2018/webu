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

class UniversalServicesNormalizationTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_normalization_tables_exist_with_canonical_columns(): void
    {
        foreach ([
            'service_categories',
            'services',
            'staff',
            'staff_services',
            'resources',
            'availability_rules',
            'blocked_times',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('service_categories', ['tenant_id', 'project_id', 'site_id', 'name', 'slug', 'parent_id']));
        $this->assertTrue(Schema::hasColumns('services', ['tenant_id', 'project_id', 'site_id', 'name', 'slug', 'description_html', 'price', 'currency', 'duration_minutes', 'status', 'category_id']));
        $this->assertTrue(Schema::hasColumns('staff', ['tenant_id', 'project_id', 'site_id', 'name', 'slug', 'photo_media_id', 'role_title', 'bio_html', 'status']));
        $this->assertTrue(Schema::hasColumns('staff_services', ['staff_id', 'service_id']));
        $this->assertTrue(Schema::hasColumns('resources', ['tenant_id', 'project_id', 'site_id', 'name', 'type', 'capacity', 'status']));
        $this->assertTrue(Schema::hasColumns('availability_rules', ['tenant_id', 'project_id', 'site_id', 'owner_type', 'owner_id', 'rrule', 'start_time', 'end_time', 'timezone', 'meta_json']));
        $this->assertTrue(Schema::hasColumns('blocked_times', ['tenant_id', 'project_id', 'site_id', 'owner_type', 'owner_id', 'starts_at', 'ends_at', 'reason', 'meta_json']));
    }

    public function test_services_normalization_tables_support_relational_insert_flow(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Services Norm Tenant',
            'slug' => 'svc-norm-'.Str::lower(Str::random(6)),
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

        $site = Site::query()->where('project_id', (string) $project->id)->first();
        $this->assertNotNull($site);

        $categoryId = DB::table('service_categories')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Consultations',
            'slug' => 'consultations',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = DB::table('services')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Initial Consultation',
            'slug' => 'initial-consultation',
            'description_html' => '<p>Intro session</p>',
            'price' => 80,
            'currency' => 'USD',
            'duration_minutes' => 60,
            'status' => 'published',
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffId = DB::table('staff')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Dr. Demo',
            'slug' => 'dr-demo',
            'photo_media_id' => null,
            'role_title' => 'Doctor',
            'bio_html' => '<p>Profile</p>',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resourceId = DB::table('resources')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Room A',
            'type' => 'room',
            'capacity' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('staff_services')->insert([
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('availability_rules')->insert([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'owner_type' => 'staff',
            'owner_id' => $staffId,
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'timezone' => 'UTC',
            'meta_json' => json_encode(['source' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blocked_times')->insert([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'owner_type' => 'resource',
            'owner_id' => $resourceId,
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDay()->endOfDay(),
            'reason' => 'maintenance',
            'meta_json' => json_encode(['ticket' => 'MNT-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('service_categories', ['id' => $categoryId, 'slug' => 'consultations']);
        $this->assertDatabaseHas('services', ['id' => $serviceId, 'slug' => 'initial-consultation', 'category_id' => $categoryId]);
        $this->assertDatabaseHas('staff', ['id' => $staffId, 'slug' => 'dr-demo']);
        $this->assertDatabaseHas('resources', ['id' => $resourceId, 'type' => 'room']);
        $this->assertDatabaseHas('staff_services', ['staff_id' => $staffId, 'service_id' => $serviceId]);
        $this->assertDatabaseHas('availability_rules', ['project_id' => (string) $project->id, 'owner_type' => 'staff', 'owner_id' => $staffId]);
        $this->assertDatabaseHas('blocked_times', ['project_id' => (string) $project->id, 'owner_type' => 'resource', 'owner_id' => $resourceId, 'reason' => 'maintenance']);
    }
}
