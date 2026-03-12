<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\Project;
use App\Services\DomainSettingService;
use App\Services\DomainVerificationService;
use App\Services\OperationLogService;
use App\Services\PublishedProjectCacheService;
use App\Support\CustomDomainHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectCustomDomainController extends Controller
{
    protected DomainSettingService $settingService;

    protected DomainVerificationService $verificationService;

    public function __construct(
        DomainSettingService $settingService,
        DomainVerificationService $verificationService,
        protected OperationLogService $operationLogs,
        protected PublishedProjectCacheService $publishedCache
    ) {
        $this->settingService = $settingService;
        $this->verificationService = $verificationService;
    }

    /**
     * Check if a domain is available.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        // Check if custom domains are enabled globally
        if (! $this->settingService->isCustomDomainsEnabled()) {
            return response()->json([
                'available' => false,
                'error' => 'Custom domains are not enabled on this platform.',
            ], 403);
        }

        $request->validate([
            'domain' => 'required|string|max:255',
            'exclude_project_id' => 'nullable|string',
        ]);

        $domain = CustomDomainHelper::normalize($request->input('domain'));

        // Validate format
        $errors = CustomDomainHelper::validate($domain);
        if (! empty($errors)) {
            return response()->json([
                'available' => false,
                'error' => $errors[0],
            ]);
        }

        // Check if domain is a subdomain of the base domain (not allowed)
        $baseDomain = $this->settingService->getBaseDomain();
        if ($baseDomain && CustomDomainHelper::isSubdomainOfBase($domain, $baseDomain)) {
            return response()->json([
                'available' => false,
                'error' => 'You cannot use the platform base domain as a custom domain.',
            ]);
        }

        // Check availability
        $excludeId = $request->input('exclude_project_id');
        $available = CustomDomainHelper::isAvailable($domain, $excludeId);

        return response()->json([
            'available' => $available,
            'error' => $available ? null : 'This domain is already in use.',
        ]);
    }

    /**
     * Store a custom domain for a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        // Authorize access
        $this->authorize('update', $project);

        // Check if custom domains are enabled globally
        if (! $this->settingService->isCustomDomainsEnabled()) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_disabled_globally',
                status: OperationLog::STATUS_WARNING,
                message: 'Custom domains are disabled by platform settings.',
                attributes: ['source' => self::class]
            );

            return response()->json([
                'success' => false,
                'error' => 'Custom domains are not enabled on this platform.',
            ], 403);
        }

        // Check if user can use custom domains
        $user = $request->user();
        if (! $user->canUseCustomDomains()) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_plan_restricted',
                status: OperationLog::STATUS_WARNING,
                message: 'Custom domain setup blocked by plan restrictions.',
                attributes: ['source' => self::class]
            );

            return response()->json([
                'success' => false,
                'error' => 'Your plan does not include custom domain publishing.',
            ], 403);
        }

        // Check if user can create more custom domains
        if (! $user->canCreateMoreCustomDomains() && ! $project->custom_domain) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_limit_reached',
                status: OperationLog::STATUS_WARNING,
                message: 'Custom domain limit reached for current user plan.',
                attributes: ['source' => self::class]
            );

            return response()->json([
                'success' => false,
                'error' => 'You have reached your custom domain limit.',
            ], 403);
        }

        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $domain = CustomDomainHelper::normalize($request->input('domain'));

        // Validate format
        $errors = CustomDomainHelper::validate($domain);
        if (! empty($errors)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_invalid_format',
                status: OperationLog::STATUS_WARNING,
                message: $errors[0],
                attributes: [
                    'source' => self::class,
                    'domain' => $domain,
                ]
            );

            return response()->json([
                'success' => false,
                'error' => $errors[0],
            ], 422);
        }

        // Check if domain is a subdomain of the base domain
        $baseDomain = $this->settingService->getBaseDomain();
        if ($baseDomain && CustomDomainHelper::isSubdomainOfBase($domain, $baseDomain)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_base_domain_forbidden',
                status: OperationLog::STATUS_WARNING,
                message: 'Platform base domain cannot be used as custom domain.',
                attributes: [
                    'source' => self::class,
                    'domain' => $domain,
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'You cannot use the platform base domain as a custom domain.',
            ], 422);
        }

        // Check availability
        if (! CustomDomainHelper::isAvailable($domain, $project->id)) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_already_taken',
                status: OperationLog::STATUS_WARNING,
                message: 'This domain is already in use.',
                attributes: [
                    'source' => self::class,
                    'domain' => $domain,
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'This domain is already in use.',
            ], 422);
        }

        // Generate verification token
        $token = $this->verificationService->generateToken();

        // Update project
        $project->update([
            'custom_domain' => $domain,
            'custom_domain_verified' => false,
            'custom_domain_ssl_status' => null,
            'custom_domain_ssl_attempts' => 0,
            'custom_domain_ssl_next_retry_at' => null,
            'custom_domain_ssl_last_error' => null,
            'domain_verification_token' => $token,
            'custom_domain_verified_at' => null,
        ]);
        $this->publishedCache->flushProject((string) $project->id);

        // Get verification instructions
        $instructions = $this->verificationService->getVerificationInstructions($project->fresh());

        $this->operationLogs->logPublish(
            project: $project,
            event: 'custom_domain_added',
            status: OperationLog::STATUS_SUCCESS,
            message: "Custom domain {$domain} added successfully.",
            attributes: [
                'source' => self::class,
                'domain' => $domain,
                'context' => [
                    'verification_method' => $instructions['method'] ?? null,
                    'record_type' => $instructions['record_type'] ?? null,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'domain' => $domain,
            'verification' => $instructions,
        ]);
    }

    /**
     * Verify a project's custom domain.
     */
    public function verify(Project $project): JsonResponse
    {
        // Authorize access
        $this->authorize('update', $project);

        if (! $project->custom_domain) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_verify_missing',
                status: OperationLog::STATUS_WARNING,
                message: 'Custom domain verification requested but no domain is configured.',
                attributes: ['source' => self::class]
            );

            return response()->json([
                'success' => false,
                'error' => 'No custom domain configured for this project.',
            ], 422);
        }

        if ($project->custom_domain_verified) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_verify_already_verified',
                status: OperationLog::STATUS_INFO,
                message: "Custom domain {$project->custom_domain} is already verified.",
                attributes: [
                    'source' => self::class,
                    'domain' => $project->custom_domain,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain is already verified.',
            ]);
        }

        $result = $this->verificationService->verify($project);

        if ($result['success']) {
            $this->publishedCache->flushProject((string) $project->id);
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_verify_success',
                status: OperationLog::STATUS_SUCCESS,
                message: "Custom domain {$project->custom_domain} verified successfully.",
                attributes: [
                    'source' => self::class,
                    'domain' => $project->custom_domain,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully.',
            ]);
        }

        $this->operationLogs->logPublish(
            project: $project,
            event: 'custom_domain_verify_failed',
            status: OperationLog::STATUS_ERROR,
            message: $result['error'] ?? 'Custom domain verification failed.',
            attributes: [
                'source' => self::class,
                'domain' => $project->custom_domain,
            ]
        );

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 422);
    }

    /**
     * Get verification instructions for a project's custom domain.
     */
    public function instructions(Project $project): JsonResponse
    {
        // Authorize access
        $this->authorize('view', $project);

        if (! $project->custom_domain) {
            return response()->json([
                'success' => false,
                'error' => 'No custom domain configured for this project.',
            ], 422);
        }

        $instructions = $this->verificationService->getVerificationInstructions($project);

        return response()->json([
            'success' => true,
            'instructions' => $instructions,
            'verified' => $project->custom_domain_verified,
            'ssl_status' => $project->custom_domain_ssl_status,
            'ssl_attempts' => (int) ($project->custom_domain_ssl_attempts ?? 0),
            'ssl_next_retry_at' => $project->custom_domain_ssl_next_retry_at?->toISOString(),
            'ssl_last_error' => $project->custom_domain_ssl_last_error,
        ]);
    }

    /**
     * Remove a custom domain from a project.
     */
    public function destroy(Project $project): JsonResponse
    {
        // Authorize access
        $this->authorize('update', $project);

        if (! $project->custom_domain) {
            $this->operationLogs->logPublish(
                project: $project,
                event: 'custom_domain_remove_missing',
                status: OperationLog::STATUS_WARNING,
                message: 'Remove custom domain requested but no domain is configured.',
                attributes: ['source' => self::class]
            );

            return response()->json([
                'success' => false,
                'error' => 'No custom domain configured for this project.',
            ], 422);
        }

        $removedDomain = $project->custom_domain;

        $project->update([
            'custom_domain' => null,
            'custom_domain_verified' => false,
            'custom_domain_ssl_status' => null,
            'custom_domain_ssl_attempts' => 0,
            'custom_domain_ssl_next_retry_at' => null,
            'custom_domain_ssl_last_error' => null,
            'domain_verification_token' => null,
            'custom_domain_verified_at' => null,
        ]);
        $this->publishedCache->flushProject((string) $project->id);

        $this->operationLogs->logPublish(
            project: $project,
            event: 'custom_domain_removed',
            status: OperationLog::STATUS_SUCCESS,
            message: "Custom domain {$removedDomain} removed successfully.",
            attributes: [
                'source' => self::class,
                'domain' => $removedDomain,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Custom domain removed successfully.',
        ]);
    }
}
