<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ecommerce_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('ecommerce_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('sku', 64)->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('GEL');
            $table->string('status', 20)->default('draft');
            $table->boolean('stock_tracking')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('attributes_json')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'slug']);
            $table->unique(['site_id', 'sku']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'category_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('ecommerce_categories')->nullOnDelete();
        });

        Schema::create('ecommerce_product_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('path')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'product_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
        });

        Schema::create('ecommerce_product_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->json('options_json')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->boolean('stock_tracking')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'sku']);
            $table->index(['site_id', 'product_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->onDelete('cascade');
        });

        Schema::create('ecommerce_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('sku', 64)->nullable();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('low_stock_threshold')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'product_id']);
            $table->index(['site_id', 'variant_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('ecommerce_product_variants')->nullOnDelete();
        });

        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('order_number');
            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('fulfillment_status', 30)->default('unfulfilled');
            $table->string('currency', 3)->default('GEL');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->json('billing_address_json')->nullable();
            $table->json('shipping_address_json')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('outstanding_total', 12, 2)->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'order_number']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'payment_status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });

        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->json('options_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'order_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('ecommerce_product_variants')->nullOnDelete();
        });

        Schema::create('ecommerce_order_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('order_id');
            $table->string('provider');
            $table->string('status', 30);
            $table->string('method', 30)->nullable();
            $table->string('transaction_reference')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('GEL');
            $table->boolean('is_installment')->default(false);
            $table->json('installment_plan_json')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'order_id']);
            $table->index(['site_id', 'provider', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade');
        });

        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('currency', 3)->default('GEL');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('converted_order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
        });

        Schema::create('ecommerce_cart_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->uuid('cart_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->json('options_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'cart_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('cart_id')->references('id')->on('ecommerce_carts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('ecommerce_product_variants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_cart_items');
        Schema::dropIfExists('ecommerce_carts');
        Schema::dropIfExists('ecommerce_order_payments');
        Schema::dropIfExists('ecommerce_order_items');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('ecommerce_inventory_items');
        Schema::dropIfExists('ecommerce_product_variants');
        Schema::dropIfExists('ecommerce_product_images');
        Schema::dropIfExists('ecommerce_products');
        Schema::dropIfExists('ecommerce_categories');
    }
};

