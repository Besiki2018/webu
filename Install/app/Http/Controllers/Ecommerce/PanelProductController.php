<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommercePanelProductServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelProductController extends Controller
{
    public function __construct(
        protected EcommercePanelProductServiceContract $products
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->products->list($site));
    }

    public function show(Site $site, EcommerceProduct $product): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $product);

        return response()->json($this->products->show($site, $product));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ecommerce_products')
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('ecommerce_products')
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'short_description' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'archived'])],
            'stock_tracking' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'allow_backorder' => ['nullable', 'boolean'],
            'is_digital' => ['nullable', 'boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'attributes_json' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'integer'],
            'images.*.media_id' => ['nullable', 'integer'],
            'images.*.path' => ['nullable', 'string', 'max:2048'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.options_json' => ['nullable', 'array'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_tracking' => ['nullable', 'boolean'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.allow_backorder' => ['nullable', 'boolean'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.position' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $product = $this->products->create($site, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function update(Request $request, Site $site, EcommerceProduct $product): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ecommerce_products')
                    ->ignore($product->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('ecommerce_products')
                    ->ignore($product->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'short_description' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'active', 'archived'])],
            'stock_tracking' => ['sometimes', 'boolean'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'allow_backorder' => ['sometimes', 'boolean'],
            'is_digital' => ['sometimes', 'boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'attributes_json' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'integer'],
            'images.*.media_id' => ['nullable', 'integer'],
            'images.*.path' => ['nullable', 'string', 'max:2048'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.options_json' => ['nullable', 'array'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_tracking' => ['nullable', 'boolean'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.allow_backorder' => ['nullable', 'boolean'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.position' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $updated = $this->products->update($site, $product, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $updated,
        ]);
    }

    public function destroy(Site $site, EcommerceProduct $product): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('delete', $product);

        $this->products->delete($site, $product);

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
