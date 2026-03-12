<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_notification_logs')) {
            return;
        }

        Schema::create('site_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->foreignId('site_notification_template_id')->nullable()->constrained('site_notification_templates')->nullOnDelete();
            $table->string('channel', 20); // email|sms
            $table->string('event_key', 120);
            $table->string('status', 20)->default('preview'); // preview|queued|sent|failed|skipped
            $table->string('recipient', 255)->nullable();
            $table->string('subject_snapshot', 500)->nullable();
            $table->text('body_snapshot')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'created_at'], 'sn_logs_site_status_created');
            $table->index(['site_id', 'channel', 'event_key'], 'sn_logs_site_channel_event');
            $table->index(['site_id', 'site_notification_template_id'], 'sn_logs_site_template_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_notification_logs');
    }
};
