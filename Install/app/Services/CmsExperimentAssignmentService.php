<?php

namespace App\Services;

use App\Models\CmsExperiment;
use App\Models\CmsExperimentAssignment;
use App\Models\CmsExperimentVariant;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CmsExperimentAssignmentService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function assignActiveExperimentsForRequest(Site $site, Request $request, array $context = []): array
    {
        $experiments = CmsExperiment::query()
            ->where('site_id', (string) $site->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $assignments = [];
        $skipped = [];

        foreach ($experiments as $experiment) {
            $result = $this->assignForExperiment($site, $experiment, $request, $context);
            if (($result['assigned'] ?? false) === true) {
                $assignments[] = $result;
                continue;
            }

            $skipped[] = $result;
        }

        return [
            'ok' => true,
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'evaluated' => $experiments->count(),
            'assigned' => count($assignments),
            'assignments' => $assignments,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function assignForExperiment(Site $site, CmsExperiment $experiment, Request $request, array $context = []): array
    {
        if ((string) $experiment->site_id !== (string) $site->id) {
            return $this->skippedResult($site, $experiment, 'site_mismatch', 'Experiment does not belong to the requested site.');
        }

        if ($this->safeString($experiment->status, 20) !== 'active') {
            return $this->skippedResult($site, $experiment, 'experiment_not_active', 'Experiment is not active.');
        }

        $now = $this->resolveClock($context['now'] ?? null);
        if ($experiment->starts_at instanceof Carbon && $experiment->starts_at->greaterThan($now)) {
            return $this->skippedResult($site, $experiment, 'experiment_not_started', 'Experiment start time is in the future.');
        }
        if ($experiment->ends_at instanceof Carbon && $experiment->ends_at->lessThanOrEqualTo($now)) {
            return $this->skippedResult($site, $experiment, 'experiment_ended', 'Experiment end time has passed.');
        }

        $activeVariants = $experiment->variants()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($activeVariants->isEmpty()) {
            return $this->skippedResult($site, $experiment, 'no_active_variants', 'Experiment has no active variants.');
        }

        $subject = $this->resolveAssignmentSubject(
            $request,
            $context,
            $this->safeString($experiment->assignment_unit, 32) ?: 'session_or_device'
        );
        if ($subject === null) {
            return $this->skippedResult($site, $experiment, 'assignment_subject_missing', 'Could not derive a stable session/device subject.');
        }

        $existing = CmsExperimentAssignment::query()
            ->where('experiment_id', $experiment->id)
            ->where('subject_hash', $subject['subject_hash'])
            ->first();

        $activeVariantKeys = $activeVariants->pluck('variant_key')->map(fn ($value) => (string) $value)->all();
        if ($existing instanceof CmsExperimentAssignment && in_array((string) $existing->variant_key, $activeVariantKeys, true)) {
            return $this->successResult($site, $experiment, $subject, (string) $existing->variant_key, true, [
                'assignment_id' => $existing->id,
                'assigned_at' => optional($existing->assigned_at)->toISOString(),
                'traffic_percent' => $this->normalizeTrafficPercent($experiment->traffic_percent),
            ]);
        }

        $trafficPercent = $this->normalizeTrafficPercent($experiment->traffic_percent);
        $trafficHash = $this->deterministicHashBucket(
            'traffic|'.$subject['subject_hash'].'|'.$experiment->id.'|'.$this->safeString($experiment->key, 120),
            10000
        );
        if ($trafficHash >= ($trafficPercent * 100)) {
            return $this->skippedResult($site, $experiment, 'outside_traffic_allocation', 'Subject is outside experiment traffic allocation.', [
                'basis' => $subject['basis'],
                'traffic_percent' => $trafficPercent,
                'traffic_bucket' => $trafficHash,
            ]);
        }

        [$selectedVariantKey, $selectionMeta] = $this->selectVariantDeterministically($experiment, $activeVariants->all(), $subject['subject_hash']);

        $assignment = CmsExperimentAssignment::query()->updateOrCreate(
            [
                'experiment_id' => $experiment->id,
                'subject_hash' => $subject['subject_hash'],
            ],
            [
                'site_id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'variant_key' => $selectedVariantKey,
                'assignment_basis' => $subject['basis'],
                'session_id_hash' => $subject['session_id_hash'],
                'device_id_hash' => $subject['device_id_hash'],
                'context_json' => $this->sanitizeContextForStorage($context, $request),
                'assigned_at' => $now,
            ]
        );

        $selectionMeta['assignment_id'] = $assignment->id;
        $selectionMeta['assigned_at'] = optional($assignment->assigned_at)->toISOString();
        $selectionMeta['traffic_percent'] = $trafficPercent;
        $selectionMeta['traffic_bucket'] = $trafficHash;

        return $this->successResult($site, $experiment, $subject, $selectedVariantKey, false, $selectionMeta);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{basis:string,subject_hash:string,session_id_hash:?string,device_id_hash:?string}|null
     */
    private function resolveAssignmentSubject(Request $request, array $context, string $assignmentUnit = 'session_or_device'): ?array
    {
        $normalizedUnit = in_array($assignmentUnit, ['session', 'device', 'session_or_device'], true)
            ? $assignmentUnit
            : 'session_or_device';

        $sessionToken = $normalizedUnit !== 'device'
            ? $this->resolveSessionToken($request, $context)
            : null;
        if ($sessionToken !== null) {
            $sessionHash = $this->hashSubject($sessionToken, 'session');

            return [
                'basis' => 'session',
                'subject_hash' => $sessionHash,
                'session_id_hash' => $sessionHash,
                'device_id_hash' => null,
            ];
        }

        if ($normalizedUnit === 'session') {
            return null;
        }

        $deviceToken = $this->resolveDeviceToken($request, $context);
        if ($deviceToken === null) {
            return null;
        }

        $deviceHash = $this->hashSubject($deviceToken, 'device');

        return [
            'basis' => 'device',
            'subject_hash' => $deviceHash,
            'session_id_hash' => null,
            'device_id_hash' => $deviceHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveSessionToken(Request $request, array $context): ?string
    {
        $candidates = [
            $context['session_id'] ?? null,
            $request->headers->get('X-Cms-Session-Id'),
            $request->headers->get('X-Session-Id'),
            $request->cookie('cms_session_id'),
        ];

        try {
            if (method_exists($request, 'hasSession') && $request->hasSession()) {
                $candidates[] = $request->session()->getId();
            }
        } catch (\Throwable) {
            // Best-effort fallback only.
        }

        foreach ($candidates as $candidate) {
            $safe = $this->safeString($candidate, 255);
            if ($safe !== '') {
                return $safe;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveDeviceToken(Request $request, array $context): ?string
    {
        $explicitDeviceId = $this->safeString($context['device_id'] ?? $request->headers->get('X-Device-Id'), 255);
        if ($explicitDeviceId !== '') {
            return 'device_id:'.$explicitDeviceId;
        }

        $userAgent = $this->safeString($request->userAgent(), 255);
        $ip = $this->safeString($request->ip(), 120);
        $language = $this->safeString($request->headers->get('Accept-Language'), 120);

        if ($userAgent === '' && $ip === '' && $language === '') {
            return null;
        }

        return 'ua:'.$userAgent.'|ip:'.$ip.'|lang:'.$language;
    }

    private function hashSubject(string $token, string $namespace): string
    {
        $key = (string) config('app.key', 'webu-cms-experimentation');

        return hash_hmac('sha256', $namespace.'|'.$token, $key);
    }

    /**
     * @param  list<CmsExperimentVariant>  $variants
     * @return array{0:string,1:array<string,mixed>}
     */
    private function selectVariantDeterministically(CmsExperiment $experiment, array $variants, string $subjectHash): array
    {
        $weightedVariants = [];
        $totalWeight = 0;

        foreach ($variants as $variant) {
            $weight = max(1, (int) $variant->weight);
            $weightedVariants[] = [
                'variant_key' => (string) $variant->variant_key,
                'weight' => $weight,
            ];
            $totalWeight += $weight;
        }

        $bucket = $this->deterministicHashBucket(
            'variant|'.$subjectHash.'|'.$experiment->id.'|'.$this->safeString($experiment->key, 120),
            max(1, $totalWeight)
        );

        $cursor = 0;
        $selectedKey = $weightedVariants[0]['variant_key'] ?? 'control';
        foreach ($weightedVariants as $candidate) {
            $cursor += (int) $candidate['weight'];
            if ($bucket < $cursor) {
                $selectedKey = (string) $candidate['variant_key'];
                break;
            }
        }

        return [
            $selectedKey,
            [
                'strategy' => 'deterministic_weighted_hash_v1',
                'bucket' => $bucket,
                'total_weight' => $totalWeight,
                'weights' => $weightedVariants,
            ],
        ];
    }

    private function deterministicHashBucket(string $seed, int $modulus): int
    {
        $normalizedModulus = max(1, $modulus);
        $value = hexdec(substr(hash('sha256', $seed), 0, 8));

        return $value % $normalizedModulus;
    }

    private function normalizeTrafficPercent(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(0, min(100, (int) $value));
        }

        return 100;
    }

    private function resolveClock(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function sanitizeContextForStorage(array $context, Request $request): ?array
    {
        $sanitized = [];

        $route = $context['route'] ?? null;
        if (is_array($route)) {
            $sanitized['route'] = [
                'path' => $this->safeString($route['path'] ?? null, 255) ?: null,
                'slug' => $this->safeString($route['slug'] ?? null, 120) ?: null,
            ];
        } else {
            $sanitized['route'] = [
                'path' => $this->safeString('/'.$request->path(), 255) ?: '/',
                'slug' => null,
            ];
        }

        $sanitized['source'] = $this->safeString($context['source'] ?? null, 40) ?: null;

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function successResult(Site $site, CmsExperiment $experiment, array $subject, string $variantKey, bool $reused, array $meta = []): array
    {
        return [
            'ok' => true,
            'assigned' => true,
            'reused' => $reused,
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'experiment_id' => $experiment->id,
            'experiment_key' => (string) $experiment->key,
            'variant_key' => $variantKey,
            'basis' => $subject['basis'],
            'subject_hash' => $subject['subject_hash'],
            'session_id_hash' => $subject['session_id_hash'],
            'device_id_hash' => $subject['device_id_hash'],
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function skippedResult(Site $site, CmsExperiment $experiment, string $code, string $message, array $meta = []): array
    {
        return [
            'ok' => true,
            'assigned' => false,
            'reused' => false,
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'experiment_id' => $experiment->id,
            'experiment_key' => (string) $experiment->key,
            'reason' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => $meta,
        ];
    }

    private function safeString(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, max(1, $max));
    }
}
