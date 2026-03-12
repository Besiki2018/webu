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
        Schema::create('ecommerce_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_payment_id')->nullable();
            $table->string('event_type', 60);
            $table->string('event_key', 160);
            $table->string('currency', 3)->default('GEL');
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'event_key']);
            $table->index(['site_id', 'event_type']);
            $table->index(['site_id', 'order_id']);
            $table->index(['site_id', 'occurred_at']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
            $table->foreign('order_payment_id')->references('id')->on('ecommerce_order_payments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ecommerce_accounting_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_payment_id')->nullable();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->string('account_code', 80);
            $table->string('account_name', 180);
            $table->string('side', 6);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('GEL');
            $table->string('description', 255)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'entry_id']);
            $table->index(['site_id', 'order_id']);
            $table->index(['site_id', 'account_code']);
            $table->index(['site_id', 'side']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('entry_id')->references('id')->on('ecommerce_accounting_entries')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
            $table->foreign('order_payment_id')->references('id')->on('ecommerce_order_payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_accounting_entry_lines');
        Schema::dropIfExists('ecommerce_accounting_entries');
    }
};
