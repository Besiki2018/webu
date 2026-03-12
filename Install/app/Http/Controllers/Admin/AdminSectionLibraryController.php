<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Services\SectionLibraryPresetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionLibraryRequest;
use App\Http\Requests\Admin\UpdateSectionLibraryRequest;
use App\Http\Traits\ChecksDemoMode;
use App\Models\SectionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminSectionLibraryController extends Controller
{
    use ChecksDemoMode;

    public function __construct(
        protected SectionLibraryPresetService $presetService
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', 'all'));
        $status = trim((string) $request->input('status', 'all'));

        if (! in_array($status, ['all', 'enabled', 'disabled'], true)) {
            $status = 'all';
        }

        $query = SectionLibrary::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('key', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($status === 'enabled') {
            $query->where('enabled', true);
        } elseif ($status === 'disabled') {
            $query->where('enabled', false);
        }

        $sections = $query
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->map(fn (SectionLibrary $section): array => $this->mapSection($section))
            ->values();

        $categories = $this->categories();

        return Inertia::render('Admin/CmsSections', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'role'),
            'sections' => $sections,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category' => $category === '' ? 'all' : $category,
                'status' => $status,
            ],
            'stats' => [
                'total' => SectionLibrary::query()->count(),
                'enabled' => SectionLibrary::query()->where('enabled', true)->count(),
                'categories' => SectionLibrary::query()->distinct('category')->count('category'),
                'preset_count' => $this->presetService->defaultsCount(),
            ],
        ]);
    }

    public function store(StoreSectionLibraryRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validated();
        $schema = $this->resolveSchemaPayload($request, required: true);

        SectionLibrary::query()->create([
            'key' => strtolower((string) $validated['key']),
            'category' => $this->normalizeCategory((string) $validated['category']),
            'schema_json' => $schema,
            'enabled' => (bool) ($validated['enabled'] ?? true),
        ]);

        return back()->with('success', 'Section template uploaded successfully');
    }

    public function update(UpdateSectionLibraryRequest $request, SectionLibrary $section): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('key', $validated)) {
            $payload['key'] = strtolower((string) $validated['key']);
        }

        if (array_key_exists('category', $validated)) {
            $payload['category'] = $this->normalizeCategory((string) $validated['category']);
        }

        if (array_key_exists('enabled', $validated)) {
            $payload['enabled'] = (bool) $validated['enabled'];
        }

        if ($request->hasFile('schema_file') || $request->has('schema_json')) {
            $payload['schema_json'] = $this->resolveSchemaPayload($request, required: false);
        }

        if ($payload !== []) {
            $section->update($payload);
        }

        return back()->with('success', 'Section template updated successfully');
    }

    public function destroy(Request $request, SectionLibrary $section): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $section->delete();

        return back()->with('success', 'Section template deleted');
    }

    public function importDefaults(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $count = $this->presetService->syncDefaults();

        return back()->with('success', "Imported {$count} section templates");
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        return SectionLibrary::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSchemaPayload(Request $request, bool $required): array
    {
        $decoded = null;

        if ($request->hasFile('schema_file')) {
            $raw = file_get_contents($request->file('schema_file')->getRealPath());
            $decoded = json_decode((string) $raw, true);
        } elseif ($request->has('schema_json')) {
            $raw = $request->input('schema_json');

            if (is_array($raw)) {
                $decoded = $raw;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
            } elseif ($required) {
                throw ValidationException::withMessages([
                    'schema_json' => 'Schema JSON is required.',
                ]);
            }
        } elseif ($required) {
            throw ValidationException::withMessages([
                'schema_json' => 'Schema JSON or JSON file is required.',
            ]);
        }

        if (! is_array($decoded) || Arr::isList($decoded)) {
            throw ValidationException::withMessages([
                'schema_json' => 'Schema must be a valid JSON object.',
            ]);
        }

        if (! isset($decoded['type'])) {
            $decoded['type'] = 'object';
        }

        if (! isset($decoded['properties']) || ! is_array($decoded['properties'])) {
            $decoded['properties'] = [];
        }

        return $decoded;
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = (string) str($category)
            ->lower()
            ->trim()
            ->replace(' ', '_')
            ->replaceMatches('/[^a-z0-9_-]/', '');

        return $normalized !== '' ? $normalized : 'general';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSection(SectionLibrary $section): array
    {
        $meta = collect($section->schema_json['_meta'] ?? []);

        return [
            'id' => $section->id,
            'key' => $section->key,
            'category' => $section->category,
            'enabled' => (bool) $section->enabled,
            'schema_json' => $section->schema_json ?? [],
            'meta' => [
                'label' => $meta->get('label', $section->key),
                'description' => $meta->get('description'),
                'design_variant' => $meta->get('design_variant'),
                'backend_updatable' => (bool) $meta->get('backend_updatable', false),
                'bindings' => $this->normalizeBindings($meta->get('bindings')),
            ],
            'updated_at' => $section->updated_at?->toISOString(),
            'created_at' => $section->created_at?->toISOString(),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, string>
     */
    private function normalizeBindings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return Collection::make($value)
            ->filter(fn ($binding, $field) => is_string($field) && is_string($binding))
            ->mapWithKeys(fn ($binding, $field) => [(string) $field => (string) $binding])
            ->all();
    }
}
