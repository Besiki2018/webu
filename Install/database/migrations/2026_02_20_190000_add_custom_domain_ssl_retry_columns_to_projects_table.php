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
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'custom_domain_ssl_attempts')) {
                $table->unsignedSmallInteger('custom_domain_ssl_attempts')
                    ->default(0)
                    ->after('custom_domain_ssl_status');
            }

            if (! Schema::hasColumn('projects', 'custom_domain_ssl_next_retry_at')) {
                $table->timestamp('custom_domain_ssl_next_retry_at')
                    ->nullable()
                    ->after('custom_domain_ssl_attempts');
            }

            if (! Schema::hasColumn('projects', 'custom_domain_ssl_last_error')) {
                $table->text('custom_domain_ssl_last_error')
                    ->nullable()
                    ->after('custom_domain_ssl_next_retry_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $columns = [
                'custom_domain_ssl_last_error',
                'custom_domain_ssl_next_retry_at',
                'custom_domain_ssl_attempts',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
