<?php

namespace App\Booking\Services;

use App\Booking\Contracts\BookingCollisionServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffTimeOff;
use App\Models\BookingStaffWorkSchedule;
use App\Models\OperationLog;
use App\Models\Site;
use App\Models\User;
use App\Services\OperationLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingPanelService implements BookingPanelServiceContract
{
    public function __construct(
        protected BookingCollisionServiceContract $collisions,
        protected OperationLogService $operationLogs,
        protected BookingNotificationService $notifications
    ) {}

    public function listServices(Site $site): array
    {
        $services = BookingService::query()
            ->where('site_id', $site->id)
            ->orderBy('name')
            ->get()
            ->map(fn (BookingService $service): array => $this->serializeService($service))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'services' => $services,
        ];
    }

    public function createService(Site $site, array $payload): BookingService
    {
        return BookingService::query()->create([
            'site_id' => $site->id,
            'name' => trim((string) $payload['name']),
            'slug' => trim((string) $payload['slug']),
            'status' => (string) ($payload['status'] ?? BookingService::STATUS_ACTIVE),
            'description' => $payload['description'] ?? null,
            'duration_minutes' => (int) ($payload['duration_minutes'] ?? 60),
            'buffer_before_minutes' => (int) ($payload['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($payload['buffer_after_minutes'] ?? 0),
            'slot_step_minutes' => $payload['slot_step_minutes'] ?? null,
            'max_parallel_bookings' => (int) ($payload['max_parallel_bookings'] ?? 1),
            'requires_staff' => (bool) ($payload['requires_staff'] ?? true),
            'allow_online_payment' => (bool) ($payload['allow_online_payment'] ?? false),
            'price' => $this->toMoney($payload['price'] ?? 0),
            'currency' => (string) ($payload['currency'] ?? 'GEL'),
            'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : null,
        ]);
    }

    public function updateService(Site $site, BookingService $service, array $payload): BookingService
    {
        $target = $this->resolveService($site, $service);

        $target->update([
            'name' => array_key_exists('name', $payload) ? trim((string) $payload['name']) : $target->name,
            'slug' => array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : $target->slug,
            'status' => $payload['status'] ?? $target->status,
            'description' => array_key_exists('description', $payload) ? $payload['description'] : $target->description,
            'duration_minutes' => array_key_exists('duration_minutes', $payload)
                ? (int) $payload['duration_minutes']
                : $target->duration_minutes,
            'buffer_before_minutes' => array_key_exists('buffer_before_minutes', $payload)
                ? (int) $payload['buffer_before_minutes']
                : $target->buffer_before_minutes,
            'buffer_after_minutes' => array_key_exists('buffer_after_minutes', $payload)
                ? (int) $payload['buffer_after_minutes']
                : $target->buffer_after_minutes,
            'slot_step_minutes' => array_key_exists('slot_step_minutes', $payload)
                ? $payload['slot_step_minutes']
                : $target->slot_step_minutes,
            'max_parallel_bookings' => array_key_exists('max_parallel_bookings', $payload)
                ? (int) $payload['max_parallel_bookings']
                : $target->max_parallel_bookings,
            'requires_staff' => array_key_exists('requires_staff', $payload)
                ? (bool) $payload['requires_staff']
                : $target->requires_staff,
            'allow_online_payment' => array_key_exists('allow_online_payment', $payload)
                ? (bool) $payload['allow_online_payment']
                : $target->allow_online_payment,
            'price' => array_key_exists('price', $payload)
                ? $this->toMoney($payload['price'])
                : $target->price,
            'currency' => array_key_exists('currency', $payload)
                ? (string) $payload['currency']
                : $target->currency,
            'meta_json' => array_key_exists('meta_json', $payload) && is_array($payload['meta_json'])
                ? $payload['meta_json']
                : $target->meta_json,
        ]);

        return $target->fresh();
    }

    public function deleteService(Site $site, BookingService $service): void
    {
        $target = $this->resolveService($site, $service);

        $hasBookings = Booking::query()
            ->where('site_id', $site->id)
            ->where('service_id', $target->id)
            ->exists();

        if ($hasBookings) {
            throw new BookingDomainException('Cannot delete service with existing bookings.', 422, [
                'service_id' => $target->id,
            ]);
        }

        $target->delete();
    }

    public function listStaff(Site $site): array
    {
        $staff = BookingStaffResource::query()
            ->where('site_id', $site->id)
            ->orderBy('name')
            ->get()
            ->map(fn (BookingStaffResource $resource): array => $this->serializeStaff($resource))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'staff' => $staff,
        ];
    }

    public function createStaff(Site $site, array $payload): BookingStaffResource
    {
        return BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => trim((string) $payload['name']),
            'slug' => trim((string) $payload['slug']),
            'type' => (string) ($payload['type'] ?? BookingStaffResource::TYPE_STAFF),
            'status' => (string) ($payload['status'] ?? BookingStaffResource::STATUS_ACTIVE),
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'timezone' => (string) ($payload['timezone'] ?? config('app.timezone', 'UTC')),
            'max_parallel_bookings' => (int) ($payload['max_parallel_bookings'] ?? 1),
            'buffer_minutes' => (int) ($payload['buffer_minutes'] ?? 0),
            'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : null,
        ]);
    }

    public function updateStaff(Site $site, BookingStaffResource $staffResource, array $payload): BookingStaffResource
    {
        $target = $this->resolveStaff($site, $staffResource);

        $target->update([
            'name' => array_key_exists('name', $payload) ? trim((string) $payload['name']) : $target->name,
            'slug' => array_key_exists('slug', $payload) ? trim((string) $payload['slug']) : $target->slug,
            'type' => array_key_exists('type', $payload) ? (string) $payload['type'] : $target->type,
            'status' => array_key_exists('status', $payload) ? (string) $payload['status'] : $target->status,
            'email' => array_key_exists('email', $payload) ? $payload['email'] : $target->email,
            'phone' => array_key_exists('phone', $payload) ? $payload['phone'] : $target->phone,
            'timezone' => array_key_exists('timezone', $payload) ? (string) $payload['timezone'] : $target->timezone,
            'max_parallel_bookings' => array_key_exists('max_parallel_bookings', $payload)
                ? (int) $payload['max_parallel_bookings']
                : $target->max_parallel_bookings,
            'buffer_minutes' => array_key_exists('buffer_minutes', $payload)
                ? (int) $payload['buffer_minutes']
                : $target->buffer_minutes,
            'meta_json' => array_key_exists('meta_json', $payload) && is_array($payload['meta_json'])
                ? $payload['meta_json']
                : $target->meta_json,
        ]);

        return $target->fresh();
    }

    public function deleteStaff(Site $site, BookingStaffResource $staffResource): void
    {
        $target = $this->resolveStaff($site, $staffResource);

        $hasBookings = Booking::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $target->id)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->exists();

        if ($hasBookings) {
            throw new BookingDomainException('Cannot delete staff/resource with active bookings.', 422, [
                'staff_resource_id' => $target->id,
            ]);
        }

        BookingAssignment::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $target->id)
            ->delete();

        $target->delete();
    }

    public function listStaffSchedules(Site $site, BookingStaffResource $staffResource): array
    {
        $resource = $this->resolveStaff($site, $staffResource);

        $schedules = BookingStaffWorkSchedule::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $resource->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingStaffWorkSchedule $schedule): array => $this->serializeStaffSchedule($schedule))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'staff_resource' => $this->serializeStaff($resource),
            'schedules' => $schedules,
        ];
    }

    public function syncStaffSchedules(
        Site $site,
        BookingStaffResource $staffResource,
        array $schedules,
        ?User $actor = null
    ): array {
        $resource = $this->resolveStaff($site, $staffResource);

        return DB::transaction(function () use ($site, $resource, $schedules, $actor): array {
            BookingStaffWorkSchedule::query()
                ->where('site_id', $site->id)
                ->where('staff_resource_id', $resource->id)
                ->delete();

            foreach ($schedules as $schedule) {
                $startTime = CarbonImmutable::parse('2000-01-01 '.(string) $schedule['start_time']);
                $endTime = CarbonImmutable::parse('2000-01-01 '.(string) $schedule['end_time']);
                if ($endTime->lessThanOrEqualTo($startTime)) {
                    throw new BookingDomainException('Work schedule end time must be after start time.', 422, [
                        'day_of_week' => (int) $schedule['day_of_week'],
                    ]);
                }

                $effectiveFrom = $schedule['effective_from'] ?? null;
                $effectiveTo = $schedule['effective_to'] ?? null;
                if ($effectiveFrom && $effectiveTo && CarbonImmutable::parse((string) $effectiveTo)->lessThan(CarbonImmutable::parse((string) $effectiveFrom))) {
                    throw new BookingDomainException('Work schedule effective_to must be on or after effective_from.', 422, [
                        'day_of_week' => (int) $schedule['day_of_week'],
                    ]);
                }

                BookingStaffWorkSchedule::query()->create([
                    'site_id' => $site->id,
                    'staff_resource_id' => $resource->id,
                    'day_of_week' => (int) $schedule['day_of_week'],
                    'start_time' => (string) $schedule['start_time'],
                    'end_time' => (string) $schedule['end_time'],
                    'is_available' => (bool) ($schedule['is_available'] ?? true),
                    'timezone' => (string) ($schedule['timezone'] ?? $resource->timezone ?? config('app.timezone', 'UTC')),
                    'effective_from' => $schedule['effective_from'] ?? null,
                    'effective_to' => $schedule['effective_to'] ?? null,
                    'meta_json' => is_array($schedule['meta_json'] ?? null) ? $schedule['meta_json'] : null,
                ]);
            }

            $this->log($site, $actor, 'booking_staff_schedule_synced', OperationLog::STATUS_INFO, 'Booking staff schedule synced.', [
                'staff_resource_id' => $resource->id,
                'schedules_count' => count($schedules),
            ]);

            return $this->listStaffSchedules($site, $resource);
        }, 3);
    }

    public function listStaffTimeOff(Site $site, BookingStaffResource $staffResource, array $filters = []): array
    {
        $resource = $this->resolveStaff($site, $staffResource);

        $query = BookingStaffTimeOff::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $resource->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if (is_string($filters['status'] ?? null) && trim((string) $filters['status']) !== '') {
            $query->where('status', trim((string) $filters['status']));
        }

        if (is_string($filters['from'] ?? null) && trim((string) $filters['from']) !== '') {
            $query->where('ends_at', '>=', CarbonImmutable::parse((string) $filters['from'])->startOfDay());
        }

        if (is_string($filters['to'] ?? null) && trim((string) $filters['to']) !== '') {
            $query->where('starts_at', '<=', CarbonImmutable::parse((string) $filters['to'])->endOfDay());
        }

        $limit = max(1, min((int) ($filters['limit'] ?? 100), 500));

        $timeOff = $query
            ->limit($limit)
            ->get()
            ->map(fn (BookingStaffTimeOff $entry): array => $this->serializeStaffTimeOff($entry))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'staff_resource' => $this->serializeStaff($resource),
            'time_off' => $timeOff,
        ];
    }

    public function createStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        array $payload,
        ?User $actor = null
    ): BookingStaffTimeOff {
        $resource = $this->resolveStaff($site, $staffResource);
        $startsAt = CarbonImmutable::parse((string) $payload['starts_at']);
        $endsAt = CarbonImmutable::parse((string) $payload['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new BookingDomainException('Time-off end must be after start.', 422);
        }

        $entry = BookingStaffTimeOff::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => (string) ($payload['status'] ?? 'approved'),
            'reason' => $payload['reason'] ?? null,
            'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : null,
            'created_by' => $actor?->id,
        ]);

        $this->log($site, $actor, 'booking_staff_time_off_created', OperationLog::STATUS_INFO, 'Booking staff time-off created.', [
            'staff_resource_id' => $resource->id,
            'time_off_id' => $entry->id,
            'starts_at' => $startsAt->toISOString(),
            'ends_at' => $endsAt->toISOString(),
            'status' => $entry->status,
        ]);

        return $entry;
    }

    public function updateStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff,
        array $payload,
        ?User $actor = null
    ): BookingStaffTimeOff {
        $resource = $this->resolveStaff($site, $staffResource);
        $entry = $this->resolveStaffTimeOff($site, $resource, $timeOff);

        $startsAt = array_key_exists('starts_at', $payload)
            ? CarbonImmutable::parse((string) $payload['starts_at'])
            : CarbonImmutable::instance($entry->starts_at);

        $endsAt = array_key_exists('ends_at', $payload)
            ? CarbonImmutable::parse((string) $payload['ends_at'])
            : CarbonImmutable::instance($entry->ends_at);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new BookingDomainException('Time-off end must be after start.', 422);
        }

        $entry->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => array_key_exists('status', $payload) ? (string) $payload['status'] : $entry->status,
            'reason' => array_key_exists('reason', $payload) ? $payload['reason'] : $entry->reason,
            'meta_json' => array_key_exists('meta_json', $payload) && is_array($payload['meta_json'])
                ? $payload['meta_json']
                : $entry->meta_json,
        ]);

        $this->log($site, $actor, 'booking_staff_time_off_updated', OperationLog::STATUS_INFO, 'Booking staff time-off updated.', [
            'staff_resource_id' => $resource->id,
            'time_off_id' => $entry->id,
            'starts_at' => $startsAt->toISOString(),
            'ends_at' => $endsAt->toISOString(),
            'status' => $entry->status,
        ]);

        return $entry->fresh();
    }

    public function deleteStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff,
        ?User $actor = null
    ): void {
        $resource = $this->resolveStaff($site, $staffResource);
        $entry = $this->resolveStaffTimeOff($site, $resource, $timeOff);

        $entry->delete();

        $this->log($site, $actor, 'booking_staff_time_off_deleted', OperationLog::STATUS_INFO, 'Booking staff time-off deleted.', [
            'staff_resource_id' => $resource->id,
            'time_off_id' => $entry->id,
        ]);
    }

    public function listBookings(Site $site, array $filters = []): array
    {
        $query = Booking::query()
            ->where('site_id', $site->id)
            ->with(['service', 'staffResource'])
            ->withCount('events');

        if (is_string($filters['status'] ?? null) && trim((string) $filters['status']) !== '') {
            $query->where('status', trim((string) $filters['status']));
        }

        if (is_string($filters['search'] ?? null) && trim((string) $filters['search']) !== '') {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('booking_number', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_email', 'like', $search)
                    ->orWhere('customer_phone', 'like', $search);
            });
        }

        if (is_string($filters['date_from'] ?? null) && trim((string) $filters['date_from']) !== '') {
            $query->where('starts_at', '>=', CarbonImmutable::parse((string) $filters['date_from'])->startOfDay());
        }

        if (is_string($filters['date_to'] ?? null) && trim((string) $filters['date_to']) !== '') {
            $query->where('starts_at', '<=', CarbonImmutable::parse((string) $filters['date_to'])->endOfDay());
        }

        $limit = (int) ($filters['limit'] ?? 100);
        $limit = max(1, min($limit, 500));

        $bookings = $query
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (Booking $booking): array => $this->serializeBookingSummary($booking))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'bookings' => $bookings,
            'inbox_counts' => $this->inboxCounts($site),
        ];
    }

    public function calendar(Site $site, array $filters = []): array
    {
        $from = is_string($filters['from'] ?? null) && trim((string) $filters['from']) !== ''
            ? CarbonImmutable::parse((string) $filters['from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = is_string($filters['to'] ?? null) && trim((string) $filters['to']) !== ''
            ? CarbonImmutable::parse((string) $filters['to'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        if ($to->lessThan($from)) {
            throw new BookingDomainException('Calendar range is invalid.', 422);
        }

        $events = Booking::query()
            ->where('site_id', $site->id)
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->with(['service', 'staffResource'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status,
                'starts_at' => $booking->starts_at?->toISOString(),
                'ends_at' => $booking->ends_at?->toISOString(),
                'customer_name' => $booking->customer_name,
                'service' => [
                    'id' => $booking->service?->id,
                    'name' => $booking->service?->name,
                ],
                'staff_resource' => [
                    'id' => $booking->staffResource?->id,
                    'name' => $booking->staffResource?->name,
                ],
            ])
            ->values()
            ->all();

        $timeOffBlocks = $this->calendarTimeOffBlocks($site, $from, $to);
        $staffScheduleBlocks = $this->calendarStaffScheduleBlocks($site, $from, $to);

        return [
            'site_id' => $site->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events,
            'staff_schedule_blocks' => $staffScheduleBlocks,
            'time_off_blocks' => $timeOffBlocks,
        ];
    }

    public function showBooking(Site $site, Booking $booking): array
    {
        $target = $this->resolveBooking($site, $booking, withRelations: true);

        return [
            'site_id' => $site->id,
            'booking' => $this->serializeBookingDetails($target),
        ];
    }

    public function createBooking(Site $site, array $payload, ?User $actor = null): Booking
    {
        $booking = $this->collisions->createBooking($site, $payload, $actor);
        $this->notifications->sendConfirmation($booking);

        return $booking;
    }

    public function updateBookingStatus(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($site, $booking, $payload, $actor): Booking {
            $target = $this->resolveBooking($site, $booking);
            $newStatus = strtolower(trim((string) ($payload['status'] ?? '')));

            $allowed = [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW,
            ];

            if (! in_array($newStatus, $allowed, true)) {
                throw new BookingDomainException('Unsupported booking status.', 422, [
                    'status' => $newStatus,
                ]);
            }

            $updates = [
                'status' => $newStatus,
                'updated_by' => $actor?->id,
            ];

            if ($newStatus === Booking::STATUS_CONFIRMED && ! $target->confirmed_at) {
                $updates['confirmed_at'] = now();
            }

            if ($newStatus === Booking::STATUS_COMPLETED && ! $target->completed_at) {
                $updates['completed_at'] = now();
            }

            if (in_array($newStatus, [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW], true) && ! $target->cancelled_at) {
                $updates['cancelled_at'] = now();
            }

            if (array_key_exists('internal_notes', $payload)) {
                $updates['internal_notes'] = $payload['internal_notes'];
            }

            if (array_key_exists('meta_json', $payload) && is_array($payload['meta_json'])) {
                $updates['meta_json'] = $payload['meta_json'];
            }

            $target->update($updates);

            $this->recordEvent($site, $target, 'status_updated', [
                'old_status' => $booking->status,
                'new_status' => $newStatus,
                'internal_notes' => $payload['internal_notes'] ?? null,
            ], $actor);

            $this->log($site, $actor, 'booking_status_updated', OperationLog::STATUS_INFO, 'Booking status updated.', [
                'booking_id' => $target->id,
                'booking_number' => $target->booking_number,
                'status' => $newStatus,
            ]);

            return $target->fresh(['service', 'staffResource', 'assignments', 'events']);
        }, 3);
    }

    public function rescheduleBooking(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking
    {
        return $this->collisions->rescheduleBooking($site, $booking, $payload, $actor);
    }

    public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $actor = null): Booking
    {
        $reason = $payload['reason'] ?? null;

        return $this->updateBookingStatus($site, $booking, [
            'status' => Booking::STATUS_CANCELLED,
            'internal_notes' => $reason ?: ($payload['internal_notes'] ?? null),
            'meta_json' => is_array($payload['meta_json'] ?? null)
                ? $payload['meta_json']
                : ($booking->meta_json ?? []),
        ], $actor);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeService(BookingService $service): array
    {
        return [
            'id' => $service->id,
            'site_id' => $service->site_id,
            'name' => $service->name,
            'slug' => $service->slug,
            'status' => $service->status,
            'description' => $service->description,
            'duration_minutes' => (int) $service->duration_minutes,
            'buffer_before_minutes' => (int) $service->buffer_before_minutes,
            'buffer_after_minutes' => (int) $service->buffer_after_minutes,
            'slot_step_minutes' => $service->slot_step_minutes !== null ? (int) $service->slot_step_minutes : null,
            'max_parallel_bookings' => (int) $service->max_parallel_bookings,
            'requires_staff' => (bool) $service->requires_staff,
            'allow_online_payment' => (bool) $service->allow_online_payment,
            'price' => (string) $service->price,
            'currency' => $service->currency,
            'meta_json' => $service->meta_json ?? [],
            'created_at' => $service->created_at?->toISOString(),
            'updated_at' => $service->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeStaff(BookingStaffResource $resource): array
    {
        return [
            'id' => $resource->id,
            'site_id' => $resource->site_id,
            'name' => $resource->name,
            'slug' => $resource->slug,
            'type' => $resource->type,
            'status' => $resource->status,
            'email' => $resource->email,
            'phone' => $resource->phone,
            'timezone' => $resource->timezone,
            'max_parallel_bookings' => (int) $resource->max_parallel_bookings,
            'buffer_minutes' => (int) $resource->buffer_minutes,
            'meta_json' => $resource->meta_json ?? [],
            'created_at' => $resource->created_at?->toISOString(),
            'updated_at' => $resource->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeStaffSchedule(BookingStaffWorkSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'site_id' => $schedule->site_id,
            'staff_resource_id' => $schedule->staff_resource_id,
            'day_of_week' => (int) $schedule->day_of_week,
            'start_time' => (string) $schedule->start_time,
            'end_time' => (string) $schedule->end_time,
            'is_available' => (bool) $schedule->is_available,
            'timezone' => $schedule->timezone,
            'effective_from' => $schedule->effective_from?->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
            'meta_json' => $schedule->meta_json ?? [],
            'created_at' => $schedule->created_at?->toISOString(),
            'updated_at' => $schedule->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeStaffTimeOff(BookingStaffTimeOff $entry): array
    {
        return [
            'id' => $entry->id,
            'site_id' => $entry->site_id,
            'staff_resource_id' => $entry->staff_resource_id,
            'starts_at' => $entry->starts_at?->toISOString(),
            'ends_at' => $entry->ends_at?->toISOString(),
            'status' => $entry->status,
            'reason' => $entry->reason,
            'meta_json' => $entry->meta_json ?? [],
            'created_by' => $entry->created_by,
            'created_at' => $entry->created_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeBookingSummary(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'site_id' => $booking->site_id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'source' => $booking->source,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'starts_at' => $booking->starts_at?->toISOString(),
            'ends_at' => $booking->ends_at?->toISOString(),
            'duration_minutes' => (int) $booking->duration_minutes,
            'service' => [
                'id' => $booking->service?->id,
                'name' => $booking->service?->name,
            ],
            'staff_resource' => [
                'id' => $booking->staffResource?->id,
                'name' => $booking->staffResource?->name,
            ],
            'service_fee' => (string) $booking->service_fee,
            'grand_total' => (string) $booking->grand_total,
            'paid_total' => (string) $booking->paid_total,
            'outstanding_total' => (string) $booking->outstanding_total,
            'currency' => $booking->currency,
            'events_count' => (int) ($booking->events_count ?? 0),
            'created_at' => $booking->created_at?->toISOString(),
            'updated_at' => $booking->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeBookingDetails(Booking $booking): array
    {
        return [
            ...$this->serializeBookingSummary($booking),
            'collision_starts_at' => $booking->collision_starts_at?->toISOString(),
            'collision_ends_at' => $booking->collision_ends_at?->toISOString(),
            'buffer_before_minutes' => (int) $booking->buffer_before_minutes,
            'buffer_after_minutes' => (int) $booking->buffer_after_minutes,
            'customer_notes' => $booking->customer_notes,
            'internal_notes' => $booking->internal_notes,
            'meta_json' => $booking->meta_json ?? [],
            'confirmed_at' => $booking->confirmed_at?->toISOString(),
            'cancelled_at' => $booking->cancelled_at?->toISOString(),
            'completed_at' => $booking->completed_at?->toISOString(),
            'assignments' => $booking->assignments
                ->map(fn (BookingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'staff_resource_id' => $assignment->staff_resource_id,
                    'assignment_type' => $assignment->assignment_type,
                    'status' => $assignment->status,
                    'starts_at' => $assignment->starts_at?->toISOString(),
                    'ends_at' => $assignment->ends_at?->toISOString(),
                    'meta_json' => $assignment->meta_json ?? [],
                    'created_at' => $assignment->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'events' => $booking->events
                ->sortByDesc(fn (BookingEvent $event): int => $event->occurred_at?->getTimestamp() ?? 0)
                ->values()
                ->map(fn (BookingEvent $event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_key' => $event->event_key,
                    'payload_json' => $event->payload_json ?? [],
                    'occurred_at' => $event->occurred_at?->toISOString(),
                    'created_at' => $event->created_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function calendarTimeOffBlocks(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return BookingStaffTimeOff::query()
            ->where('site_id', $site->id)
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->whereIn('status', ['pending', 'approved'])
            ->with('staffResource:id,name,type')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (BookingStaffTimeOff $entry): array => [
                'id' => $entry->id,
                'type' => 'time_off',
                'status' => $entry->status,
                'starts_at' => $entry->starts_at?->toISOString(),
                'ends_at' => $entry->ends_at?->toISOString(),
                'reason' => $entry->reason,
                'staff_resource' => [
                    'id' => $entry->staffResource?->id,
                    'name' => $entry->staffResource?->name,
                    'type' => $entry->staffResource?->type,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function calendarStaffScheduleBlocks(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $scheduleRows = BookingStaffWorkSchedule::query()
            ->where('site_id', $site->id)
            ->with('staffResource:id,name,type')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        if ($scheduleRows->isEmpty()) {
            return [];
        }

        $blocks = [];
        $cursor = $from->startOfDay();
        $lastDay = $to->endOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $dayOfWeek = (int) $cursor->dayOfWeek;
            $date = $cursor->toDateString();

            foreach ($scheduleRows as $row) {
                if ((int) $row->day_of_week !== $dayOfWeek) {
                    continue;
                }

                if ($row->effective_from && $row->effective_from->toDateString() > $date) {
                    continue;
                }

                if ($row->effective_to && $row->effective_to->toDateString() < $date) {
                    continue;
                }

                $startsAt = CarbonImmutable::parse($date.' '.(string) $row->start_time);
                $endsAt = CarbonImmutable::parse($date.' '.(string) $row->end_time);
                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    continue;
                }

                $blocks[] = [
                    'id' => $row->id.'-'.$date,
                    'type' => 'work_schedule',
                    'day_of_week' => (int) $row->day_of_week,
                    'is_available' => (bool) $row->is_available,
                    'starts_at' => $startsAt->toISOString(),
                    'ends_at' => $endsAt->toISOString(),
                    'timezone' => $row->timezone,
                    'staff_resource' => [
                        'id' => $row->staffResource?->id,
                        'name' => $row->staffResource?->name,
                        'type' => $row->staffResource?->type,
                    ],
                ];
            }

            $cursor = $cursor->addDay();
        }

        return collect($blocks)
            ->sortBy([
                ['starts_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function resolveService(Site $site, BookingService $service): BookingService
    {
        $target = BookingService::query()
            ->where('site_id', $site->id)
            ->whereKey($service->id)
            ->first();

        if (! $target) {
            throw (new ModelNotFoundException)->setModel(BookingService::class, [$service->id]);
        }

        return $target;
    }

    private function resolveStaff(Site $site, BookingStaffResource $staffResource): BookingStaffResource
    {
        $target = BookingStaffResource::query()
            ->where('site_id', $site->id)
            ->whereKey($staffResource->id)
            ->first();

        if (! $target) {
            throw (new ModelNotFoundException)->setModel(BookingStaffResource::class, [$staffResource->id]);
        }

        return $target;
    }

    private function resolveStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff
    ): BookingStaffTimeOff {
        $target = BookingStaffTimeOff::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $staffResource->id)
            ->whereKey($timeOff->id)
            ->first();

        if (! $target) {
            throw (new ModelNotFoundException)->setModel(BookingStaffTimeOff::class, [$timeOff->id]);
        }

        return $target;
    }

    private function resolveBooking(Site $site, Booking $booking, bool $withRelations = false): Booking
    {
        $query = Booking::query()
            ->where('site_id', $site->id)
            ->whereKey($booking->id);

        if ($withRelations) {
            $query->with(['service', 'staffResource', 'assignments', 'events']);
        }

        $target = $query->first();

        if (! $target) {
            throw (new ModelNotFoundException)->setModel(Booking::class, [$booking->id]);
        }

        return $target;
    }

    /**
     * @return array<string,int>
     */
    private function inboxCounts(Site $site): array
    {
        $rows = Booking::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->where('site_id', $site->id)
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            Booking::STATUS_PENDING => (int) ($rows[Booking::STATUS_PENDING] ?? 0),
            Booking::STATUS_CONFIRMED => (int) ($rows[Booking::STATUS_CONFIRMED] ?? 0),
            Booking::STATUS_IN_PROGRESS => (int) ($rows[Booking::STATUS_IN_PROGRESS] ?? 0),
            Booking::STATUS_COMPLETED => (int) ($rows[Booking::STATUS_COMPLETED] ?? 0),
            Booking::STATUS_CANCELLED => (int) ($rows[Booking::STATUS_CANCELLED] ?? 0),
            Booking::STATUS_NO_SHOW => (int) ($rows[Booking::STATUS_NO_SHOW] ?? 0),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function recordEvent(Site $site, Booking $booking, string $type, array $payload, ?User $actor): void
    {
        BookingEvent::query()->create([
            'site_id' => $site->id,
            'booking_id' => $booking->id,
            'event_type' => $type,
            'event_key' => (string) Str::uuid(),
            'payload_json' => $payload,
            'occurred_at' => now(),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function log(
        Site $site,
        ?User $actor,
        string $event,
        string $status,
        string $message,
        array $context = []
    ): void {
        $site->loadMissing('project');
        if (! $site->project) {
            return;
        }

        $this->operationLogs->logProject(
            project: $site->project,
            channel: OperationLog::CHANNEL_BOOKING,
            event: $event,
            status: $status,
            message: $message,
            attributes: [
                'user_id' => $actor?->id,
                'identifier' => (string) ($context['booking_number'] ?? ''),
                'context' => $context,
            ]
        );
    }

    private function toMoney(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new BookingDomainException('Monetary fields must be numeric.', 422);
        }

        return number_format((float) $value, 2, '.', '');
    }
}
