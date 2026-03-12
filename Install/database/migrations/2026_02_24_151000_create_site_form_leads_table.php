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
        Schema::create('site_form_leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->foreignId('site_form_id')->constrained('site_forms')->cascadeOnDelete();
            $table->string('status', 20)->default('new'); // new|reviewed|archived|spam
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            // Legacy-compatible payload snapshot while builder-facing APIs use fields_json/source_json.
            $table->json('payload_json');
            $table->json('fields_json')->nullable();
            $table->json('source_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->string('ip_hash', 128)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['site_id', 'site_form_id']);
            $table->index(['site_id', 'status', 'submitted_at']);
            $table->index(['site_form_id', 'submitted_at']);
            $table->index(['site_id', 'contact_email']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_form_leads');
    }
};
