<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Request;

class TenantProjectRequestResolver
{
    /**
     * Canonical request attributes used by middleware that identify a project scope.
     *
     * Note: "tenant" here is current project runtime scope naming, not future universal tenants table.
     *
     * @return array<int, string>
     */
    public function projectAttributeKeys(): array
    {
        return [
            'tenant_project',
            'subdomain_project',
            'custom_domain_project',
            'project',
        ];
    }

    public function resolveProject(Request $request): ?Project
    {
        foreach ($this->projectAttributeKeys() as $attributeKey) {
            $attributeProject = $request->attributes->get($attributeKey);
            if ($attributeProject instanceof Project) {
                return $attributeProject;
            }
        }

        $routeProject = $this->extractRouteProject($request);
        if ($routeProject !== null) {
            return $routeProject;
        }

        return null;
    }

    public function requestNeedsTenantContext(Request $request): bool
    {
        if ($this->extractRouteProjectIdentifier($request) !== null) {
            return true;
        }

        foreach ($this->projectAttributeKeys() as $attributeKey) {
            if ($request->attributes->has($attributeKey)) {
                return true;
            }
        }

        return false;
    }

    public function extractRouteProject(Request $request): ?Project
    {
        $routeProject = $request->route('project');

        if ($routeProject instanceof Project) {
            return $routeProject;
        }

        if (is_string($routeProject) && $routeProject !== '') {
            return Project::withTrashed()->find($routeProject);
        }

        $routeProjectId = $request->route('projectId');
        if (is_string($routeProjectId) && $routeProjectId !== '') {
            return Project::withTrashed()->find($routeProjectId);
        }

        return null;
    }

    public function extractRouteProjectIdentifier(Request $request): ?string
    {
        $routeProject = $request->route('project');
        if ($routeProject instanceof Project) {
            return (string) $routeProject->id;
        }

        if (is_string($routeProject) && $routeProject !== '') {
            return $routeProject;
        }

        $routeProjectId = $request->route('projectId');
        if (is_string($routeProjectId) && $routeProjectId !== '') {
            return $routeProjectId;
        }

        return null;
    }
}

