<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
                $table->string('title');
                $table->text('url');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['menu_id', 'sort_order']);
                $table->index(['menu_id', 'parent_id']);
                $table->foreign('parent_id')->references('id')->on('menu_items')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('post_categories')) {
            Schema::create('post_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->uuid('project_id');
                $table->uuid('site_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'slug']);
                $table->index(['site_id', 'slug']);
                $table->unique(['project_id', 'slug']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('parent_id')->references('id')->on('post_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('post_category_relations')) {
            Schema::create('post_category_relations', function (Blueprint $table): void {
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('category_id');

                $table->primary(['post_id', 'category_id']);
                $table->index(['category_id', 'post_id']);
                $table->foreign('post_id')->references('id')->on('blog_posts')->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('post_categories')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_category_relations');
        Schema::dropIfExists('post_categories');
        Schema::dropIfExists('menu_items');
    }
};
