<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Http\Traits\ChecksDemoMode;
use App\Models\OperationLog;
use App\Models\Project;
use App\Models\ProjectSqlExport;
use App\Models\Template;
use App\Models\TenantDatabaseBinding;
use App\Models\User;
use App\Support\OwnedTemplateCatalog;
use App\Services\OperationLogService;
use App\Services\ProjectSqlExportService;
use App\Services\TenantDatabaseBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProjectController extends Controller
{
    use ChecksDemoMode;

    public function __construct(
        protected OperationLogService $operationLogs,
        protected ProjectSqlExportService $projectSqlExports,
        protected TenantDatabaseBindingService $tenantDatabases
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $search = trim((string) $request->input('search', ''));
        $state = (string) $request->input('state', 'active');
        $ownerUserId = $request->filled('owner_user_id')
            ? (int) $request->input('owner_user_id')
            : null;
        $buildStatus = (string) $request->input('build_status', 'all');
        $publishStatus = (string) $request->input('publish_status', 'all');
        $sort = (string) $request->input('sort', 'updated_desc');
        $perPage = max(10, min((int) $request->input('per_page', 20), 100));

        if (! in_array($state, ['active', 'trashed', 'all'], true)) {
            $state = 'active';
        }

        if (! in_array($publishStatus, ['all', 'published', 'unpublished'], true)) {
            $publishStatus = 'all';
        }

        if (! in_array($sort, ['updated_desc', 'created_desc', 'name_asc', 'name_desc'], true)) {
            $sort = 'updated_desc';
        }

        $query = Project::query()
            ->with(['user:id,name,email', 'template:id,name,slug']);

        if ($state === 'all') {
            $query->withTrashed();
        } elseif ($state === 'trashed') {
            $query->onlyTrashed();
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($ownerUserId !== null && $ownerUserId > 0) {
            $query->where('user_id', $ownerUserId);
        }

        if ($buildStatus !== 'all') {
            if ($buildStatus === 'none') {
                $query->whereNull('build_status');
            } else {
                $query->where('build_status', $buildStatus);
            }
        }

        if ($publishStatus === 'published') {
            $query->whereNotNull('published_at');
        } elseif ($publishStatus === 'unpublished') {
            $query->whereNull('published_at');
        }

        $query = match ($sort) {
            'created_desc' => $query->orderByDesc('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->orderByDesc('updated_at'),
        };

        $projects = $query->paginate($perPage)->withQueryString();

        $owners = User::query()
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'name', 'email']);

        if ($ownerUserId !== null && ! $owners->contains('id', $ownerUserId)) {
            $selectedOwner = User::query()->find($ownerUserId, ['id', 'name', 'email']);
            if ($selectedOwner) {
                $owners->push($selectedOwner);
            }
        }

        $pageOwnerIds = $projects->getCollection()
            ->pluck('user_id')
            ->filter()
            ->unique();

        $missingOwnerIds = $pageOwnerIds->diff($owners->pluck('id'));
        if ($missingOwnerIds->isNotEmpty()) {
            $owners->push(
                ...User::query()
                    ->whereIn('id', $missingOwnerIds->all())
                    ->get(['id', 'name', 'email'])
                    ->all()
            );
        }

        $owners = $owners
            ->unique('id')
            ->sortBy('name')
            ->values();

        $buildStatusOptions = Project::query()
            ->withTrashed()
            ->whereNotNull('build_status')
            ->select('build_status')
            ->distinct()
            ->orderBy('build_status')
            ->pluck('build_status')
            ->values();

        $templates = Template::query()
            ->whereIn('slug', OwnedTemplateCatalog::slugs())
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_system']);

        return Inertia::render('Admin/Projects', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'role'),
            'projects' => $projects->through(function (Project $project): array {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'owner' => $project->user ? [
                        'id' => $project->user->id,
                        'name' => $project->user->name,
                        'email' => $project->user->email,
                    ] : null,
                    'is_public' => (bool) $project->is_public,
                    'build_status' => $project->build_status,
                    'is_published' => $project->published_at !== null,
                    'published_at' => $project->published_at?->toISOString(),
                    'subdomain' => $project->subdomain,
                    'custom_domain' => $project->custom_domain,
                    'theme_preset' => $project->theme_preset,
                    'template' => $project->template ? [
                        'id' => $project->template->id,
                        'name' => $project->template->name,
                        'slug' => $project->template->slug,
                    ] : null,
                    'deleted_at' => $project->deleted_at?->toISOString(),
                    'created_at' => $project->created_at->toISOString(),
                    'updated_at' => $project->updated_at->toISOString(),
                ];
            }),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
            'filters' => [
                'search' => $search,
                'state' => $state,
                'owner_user_id' => $ownerUserId ? (string) $ownerUserId : '',
                'build_status' => $buildStatus,
                'publish_status' => $publishStatus,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
            'owners' => $owners->map(fn (User $owner): array => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ])->values(),
            'templates' => $templates->map(fn (Template $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'is_system' => (bool) $template->is_system,
            ])->values(),
            'build_status_options' => $buildStatusOptions,
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validated();
        $owner = User::query()->findOrFail((int) $validated['owner_user_id']);
        $templateId = isset($validated['template_id']) ? (int) $validated['template_id'] : null;

        if ($templateId !== null) {
            $template = Template::query()->find($templateId);
            if ($template && ! $owner->hasAdminBypass() && ! $template->isAvailableForPlan($owner->getCurrentPlan())) {
                return back()->withErrors([
                    'template_id' => 'Selected template is not available for the selected owner plan.',
                ]);
            }
        }

        Project::query()->create([
            'user_id' => (int) $validated['owner_user_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => (bool) ($validated['is_public'] ?? false),
            'template_id' => $templateId,
            'theme_preset' => $validated['theme_preset'] ?? null,
            'last_viewed_at' => now(),
        ]);

        return back()->with('success', 'Project created successfully');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }

        if (array_key_exists('owner_user_id', $validated)) {
            $payload['user_id'] = (int) $validated['owner_user_id'];
        }

        if (array_key_exists('is_public', $validated)) {
            $payload['is_public'] = (bool) $validated['is_public'];
        }

        if (array_key_exists('template_id', $validated)) {
            $templateId = $validated['template_id'] !== null ? (int) $validated['template_id'] : null;
            if ($templateId !== null) {
                $ownerId = (int) ($payload['user_id'] ?? $project->user_id);
                $owner = User::query()->find($ownerId);
                $template = Template::query()->find($templateId);
                if ($owner && $template && ! $owner->hasAdminBypass() && ! $template->isAvailableForPlan($owner->getCurrentPlan())) {
                    return back()->withErrors([
                        'template_id' => 'Selected template is not available for the selected owner plan.',
                    ]);
                }
            }

            $payload['template_id'] = $templateId;
        }

        if (array_key_exists('theme_preset', $validated)) {
            $payload['theme_preset'] = $validated['theme_preset'];
        }

        if ($payload !== []) {
            $project->update($payload);
        }

        return back()->with('success', 'Project updated successfully');
    }

    public function accessAsAdmin(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'target' => 'nullable|string|in:chat,settings,cms',
        ]);

        $target = (string) ($validated['target'] ?? 'cms');
        $workspaceUrl = match ($target) {
            'chat' => route('chat', $project),
            'settings' => route('project.settings', $project),
            default => route('project.cms', $project),
        };

        $this->operationLogs->logProject(
            project: $project,
            channel: OperationLog::CHANNEL_SYSTEM,
            event: 'admin_override_entrypoint',
            status: OperationLog::STATUS_INFO,
            message: 'Admin opened tenant workspace via admin project panel.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'context' => [
                    'target' => $target,
                    'workspace_url' => $workspaceUrl,
                    'project_owner_id' => $project->user_id,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'target' => $target,
            'workspace_url' => $workspaceUrl,
            'project_id' => $project->id,
        ]);
    }

    public function accessAudits(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $perPage = max(10, min((int) $request->input('per_page', 30), 200));

        $logs = OperationLog::query()
            ->withoutTenantProject()
            ->with(['user:id,name,email'])
            ->where('project_id', $project->id)
            ->where('channel', OperationLog::CHANNEL_SYSTEM)
            ->whereIn('event', [
                'admin_override_entrypoint',
                'admin_override_action_trail',
            ])
            ->orderByDesc('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'project_id' => $project->id,
            'data' => $logs->getCollection()
                ->map(fn (OperationLog $log): array => $this->operationLogs->transform($log))
                ->values(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function sqlExport(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => $redirect->getSession()->get('error') ?? 'Action is disabled in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'disk' => ['nullable', 'string', 'max:32'],
            'path' => ['nullable', 'string', 'max:191'],
        ]);

        $export = $this->projectSqlExports->export(
            project: $project,
            requestedBy: $request->user()?->id,
            disk: (string) ($validated['disk'] ?? 'local'),
            basePath: (string) ($validated['path'] ?? 'project-sql-exports')
        );

        $statusCode = $export->status === ProjectSqlExport::STATUS_COMPLETED ? 200 : 500;

        return response()->json([
            'success' => $export->status === ProjectSqlExport::STATUS_COMPLETED,
            'export' => [
                'id' => $export->id,
                'project_id' => $export->project_id,
                'status' => $export->status,
                'sql_path' => $export->sql_path,
                'manifest_path' => $export->manifest_path,
                'checksum' => $export->checksum,
                'file_size_bytes' => $export->file_size_bytes,
                'error_message' => $export->error_message,
                'exported_at' => $export->exported_at?->toISOString(),
            ],
        ], $statusCode);
    }

    public function sqlRestoreDryRun(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'export_id' => ['nullable', 'integer'],
        ]);

        $export = null;
        if (! empty($validated['export_id'])) {
            $export = ProjectSqlExport::query()
                ->where('project_id', $project->id)
                ->find($validated['export_id']);
        }

        $result = $this->projectSqlExports->dryRun($project, $export);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    public function dedicatedDbStatus(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $binding = TenantDatabaseBinding::query()
            ->where('project_id', $project->id)
            ->first();

        return response()->json([
            'feature_enabled' => $this->tenantDatabases->dedicatedDbFeatureEnabled(),
            'eligible' => $this->tenantDatabases->isEligible($project),
            'binding' => $binding ? [
                'id' => $binding->id,
                'status' => $binding->status,
                'driver' => $binding->driver,
                'host' => $binding->host,
                'port' => $binding->port,
                'database' => $binding->database,
                'username' => $binding->username,
                'provisioned_at' => $binding->provisioned_at?->toISOString(),
                'disabled_at' => $binding->disabled_at?->toISOString(),
                'last_health_check_at' => $binding->last_health_check_at?->toISOString(),
                'last_error' => $binding->last_error,
            ] : null,
        ]);
    }

    public function provisionDedicatedDb(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => $redirect->getSession()->get('error') ?? 'Action is disabled in demo mode.',
            ], 403);
        }

        $validated = $request->validate([
            'driver' => ['nullable', 'string', 'max:32'],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:191'],
            'username' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:191'],
            'options_json' => ['nullable', 'array'],
        ]);

        $binding = $this->tenantDatabases->provision(
            project: $project,
            payload: $validated,
            actorId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'binding' => [
                'id' => $binding->id,
                'status' => $binding->status,
                'driver' => $binding->driver,
                'host' => $binding->host,
                'port' => $binding->port,
                'database' => $binding->database,
                'username' => $binding->username,
                'provisioned_at' => $binding->provisioned_at?->toISOString(),
            ],
        ]);
    }

    public function disableDedicatedDb(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return response()->json([
                'error' => $redirect->getSession()->get('error') ?? 'Action is disabled in demo mode.',
            ], 403);
        }

        $binding = $this->tenantDatabases->deactivate($project, $request->user()?->id);

        return response()->json([
            'success' => true,
            'binding' => $binding ? [
                'id' => $binding->id,
                'status' => $binding->status,
                'disabled_at' => $binding->disabled_at?->toISOString(),
            ] : null,
        ]);
    }
}
