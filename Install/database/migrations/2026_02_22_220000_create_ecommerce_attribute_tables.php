<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_attributes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('type', 32)->default('text');
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'type']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'sort_order']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('ecommerce_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->foreignId('ecommerce_attribute_id')->constrained('ecommerce_attributes')->cascadeOnDelete();
            $table->string('label');
            $table->string('slug');
            $table->string('color_hex', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'ecommerce_attribute_id', 'slug'], 'ecom_attr_values_site_attr_slug_unique');
            $table->index(['site_id', 'ecommerce_attribute_id']);
            $table->index(['site_id', 'sort_order']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_attribute_values');
        Schema::dropIfExists('ecommerce_attributes');
    }
};

