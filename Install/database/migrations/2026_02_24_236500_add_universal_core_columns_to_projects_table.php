<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('projects', 'type')) {
                $table->string('type', 64)->nullable()->after('name');
            }
            if (! Schema::hasColumn('projects', 'default_currency')) {
                $table->string('default_currency', 3)->nullable()->after('type');
            }
            if (! Schema::hasColumn('projects', 'default_locale')) {
                $table->string('default_locale', 10)->nullable()->after('default_currency');
            }
            if (! Schema::hasColumn('projects', 'timezone')) {
                $table->string('timezone', 80)->nullable()->after('default_locale');
            }
        });

        Schema::table('projects', function (Blueprint $table): void {
            try {
                if (! $this->hasIndex('projects', 'projects_tenant_id_index') && Schema::hasColumn('projects', 'tenant_id')) {
                    $table->index('tenant_id');
                }
            } catch (\Throwable) {
                // Allow reruns on drivers that already created the index.
            }

            try {
                if (! $this->hasIndex('projects', 'projects_type_index') && Schema::hasColumn('projects', 'type')) {
                    $table->index('type');
                }
            } catch (\Throwable) {
                // Allow reruns on drivers that already created the index.
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('projects', 'tenant_id')) {
            try {
                Schema::table('projects', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Foreign key may already exist on rerun / existing environments.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        try {
            Schema::table('projects', function (Blueprint $table): void {
                if (Schema::hasColumn('projects', 'tenant_id')) {
                    $table->dropForeign(['tenant_id']);
                }
            });
        } catch (\Throwable) {
            // Ignore if FK/index missing.
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'timezone')) {
                $table->dropColumn('timezone');
            }
            if (Schema::hasColumn('projects', 'default_locale')) {
                $table->dropColumn('default_locale');
            }
            if (Schema::hasColumn('projects', 'default_currency')) {
                $table->dropColumn('default_currency');
            }
            if (Schema::hasColumn('projects', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('projects', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (method_exists(Schema::getConnection()->getSchemaBuilder(), 'hasIndex')) {
            /** @phpstan-ignore-next-line */
            return Schema::getConnection()->getSchemaBuilder()->hasIndex($table, $indexName);
        }

        return false;
    }
};
