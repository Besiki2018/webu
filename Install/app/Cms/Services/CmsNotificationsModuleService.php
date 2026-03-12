<?php

namespace App\Cms\Services;

use App\Cms\Exceptions\CmsDomainException;
use App\Models\Site;
use App\Models\SiteNotificationLog;
use App\Models\SiteNotificationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsNotificationsModuleService
{
    /** @var array<string, bool> */
    private const CHANNELS = [
        'email' => true,
        'sms' => true,
    ];

    /** @var array<string, bool> */
    private const TEMPLATE_STATUSES = [
        'draft' => true,
        'active' => true,
        'disabled' => true,
    ];

    /** @var array<string, bool> */
    private const LOG_STATUSES = [
        'preview' => true,
        'queued' => true,
        'sent' => true,
        'failed' => true,
        'skipped' => true,
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listTemplates(Site $site, array $filters = []): array
    {
        $query = SiteNotificationTemplate::query()
            ->where('site_id', $site->id)
            ->withCount('logs')
            ->orderBy('event_key')
            ->orderBy('key');

        $channel = $this->normalizeChannel((string) ($filters['channel'] ?? ''));
        if ($channel !== null) {
            $query->where('channel', $channel);
        }

        $status = $this->normalizeTemplateStatus((string) ($filters['status'] ?? ''));
        if ($status !== null) {
            $query->where('status', $status);
        }

        $eventKey = $this->normalizeEventKey((string) ($filters['event_key'] ?? ''));
        if ($eventKey !== '') {
            $query->where('event_key', $eventKey);
        }

        return [
            'site_id' => $site->id,
            'filters' => [
                'channel' => $channel,
                'status' => $status,
                'event_key' => $eventKey !== '' ? $eventKey : null,
            ],
            'templates' => $query->get()
                ->map(fn (SiteNotificationTemplate $template): array => $this->serializeTemplate($site, $template, true))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showTemplate(Site $site, SiteNotificationTemplate $template): array
    {
        $resolved = $this->ensureTemplateBelongsToSite($site, $template);
        $resolved->loadCount('logs');

        return [
            'site_id' => $site->id,
            'template' => $this->serializeTemplate($site, $resolved, true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTemplate(Site $site, array $payload, ?int $actorId = null): SiteNotificationTemplate
    {
        $normalized = $this->normalizeTemplatePayload($payload, false);

        if (SiteNotificationTemplate::query()->where('site_id', $site->id)->where('key', $normalized['key'])->exists()) {
            throw new CmsDomainException('Notification template key already exists for this site.', 422, [
                'field' => 'key',
                'key' => $normalized['key'],
            ]);
        }

        return DB::transaction(function () use ($site, $normalized, $actorId): SiteNotificationTemplate {
            /** @var SiteNotificationTemplate $template */
            $template = SiteNotificationTemplate::query()->create([
                ...$normalized,
                'site_id' => $site->id,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $template->fresh() ?? $template;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTemplate(Site $site, SiteNotificationTemplate $template, array $payload, ?int $actorId = null): SiteNotificationTemplate
    {
        $resolved = $this->ensureTemplateBelongsToSite($site, $template);
        $normalized = $this->normalizeTemplatePayload($payload, true);

        if (array_key_exists('key', $normalized)) {
            $exists = SiteNotificationTemplate::query()
                ->where('site_id', $site->id)
                ->where('key', $normalized['key'])
                ->where('id', '!=', $resolved->id)
                ->exists();
            if ($exists) {
                throw new CmsDomainException('Notification template key already exists for this site.', 422, [
                    'field' => 'key',
                    'key' => $normalized['key'],
                ]);
            }
        }

        if ($normalized !== []) {
            if ($actorId !== null) {
                $normalized['updated_by'] = $actorId;
            }
            $resolved->fill($normalized);
            $resolved->save();
        }

        return $resolved->fresh() ?? $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteTemplate(Site $site, SiteNotificationTemplate $template): array
    {
        $resolved = $this->ensureTemplateBelongsToSite($site, $template);
        $deleted = [
            'id' => $resolved->id,
            'key' => $resolved->key,
        ];
        $resolved->delete();

        return [
            'site_id' => $site->id,
            'deleted_template_id' => $deleted['id'],
            'deleted_key' => $deleted['key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listLogs(Site $site, array $filters = []): array
    {
        $query = SiteNotificationLog::query()
            ->where('site_id', $site->id)
            ->with('template')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $channel = $this->normalizeChannel((string) ($filters['channel'] ?? ''));
        if ($channel !== null) {
            $query->where('channel', $channel);
        }

        $status = $this->normalizeLogStatus((string) ($filters['status'] ?? ''));
        if ($status !== null) {
            $query->where('status', $status);
        }

        $eventKey = $this->normalizeEventKey((string) ($filters['event_key'] ?? ''));
        if ($eventKey !== '') {
            $query->where('event_key', $eventKey);
        }

        $templateId = isset($filters['template_id']) ? (int) $filters['template_id'] : null;
        if ($templateId && $templateId > 0) {
            $query->where('site_notification_template_id', $templateId);
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $items = $query->limit($limit)->get();

        return [
            'site_id' => $site->id,
            'filters' => [
                'channel' => $channel,
                'status' => $status,
                'event_key' => $eventKey !== '' ? $eventKey : null,
                'template_id' => $templateId ?: null,
                'limit' => $limit,
            ],
            'logs' => $items->map(fn (SiteNotificationLog $log): array => $this->serializeLog($log))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function previewDispatchAndLog(Site $site, array $payload, ?int $actorId = null): array
    {
        $template = $this->resolveTemplateForDispatch($site, $payload);
        if ($template->status === 'disabled') {
            throw new CmsDomainException('Notification template is disabled.', 422, ['field' => 'template']);
        }

        $recipient = $this->normalizeOptionalString($payload['recipient'] ?? null, 255);
        if ($recipient === null) {
            throw new CmsDomainException('Recipient is required.', 422, ['field' => 'recipient']);
        }

        $context = is_array($payload['payload_json'] ?? null) ? $payload['payload_json'] : [];
        $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
        $status = $this->normalizeLogStatus((string) ($payload['status'] ?? 'preview')) ?? 'preview';
        $provider = $this->normalizeOptionalString($payload['provider'] ?? null, 80);
        $providerMessageId = $this->normalizeOptionalString($payload['provider_message_id'] ?? null, 255);

        $rendered = $this->renderTemplate($template, $context);

        $timestamps = [
            'queued_at' => null,
            'sent_at' => null,
            'failed_at' => null,
        ];
        if ($status === 'queued') {
            $timestamps['queued_at'] = now();
        } elseif ($status === 'sent') {
            $timestamps['sent_at'] = now();
        } elseif ($status === 'failed') {
            $timestamps['failed_at'] = now();
        }

        $logMeta = array_merge($meta, [
            'render_missing_variables' => $rendered['missing_variables'],
            'render_used_variables' => $rendered['used_variables'],
            'dispatch_mode' => 'panel_preview_dispatch',
            'actor_id' => $actorId,
        ]);

        /** @var SiteNotificationLog $log */
        $log = SiteNotificationLog::query()->create([
            'site_id' => $site->id,
            'site_notification_template_id' => $template->id,
            'channel' => $template->channel,
            'event_key' => $template->event_key,
            'status' => $status,
            'recipient' => $recipient,
            'subject_snapshot' => $rendered['subject'],
            'body_snapshot' => $rendered['body'],
            'payload_json' => $context,
            'meta_json' => $logMeta,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'queued_at' => $timestamps['queued_at'],
            'sent_at' => $timestamps['sent_at'],
            'failed_at' => $timestamps['failed_at'],
        ]);

        $log = $log->fresh(['template']) ?? $log;

        return [
            'site_id' => $site->id,
            'message' => 'Notification preview dispatch logged successfully.',
            'rendered' => [
                'channel' => $template->channel,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'missing_variables' => $rendered['missing_variables'],
                'used_variables' => $rendered['used_variables'],
            ],
            'log' => $this->serializeLog($log),
        ];
    }

    private function ensureTemplateBelongsToSite(Site $site, SiteNotificationTemplate $template): SiteNotificationTemplate
    {
        $resolved = SiteNotificationTemplate::query()
            ->where('site_id', $site->id)
            ->where('id', $template->id)
            ->withCount('logs')
            ->first();

        if (! $resolved) {
            throw (new ModelNotFoundException)->setModel(SiteNotificationTemplate::class, [(string) $template->id]);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTemplateForDispatch(Site $site, array $payload): SiteNotificationTemplate
    {
        $templateId = isset($payload['template_id']) ? (int) $payload['template_id'] : null;
        if ($templateId && $templateId > 0) {
            $template = SiteNotificationTemplate::query()->where('site_id', $site->id)->where('id', $templateId)->first();
            if ($template) {
                return $template;
            }
        }

        $templateKey = $this->normalizeTemplateKey((string) ($payload['template_key'] ?? ''));
        if ($templateKey !== '') {
            $template = SiteNotificationTemplate::query()->where('site_id', $site->id)->where('key', $templateKey)->first();
            if ($template) {
                return $template;
            }
        }

        throw new CmsDomainException('Notification template not found.', 404, ['field' => 'template']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTemplatePayload(array $payload, bool $partial): array
    {
        $normalized = [];

        if (! $partial || array_key_exists('key', $payload)) {
            $key = $this->normalizeTemplateKey((string) ($payload['key'] ?? ''));
            if ($key === '') {
                throw new CmsDomainException('Invalid notification template key.', 422, ['field' => 'key']);
            }
            $normalized['key'] = $key;
        }

        if (! $partial || array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                throw new CmsDomainException('Notification template name is required.', 422, ['field' => 'name']);
            }
            $normalized['name'] = Str::limit($name, 255, '');
        }

        if (! $partial || array_key_exists('channel', $payload)) {
            $channel = $this->normalizeChannel((string) ($payload['channel'] ?? ''));
            if ($channel === null) {
                throw new CmsDomainException('Invalid notification channel.', 422, ['field' => 'channel']);
            }
            $normalized['channel'] = $channel;
        }

        if (! $partial || array_key_exists('event_key', $payload)) {
            $eventKey = $this->normalizeEventKey((string) ($payload['event_key'] ?? ''));
            if ($eventKey === '') {
                throw new CmsDomainException('Invalid notification event key.', 422, ['field' => 'event_key']);
            }
            $normalized['event_key'] = $eventKey;
        }

        if (! $partial || array_key_exists('locale', $payload)) {
            $normalized['locale'] = $this->normalizeLocale((string) ($payload['locale'] ?? 'en'));
        }

        if (! $partial || array_key_exists('status', $payload)) {
            $status = $this->normalizeTemplateStatus((string) ($payload['status'] ?? 'active'));
            if ($status === null) {
                throw new CmsDomainException('Invalid notification template status.', 422, ['field' => 'status']);
            }
            $normalized['status'] = $status;
        }

        $resolvedChannel = (string) ($normalized['channel'] ?? ($partial ? '' : 'email'));
        if ($resolvedChannel === '' && $partial) {
            $resolvedChannel = '';
        }

        if (! $partial || array_key_exists('subject_template', $payload)) {
            $subject = $this->normalizeOptionalString($payload['subject_template'] ?? null, 500);
            if (($normalized['channel'] ?? null) === 'email' && $subject === null) {
                throw new CmsDomainException('Email templates require a subject template.', 422, ['field' => 'subject_template']);
            }
            $normalized['subject_template'] = $subject;
        }

        if (! $partial || array_key_exists('body_template', $payload)) {
            $body = trim((string) ($payload['body_template'] ?? ''));
            if ($body === '') {
                throw new CmsDomainException('Notification body template is required.', 422, ['field' => 'body_template']);
            }
            $normalized['body_template'] = Str::limit($body, 10000, '');
        }

        if (! $partial || array_key_exists('variables_json', $payload)) {
            $normalized['variables_json'] = $this->normalizeTemplateVariables($payload['variables_json'] ?? []);
        }

        if (! $partial || array_key_exists('meta_json', $payload)) {
            $normalized['meta_json'] = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
        }

        if (isset($normalized['channel']) && $normalized['channel'] === 'email') {
            $subject = $normalized['subject_template'] ?? ($partial ? null : null);
            if ($subject === null && (! $partial || array_key_exists('subject_template', $payload))) {
                throw new CmsDomainException('Email templates require a subject template.', 422, ['field' => 'subject_template']);
            }
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return array<int, array{key:string,description:?string,required:bool}>
     */
    private function normalizeTemplateVariables(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ($value as $index => $entry) {
            $key = null;
            $description = null;
            $required = false;

            if (is_string($entry)) {
                $key = $entry;
            } elseif (is_array($entry)) {
                $key = (string) ($entry['key'] ?? '');
                $description = $this->normalizeOptionalString($entry['description'] ?? null, 255);
                $required = (bool) ($entry['required'] ?? false);
            }

            $normalizedKey = $this->normalizePlaceholderKey((string) $key);
            if ($normalizedKey === '') {
                if ($key !== null && $key !== '') {
                    throw new CmsDomainException('Invalid notification variable key.', 422, [
                        'field' => 'variables_json.'.$index,
                    ]);
                }
                continue;
            }
            if (isset($seen[$normalizedKey])) {
                continue;
            }
            $seen[$normalizedKey] = true;

            $items[] = [
                'key' => $normalizedKey,
                'description' => $description,
                'required' => $required,
            ];
        }

        return $items;
    }

    private function normalizeTemplateKey(string $value): string
    {
        $normalized = Str::of($value)->trim()->lower()->replace(' ', '-')->value();

        return preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $normalized) === 1
            ? $normalized
            : '';
    }

    private function normalizeChannel(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return isset(self::CHANNELS[$normalized]) ? $normalized : null;
    }

    private function normalizeTemplateStatus(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return isset(self::TEMPLATE_STATUSES[$normalized]) ? $normalized : null;
    }

    private function normalizeLogStatus(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return isset(self::LOG_STATUSES[$normalized]) ? $normalized : null;
    }

    private function normalizeEventKey(string $value): string
    {
        $normalized = strtolower(trim($value));

        return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $normalized) === 1
            ? $normalized
            : '';
    }

    private function normalizeLocale(string $value): string
    {
        $candidate = strtolower(trim($value));
        if ($candidate === '' || preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $candidate) !== 1) {
            return 'en';
        }

        return $candidate;
    }

    private function normalizePlaceholderKey(string $value): string
    {
        $candidate = trim($value);

        return preg_match('/^[a-zA-Z0-9]+(?:[._-][a-zA-Z0-9]+)*$/', $candidate) === 1
            ? $candidate
            : '';
    }

    private function normalizeOptionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return Str::limit($string, $maxLength, '');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{subject:?string,body:string,missing_variables:array<int,string>,used_variables:array<int,string>}
     */
    private function renderTemplate(SiteNotificationTemplate $template, array $context): array
    {
        $missing = [];
        $used = [];

        $subject = $template->subject_template;
        if ($subject !== null) {
            $subject = $this->renderStringTemplate($subject, $context, $used, $missing);
        }

        $body = $this->renderStringTemplate((string) $template->body_template, $context, $used, $missing);

        $missing = array_values(array_unique($missing));
        $used = array_values(array_unique($used));

        return [
            'subject' => $subject,
            'body' => $body,
            'missing_variables' => $missing,
            'used_variables' => $used,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $used
     * @param  array<int, string>  $missing
     */
    private function renderStringTemplate(string $template, array $context, array &$used, array &$missing): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9._-]+)\s*\}\}/', function (array $matches) use ($context, &$used, &$missing): string {
            $key = (string) ($matches[1] ?? '');
            $used[] = $key;
            $value = Arr::get($context, $key);
            if ($value === null) {
                $missing[] = $key;

                return '';
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return (string) $value;
        }, $template);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(Site $site, SiteNotificationTemplate $template, bool $includeBuilderContract = false): array
    {
        $payload = [
            'id' => $template->id,
            'site_id' => $template->site_id,
            'key' => $template->key,
            'name' => $template->name,
            'channel' => $template->channel,
            'event_key' => $template->event_key,
            'locale' => $template->locale,
            'status' => $template->status,
            'subject_template' => $template->subject_template,
            'body_template' => $template->body_template,
            'variables_json' => is_array($template->variables_json) ? $template->variables_json : [],
            'meta_json' => is_array($template->meta_json) ? $template->meta_json : [],
            'log_count' => isset($template->logs_count) ? (int) $template->logs_count : null,
            'updated_at' => $template->updated_at?->toISOString(),
            'created_at' => $template->created_at?->toISOString(),
        ];

        if ($includeBuilderContract) {
            $payload['builder_contract'] = [
                'component_type' => 'notification_template',
                'channel' => $template->channel,
                'preview_dispatch_endpoint' => route('panel.sites.notification-logs.preview-dispatch', ['site' => $site->id]),
                'payload_shape' => [
                    'recipient' => 'string',
                    'payload_json' => 'object',
                    'meta_json' => 'object',
                ],
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLog(SiteNotificationLog $log): array
    {
        return [
            'id' => $log->id,
            'site_id' => $log->site_id,
            'template_id' => $log->site_notification_template_id,
            'template_key' => $log->template?->key,
            'template_name' => $log->template?->name,
            'channel' => $log->channel,
            'event_key' => $log->event_key,
            'status' => $log->status,
            'recipient' => $log->recipient,
            'subject_snapshot' => $log->subject_snapshot,
            'body_snapshot' => $log->body_snapshot,
            'payload_json' => is_array($log->payload_json) ? $log->payload_json : [],
            'meta_json' => is_array($log->meta_json) ? $log->meta_json : [],
            'provider' => $log->provider,
            'provider_message_id' => $log->provider_message_id,
            'queued_at' => $log->queued_at?->toISOString(),
            'sent_at' => $log->sent_at?->toISOString(),
            'failed_at' => $log->failed_at?->toISOString(),
            'created_at' => $log->created_at?->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];
    }
}
