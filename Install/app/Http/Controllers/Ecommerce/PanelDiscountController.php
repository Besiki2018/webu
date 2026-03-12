<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceDiscount;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelDiscountController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $discounts = EcommerceDiscount::query()
            ->where('site_id', $site->id)
            ->latest('id')
            ->get()
            ->map(fn (EcommerceDiscount $discount): array => $this->serializeDiscount($discount))
            ->values()
            ->all();

        return response()->json([
            'site_id' => $site->id,
            'discounts' => $discounts,
        ]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $this->validateDiscountPayload($request, $site);

        /** @var EcommerceDiscount $discount */
        $discount = EcommerceDiscount::query()->create([
            ...$validated,
            'site_id' => $site->id,
        ]);

        return response()->json([
            'message' => 'Discount created successfully.',
            'discount' => $this->serializeDiscount($discount->fresh()),
        ], 201);
    }

    public function update(Request $request, Site $site, EcommerceDiscount $discount): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $target = $this->findSiteDiscountOrFail($site, $discount);

        $validated = $this->validateDiscountPayload($request, $site, $target);
        $target->update($validated);

        return response()->json([
            'message' => 'Discount updated successfully.',
            'discount' => $this->serializeDiscount($target->fresh()),
        ]);
    }

    public function destroy(Site $site, EcommerceDiscount $discount): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $target = $this->findSiteDiscountOrFail($site, $discount);
        $target->delete();

        return response()->json([
            'message' => 'Discount deleted successfully.',
        ]);
    }

    public function bulkApply(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'create_discount_record' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'inactive'])],
        ]);

        $productIds = array_values(array_unique(array_map('intval', $validated['product_ids'] ?? [])));
        $products = EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->whereIn('id', $productIds)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'error' => 'No valid products selected for bulk discount.',
            ], 422);
        }

        $type = (string) $validated['type'];
        $value = (float) $validated['value'];

        DB::transaction(function () use ($products, $site, $type, $value, $validated) {
            foreach ($products as $product) {
                $basePrice = (float) $product->price;
                $nextPrice = $this->calculateDiscountedPrice($basePrice, $type, $value);

                $update = [
                    'price' => number_format($nextPrice, 2, '.', ''),
                ];

                if ($product->compare_at_price === null || (float) $product->compare_at_price <= 0) {
                    $update['compare_at_price'] = number_format($basePrice, 2, '.', '');
                }

                $product->update($update);

                EcommerceProductVariant::query()
                    ->where('site_id', $site->id)
                    ->where('product_id', $product->id)
                    ->whereNotNull('price')
                    ->get()
                    ->each(function (EcommerceProductVariant $variant) use ($type, $value): void {
                        $variantBasePrice = (float) ($variant->price ?? 0);
                        $variantNextPrice = $this->calculateDiscountedPrice($variantBasePrice, $type, $value);

                        $variantUpdate = [
                            'price' => number_format($variantNextPrice, 2, '.', ''),
                        ];

                        if ($variant->compare_at_price === null || (float) $variant->compare_at_price <= 0) {
                            $variantUpdate['compare_at_price'] = number_format($variantBasePrice, 2, '.', '');
                        }

                        $variant->update($variantUpdate);
                    });
            }

            if ((bool) ($validated['create_discount_record'] ?? false)) {
                EcommerceDiscount::query()->create([
                    'site_id' => $site->id,
                    'name' => trim((string) ($validated['name'] ?? 'Bulk Discount')),
                    'code' => $this->nullableString($validated['code'] ?? null),
                    'type' => $type,
                    'value' => number_format($value, 2, '.', ''),
                    'status' => (string) ($validated['status'] ?? 'active'),
                    'scope' => 'specific_products',
                    'product_ids_json' => $products->pluck('id')->values()->all(),
                    'category_ids_json' => [],
                    'notes' => 'Created from bulk discount apply action.',
                    'meta_json' => [
                        'source' => 'panel_bulk_apply',
                    ],
                ]);
            }
        });

        return response()->json([
            'message' => 'Bulk discount applied successfully.',
            'affected_products' => $products->count(),
        ]);
    }

    private function findSiteDiscountOrFail(Site $site, EcommerceDiscount $discount): EcommerceDiscount
    {
        abort_unless($discount->site_id === $site->id, 404);

        return $discount;
    }

    /**
     * @return array<string,mixed>
     */
    private function validateDiscountPayload(Request $request, Site $site, ?EcommerceDiscount $discount = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('ecommerce_discounts')
                    ->ignore($discount?->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'type' => ['required', 'string', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'inactive'])],
            'scope' => ['nullable', 'string', Rule::in(['all_products', 'specific_products', 'categories'])],
            'product_ids_json' => ['nullable', 'array'],
            'product_ids_json.*' => ['integer', 'min:1'],
            'category_ids_json' => ['nullable', 'array'],
            'category_ids_json.*' => ['integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $validated['code'] = $this->nullableString($validated['code'] ?? null);
        $validated['status'] = (string) ($validated['status'] ?? 'draft');
        $validated['scope'] = (string) ($validated['scope'] ?? 'specific_products');
        $validated['product_ids_json'] = $this->validatedScopedIds(
            $site,
            $validated['product_ids_json'] ?? [],
            'product'
        );
        $validated['category_ids_json'] = $this->validatedScopedIds(
            $site,
            $validated['category_ids_json'] ?? [],
            'category'
        );

        return $validated;
    }

    /**
     * @param  array<int,mixed>  $ids
     * @return array<int,int>
     */
    private function validatedScopedIds(Site $site, array $ids, string $kind): array
    {
        $normalizedIds = array_values(array_unique(array_map('intval', $ids)));
        if ($normalizedIds === []) {
            return [];
        }

        if ($kind === 'product') {
            $validIds = EcommerceProduct::query()
                ->where('site_id', $site->id)
                ->whereIn('id', $normalizedIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            return $validIds;
        }

        $validIds = DB::table('ecommerce_categories')
            ->where('site_id', $site->id)
            ->whereIn('id', $normalizedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $validIds;
    }

    private function calculateDiscountedPrice(float $basePrice, string $type, float $value): float
    {
        $next = match ($type) {
            'fixed' => $basePrice - $value,
            default => $basePrice - (($basePrice * $value) / 100),
        };

        return max(0.0, round($next, 2));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeDiscount(EcommerceDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'site_id' => $discount->site_id,
            'name' => $discount->name,
            'code' => $discount->code,
            'type' => $discount->type,
            'value' => (string) $discount->value,
            'status' => $discount->status,
            'scope' => $discount->scope,
            'product_ids_json' => $discount->product_ids_json ?? [],
            'category_ids_json' => $discount->category_ids_json ?? [],
            'starts_at' => $discount->starts_at?->toISOString(),
            'ends_at' => $discount->ends_at?->toISOString(),
            'notes' => $discount->notes,
            'meta_json' => $discount->meta_json ?? [],
            'created_at' => $discount->created_at?->toISOString(),
            'updated_at' => $discount->updated_at?->toISOString(),
        ];
    }
}

