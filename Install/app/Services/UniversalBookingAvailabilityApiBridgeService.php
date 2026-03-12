<?php

namespace App\Services;

use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Contracts\BookingPublicServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Models\BookingAvailabilityRule;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Site;
use Carbon\CarbonImmutable;

class UniversalBookingAvailabilityApiBridgeService
{
    public const SCHEMA_NAME = 'universal_booking_availability_api_bridge';

    public const SCHEMA_VERSION = 1;

    public function __construct(
        protected BookingPublicServiceContract $publicBooking,
        protected BookingPanelServiceContract $panelBooking
    ) {}

    /**
     * Build a read-only canonical snapshot over current booking availability/slots API behavior.
     *
     * This is the P5-F3-02 baseline and intentionally reuses the existing public/panel
     * booking services instead of introducing parallel availability logic.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function snapshot(Site $site, array $options = []): array
    {
        $includeMeta = (bool) ($options['include_meta'] ?? true);
        $includeApiSurface = ! array_key_exists('include_api_surface', $options) || (bool) $options['include_api_surface'];
        $includeSamples = ! array_key_exists('include_samples', $options) || (bool) $options['include_samples'];
        $slotPreviewLimit = max(1, min(50, (int) ($options['slot_preview_limit'] ?? 10)));

        $site = $site->fresh() ?? $site;
        $site->loadMissing([
            'project',
            'bookingServices',
            'bookingStaffResources',
            'bookingAvailabilityRules.service:id,name,slug',
            'bookingAvailabilityRules.staffResource:id,name,slug,type',
        ]);

        $availabilityRules = ($site->bookingAvailabilityRules ?? collect())
            ->sortBy(fn (BookingAvailabilityRule $rule): string => sprintf(
                '%05d|%s|%s|%020d',
                (int) ($rule->priority ?? 100),
                (string) ($rule->rule_type ?? ''),
                (string) ($rule->day_of_week ?? ''),
                (int) $rule->id
            ))
            ->values()
            ->map(fn (BookingAvailabilityRule $rule): array => $this->normalizeAvailabilityRule($rule, $includeMeta))
            ->all();

        $slotRequest = $this->resolveSlotRequest($site, $options);
        $calendarRange = $this->resolveCalendarRange($slotRequest, $options);

        $slotPayload = null;
        $slotError = null;
        if ($includeSamples && $slotRequest !== null) {
            try {
                $slotPayload = $this->publicBooking->slots($site, $slotRequest);
            } catch (BookingDomainException $exception) {
                $slotError = [
                    'status' => $exception->status(),
                    'error' => $exception->getMessage(),
                    'context' => $exception->context(),
                ];
            }
        }

        $calendarPayload = null;
        $calendarError = null;
        if ($includeSamples && $calendarRange !== null) {
            try {
                $calendarPayload = $this->panelBooking->calendar($site, $calendarRange);
            } catch (BookingDomainException $exception) {
                $calendarError = [
                    'status' => $exception->status(),
                    'error' => $exception->getMessage(),
                    'context' => $exception->context(),
                ];
            }
        }

        $blockedTimes = $this->normalizeBlockedTimes($calendarPayload);

        return [
            'schema' => [
                'name' => self::SCHEMA_NAME,
                'version' => self::SCHEMA_VERSION,
                'task' => 'P5-F3-02',
                'read_only' => true,
            ],
            'site' => [
                'id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'name' => (string) $site->name,
                'locale' => (string) $site->locale,
                'status' => (string) $site->status,
                'project_type' => (string) ($site->project?->getAttribute('type') ?? ''),
            ],
            'sources' => [
                'availability_rules' => ['booking_availability_rules'],
                'work_schedules' => ['booking_staff_work_schedules'],
                'time_off' => ['booking_staff_time_off'],
                'bookings' => ['bookings'],
            ],
            'counts' => [
                'availability_rules' => count($availabilityRules),
                'slots' => is_array($slotPayload) ? count((array) data_get($slotPayload, 'slots', [])) : 0,
                'calendar_events' => (int) count((array) data_get($calendarPayload, 'events', [])),
                'calendar_staff_schedule_blocks' => (int) count((array) data_get($calendarPayload, 'staff_schedule_blocks', [])),
                'calendar_time_off_blocks' => (int) count((array) data_get($calendarPayload, 'time_off_blocks', [])),
                'blocked_times' => count($blockedTimes),
            ],
            'availability_rules' => $availabilityRules,
            'samples' => [
                'slot_request' => $slotRequest,
                'calendar_range' => $calendarRange,
                'public_slots' => $this->normalizePublicSlotsSample($slotPayload, $slotPreviewLimit),
                'public_slots_error' => $slotError,
                'panel_calendar' => $this->normalizePanelCalendarSample($calendarPayload),
                'panel_calendar_error' => $calendarError,
            ],
            'blocked_times' => $blockedTimes,
            'collision_contract' => $this->collisionContract(),
            'api_surface' => $includeApiSurface ? $this->apiSurface() : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSlotRequest(Site $site, array $options): ?array
    {
        $raw = is_array($options['slot_request'] ?? null) ? $options['slot_request'] : [];

        $services = ($site->bookingServices ?? collect())
            ->filter(fn (BookingService $service): bool => (string) $service->status === BookingService::STATUS_ACTIVE)
            ->sortBy(fn (BookingService $service): string => sprintf('%s|%020d', (string) $service->slug, (int) $service->id))
            ->values();

        /** @var BookingService|null $service */
        $service = null;
        $requestedServiceId = isset($raw['service_id']) ? (int) $raw['service_id'] : 0;
        if ($requestedServiceId > 0) {
            $service = $services->firstWhere('id', $requestedServiceId);
        }

