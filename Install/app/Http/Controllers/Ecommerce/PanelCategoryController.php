<?php

namespace App\Http\Controllers\Ecommerce;

use App\Ecommerce\Contracts\EcommercePanelCategoryServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Http\Controllers\Controller;
use App\Models\EcommerceCategory;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelCategoryController extends Controller
{
    public function __construct(
        protected EcommercePanelCategoryServiceContract $categories
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->categories->list($site));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ecommerce_categories')
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $category = $this->categories->create($site, $validated);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category,
        ], 201);
    }

    public function update(Request $request, Site $site, EcommerceCategory $category): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ecommerce_categories')
                    ->ignore($category->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->categories->update($site, $category, $validated);
        } catch (EcommerceDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $updated,
        ]);
    }

    public function destroy(Site $site, EcommerceCategory $category): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('delete', $category);

        $this->categories->delete($site, $category);

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}

