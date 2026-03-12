<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommercePanelCategoryServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Models\EcommerceCategory;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EcommercePanelCategoryService implements EcommercePanelCategoryServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository
    ) {}

    public function list(Site $site): array
    {
        $categories = $this->repository->listCategories($site)
            ->map(fn (EcommerceCategory $category): array => [
                'id' => $category->id,
                'site_id' => $category->site_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'status' => $category->status,
                'sort_order' => $category->sort_order,
                'meta_json' => $category->meta_json ?? [],
                'created_at' => $category->created_at?->toISOString(),
                'updated_at' => $category->updated_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'categories' => $categories,
        ];
    }

    public function create(Site $site, array $payload): EcommerceCategory
    {
        return $this->repository->createCategory($site, $payload);
    }

    public function update(Site $site, EcommerceCategory $category, array $payload): EcommerceCategory
    {
        $target = $this->repository->findCategoryBySiteAndId($site, $category->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceCategory::class, [$category->id]);
        }

        return $this->repository->updateCategory($target, $payload);
    }

    public function delete(Site $site, EcommerceCategory $category): void
    {
        $target = $this->repository->findCategoryBySiteAndId($site, $category->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceCategory::class, [$category->id]);
        }

        $this->repository->deleteCategory($target);
    }
}

