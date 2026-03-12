<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('type', 20)->default('percent'); // percent|fixed
            $table->decimal('value', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|active|inactive
            $table->string('scope', 30)->default('specific_products'); // all_products|specific_products|categories
            $table->json('product_ids_json')->nullable();
            $table->json('category_ids_json')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'type']);
            $table->index(['site_id', 'scope']);
            $table->unique(['site_id', 'code']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_discounts');
    }
};

