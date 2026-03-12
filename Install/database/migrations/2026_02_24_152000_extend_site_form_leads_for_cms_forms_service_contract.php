<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_form_leads')) {
            return;
        }

        Schema::table('site_form_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('site_form_leads', 'fields_json')) {
                $table->json('fields_json')->nullable()->after('payload_json');
            }
            if (! Schema::hasColumn('site_form_leads', 'source_json')) {
                $table->json('source_json')->nullable()->after('fields_json');
            }
            if (! Schema::hasColumn('site_form_leads', 'ip_hash')) {
                $table->string('ip_hash', 128)->nullable()->after('meta_json');
            }
            if (! Schema::hasColumn('site_form_leads', 'user_agent')) {
                $table->string('user_agent', 1024)->nullable()->after('ip_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_form_leads')) {
            return;
        }

        Schema::table('site_form_leads', function (Blueprint $table) {
            $drops = [];
            foreach (['fields_json', 'source_json', 'ip_hash', 'user_agent'] as $column) {
                if (Schema::hasColumn('site_form_leads', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
