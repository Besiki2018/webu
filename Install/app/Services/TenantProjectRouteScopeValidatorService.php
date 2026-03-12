<?php

namespace App\Services;

use App\Contracts\TenantProjectRouteScopeValidatorContract;
use App\Models\Project;
use App\Models\Site;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TenantProjectRouteScopeValidatorService implements TenantProjectRouteScopeValidatorContract
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        $route = $request->route();
        $parameters = is_object($route) && method_exists($route, 'parameters')
            ? (array) $route->parameters()
            : [];

        $site = $this->resolveSite($parameters['site'] ?? null);
        $routeProject = $this->resolveProject($parameters['project'] ?? ($parameters['projectId'] ?? null));
        $contextProject = $this->tenantContext->project();
        $expectedProjectId = $site?->project_id ?? $routeProject?->id ?? $contextProject?->id;

        $errors = [];

        if ($site !== null && $routeProject !== null && (string) $site->project_id !== (string) $routeProject->id) {
            $errors[] = [
                'code' => 'site_project_scope_mismatch',
                'path' => '$.route.site.project_id',
                'message' => 'Route site does not belong to route project.',
                'expected' => (string) $routeProject->id,
                'actual' => (string) $site->project_id,
            ];
        }

        if ($site !== null && $contextProject !== null && (string) $site->project_id !== (string) $contextProject->id) {
            $errors[] = [
                'code' => 'tenant_context_site_mismatch',
                'path' => '$.tenant_context.project_id',
                'message' => 'Tenant context project does not match route site project.',
                'expected' => (string) $site->project_id,
                'actual' => (string) $contextProject->id,
            ];
        }

        foreach ($parameters as $name => $value) {
            if (! is_string($name) || ! $value instanceof Model) {
                continue;
            }

            if ($value instanceof Site || $value instanceof Project) {
                continue;
            }

            $attributes = $value->getAttributes();

            if ($site !== null && array_key_exists('site_id', $attributes)) {
                $resourceSiteId = (string) ($attributes['site_id'] ?? '');

                if ($resourceSiteId !== '' && $resourceSiteId !== (string) $site->id) {
                    $errors[] = [
                        'code' => 'route_model_site_scope_mismatch',
                        'path' => '$.route.'.$name.'.site_id',
                        'message' => 'Route model does not belong to the requested site.',
                        'param' => $name,
                        'model' => $value::class,
                        'expected' => (string) $site->id,
                        'actual' => $resourceSiteId,
                    ];
                }
            }

            if ($expectedProjectId !== null && array_key_exists('project_id', $attributes)) {
                $resourceProjectId = (string) ($attributes['project_id'] ?? '');

                if ($resourceProjectId !== '' && $resourceProjectId !== (string) $expectedProjectId) {
                    $errors[] = [
                        'code' => 'route_model_project_scope_mismatch',
                        'path' => '$.route.'.$name.'.project_id',
                        'message' => 'Route model does not belong to the expected project.',
                        'param' => $name,
                        'model' => $value::class,
                        'expected' => (string) $expectedProjectId,
                        'actual' => $resourceProjectId,
                    ];
                }
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values($errors),
            'snapshot' => [
                'route_name' => is_object($route) && method_exists($route, 'getName') ? $route->getName() : null,
                'tenant_context_project_id' => $contextProject?->id,
                'route_project_id' => $routeProject?->id,
                'route_site_id' => $site?->id,
                'route_site_project_id' => $site?->project_id,
                'checked_route_model_params' => array_values(array_keys(array_filter(
                    $parameters,
                    static fn (mixed $value): bool => $value instanceof Model
                ))),
            ],
        ];
    }

    private function resolveSite(mixed $candidate): ?Site
    {
        if ($candidate instanceof Site) {
            return $candidate;
        }

        if (is_string($candidate) && $candidate !== '') {
            return Site::query()->find($candidate);
        }

        return null;
    }

    private function resolveProject(mixed $candidate): ?Project
    {
        if ($candidate instanceof Project) {
            return $candidate;
        }

        if (is_string($candidate) && $candidate !== '') {
            return Project::withTrashed()->find($candidate);
        }

        return null;
    }
}

