<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_notification_templates')) {
            return;
        }

        Schema::create('site_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('key', 120);
            $table->string('name', 255);
            $table->string('channel', 20); // email|sms
            $table->string('event_key', 120);
            $table->string('locale', 12)->default('en');
            $table->string('status', 20)->default('active'); // draft|active|disabled
            $table->string('subject_template', 500)->nullable();
            $table->text('body_template');
            $table->json('variables_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'channel', 'event_key']);
            $table->index(['site_id', 'status']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_notification_templates');
    }
};
