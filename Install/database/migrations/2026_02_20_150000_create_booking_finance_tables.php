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
        Schema::create('booking_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('invoice_number', 80);
            $table->string('status', 30)->default('issued');
            $table->string('currency', 3)->default('GEL');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('outstanding_total', 12, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'invoice_number']);
            $table->index(['site_id', 'booking_id']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'issued_at']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('provider', 60)->default('manual');
            $table->string('status', 30)->default('paid');
            $table->string('method', 30)->nullable();
            $table->string('transaction_reference', 190)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('GEL');
            $table->boolean('is_prepayment')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'booking_id']);
            $table->index(['site_id', 'invoice_id']);
            $table->index(['site_id', 'provider', 'status']);
            $table->index(['site_id', 'transaction_reference']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('booking_invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('status', 30)->default('completed');
            $table->string('reason', 255)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('GEL');
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'booking_id']);
            $table->index(['site_id', 'payment_id']);
            $table->index(['site_id', 'invoice_id']);
            $table->index(['site_id', 'status']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('booking_payments')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('booking_invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('refund_id')->nullable();
            $table->string('event_type', 60);
            $table->string('event_key', 180);
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
            $table->index(['site_id', 'booking_id']);
            $table->index(['site_id', 'occurred_at']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('booking_invoices')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('booking_payments')->nullOnDelete();
            $table->foreign('refund_id')->references('id')->on('booking_refunds')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_financial_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('refund_id')->nullable();
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
            $table->index(['site_id', 'booking_id']);
            $table->index(['site_id', 'account_code']);
            $table->index(['site_id', 'side']);

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('entry_id')->references('id')->on('booking_financial_entries')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('booking_invoices')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('booking_payments')->nullOnDelete();
            $table->foreign('refund_id')->references('id')->on('booking_refunds')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_financial_entry_lines');
        Schema::dropIfExists('booking_financial_entries');
        Schema::dropIfExists('booking_refunds');
        Schema::dropIfExists('booking_payments');
        Schema::dropIfExists('booking_invoices');
    }
};
