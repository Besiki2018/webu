<?php

namespace App\Cms\Services;

use App\Cms\Exceptions\CmsDomainException;
use App\Models\Site;
use App\Models\SiteForm;
use App\Models\SiteFormLead;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsFormsLeadsService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listForms(Site $site, array $filters = []): array
    {
        $query = SiteForm::query()
            ->where('site_id', $site->id)
            ->withCount('leads')
            ->orderBy('key');

        $status = $this->normalizeFormStatus((string) ($filters['status'] ?? ''));
        if ($status !== null) {
            $query->where('status', $status);
        }

        return [
            'site_id' => $site->id,
            'filters' => [
                'status' => $status,
            ],
            'forms' => $query->get()
                ->map(fn (SiteForm $form): array => $this->serializeForm($site, $form, includeSchema: true, includeBuilderContract: true))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showForm(Site $site, SiteForm $form): array
    {
        $resolved = $this->ensureFormBelongsToSite($site, $form);
        $resolved->loadCount('leads');

        return [
            'site_id' => $site->id,
            'form' => $this->serializeForm($site, $resolved, includeSchema: true, includeBuilderContract: true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createForm(Site $site, array $payload): SiteForm
    {
        $key = $this->normalizeFormKey((string) ($payload['key'] ?? ''));
        if ($key === '') {
            throw new CmsDomainException('Invalid form key.', 422, [
                'field' => 'key',
            ]);
        }

        if (SiteForm::query()->where('site_id', $site->id)->where('key', $key)->exists()) {
            throw new CmsDomainException('Form key already exists for this site.', 422, [
                'field' => 'key',
                'key' => $key,
            ]);
        }

        $schema = $this->normalizeSchema($payload['schema_json'] ?? []);
        $settings = $this->normalizeSettings($payload['settings_json'] ?? []);
        $status = $this->normalizeFormStatus((string) ($payload['status'] ?? 'draft')) ?? 'draft';

        return DB::transaction(function () use ($site, $key, $payload, $status, $schema, $settings): SiteForm {
            /** @var SiteForm $form */
            $form = SiteForm::query()->create([
                'site_id' => $site->id,
                'key' => $key,
                'name' => trim((string) ($payload['name'] ?? '')),
                'status' => $status,
                'schema_json' => $schema,
                'settings_json' => $settings,
            ]);

            return $form->fresh() ?? $form;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateForm(Site $site, SiteForm $form, array $payload): SiteForm
    {
        $resolved = $this->ensureFormBelongsToSite($site, $form);

        $updates = [];
        if (array_key_exists('name', $payload)) {
            $updates['name'] = trim((string) $payload['name']);
        }
        if (array_key_exists('status', $payload)) {
            $status = $this->normalizeFormStatus((string) $payload['status']);
            if ($status === null) {
                throw new CmsDomainException('Invalid form status.', 422, ['field' => 'status']);
            }
            $updates['status'] = $status;
        }
        if (array_key_exists('schema_json', $payload)) {
            $updates['schema_json'] = $this->normalizeSchema($payload['schema_json']);
        }
        if (array_key_exists('settings_json', $payload)) {
            $updates['settings_json'] = $this->normalizeSettings($payload['settings_json']);
        }
        if (array_key_exists('key', $payload)) {
            $nextKey = $this->normalizeFormKey((string) $payload['key']);
            if ($nextKey === '') {
                throw new CmsDomainException('Invalid form key.', 422, ['field' => 'key']);
            }
            $exists = SiteForm::query()
                ->where('site_id', $site->id)
                ->where('key', $nextKey)
                ->where('id', '!=', $resolved->id)
                ->exists();
            if ($exists) {
                throw new CmsDomainException('Form key already exists for this site.', 422, ['field' => 'key', 'key' => $nextKey]);
            }
            $updates['key'] = $nextKey;
        }

        if ($updates !== []) {
            $resolved->fill($updates);
            $resolved->save();
        }

        return $resolved->fresh() ?? $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteForm(Site $site, SiteForm $form): array
    {
        $resolved = $this->ensureFormBelongsToSite($site, $form);
        $deletedId = $resolved->id;
        $deletedKey = $resolved->key;

        $resolved->delete();

        return [
            'site_id' => $site->id,
            'deleted_form_id' => $deletedId,
            'deleted_key' => $deletedKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listLeads(Site $site, array $filters = []): array
    {
        $query = SiteFormLead::query()
            ->where('site_id', $site->id)
            ->with('form')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $status = $this->normalizeLeadStatus((string) ($filters['status'] ?? ''));
        if ($status !== null) {
            $query->where('status', $status);
        }

        $formKey = $this->normalizeFormKey((string) ($filters['form_key'] ?? ''));
        if ($formKey !== '') {
            $query->whereHas('form', fn ($q) => $q->where('key', $formKey));
        }

        $formId = isset($filters['form_id']) ? (int) $filters['form_id'] : null;
        if ($formId && $formId > 0) {
            $query->where('site_form_id', $formId);
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));

        $items = $query->limit($limit)->get();

        return [
            'site_id' => $site->id,
            'filters' => [
                'status' => $status,
                'form_key' => $formKey !== '' ? $formKey : null,
                'form_id' => $formId ?: null,
                'limit' => $limit,
            ],
            'leads' => $items->map(fn (SiteFormLead $lead): array => $this->serializeLead($lead))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showPublicForm(Site $site, string $key): array
    {
        $form = $this->findPublicForm($site, $key);

        return [
            'site_id' => $site->id,
            'form' => $this->serializeForm($site, $form, includeSchema: true, includeBuilderContract: true, publicMode: true),
            'submission' => [
                'method' => 'POST',
                'endpoint' => route('public.sites.forms.submit', ['site' => $site->id, 'key' => $form->key]),
                'payload' => [
                    'fields' => 'object',
                    'context' => 'object',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitPublicLead(Site $site, string $key, array $payload, Request $request): array
    {
        $form = $this->findPublicForm($site, $key);
        $submission = $this->normalizeSubmissionPayload($form, $payload);
        $contactSnapshot = $this->extractLeadContactSnapshot($submission['fields']);

        $lead = DB::transaction(function () use ($site, $form, $submission, $contactSnapshot, $request): SiteFormLead {
            /** @var SiteFormLead $lead */
            $lead = SiteFormLead::query()->create([
                'site_id' => $site->id,
                'site_form_id' => $form->id,
                'status' => 'new',
                'contact_name' => $contactSnapshot['name'],
                'contact_email' => $contactSnapshot['email'],
                'contact_phone' => $contactSnapshot['phone'],
                // Keep legacy payload snapshot populated while newer API contracts use fields_json/source_json.
                'payload_json' => [
                    'fields' => $submission['fields'],
                    'source' => $submission['source'],
                ],
                'fields_json' => $submission['fields'],
                'source_json' => $submission['source'],
                'meta_json' => [
                    'submitted_via' => 'public_api',
                    'dropped_fields' => $submission['dropped_fields'],
                    'request_locale' => $request->getLocale(),
                ],
                'ip_hash' => $this->hashIp($request->ip()),
                'user_agent' => Str::limit((string) ($request->userAgent() ?? ''), 1024, ''),
                'submitted_at' => now(),
            ]);

            return $lead->fresh(['form']) ?? $lead;
        });

        $successMessage = trim((string) data_get($form->settings_json ?? [], 'success_message', ''));
        if ($successMessage === '') {
            $successMessage = trim((string) data_get($form->schema_json ?? [], 'success_message', ''));
        }
        if ($successMessage === '') {
            $successMessage = 'Form submitted successfully.';
        }

        return [
            'site_id' => $site->id,
            'message' => $successMessage,
            'lead' => [
                'id' => $lead->id,
                'form_id' => $form->id,
                'form_key' => $form->key,
                'status' => $lead->status,
                'submitted_at' => $lead->submitted_at?->toISOString(),
            ],
            'meta' => [
                'accepted_fields' => array_keys($submission['fields']),
                'dropped_fields' => $submission['dropped_fields'],
            ],
        ];
    }

    public function updateLeadStatus(Site $site, SiteFormLead $lead, string $status): SiteFormLead
    {
        $resolved = $this->ensureLeadBelongsToSite($site, $lead);
        $nextStatus = $this->normalizeLeadStatus($status);
        if ($nextStatus === null) {
            throw new CmsDomainException('Invalid lead status.', 422, [
                'field' => 'status',
            ]);
        }

        $resolved->status = $nextStatus;
        $resolved->save();

        return $resolved->fresh(['form']) ?? $resolved;
    }

    private function ensureFormBelongsToSite(Site $site, SiteForm $form): SiteForm
    {
        $resolved = SiteForm::query()->where('site_id', $site->id)->where('id', $form->id)->withCount('leads')->first();
        if (! $resolved) {
            throw (new ModelNotFoundException)->setModel(SiteForm::class, [(string) $form->id]);
        }

        return $resolved;
    }

    private function ensureLeadBelongsToSite(Site $site, SiteFormLead $lead): SiteFormLead
    {
        $resolved = SiteFormLead::query()
            ->where('site_id', $site->id)
            ->where('id', $lead->id)
            ->with('form')
            ->first();

        if (! $resolved) {
            throw (new ModelNotFoundException)->setModel(SiteFormLead::class, [(string) $lead->id]);
        }

        return $resolved;
    }

    private function findPublicForm(Site $site, string $key): SiteForm
    {
        $normalizedKey = $this->normalizeFormKey($key);
        $form = SiteForm::query()
            ->where('site_id', $site->id)
            ->where('key', $normalizedKey)
            ->where('status', 'active')
            ->first();

        if (! $form) {
            throw new CmsDomainException('Form not found.', 404, [
                'key' => $normalizedKey,
            ]);
        }

        return $form;
    }

    /**
     * @param  mixed  $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(mixed $schema): array
    {
        if (! is_array($schema)) {
            throw new CmsDomainException('Form schema must be an object.', 422, [
                'field' => 'schema_json',
            ]);
        }

        $rawFields = $schema['fields'] ?? null;
        if (! is_array($rawFields) || $rawFields === []) {
            throw new CmsDomainException('Form schema must include at least one field.', 422, [
                'field' => 'schema_json.fields',
            ]);
        }

        $normalizedFields = [];
        $seen = [];
        foreach ($rawFields as $index => $field) {
            if (! is_array($field)) {
                throw new CmsDomainException('Form field definition must be an object.', 422, [
                    'field' => 'schema_json.fields.'.$index,
                ]);
            }

            $name = trim(Str::snake((string) ($field['name'] ?? '')));
            if ($name === '' || preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new CmsDomainException('Invalid form field name.', 422, [
                    'field' => 'schema_json.fields.'.$index.'.name',
                ]);
            }
            if (isset($seen[$name])) {
                throw new CmsDomainException('Duplicate form field name.', 422, [
                    'field' => 'schema_json.fields.'.$index.'.name',
                    'name' => $name,
                ]);
            }
            $seen[$name] = true;

            $type = $this->normalizeFieldType((string) ($field['type'] ?? 'text'));
            if ($type === null) {
                throw new CmsDomainException('Invalid form field type.', 422, [
                    'field' => 'schema_json.fields.'.$index.'.type',
                ]);
            }

            $label = trim((string) ($field['label'] ?? Str::headline($name)));
            if ($label === '') {
                $label = Str::headline($name);
            }

            $required = (bool) ($field['required'] ?? false);
            $enabled = ! array_key_exists('enabled', $field) || (bool) $field['enabled'];
            $placeholder = trim((string) ($field['placeholder'] ?? ''));
            $maxLength = isset($field['max_length']) ? max(1, min(10000, (int) $field['max_length'])) : null;

            $options = [];
            if ($type === 'select' || $type === 'radio') {
                $rawOptions = $field['options'] ?? [];
                if (! is_array($rawOptions) || $rawOptions === []) {
                    throw new CmsDomainException(ucfirst($type).' field must define options.', 422, [
                        'field' => 'schema_json.fields.'.$index.'.options',
                    ]);
                }

                $seenOptionValues = [];
                foreach ($rawOptions as $optionIndex => $rawOption) {
                    $value = null;
                    $optionLabel = null;

                    if (is_array($rawOption)) {
                        $value = trim((string) ($rawOption['value'] ?? ''));
                        $optionLabel = trim((string) ($rawOption['label'] ?? ''));
                    } elseif (is_string($rawOption) || is_numeric($rawOption)) {
                        $value = trim((string) $rawOption);
                        $optionLabel = $value;
                    }

                    if ($value === null || $value === '') {
                        throw new CmsDomainException('Invalid '.$type.' option value.', 422, [
                            'field' => 'schema_json.fields.'.$index.'.options.'.$optionIndex,
                        ]);
                    }

                    if (isset($seenOptionValues[$value])) {
                        continue;
                    }
                    $seenOptionValues[$value] = true;

                    $options[] = [
                        'value' => $value,
                        'label' => $optionLabel !== '' ? $optionLabel : $value,
                    ];
                }
            }

            $normalizedFields[] = array_filter([
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'enabled' => $enabled,
                'placeholder' => $placeholder !== '' ? $placeholder : null,
                'max_length' => $maxLength,
                'options' => $options !== [] ? $options : null,
            ], static fn ($value): bool => $value !== null);
        }

        return array_filter([
            'fields' => $normalizedFields,
            'submit_label' => $this->normalizeOptionalString($schema['submit_label'] ?? null, 120),
            'success_message' => $this->normalizeOptionalString($schema['success_message'] ?? null, 500),
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  mixed  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(mixed $settings): array
    {
        $input = is_array($settings) ? $settings : [];

        $successMessage = $this->normalizeOptionalString($input['success_message'] ?? null, 500);
        $notifyEmail = $this->normalizeOptionalString($input['notify_email'] ?? null, 255);
        $storeContext = array_key_exists('store_context', $input) ? (bool) $input['store_context'] : true;

        return array_filter([
            'success_message' => $successMessage,
            'notify_email' => $notifyEmail,
            'store_context' => $storeContext,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{fields: array<string, mixed>, source: array<string, mixed>, dropped_fields: array<int, string>}
     */
    private function normalizeSubmissionPayload(SiteForm $form, array $payload): array
    {
        $rawFields = is_array($payload['fields'] ?? null) ? $payload['fields'] : null;
        if ($rawFields === null) {
            throw new CmsDomainException('Submission payload must include fields object.', 422, [
                'field' => 'fields',
            ]);
        }

        $fieldDefinitions = array_values(array_filter(
            is_array($form->schema_json['fields'] ?? null) ? $form->schema_json['fields'] : [],
            static fn ($field): bool => is_array($field) && (bool) ($field['enabled'] ?? true)
        ));

        $normalized = [];
        $allowedFieldNames = [];
        foreach ($fieldDefinitions as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $allowedFieldNames[] = $name;
            $value = $rawFields[$name] ?? null;
            $normalized[$name] = $this->normalizeSubmittedFieldValue($field, $value);
        }

        $missingRequired = [];
        foreach ($fieldDefinitions as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if ((bool) ($field['required'] ?? false) && $this->isEmptySubmittedValue($normalized[$name] ?? null, (string) ($field['type'] ?? 'text'))) {
                $missingRequired[] = $name;
            }
        }
        if ($missingRequired !== []) {
            throw new CmsDomainException('Missing required form fields.', 422, [
                'field' => 'fields',
                'missing' => $missingRequired,
            ]);
        }

        $droppedFields = [];
        foreach (array_keys($rawFields) as $rawKey) {
            if (! is_string($rawKey)) {
                continue;
            }
            if (! in_array($rawKey, $allowedFieldNames, true)) {
                $droppedFields[] = $rawKey;
            }
        }

        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $source = array_filter([
            'page_slug' => $this->normalizeOptionalString($context['page_slug'] ?? null, 120),
            'page_url' => $this->normalizeOptionalString($context['page_url'] ?? null, 2000),
            'referrer' => $this->normalizeOptionalString($context['referrer'] ?? null, 2000),
            'component_id' => $this->normalizeOptionalString($context['component_id'] ?? null, 120),
        ], static fn ($value): bool => $value !== null);

        return [
            'fields' => $normalized,
            'source' => $source,
            'dropped_fields' => array_values(array_unique($droppedFields)),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedFieldValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($value === null) {
            return $type === 'checkbox' ? false : null;
        }

        return match ($type) {
            'checkbox' => (bool) $value,
            'number' => $this->normalizeSubmittedNumber($field, $value),
            'email' => $this->normalizeSubmittedEmail($field, $value),
            'url' => $this->normalizeSubmittedUrl($field, $value),
            'select', 'radio' => $this->normalizeSubmittedSelect($field, $value),
            default => $this->normalizeSubmittedString($field, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedNumber(array $field, mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (! is_numeric($value)) {
            throw new CmsDomainException('Invalid number field value.', 422, [
                'field' => 'fields.'.$field['name'],
            ]);
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedEmail(array $field, mixed $value): ?string
    {
        $normalized = $this->normalizeSubmittedString($field, $value);
        if ($normalized === null || $normalized === '') {
            return $normalized;
        }
        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new CmsDomainException('Invalid email field value.', 422, [
                'field' => 'fields.'.$field['name'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedUrl(array $field, mixed $value): ?string
    {
        $normalized = $this->normalizeSubmittedString($field, $value);
        if ($normalized === null || $normalized === '') {
            return $normalized;
        }
        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new CmsDomainException('Invalid URL field value.', 422, [
                'field' => 'fields.'.$field['name'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedSelect(array $field, mixed $value): ?string
    {
        $normalized = $this->normalizeSubmittedString($field, $value);
        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        $allowed = collect(is_array($field['options'] ?? null) ? $field['options'] : [])
            ->map(fn ($option) => is_array($option) ? (string) ($option['value'] ?? '') : '')
            ->filter()
            ->values()
            ->all();

        if ($allowed !== [] && ! in_array($normalized, $allowed, true)) {
            throw new CmsDomainException('Invalid select field option.', 422, [
                'field' => 'fields.'.$field['name'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalizeSubmittedString(array $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            throw new CmsDomainException('Invalid string field value.', 422, [
                'field' => 'fields.'.$field['name'],
            ]);
        }

        $string = trim((string) $value);
        $maxLength = isset($field['max_length']) ? (int) $field['max_length'] : 5000;
        if ($maxLength <= 0) {
            $maxLength = 5000;
        }
        if (mb_strlen($string) > $maxLength) {
            throw new CmsDomainException('Field value exceeds maximum length.', 422, [
                'field' => 'fields.'.$field['name'],
                'max_length' => $maxLength,
            ]);
        }

        return $string;
    }

    private function isEmptySubmittedValue(mixed $value, string $type): bool
    {
        if ($type === 'checkbox') {
            return $value === false || $value === null;
        }

        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForm(
        Site $site,
        SiteForm $form,
        bool $includeSchema = true,
        bool $includeBuilderContract = false,
        bool $publicMode = false
    ): array {
        $schema = is_array($form->schema_json) ? $form->schema_json : [];
        $settings = is_array($form->settings_json) ? $form->settings_json : [];
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

        $payload = [
            'id' => $form->id,
            'site_id' => $form->site_id,
            'key' => $form->key,
            'name' => $form->name,
            'status' => $form->status,
            'schema_json' => $includeSchema ? $schema : null,
            'settings_json' => $publicMode ? null : $settings,
            'field_count' => count($fields),
            'lead_count' => isset($form->leads_count) ? (int) $form->leads_count : null,
            'updated_at' => $form->updated_at?->toISOString(),
            'created_at' => $form->created_at?->toISOString(),
        ];

        if ($includeBuilderContract) {
            $payload['builder_contract'] = [
                'component_type' => 'form',
                'submit_endpoint' => route('public.sites.forms.submit', ['site' => $site->id, 'key' => $form->key]),
                'definition_endpoint' => route('public.sites.forms.show', ['site' => $site->id, 'key' => $form->key]),
                'payload_shape' => [
                    'fields' => 'object',
                    'context' => 'object',
                ],
            ];
        }

        if ($publicMode) {
            unset($payload['settings_json']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLead(SiteFormLead $lead): array
    {
        $payload = is_array($lead->payload_json ?? null) ? $lead->payload_json : [];
        $fields = is_array($lead->fields_json ?? null)
            ? $lead->fields_json
            : (is_array($payload['fields'] ?? null) ? $payload['fields'] : []);
        $source = is_array($lead->source_json ?? null)
            ? $lead->source_json
            : (is_array($payload['source'] ?? null) ? $payload['source'] : []);

        return [
            'id' => $lead->id,
            'site_id' => $lead->site_id,
            'form_id' => $lead->site_form_id,
            'form_key' => $lead->form?->key,
            'form_name' => $lead->form?->name,
            'status' => $lead->status,
            'fields_json' => $fields,
            'source_json' => $source,
            'meta_json' => $lead->meta_json ?? [],
            'submitted_at' => $lead->submitted_at?->toISOString(),
            'created_at' => $lead->created_at?->toISOString(),
            'updated_at' => $lead->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{name:?string,email:?string,phone:?string}
     */
    private function extractLeadContactSnapshot(array $fields): array
    {
        return [
            'name' => $this->pickLeadFieldString($fields, ['full_name', 'fullname', 'name', 'contact_name'], 255),
            'email' => $this->pickLeadFieldString($fields, ['email', 'contact_email'], 255),
            'phone' => $this->pickLeadFieldString($fields, ['phone', 'tel', 'telephone', 'contact_phone'], 255),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $keys
     */
    private function pickLeadFieldString(array $fields, array $keys, int $maxLength): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $value = $this->normalizeOptionalString($fields[$key], $maxLength);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeFormKey(string $value): string
    {
        $normalized = Str::of($value)->trim()->lower()->replace(' ', '-')->value();

        return preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $normalized) === 1
            ? $normalized
            : '';
    }

    private function normalizeFormStatus(string $status): ?string
    {
        $value = strtolower(trim($status));

        return in_array($value, ['draft', 'active', 'disabled'], true) ? $value : null;
    }

    private function normalizeLeadStatus(string $status): ?string
    {
        $value = strtolower(trim($status));

        return in_array($value, ['new', 'reviewed', 'archived', 'spam'], true) ? $value : null;
    }

    private function normalizeFieldType(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'number', 'url', 'hidden'], true)
            ? $normalized
            : null;
    }

    private function normalizeOptionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return null;
        }
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, $maxLength, '');
    }

    private function hashIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }

        $key = (string) config('app.key', 'webu');
        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);
            if (is_string($decoded) && $decoded !== '') {
                $key = $decoded;
            }
        }

        return hash_hmac('sha256', $ip, $key);
    }
}
