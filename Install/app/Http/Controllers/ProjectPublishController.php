<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Services\FallbackSubdomainService;
use App\Services\OperationLogService;
use App\Services\PublishedProjectCacheService;
use App\Services\ProjectOperationGuardService;
use App\Support\SubdomainHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectPublishController extends Controller
{
    public function __construct(
        protected ProjectOperationGuardService $operationGuard,
        protected OperationLogService $operationLogs,
        protected PublishedProjectCacheService $publishedCache,
        protected FallbackSubdomainService $fallbackSubdomains
    ) {}

    /**
     * Check subdomain availability.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        // Check if subdomains are enabled globally
        if (! SystemSetting::get('domain_enable_subdomains', false)) {
            return response()->json([
                'available' => false,
                'errors' => ['Subdomain publishing is not enabled on this platform.'],
            ], 403);
        }

        $validated = $request->validate([
            'subdomain' => 'required|string|max:63',
            'project_id' => 'nullable|string',
        ]);

        $subdomain = SubdomainHelper::normalize($validated['subdomain']);
        $errors = SubdomainHelper::validate($subdomain);

        if (! empty($errors)) {
            return response()->json([
                'available' => false,
                'errors' => $errors,
            ]);
        }

        $available = SubdomainHelper::isAvailable(
            $subdomain,
            $validated['project_id'] ?? null
        );

        return response()->json([
            'available' => $available,
            'errors' => $available ? [] : ['This subdomain is already taken.'],
        ]);
    }

    /**
     * Publish project to subdomain.
     */
    public function publish(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'subdomain' => 'nullable|string|max:63',
            'visibility' => 'required|in:public,private',
        ]);

        return $this->publishWithGuard(
            $request,
            $project,
            (string) ($validated['subdomain'] ?? ''),
            $validated['visibility'],
            'publish'
        );
    }

    /**
     * Retry publish using stored settings (or request overrides).
     */
    public function retry(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'subdomain' => 'nullable|string|max:63',
            'visibility' => 'nullable|in:public,private',
        ]);

        $subdomain = $validated['subdomain'] ?? $project->subdomain ?? '';
        $visibility = $validated['visibility'] ?? $project->published_visibility ?? 'public';

        return $this->publishWithGuard(
            $request,
            $project,
            (string) $subdomain,
            $visibility,
            'publish-retry'
        );
    }

    /**
     * Unpublish project.
     */
    public function unpublish(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update(['published_at' => null]);
        $this->publishedCache->flushProject((string) $project->id);

        $this->operationLogs->logPublish(
            project: $project,
            event: 'unpublish_success',
            status: OperationLog::STATUS_SUCCESS,
            message: 'Project has been unpublished from subdomain.',
            attributes: [
                'source' => self::class,
            ]
        );

        return response()->json(['success' => true]);
    }

    protected function publishWithGuard(
        Request $request,
        Project $project,
        string $subdomainInput,
        string $visibility,
        string $operation
    ): JsonResponse {
        try {
            $response = $this->operationGuard->execute(
                $request,
                $project,
                $operation,
                fn () => $this->performPublish($request, $project, $subdomainInput, $visibility),
                [
                    'subdomain' => $subdomainInput,
                    'visibility' => $visibility,
                    'operation' => $operation,
                ]
            );
        } catch (\Throwable $e) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_unexpected_exception',
                status: OperationLog::STATUS_ERROR,
                message: $e->getMessage(),
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                    'context' => [
                        'operation' => $operation,
                        'exception' => $e->getMessage(),
                    ],
                ]
            );

            return response()->json([
                'error' => 'Publish failed due to an unexpected server error.',
            ], 500);
        }

        if ($response->headers->get('X-Idempotent-Replay') === 'true') {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_idempotent_replay',
                status: OperationLog::STATUS_INFO,
                message: 'Publish response served from idempotency cache.',
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                    'context' => ['operation' => $operation],
                ]
            );
        } elseif ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            $body = $response->getData(true);
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_rejected',
                status: OperationLog::STATUS_WARNING,
                message: $body['error'] ?? 'Publish request was rejected.',
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                    'context' => [
                        'operation' => $operation,
                        'http_status' => $response->getStatusCode(),
                    ],
                ]
            );
        }

        return $response;
    }

    protected function performPublish(
        Request $request,
        Project $project,
        string $subdomainInput,
        string $visibility
    ): JsonResponse {
        // Check if subdomains are enabled globally
        if (! SystemSetting::get('domain_enable_subdomains', false)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_disabled_globally',
                status: OperationLog::STATUS_ERROR,
                message: 'Subdomain publishing is disabled by platform settings.',
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                ]
            );

            return response()->json([
                'error' => 'Subdomain publishing is not enabled on this platform.',
            ], 403);
        }

        $user = $request->user();

        if ($user->canUseFileStorage()) {
            $remainingStorage = $user->getRemainingStorageBytes();
            if ($remainingStorage !== -1 && $remainingStorage <= 0) {
                $this->operationLogs->logPublish(
                    project: $project,
                    event: 'publish_capacity_quota_exceeded',
                    status: OperationLog::STATUS_WARNING,
                    message: 'Publish blocked because tenant storage quota is exhausted.',
                    attributes: [
                        'source' => self::class,
                        'domain' => SubdomainHelper::normalize($subdomainInput),
                        'context' => [
                            'remaining_storage_bytes' => $remainingStorage,
                        ],
                    ]
                );

                return response()->json([
                    'error' => 'Storage quota exceeded for current plan.',
                    'code' => 'capacity_quota_exceeded',
                ], 403);
            }
        }

        // Check plan permissions
        if (! $user->canUseSubdomains()) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_plan_restricted',
                status: OperationLog::STATUS_WARNING,
                message: 'Publish blocked because user plan does not allow subdomains.',
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                ]
            );

            return response()->json([
                'error' => 'Your plan does not include subdomain publishing.',
            ], 403);
        }

        // Check subdomain limit (only for new subdomain)
        $subdomainInput = SubdomainHelper::normalize($subdomainInput);
        $subdomain = $subdomainInput;

        if ($subdomain === '') {
            $subdomain = $project->subdomain
                ? SubdomainHelper::normalize((string) $project->subdomain)
                : $this->fallbackSubdomains->suggest($project);
        }

        $isNewSubdomain = $project->subdomain === null;
        if ($isNewSubdomain && ! $user->canCreateMoreSubdomains()) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_limit_reached',
                status: OperationLog::STATUS_WARNING,
                message: 'Publish blocked because subdomain quota is reached.',
                attributes: [
                    'source' => self::class,
                    'domain' => SubdomainHelper::normalize($subdomainInput),
                ]
            );

            return response()->json([
                'error' => 'You have reached your subdomain limit.',
            ], 403);
        }

        // Validate subdomain
        $errors = SubdomainHelper::validate($subdomain);

        if (! empty($errors)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_invalid_subdomain',
                status: OperationLog::STATUS_WARNING,
                message: $errors[0],
                attributes: [
                    'source' => self::class,
                    'domain' => $subdomain,
                ]
            );

            return response()->json(['error' => $errors[0]], 422);
        }

        if (! SubdomainHelper::isAvailable($subdomain, $project->id)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_subdomain_taken',
                status: OperationLog::STATUS_WARNING,
                message: 'This subdomain is already taken.',
                attributes: [
                    'source' => self::class,
                    'domain' => $subdomain,
                ]
            );

            return response()->json(['error' => 'This subdomain is already taken.'], 422);
        }

        // Check private visibility permission
        if ($visibility === 'private' && ! $user->canUsePrivateVisibility()) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'publish_private_visibility_restricted',
                status: OperationLog::STATUS_WARNING,
                message: 'Private visibility is not available on the current plan.',
                attributes: [
                    'source' => self::class,
                    'domain' => $subdomain,
                ]
            );

            return response()->json([
                'error' => 'Your plan does not include private visibility.',
            ], 403);
        }

        $project->update([
            'subdomain' => $subdomain,
            'published_title' => $project->published_title ?? $project->name,
            'published_description' => $project->published_description ?? '',
            'published_visibility' => $visibility,
            'published_at' => $project->published_at ?? now(),
        ]);
        $this->publishedCache->flushProject((string) $project->id);

        $this->operationLogs->logPublish(
            project: $project,
            event: 'publish_success',
            status: OperationLog::STATUS_SUCCESS,
            message: "Project published to {$subdomain}.",
            attributes: [
                'source' => self::class,
                'domain' => $subdomain,
                'context' => [
                    'visibility' => $visibility,
                    'published_url' => $project->getPublishedUrl(),
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'project' => $project->fresh(),
            'url' => $project->getPublishedUrl(),
        ]);
    }
}
