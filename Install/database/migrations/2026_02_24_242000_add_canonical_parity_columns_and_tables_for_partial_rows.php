<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->augmentTenants();
        $this->augmentTenantUsers();
        $this->augmentProjects();
        $this->augmentPages();
        $this->augmentPageRevisions();
        $this->augmentMenus();
        $this->augmentMedia();

        $this->createCanonicalPostsTable();
        $this->createProjectSettingsTable();
        $this->createFeatureFlagsTable();
        $this->createLeadsTable();
        $this->createProductCategoryRelationsTable();
        $this->createOrderAddressesTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('product_category_relations');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('project_settings');
        Schema::dropIfExists('posts');

        if (Schema::hasTable('media')) {
            Schema::table('media', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'media_tenant_id_foreign');
                $this->dropForeignIfExists($table, 'media_project_id_foreign');
                $this->dropColumnsIfExist($table, 'media', ['tenant_id', 'project_id', 'url', 'file_name', 'mime_type', 'width', 'height', 'alt']);
            });
        }

        if (Schema::hasTable('menus')) {
            Schema::table('menus', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'menus_tenant_id_foreign');
                $this->dropForeignIfExists($table, 'menus_project_id_foreign');
                $this->dropColumnsIfExist($table, 'menus', ['tenant_id', 'project_id', 'name']);
            });
        }

        if (Schema::hasTable('page_revisions')) {
            Schema::table('page_revisions', function (Blueprint $table): void {
                $this->dropColumnsIfExist($table, 'page_revisions', ['page_json', 'page_css']);
            });
        }

        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'pages_tenant_id_foreign');
                $this->dropForeignIfExists($table, 'pages_project_id_foreign');
                $this->dropForeignIfExists($table, 'pages_og_image_media_id_foreign');
                try {
                    $table->dropUnique(['project_id', 'slug']);
                } catch (\Throwable) {
                    // Ignore if index missing.
                }
                $this->dropColumnsIfExist($table, 'pages', ['tenant_id', 'project_id', 'page_json', 'page_css', 'og_image_media_id', 'published_at', 'version']);
            });
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table): void {
                try {
                    $table->dropUnique(['tenant_id', 'slug']);
                } catch (\Throwable) {
                    // Ignore if index missing.
                }
                $this->dropColumnsIfExist($table, 'projects', ['slug', 'primary_domain', 'subdomain', 'status']);
            });
        }

        if (Schema::hasTable('tenant_users')) {
            Schema::table('tenant_users', function (Blueprint $table): void {
                $this->dropColumnsIfExist($table, 'tenant_users', ['password_hash', 'role']);
            });
        }

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $this->dropForeignIfExists($table, 'tenants_logo_media_id_foreign');
                $this->dropColumnsIfExist($table, 'tenants', ['email', 'phone', 'logo_media_id']);
            });
        }
    }

    private function augmentTenants(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'email')) {
                $table->string('email')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('tenants', 'phone')) {
                $table->string('phone', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('tenants', 'logo_media_id')) {
                $table->unsignedBigInteger('logo_media_id')->nullable()->after('phone');
            }
        });

        if (Schema::hasTable('media') && Schema::hasColumn('tenants', 'logo_media_id')) {
            try {
                Schema::table('tenants', function (Blueprint $table): void {
                    $table->foreign('logo_media_id')->references('id')->on('media')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may already exist on rerun / environment differences.
            }
        }
    }

    private function augmentTenantUsers(): void
    {
        if (! Schema::hasTable('tenant_users')) {
            return;
        }

        Schema::table('tenant_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('tenant_users', 'role')) {
                $table->string('role', 64)->nullable()->after('password_hash');
            }
        });
    }

    private function augmentProjects(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (! Schema::hasColumn('projects', 'primary_domain')) {
                $table->string('primary_domain')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('projects', 'subdomain')) {
                $table->string('subdomain')->nullable()->after('primary_domain');
            }
            if (! Schema::hasColumn('projects', 'status')) {
                $table->string('status', 32)->nullable()->after('type');
            }
        });

        try {
            Schema::table('projects', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'slug']);
            });
        } catch (\Throwable) {
            // Ignore duplicates/already-existing index; migration coverage is additive and non-destructive.
        }
    }

    private function augmentPages(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('pages', 'project_id')) {
                $table->uuid('project_id')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('pages', 'page_json')) {
                $table->json('page_json')->nullable()->after('status');
            }
            if (! Schema::hasColumn('pages', 'page_css')) {
                $table->longText('page_css')->nullable()->after('page_json');
            }
            if (! Schema::hasColumn('pages', 'og_image_media_id')) {
                $table->unsignedBigInteger('og_image_media_id')->nullable()->after('seo_description');
            }
            if (! Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('og_image_media_id');
            }
            if (! Schema::hasColumn('pages', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('published_at');
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('pages', 'tenant_id')) {
            try {
                Schema::table('pages', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('pages', 'project_id')) {
            try {
                Schema::table('pages', function (Blueprint $table): void {
                    $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }

        if (Schema::hasTable('media') && Schema::hasColumn('pages', 'og_image_media_id')) {
            try {
                Schema::table('pages', function (Blueprint $table): void {
                    $table->foreign('og_image_media_id')->references('id')->on('media')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }

        try {
            Schema::table('pages', function (Blueprint $table): void {
                $table->unique(['project_id', 'slug']);
            });
        } catch (\Throwable) {
            // Ignore duplicates/already-existing index.
        }
    }

    private function augmentPageRevisions(): void
    {
        if (! Schema::hasTable('page_revisions')) {
            return;
        }

        Schema::table('page_revisions', function (Blueprint $table): void {
            if (! Schema::hasColumn('page_revisions', 'page_json')) {
                $table->json('page_json')->nullable()->after('content_json');
            }
            if (! Schema::hasColumn('page_revisions', 'page_css')) {
                $table->longText('page_css')->nullable()->after('page_json');
            }
        });
    }

    private function augmentMenus(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            if (! Schema::hasColumn('menus', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('menus', 'project_id')) {
                $table->uuid('project_id')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('menus', 'name')) {
                $table->string('name')->nullable()->after('project_id');
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('menus', 'tenant_id')) {
            try {
                Schema::table('menus', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('menus', 'project_id')) {
            try {
                Schema::table('menus', function (Blueprint $table): void {
                    $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }
    }

    private function augmentMedia(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('media', 'project_id')) {
                $table->uuid('project_id')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('media', 'url')) {
                $table->string('url')->nullable()->after('path');
            }
            if (! Schema::hasColumn('media', 'file_name')) {
                $table->string('file_name')->nullable()->after('url');
            }
            if (! Schema::hasColumn('media', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('mime');
            }
            if (! Schema::hasColumn('media', 'width')) {
                $table->unsignedInteger('width')->nullable()->after('size');
            }
            if (! Schema::hasColumn('media', 'height')) {
                $table->unsignedInteger('height')->nullable()->after('width');
            }
            if (! Schema::hasColumn('media', 'alt')) {
                $table->string('alt')->nullable()->after('height');
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('media', 'tenant_id')) {
            try {
                Schema::table('media', function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('media', 'project_id')) {
            try {
                Schema::table('media', function (Blueprint $table): void {
                    $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if already exists.
            }
        }
    }

    private function createCanonicalPostsTable(): void
    {
        if (Schema::hasTable('posts')) {
            return;
        }

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('project_id');
            $table->uuid('site_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->string('status', 32)->default('draft');
            $table->text('excerpt')->nullable();
            $table->longText('content_html')->nullable();
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['tenant_id', 'project_id']);
            $table->index(['project_id', 'status', 'published_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->foreign('cover_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    private function createProjectSettingsTable(): void
    {
        if (Schema::hasTable('project_settings')) {
            return;
        }

        Schema::create('project_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->string('key', 190);
            $table->json('value_json');
            $table->timestamps();

            $table->unique(['project_id', 'key']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    private function createFeatureFlagsTable(): void
    {
        if (Schema::hasTable('feature_flags')) {
            return;
        }

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('project_id')->nullable();
            $table->string('key', 190);
            $table->boolean('enabled')->default(false);
            $table->json('rules_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['key', 'enabled']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    private function createLeadsTable(): void
    {
        if (Schema::hasTable('leads')) {
            return;
        }

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('project_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['project_id', 'status', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    private function createProductCategoryRelationsTable(): void
    {
        if (Schema::hasTable('product_category_relations')) {
            return;
        }

        Schema::create('product_category_relations', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['product_id', 'category_id']);
            $table->index(['category_id', 'product_id']);
            $table->foreign('product_id')->references('id')->on('ecommerce_products')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('ecommerce_categories')->cascadeOnDelete();
        });
    }

    private function createOrderAddressesTable(): void
    {
        if (Schema::hasTable('order_addresses')) {
            return;
        }

        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type', 32);
            $table->string('name');
            $table->string('phone', 64);
            $table->string('country', 120);
            $table->string('city', 120);
            $table->string('address1', 255);
            $table->string('address2', 255)->nullable();
            $table->string('zip', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['order_id', 'type']);
            $table->foreign('order_id')->references('id')->on('ecommerce_orders')->cascadeOnDelete();
        });
    }

    private function dropForeignIfExists(Blueprint $table, string $foreignKeyName): void
    {
        try {
            $table->dropForeign($foreignKeyName);
        } catch (\Throwable) {
            // Ignore missing FK.
        }
    }

    /**
     * @param list<string> $columns
     */
    private function dropColumnsIfExist(Blueprint $table, string $tableName, array $columns): void
    {
        $drop = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $drop[] = $column;
            }
        }
        if ($drop !== []) {
            $table->dropColumn($drop);
        }
    }
};
