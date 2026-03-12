<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommercePanelProductServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceProductImage;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class EcommercePanelProductService implements EcommercePanelProductServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository,
        protected EcommerceInventoryServiceContract $inventory
    ) {}

    public function list(Site $site): array
    {
        $products = $this->repository->listProducts($site)
            ->map(fn (EcommerceProduct $product): array => $this->serializeProduct($product))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'products' => $products,
        ];
    }

    public function show(Site $site, EcommerceProduct $product): array
    {
        $target = $this->repository->findProductBySiteAndId($site, $product->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceProduct::class, [$product->id]);
        }

        return [
            'site_id' => $site->id,
            'product' => $this->serializeProduct($target, true),
        ];
    }

    public function create(Site $site, array $payload): EcommerceProduct
    {
        $this->assertProductLimitNotExceeded($site);
        $this->ensureCategoryBelongsToSite($site, $payload['category_id'] ?? null);
        [$basePayload, $imagesPayload, $variantsPayload] = $this->extractNestedPayload($payload);

        if (($basePayload['status'] ?? null) === 'active' && ! array_key_exists('published_at', $basePayload)) {
            $basePayload['published_at'] = now();
        }

        return DB::transaction(function () use ($site, $basePayload, $imagesPayload, $variantsPayload): EcommerceProduct {
            $created = $this->repository->createProduct($site, $basePayload);
            $this->syncProductImages($site, $created, $imagesPayload);
            $this->syncProductVariants($site, $created, $variantsPayload);
            $this->syncInventorySnapshots($site, $created, 'product_created');

            return $created->fresh(['category', 'images', 'variants']);
        });
    }

    public function update(Site $site, EcommerceProduct $product, array $payload): EcommerceProduct
    {
        $target = $this->repository->findProductBySiteAndId($site, $product->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceProduct::class, [$product->id]);
        }

        $this->ensureCategoryBelongsToSite($site, $payload['category_id'] ?? $target->category_id);
        [$basePayload, $imagesPayload, $variantsPayload] = $this->extractNestedPayload($payload);

        if (array_key_exists('status', $basePayload)) {
            if ($basePayload['status'] === 'active' && ! $target->published_at) {
                $basePayload['published_at'] = now();
            }

            if ($basePayload['status'] !== 'active') {
                $basePayload['published_at'] = null;
            }
        }

        return DB::transaction(function () use ($site, $target, $basePayload, $imagesPayload, $variantsPayload): EcommerceProduct {
            $updated = $this->repository->updateProduct($target, $basePayload);
            $this->syncProductImages($site, $updated, $imagesPayload);
            $this->syncProductVariants($site, $updated, $variantsPayload);
            $this->syncInventorySnapshots($site, $updated, 'product_updated');

            return $updated->fresh(['category', 'images', 'variants']);
        });
    }

    public function delete(Site $site, EcommerceProduct $product): void
    {
        $target = $this->repository->findProductBySiteAndId($site, $product->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceProduct::class, [$product->id]);
        }

        $this->repository->deleteProduct($target);
    }

    private function ensureCategoryBelongsToSite(Site $site, mixed $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $resolvedCategoryId = (int) $categoryId;
        if ($resolvedCategoryId <= 0 || ! $this->repository->findCategoryBySiteAndId($site, $resolvedCategoryId)) {
            throw new EcommerceDomainException('Selected category does not belong to this site.', 422);
        }
    }

    private function assertProductLimitNotExceeded(Site $site): void
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return;
        }

        $limit = $plan->getMaxProducts();
        if ($limit === null) {
            return;
        }

        $ownerId = (int) ($owner?->id ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $currentUsage = EcommerceProduct::query()
            ->whereIn('site_id', function ($query) use ($ownerId): void {
                $query->select('sites.id')
                    ->from('sites')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->where('projects.user_id', $ownerId);
            })
            ->count();

        if ($currentUsage >= $limit) {
            throw new EcommerceDomainException(
                'Product limit reached for your current plan.',
                422,
                [
                    'reason' => 'products_limit_reached',
                    'limit' => $limit,
                    'current_usage' => $currentUsage,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>|null, 2: array<int, array<string, mixed>>|null}
     */
    private function extractNestedPayload(array $payload): array
    {
        $imagesPayload = isset($payload['images']) && is_array($payload['images']) ? array_values($payload['images']) : null;
        $variantsPayload = isset($payload['variants']) && is_array($payload['variants']) ? array_values($payload['variants']) : null;

        unset($payload['images'], $payload['variants']);

        return [$payload, $imagesPayload, $variantsPayload];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $imagesPayload
     */
    private function syncProductImages(Site $site, EcommerceProduct $product, ?array $imagesPayload): void
    {
        if ($imagesPayload === null) {
            return;
        }

        $existing = $product->images()->get()->keyBy('id');
        $keptIds = [];
        $normalizedRows = [];

        foreach ($imagesPayload as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $mediaId = isset($row['media_id']) && is_numeric($row['media_id']) ? (int) $row['media_id'] : null;
            $media = null;
            if ($mediaId !== null && $mediaId > 0) {
                $media = Media::query()
                    ->where('site_id', $site->id)
                    ->where('id', $mediaId)
                    ->first();
                if (! $media) {
                    throw new EcommerceDomainException('Selected image does not belong to this site.', 422);
                }
            }

            $path = trim((string) ($row['path'] ?? ''));
            if ($path === '' && $media) {
                $path = (string) $media->path;
            }

            if ($path === '') {
                continue;
            }

            $normalizedRows[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'media_id' => $media?->id,
                'path' => $path,
                'alt_text' => isset($row['alt_text']) ? trim((string) $row['alt_text']) : null,
                'sort_order' => isset($row['sort_order']) && is_numeric($row['sort_order']) ? max(0, (int) $row['sort_order']) : $index,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }

        if ($normalizedRows !== [] && ! collect($normalizedRows)->contains(fn (array $row): bool => $row['is_primary'] === true)) {
            $normalizedRows[0]['is_primary'] = true;
        }

        foreach ($normalizedRows as $row) {
            $imageId = $row['id'];
            $attributes = [
                'site_id' => $site->id,
                'media_id' => $row['media_id'],
                'path' => $row['path'],
                'alt_text' => $row['alt_text'] !== '' ? $row['alt_text'] : null,
                'sort_order' => $row['sort_order'],
                'is_primary' => $row['is_primary'],
            ];

            if ($imageId !== null && $existing->has($imageId)) {
                /** @var EcommerceProductImage $image */
                $image = $existing->get($imageId);
                $image->update($attributes);
                $keptIds[] = $image->id;
            } else {
                /** @var EcommerceProductImage $createdImage */
                $createdImage = $product->images()->create($attributes);
                $keptIds[] = $createdImage->id;
            }
        }

        if ($existing->isNotEmpty()) {
            $product->images()
                ->whereNotIn('id', $keptIds ?: [0])
                ->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $variantsPayload
     */
    private function syncProductVariants(Site $site, EcommerceProduct $product, ?array $variantsPayload): void
    {
        if ($variantsPayload === null) {
            return;
        }

        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = [];
        $rows = [];

        foreach ($variantsPayload as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $options = is_array($row['options_json'] ?? null) ? $row['options_json'] : [];
            $derivedName = collect($options)
                ->map(fn ($value, $key): string => trim((string) ($value ?? '')))
                ->filter(fn (string $value): bool => $value !== '')
                ->implode(' / ');
            $resolvedName = trim((string) ($row['name'] ?? '')) ?: ($derivedName !== '' ? $derivedName : 'Variant '.($index + 1));

            $rows[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'name' => $resolvedName,
                'sku' => ($row['sku'] ?? null) !== null ? trim((string) $row['sku']) : null,
                'options_json' => $options,
                'price' => isset($row['price']) && $row['price'] !== null && $row['price'] !== '' ? (float) $row['price'] : null,
                'compare_at_price' => isset($row['compare_at_price']) && $row['compare_at_price'] !== null && $row['compare_at_price'] !== '' ? (float) $row['compare_at_price'] : null,
                'stock_tracking' => array_key_exists('stock_tracking', $row) ? (bool) $row['stock_tracking'] : (bool) $product->stock_tracking,
                'stock_quantity' => isset($row['stock_quantity']) && is_numeric($row['stock_quantity']) ? max(0, (int) $row['stock_quantity']) : (int) $product->stock_quantity,
                'allow_backorder' => array_key_exists('allow_backorder', $row) ? (bool) $row['allow_backorder'] : (bool) $product->allow_backorder,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'position' => isset($row['position']) && is_numeric($row['position']) ? max(0, (int) $row['position']) : $index,
            ];
        }

        if ($rows !== [] && ! collect($rows)->contains(fn (array $row): bool => $row['is_default'] === true)) {
            $rows[0]['is_default'] = true;
        }

        foreach ($rows as $row) {
            $variantId = $row['id'];
            $attributes = [
                'site_id' => $site->id,
                'name' => $row['name'],
                'sku' => $row['sku'] !== '' ? $row['sku'] : null,
                'options_json' => $row['options_json'],
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'],
                'stock_tracking' => $row['stock_tracking'],
                'stock_quantity' => $row['stock_quantity'],
                'allow_backorder' => $row['allow_backorder'],
                'is_default' => $row['is_default'],
                'position' => $row['position'],
            ];

            if ($variantId !== null && $existing->has($variantId)) {
                /** @var EcommerceProductVariant $variant */
                $variant = $existing->get($variantId);
                $variant->update($attributes);
                $keptIds[] = $variant->id;
            } else {
                /** @var EcommerceProductVariant $createdVariant */
                $createdVariant = $product->variants()->create($attributes);
                $keptIds[] = $createdVariant->id;
            }
        }

        if ($existing->isNotEmpty()) {
            $product->variants()
                ->whereNotIn('id', $keptIds ?: [0])
                ->delete();
        }
    }

    private function syncInventorySnapshots(Site $site, EcommerceProduct $product, string $reason): void
    {
        $fresh = $product->fresh(['variants']);
        if (! $fresh) {
            return;
        }

        $this->inventory->syncInventorySnapshotForProduct($site, $fresh, reason: $reason);

        foreach ($fresh->variants as $variant) {
            $this->inventory->syncInventorySnapshotForProduct($site, $fresh, $variant, reason: $reason);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProduct(EcommerceProduct $product, bool $withDetails = false): array
    {
        /** @var EcommerceProductImage|null $primaryImage */
        $primaryImage = $product->relationLoaded('images')
            ? ($product->images->firstWhere('is_primary', true) ?? $product->images->sortBy('sort_order')->first())
            : null;

        $payload = [
            'id' => $product->id,
            'site_id' => $product->site_id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => (string) $product->price,
            'compare_at_price' => $product->compare_at_price !== null ? (string) $product->compare_at_price : null,
            'currency' => $product->currency,
            'status' => $product->status,
            'stock_tracking' => (bool) $product->stock_tracking,
            'stock_quantity' => (int) $product->stock_quantity,
            'primary_image_url' => $primaryImage?->path
                ? route('public.sites.assets', [
                    'site' => $product->site_id,
                    'path' => $primaryImage->path,
                ])
                : null,
            'primary_image_alt' => $primaryImage?->alt_text,
            'allow_backorder' => (bool) $product->allow_backorder,
            'is_digital' => (bool) $product->is_digital,
            'weight_grams' => $product->weight_grams,
            'attributes_json' => $product->attributes_json ?? [],
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'published_at' => $product->published_at?->toISOString(),
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];

        if (! $withDetails) {
            return $payload;
        }

        $payload['images'] = $product->images
            ->map(fn ($image): array => [
                'id' => $image->id,
                'media_id' => $image->media_id,
                'path' => $image->path,
                'asset_url' => route('public.sites.assets', [
                    'site' => $product->site_id,
                    'path' => $image->path,
                ]),
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'is_primary' => (bool) $image->is_primary,
            ])
            ->values()
            ->all();

        $payload['variants'] = $product->variants
            ->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'options_json' => $variant->options_json ?? [],
                'price' => $variant->price !== null ? (string) $variant->price : null,
                'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null,
                'stock_tracking' => (bool) $variant->stock_tracking,
                'stock_quantity' => (int) $variant->stock_quantity,
                'allow_backorder' => (bool) $variant->allow_backorder,
                'is_default' => (bool) $variant->is_default,
                'position' => (int) $variant->position,
            ])
            ->values()
            ->all();

        return $payload;
    }
}