        if (! $service && is_string($raw['service_slug'] ?? null) && trim((string) $raw['service_slug']) !== '') {
            $service = $services->firstWhere('slug', trim((string) $raw['service_slug']));
        }

        if (! $service) {
            $service = $services->first();
        }

        if (! $service instanceof BookingService) {
            return null;
        }

        $resources = ($site->bookingStaffResources ?? collect())
            ->filter(fn (BookingStaffResource $resource): bool => (string) $resource->status === BookingStaffResource::STATUS_ACTIVE)
            ->sortBy(fn (BookingStaffResource $resource): string => sprintf('%s|%s|%020d', (string) $resource->type, (string) $resource->slug, (int) $resource->id))
            ->values();

        $requestedStaffId = isset($raw['staff_resource_id']) ? (int) $raw['staff_resource_id'] : 0;
        $staff = $requestedStaffId > 0 ? $resources->firstWhere('id', $requestedStaffId) : null;

        if (! $staff && (bool) $service->requires_staff) {
            $staff = $resources->first(fn (BookingStaffResource $resource): bool => (string) $resource->type === BookingStaffResource::TYPE_STAFF);
        }

        $date = $this->normalizeDateString($raw['date'] ?? null)
            ?? $this->normalizeDateString($options['sample_date'] ?? null)
            ?? now()->toDateString();

