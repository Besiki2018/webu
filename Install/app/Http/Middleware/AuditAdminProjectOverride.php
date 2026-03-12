<?php

namespace App\Http\Middleware;

use App\Models\OperationLog;
use App\Models\Project;
use App\Models\Site;
use App\Services\OperationLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminProjectOverride
{
    public function __construct(
        protected OperationLogService $operationLogs
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        $project = $this->resolveProjectFromRoute($request);
        if (! $project || (string) $project->user_id === (string) $user->id) {
            return $next($request);
        }

        // Protect operation log storage from request floods (polling, refresh loops).
        $cacheKey = sprintf(
            'admin-override-audit:%s:%s:%s:%s',
            (string) $user->id,
            (string) $project->id,
            $request->method(),
            (string) $request->route()?->getName()
        );

        if (Cache::add($cacheKey, true, now()->addSeconds(20))) {
            $this->operationLogs->logProject(
                project: $project,
                channel: OperationLog::CHANNEL_SYSTEM,
                event: 'admin_override_action_trail',
                status: OperationLog::STATUS_INFO,
                message: 'Admin accessed tenant workspace action.',
                attributes: [
                    'user_id' => $user->id,
                    'source' => self::class,
                    'context' => [
                        'route_name' => $request->route()?->getName(),
                        'method' => $request->method(),
                        'path' => '/'.ltrim($request->path(), '/'),
                        'project_owner_id' => $project->user_id,
                    ],
                ]
            );
        }

        return $next($request);
    }

    private function resolveProjectFromRoute(Request $request): ?Project
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        $project = $route->parameter('project');
        if ($project instanceof Project) {
            return $project;
        }

        $site = $route->parameter('site');
        if ($site instanceof Site) {
            return $site->project()->first();
        }

        return null;
    }
}

