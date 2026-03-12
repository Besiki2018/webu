<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ecommerce_inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('key', 64);
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'is_default']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::table('ecommerce_inventory_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable()->after('variant_id');
            $table->index(['site_id', 'location_id']);
            $table->foreign('location_id')->references('id')->on('ecommerce_inventory_locations')->nullOnDelete();
        });

        $siteIds = DB::table('ecommerce_inventory_items')
            ->select('site_id')
            ->distinct()
            ->pluck('site_id');

        foreach ($siteIds as $siteId) {
            $existingDefault = DB::table('ecommerce_inventory_locations')
                ->where('site_id', $siteId)
                ->where('is_default', true)
                ->first();

            if (! $existingDefault) {
                $locationId = DB::table('ecommerce_inventory_locations')->insertGetId([
                    'site_id' => $siteId,
                    'key' => 'main',
                    'name' => 'Main Warehouse',
                    'status' => 'active',
                    'is_default' => true,
                    'notes' => null,
                    'meta_json' => json_encode([
                        'seeded' => true,
                        'source' => 'inventory_locations_migration',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $locationId = (int) $existingDefault->id;
            }

            DB::table('ecommerce_inventory_items')
                ->where('site_id', $siteId)
                ->whereNull('location_id')
                ->update([
                    'location_id' => $locationId,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['site_id', 'location_id']);
            $table->dropColumn('location_id');
        });

        Schema::dropIfExists('ecommerce_inventory_locations');
    }
};
