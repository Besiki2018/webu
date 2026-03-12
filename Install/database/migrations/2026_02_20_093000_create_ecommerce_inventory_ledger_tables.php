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
        Schema::create('ecommerce_inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->uuid('cart_id');
            $table->unsignedBigInteger('cart_item_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('reserved_until')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'cart_item_id'], 'eir_site_cart_item_uq');
            $table->index(['site_id', 'cart_id'], 'eir_site_cart_idx');
            $table->index(['site_id', 'inventory_item_id'], 'eir_site_item_idx');
            $table->index(['site_id', 'product_id', 'variant_id'], 'eir_site_prod_var_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('inventory_item_id')->references('id')->on('ecommerce_inventory_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('ecommerce_product_variants')->nullOnDelete();
            $table->foreign('cart_id')->references('id')->on('ecommerce_carts')->onDelete('cascade');
            $table->foreign('cart_item_id')->references('id')->on('ecommerce_cart_items')->onDelete('cascade');
        });

        Schema::create('ecommerce_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->uuid('cart_id')->nullable();
            $table->unsignedBigInteger('cart_item_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('movement_type', 40);
            $table->string('reason', 120)->nullable();
            $table->integer('quantity_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('quantity_on_hand_before')->default(0);
            $table->integer('quantity_on_hand_after')->default(0);
            $table->integer('quantity_reserved_before')->default(0);
            $table->integer('quantity_reserved_after')->default(0);
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'inventory_item_id'], 'esm_site_item_idx');
            $table->index(['site_id', 'movement_type'], 'esm_site_move_type_idx');
            $table->index(['site_id', 'order_id'], 'esm_site_order_idx');
            $table->index(['site_id', 'cart_id'], 'esm_site_cart_idx');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('inventory_item_id')->references('id')->on('ecommerce_inventory_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('ecommerce_product_variants')->nullOnDelete();
            $table->foreign('cart_id')->references('id')->on('ecommerce_carts')->nullOnDelete();
            $table->foreign('cart_item_id')->references('id')->on('ecommerce_cart_items')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
            $table->foreign('order_item_id')->references('id')->on('ecommerce_order_items')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_stock_movements');
        Schema::dropIfExists('ecommerce_inventory_reservations');
    }
};
