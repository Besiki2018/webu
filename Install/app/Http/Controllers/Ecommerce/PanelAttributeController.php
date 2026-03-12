<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceAttribute;
use App\Models\EcommerceAttributeValue;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelAttributeController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $attributes = EcommerceAttribute::query()
            ->where('site_id', $site->id)
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'site_id' => $site->id,
            'attributes' => $attributes,
        ]);
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
                Rule::unique('ecommerce_attributes')->where(fn ($q) => $q->where('site_id', $site->id)),
            ],
            'type' => ['nullable', 'string', Rule::in(['text', 'color', 'size', 'number'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $attribute = EcommerceAttribute::query()->create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'type' => $validated['type'] ?? 'text',
            'status' => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
            'meta_json' => $validated['meta_json'] ?? null,
        ]);

        return response()->json([
            'message' => 'Attribute created successfully.',
            'attribute' => $attribute->fresh()->loadCount('values'),
        ], 201);
    }

    public function update(Request $request, Site $site, EcommerceAttribute $attribute): JsonResponse
    {
        Gate::authorize('update', $site->project);
        abort_unless($attribute->site_id === $site->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ecommerce_attributes')
                    ->ignore($attribute->id)
                    ->where(fn ($q) => $q->where('site_id', $site->id)),
            ],
            'type' => ['sometimes', 'string', Rule::in(['text', 'color', 'size', 'number'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $attribute->fill($validated);
        $attribute->save();

        return response()->json([
            'message' => 'Attribute updated successfully.',
            'attribute' => $attribute->fresh()->loadCount('values'),
        ]);
    }

    public function destroy(Site $site, EcommerceAttribute $attribute): JsonResponse
    {
        Gate::authorize('update', $site->project);
        abort_unless($attribute->site_id === $site->id, 404);

        $attribute->delete();

        return response()->json([
            'message' => 'Attribute deleted successfully.',
        ]);
    }

    public function valuesIndex(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $values = EcommerceAttributeValue::query()
            ->where('site_id', $site->id)
            ->with(['attribute:id,name,slug,type'])
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return response()->json([
            'site_id' => $site->id,
            'values' => $values,
        ]);
    }

    public function valuesStore(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'ecommerce_attribute_id' => [
                'required',
                'integer',
                Rule::exists('ecommerce_attributes', 'id')->where(fn ($q) => $q->where('site_id', $site->id)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'color_hex' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $attribute = EcommerceAttribute::query()
            ->where('site_id', $site->id)
            ->findOrFail((int) $validated['ecommerce_attribute_id']);

        $duplicate = EcommerceAttributeValue::query()
            ->where('site_id', $site->id)
            ->where('ecommerce_attribute_id', $attribute->id)
            ->where('slug', $validated['slug'])
            ->exists();
        if ($duplicate) {
            return response()->json([
                'error' => 'Value slug already exists for this attribute.',
            ], 422);
        }

        $value = EcommerceAttributeValue::query()->create([
            'site_id' => $site->id,
            'ecommerce_attribute_id' => $attribute->id,
            'label' => $validated['label'],
            'slug' => $validated['slug'],
            'color_hex' => $validated['color_hex'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'meta_json' => $validated['meta_json'] ?? null,
        ]);

        return response()->json([
            'message' => 'Attribute value created successfully.',
            'value' => $value->fresh()->load(['attribute:id,name,slug,type']),
        ], 201);
    }

    public function valuesUpdate(Request $request, Site $site, EcommerceAttributeValue $attributeValue): JsonResponse
    {
        Gate::authorize('update', $site->project);
        abort_unless($attributeValue->site_id === $site->id, 404);

        $validated = $request->validate([
            'ecommerce_attribute_id' => [
                'sometimes',
                'integer',
                Rule::exists('ecommerce_attributes', 'id')->where(fn ($q) => $q->where('site_id', $site->id)),
            ],
            'label' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'color_hex' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $targetAttributeId = (int) ($validated['ecommerce_attribute_id'] ?? $attributeValue->ecommerce_attribute_id);
        if (array_key_exists('slug', $validated)) {
            $duplicate = EcommerceAttributeValue::query()
                ->where('site_id', $site->id)
                ->where('ecommerce_attribute_id', $targetAttributeId)
                ->where('slug', $validated['slug'])
                ->where('id', '!=', $attributeValue->id)
                ->exists();
            if ($duplicate) {
                return response()->json([
                    'error' => 'Value slug already exists for this attribute.',
                ], 422);
            }
        }

        $attributeValue->fill($validated);
        $attributeValue->save();

        return response()->json([
            'message' => 'Attribute value updated successfully.',
            'value' => $attributeValue->fresh()->load(['attribute:id,name,slug,type']),
        ]);
    }

    public function valuesDestroy(Site $site, EcommerceAttributeValue $attributeValue): JsonResponse
    {
        Gate::authorize('update', $site->project);
        abort_unless($attributeValue->site_id === $site->id, 404);

        $attributeValue->delete();

        return response()->json([
            'message' => 'Attribute value deleted successfully.',
        ]);
    }
}