        $timezone = trim((string) ($raw['timezone'] ?? ''));
        if ($timezone === '') {
            $timezone = trim((string) ($staff?->timezone ?? ''));
        }
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'UTC');
        }

        $duration = isset($raw['duration_minutes']) ? (int) $raw['duration_minutes'] : null;
        if ($duration !== null && $duration <= 0) {
            $duration = null;
        }

        return array_filter([
            'service_id' => (int) $service->id,
            'service_slug' => (string) $service->slug,
            'date' => $date,
            'staff_resource_id' => $staff?->id ? (int) $staff->id : null,
            'timezone' => $timezone,
            'duration_minutes' => $duration,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>|null  $slotRequest
     * @return array<string, string>|null
     */
    private function resolveCalendarRange(?array $slotRequest, array $options): ?array
    {
        $raw = is_array($options['calendar_range'] ?? null) ? $options['calendar_range'] : [];

        $from = $this->normalizeDateString($raw['from'] ?? null);
        $to = $this->normalizeDateString($raw['to'] ?? null);

        if (! $from && isset($slotRequest['date'])) {
            $from = (string) $slotRequest['date'];
        }
        if (! $to && isset($slotRequest['date'])) {
            $to = (string) $slotRequest['date'];
        }

        if (! $from && ! $to) {
            $today = now()->toDateString();

            return [
                'from' => $today,
                'to' => $today,
            ];
        }

        return [
            'from' => $from ?? $to ?? now()->toDateString(),
            'to' => $to ?? $from ?? now()->toDateString(),
        ];
    }

    private function normalizeDateString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAvailabilityRule(BookingAvailabilityRule $rule, bool $includeMeta): array
    {
        return [
            'kind' => 'booking_availability_rule',
            'source' => [
                'table' => 'booking_availability_rules',
                'id' => (int) $rule->id,
            ],
            'id' => (int) $rule->id,
            'site_id' => (string) $rule->site_id,
            'service_id' => $rule->service_id ? (int) $rule->service_id : null,
            'staff_resource_id' => $rule->staff_resource_id ? (int) $rule->staff_resource_id : null,
            'service' => $rule->service ? [
                'id' => (int) $rule->service->id,
                'name' => (string) $rule->service->name,
                'slug' => (string) $rule->service->slug,
            ] : null,
            'staff_resource' => $rule->staffResource ? [
                'id' => (int) $rule->staffResource->id,
                'name' => (string) $rule->staffResource->name,
                'slug' => (string) $rule->staffResource->slug,
                'type' => (string) $rule->staffResource->type,
            ] : null,
            'day_of_week' => $rule->day_of_week !== null ? (int) $rule->day_of_week : null,
            'start_time' => $rule->start_time,
            'end_time' => $rule->end_time,
            'rule_type' => (string) $rule->rule_type,
            'priority' => (int) $rule->priority,
            'effective_from' => $rule->effective_from?->toDateString(),
            'effective_to' => $rule->effective_to?->toDateString(),
            'meta_json' => $includeMeta ? ($rule->meta_json ?? []) : null,
            'updated_at' => $rule->updated_at?->toISOString(),
            'created_at' => $rule->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePublicSlotsSample(?array $payload, int $slotPreviewLimit): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $slots = collect((array) ($payload['slots'] ?? []))->values();
        $firstSlot = $slots->first();
        $lastSlot = $slots->last();

        return [
            'site_id' => (string) ($payload['site_id'] ?? ''),
            'date' => (string) ($payload['date'] ?? ''),
            'timezone' => (string) ($payload['timezone'] ?? ''),
            'service' => is_array($payload['service'] ?? null) ? $payload['service'] : null,
            'slot_count' => $slots->count(),
            'supports_staff_dimension' => $slots->contains(fn (array $slot): bool => is_array($slot['staff_resource'] ?? null)),
            'first_slot_starts_at' => is_array($firstSlot) ? ($firstSlot['starts_at'] ?? null) : null,
            'last_slot_starts_at' => is_array($lastSlot) ? ($lastSlot['starts_at'] ?? null) : null,
            'sample_slots' => $slots->take($slotPreviewLimit)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePanelCalendarSample(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $events = array_values((array) ($payload['events'] ?? []));
        $scheduleBlocks = array_values((array) ($payload['staff_schedule_blocks'] ?? []));
        $timeOffBlocks = array_values((array) ($payload['time_off_blocks'] ?? []));

        return [
            'site_id' => (string) ($payload['site_id'] ?? ''),
            'from' => (string) ($payload['from'] ?? ''),
            'to' => (string) ($payload['to'] ?? ''),
            'counts' => [
                'events' => count($events),
                'staff_schedule_blocks' => count($scheduleBlocks),
                'time_off_blocks' => count($timeOffBlocks),
            ],
            'events' => $events,
            'staff_schedule_blocks' => $scheduleBlocks,
            'time_off_blocks' => $timeOffBlocks,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $calendarPayload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBlockedTimes(?array $calendarPayload): array
    {
        if (! is_array($calendarPayload)) {
            return [];
        }

        $blocks = [];

        foreach ((array) ($calendarPayload['time_off_blocks'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $blocks[] = [
                'kind' => 'time_off',
                'source' => 'panel_calendar.time_off_blocks',
                'id' => $row['id'] ?? null,
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'status' => $row['status'] ?? null,
                'reason' => $row['reason'] ?? null,
                'staff_resource' => is_array($row['staff_resource'] ?? null) ? $row['staff_resource'] : null,
            ];
        }

        foreach ((array) ($calendarPayload['staff_schedule_blocks'] ?? []) as $row) {
            if (! is_array($row) || (bool) ($row['is_available'] ?? true) !== false) {
                continue;
            }

            $blocks[] = [
                'kind' => 'staff_unavailable_schedule',
                'source' => 'panel_calendar.staff_schedule_blocks',
                'id' => $row['id'] ?? null,
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'day_of_week' => $row['day_of_week'] ?? null,
                'timezone' => $row['timezone'] ?? null,
                'staff_resource' => is_array($row['staff_resource'] ?? null) ? $row['staff_resource'] : null,
            ];
        }

        return collect($blocks)
            ->sortBy([
                ['starts_at', 'asc'],
                ['kind', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function collisionContract(): array
    {
        return [
            'service' => 'App\\Booking\\Services\\BookingCollisionService::assertNoCollision',
            'reason_codes' => ['slot_collision'],
            'scopes' => ['service', 'staff_resource'],
            'error_payload_fields' => [
                'reason',
                'scope',
                'service_id',
                'staff_resource_id',
                'overlap_count',
                'parallel_limit',
                'starts_at',
                'ends_at',
            ],
            'applies_to' => [
                ['route' => 'public.sites.booking.bookings.store', 'flow' => 'public_booking_create'],
                ['route' => 'panel.sites.booking.bookings.store', 'flow' => 'panel_booking_create'],
                ['route' => 'panel.sites.booking.bookings.reschedule', 'flow' => 'panel_booking_reschedule'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiSurface(): array
    {
        return [
            'public' => [
                'controller' => 'App\\Http\\Controllers\\Booking\\PublicBookingController',
                'routes' => [
                    ['name' => 'public.sites.booking.services', 'method' => 'GET', 'path' => '/api/public/sites/{site}/booking/services'],
                    ['name' => 'public.sites.booking.slots', 'method' => 'GET', 'path' => '/api/public/sites/{site}/booking/slots'],
                    ['name' => 'public.sites.booking.bookings.store', 'method' => 'POST', 'path' => '/api/public/sites/{site}/booking/bookings'],
                ],
            ],
            'panel' => [
                'controller' => 'App\\Http\\Controllers\\Booking\\PanelBookingController',
                'routes' => [
                    ['name' => 'panel.sites.booking.calendar', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/calendar'],
                    ['name' => 'panel.sites.booking.calendar.advanced', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/calendar/advanced'],
                    ['name' => 'panel.sites.booking.bookings.store', 'method' => 'POST', 'path' => '/panel/sites/{site}/booking/bookings'],
                    ['name' => 'panel.sites.booking.bookings.reschedule', 'method' => 'POST', 'path' => '/panel/sites/{site}/booking/bookings/{booking}/reschedule'],
                ],
            ],
            'reused_domain_services' => [
                'public' => 'App\\Booking\\Services\\BookingPublicService',
                'panel' => 'App\\Booking\\Services\\BookingPanelService',
                'collisions' => 'App\\Booking\\Services\\BookingCollisionService',
            ],
            'coverage_notes' => [
                'public slots API produces availability windows/slots',
                'panel calendar API exposes staff schedule blocks + time off blocks (blocked-times source)',
                'booking_availability_rules table is included as schema source snapshot for future rule-API wiring',
            ],
            'deferred_to_next_tasks' => [
                'P5-F3-03' => ['booking flow orchestration', 'booking events', 'optional payment linkage'],
                'P5-F3-04' => ['booking builder components', 'project-type gated component library exposure'],
            ],
        ];
    }
}
