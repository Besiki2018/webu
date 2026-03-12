<?php

namespace App\Booking\Services;

use App\Booking\Contracts\BookingCollisionServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\OperationLog;
use App\Models\Site;
use App\Models\User;
use App\Services\OperationLogService;
use App\Services\UsageMeteringService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingCollisionService implements BookingCollisionServiceContract
{
    public function __construct(
        protected OperationLogService $operationLogs,
        protected UsageMeteringService $usageMetering
    ) {}

    public function createBooking(Site $site, array $payload, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($site, $payload, $actor): Booking {
            $this->assertMonthlyBookingLimitNotExceeded($site);

            $serviceId = $this->requiredInt($payload, 'service_id');
            $service = $this->lockService($site, $serviceId);

            $staffResource = $this->lockStaffResource($site, $this->nullableInt($payload, 'staff_resource_id'));
            if ((bool) $service->requires_staff && ! $staffResource) {
                throw new BookingDomainException('This service requires a staff assignment.', 422);
            }

            if ($staffResource && $staffResource->status !== BookingStaffResource::STATUS_ACTIVE) {
                throw new BookingDomainException('Selected staff/resource is inactive.', 422);
            }

            [$startsAt, $endsAt, $durationMinutes] = $this->resolveDurationWindow($payload, $service);
            $bufferBefore = max(0, (int) ($payload['buffer_before_minutes'] ?? $service->buffer_before_minutes));
            $bufferAfter = max(0, (int) ($payload['buffer_after_minutes'] ?? $service->buffer_after_minutes));
            $collisionStartsAt = $startsAt->subMinutes($bufferBefore);
            $collisionEndsAt = $endsAt->addMinutes($bufferAfter);

            $this->assertNoCollision(
                site: $site,
                service: $service,
                startsAt: $collisionStartsAt,
                endsAt: $collisionEndsAt,
                staffResource: $staffResource
            );

            $serviceFee = $this->toMoney($payload['service_fee'] ?? $service->price);
            $discountTotal = $this->toMoney($payload['discount_total'] ?? 0);
            $taxTotal = $this->toMoney($payload['tax_total'] ?? 0);
            $grandTotal = $this->toMoney($payload['grand_total'] ?? ($serviceFee - $discountTotal + $taxTotal));
            $paidTotal = $this->toMoney($payload['paid_total'] ?? 0);
            $outstandingTotal = $this->toMoney($payload['outstanding_total'] ?? ($grandTotal - $paidTotal));

            $booking = Booking::query()->create([
                'site_id' => $site->id,
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
                'booking_number' => $this->generateBookingNumber($site),
                'status' => $this->normalizeStatus($payload['status'] ?? null),
                'source' => (string) ($payload['source'] ?? 'panel'),
                'customer_name' => $this->nullableString($payload, 'customer_name'),
                'customer_email' => $this->nullableString($payload, 'customer_email'),
                'customer_phone' => $this->nullableString($payload, 'customer_phone'),
                'customer_notes' => $this->nullableString($payload, 'customer_notes'),
                'internal_notes' => $this->nullableString($payload, 'internal_notes'),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'collision_starts_at' => $collisionStartsAt,
                'collision_ends_at' => $collisionEndsAt,
                'duration_minutes' => $durationMinutes,
                'buffer_before_minutes' => $bufferBefore,
                'buffer_after_minutes' => $bufferAfter,
                'timezone' => (string) ($payload['timezone'] ?? $staffResource?->timezone ?? config('app.timezone', 'UTC')),
                'service_fee' => $serviceFee,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'paid_total' => $paidTotal,
                'outstanding_total' => $outstandingTotal,
                'currency' => (string) ($payload['currency'] ?? $service->currency ?? 'GEL'),
                'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            if ($staffResource) {
                BookingAssignment::query()->create([
                    'site_id' => $site->id,
                    'booking_id' => $booking->id,
                    'staff_resource_id' => $staffResource->id,
                    'assignment_type' => 'primary',
                    'status' => 'assigned',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'created_by' => $actor?->id,
                    'meta_json' => [
                        'source' => $payload['source'] ?? 'panel',
                    ],
                ]);
            }

            $this->recordEvent($site, $booking, 'created', [
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
                'starts_at' => $startsAt->toISOString(),
                'ends_at' => $endsAt->toISOString(),
            ], $actor);

            $this->log($site, $actor, 'booking_created', OperationLog::STATUS_SUCCESS, 'Booking created.', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
            ]);

            return $booking->fresh([
                'service',
                'staffResource',
                'assignments',
                'events',
            ]);
        }, 3);
    }

    public function rescheduleBooking(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($site, $booking, $payload, $actor): Booking {
            $lockedBooking = Booking::query()
                ->where('site_id', $site->id)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBooking) {
                throw new BookingDomainException('Booking not found in this site scope.', 404);
            }

            $serviceId = $this->nullableInt($payload, 'service_id') ?? (int) $lockedBooking->service_id;
            $service = $this->lockService($site, $serviceId);

            $staffResourceId = $this->nullableInt($payload, 'staff_resource_id');
            if ($staffResourceId === null && $lockedBooking->staff_resource_id) {
                $staffResourceId = (int) $lockedBooking->staff_resource_id;
            }

            $staffResource = $this->lockStaffResource($site, $staffResourceId);
            if ((bool) $service->requires_staff && ! $staffResource) {
                throw new BookingDomainException('This service requires a staff assignment.', 422);
            }

            [$startsAt, $endsAt, $durationMinutes] = $this->resolveDurationWindow($payload, $service, $lockedBooking);
            $bufferBefore = max(0, (int) ($payload['buffer_before_minutes'] ?? $lockedBooking->buffer_before_minutes ?? $service->buffer_before_minutes));
            $bufferAfter = max(0, (int) ($payload['buffer_after_minutes'] ?? $lockedBooking->buffer_after_minutes ?? $service->buffer_after_minutes));
            $collisionStartsAt = $startsAt->subMinutes($bufferBefore);
            $collisionEndsAt = $endsAt->addMinutes($bufferAfter);

            $this->assertNoCollision(
                site: $site,
                service: $service,
                startsAt: $collisionStartsAt,
                endsAt: $collisionEndsAt,
                staffResource: $staffResource,
                ignoreBookingId: (int) $lockedBooking->id
            );

            $updates = [
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'collision_starts_at' => $collisionStartsAt,
                'collision_ends_at' => $collisionEndsAt,
                'duration_minutes' => $durationMinutes,
                'buffer_before_minutes' => $bufferBefore,
                'buffer_after_minutes' => $bufferAfter,
                'timezone' => (string) ($payload['timezone'] ?? $lockedBooking->timezone),
                'updated_by' => $actor?->id,
            ];

            foreach (['customer_name', 'customer_email', 'customer_phone', 'customer_notes', 'internal_notes'] as $field) {
                if (! array_key_exists($field, $payload)) {
                    continue;
                }

                $updates[$field] = $this->nullableString($payload, $field);
            }

            if (array_key_exists('meta_json', $payload) && is_array($payload['meta_json'])) {
                $existingMeta = is_array($lockedBooking->meta_json) ? $lockedBooking->meta_json : [];
                $updates['meta_json'] = array_replace($existingMeta, $payload['meta_json']);
            }

            $lockedBooking->update($updates);

            if ($staffResource) {
                BookingAssignment::query()
                    ->where('site_id', $site->id)
                    ->where('booking_id', $lockedBooking->id)
                    ->delete();

                BookingAssignment::query()->create([
                    'site_id' => $site->id,
                    'booking_id' => $lockedBooking->id,
                    'staff_resource_id' => $staffResource->id,
                    'assignment_type' => 'primary',
                    'status' => 'assigned',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'created_by' => $actor?->id,
                    'meta_json' => [
                        'rescheduled' => true,
                    ],
                ]);
            }

            $this->recordEvent($site, $lockedBooking, 'rescheduled', [
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
                'starts_at' => $startsAt->toISOString(),
                'ends_at' => $endsAt->toISOString(),
            ], $actor);

            $this->log($site, $actor, 'booking_rescheduled', OperationLog::STATUS_INFO, 'Booking rescheduled.', [
                'booking_id' => $lockedBooking->id,
                'booking_number' => $lockedBooking->booking_number,
                'service_id' => $service->id,
                'staff_resource_id' => $staffResource?->id,
            ]);

            return $lockedBooking->fresh([
                'service',
                'staffResource',
                'assignments',
                'events',
            ]);
        }, 3);
    }

    public function assertNoCollision(
        Site $site,
        BookingService $service,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?BookingStaffResource $staffResource = null,
        ?int $ignoreBookingId = null
    ): void {
        $this->lockService($site, (int) $service->id);
        if ($staffResource) {
            $this->lockStaffResource($site, (int) $staffResource->id);
        }

        $serviceScope = $this->overlapQuery(
            site: $site,
            service: $service,
            startsAt: $startsAt,
            endsAt: $endsAt,
            ignoreBookingId: $ignoreBookingId
        );

        $serviceOverlapCount = (clone $serviceScope)->lockForUpdate()->count();
        $serviceLimit = max(1, (int) $service->max_parallel_bookings);
        if ($serviceOverlapCount >= $serviceLimit) {
            throw new BookingDomainException(
                'Selected slot is fully booked for this service.',
                422,
                [
                    'reason' => 'slot_collision',
                    'scope' => 'service',
                    'service_id' => $service->id,
                    'overlap_count' => $serviceOverlapCount,
                    'parallel_limit' => $serviceLimit,
                    'starts_at' => CarbonImmutable::instance($startsAt)->toISOString(),
                    'ends_at' => CarbonImmutable::instance($endsAt)->toISOString(),
                ]
            );
        }

        if (! $staffResource) {
            return;
        }

        $staffScope = Booking::query()
            ->where('site_id', $site->id)
            ->where('staff_resource_id', $staffResource->id)
            ->activeForCollision()
            ->where('collision_starts_at', '<', CarbonImmutable::instance($endsAt))
            ->where('collision_ends_at', '>', CarbonImmutable::instance($startsAt));

        if ($ignoreBookingId !== null) {
            $staffScope->whereKeyNot($ignoreBookingId);
        }

        $staffOverlapCount = (clone $staffScope)->lockForUpdate()->count();
        $staffLimit = max(1, (int) $staffResource->max_parallel_bookings);

        if ($staffOverlapCount >= $staffLimit) {
            throw new BookingDomainException(
                'Selected slot is unavailable for the assigned staff/resource.',
                422,
                [
                    'reason' => 'slot_collision',
                    'scope' => 'staff_resource',
                    'service_id' => $service->id,
                    'staff_resource_id' => $staffResource->id,
                    'overlap_count' => $staffOverlapCount,
                    'parallel_limit' => $staffLimit,
                    'starts_at' => CarbonImmutable::instance($startsAt)->toISOString(),
                    'ends_at' => CarbonImmutable::instance($endsAt)->toISOString(),
                ]
            );
        }
    }

    private function overlapQuery(
        Site $site,
        BookingService $service,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreBookingId = null
    ): Builder {
        $query = Booking::query()
            ->where('site_id', $site->id)
            ->where('service_id', $service->id)
            ->activeForCollision()
            ->where('collision_starts_at', '<', CarbonImmutable::instance($endsAt))
            ->where('collision_ends_at', '>', CarbonImmutable::instance($startsAt));

        if ($ignoreBookingId !== null) {
            $query->whereKeyNot($ignoreBookingId);
        }

        return $query;
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable,2:int}
     */
    private function resolveDurationWindow(array $payload, BookingService $service, ?Booking $currentBooking = null): array
    {
        $startsAt = $this->parseDateTime(
            $payload['starts_at'] ?? $currentBooking?->starts_at,
            'starts_at'
        );

        if (array_key_exists('ends_at', $payload) && $payload['ends_at']) {
            $endsAt = $this->parseDateTime($payload['ends_at'], 'ends_at');
        } elseif ($currentBooking?->ends_at && ! array_key_exists('duration_minutes', $payload)) {
            $endsAt = CarbonImmutable::instance($currentBooking->ends_at);
        } else {
            $durationMinutes = max(1, (int) ($payload['duration_minutes'] ?? $currentBooking?->duration_minutes ?? $service->duration_minutes));
            $endsAt = $startsAt->addMinutes($durationMinutes);
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new BookingDomainException('Booking end time must be later than start time.', 422);
        }

        $durationMinutes = max(1, $startsAt->diffInMinutes($endsAt));

        return [$startsAt, $endsAt, $durationMinutes];
    }

    private function lockService(Site $site, int $serviceId): BookingService
    {
        $service = BookingService::query()
            ->where('site_id', $site->id)
            ->whereKey($serviceId)
            ->lockForUpdate()
            ->first();

        if (! $service) {
            throw new BookingDomainException('Booking service not found in this site scope.', 404);
        }

        if ($service->status !== BookingService::STATUS_ACTIVE) {
            throw new BookingDomainException('Booking service is inactive.', 422);
        }

        return $service;
    }

    private function assertMonthlyBookingLimitNotExceeded(Site $site): void
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return;
        }

        $limit = $plan->getMaxMonthlyBookings();
        if ($limit === null) {
            return;
        }

        $ownerId = (int) ($owner?->id ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $currentUsage = $this->usageMetering->countMonthlyBookingsForOwner($ownerId);

        if ($currentUsage >= $limit) {
            throw new BookingDomainException(
                'Monthly booking limit reached for your current plan.',
                422,
                [
                    'reason' => 'monthly_bookings_limit_reached',
                    'limit' => $limit,
                    'current_usage' => $currentUsage,
                    'period' => $this->usageMetering->currentPeriodLabel(),
                ]
            );
        }
    }

    private function lockStaffResource(Site $site, ?int $staffResourceId): ?BookingStaffResource
    {
        if ($staffResourceId === null) {
            return null;
        }

        $staffResource = BookingStaffResource::query()
            ->where('site_id', $site->id)
            ->whereKey($staffResourceId)
            ->lockForUpdate()
            ->first();

        if (! $staffResource) {
            throw new BookingDomainException('Staff/resource not found in this site scope.', 404);
        }

        return $staffResource;
    }

    private function requiredInt(array $payload, string $key): int
    {
        if (! array_key_exists($key, $payload)) {
            throw new BookingDomainException(sprintf('Field [%s] is required.', $key), 422);
        }

        $value = $payload[$key];
        if (! is_numeric($value)) {
            throw new BookingDomainException(sprintf('Field [%s] must be numeric.', $key), 422);
        }

        return max(1, (int) $value);
    }

    private function nullableInt(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
            return null;
        }

        if (! is_numeric($payload[$key])) {
            throw new BookingDomainException(sprintf('Field [%s] must be numeric.', $key), 422);
        }

        return (int) $payload[$key];
    }

    private function parseDateTime(mixed $value, string $field): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            throw new BookingDomainException(sprintf('Field [%s] must be a valid datetime string.', $field), 422);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            throw new BookingDomainException(sprintf('Field [%s] must be a valid datetime string.', $field), 422);
        }
    }

    private function nullableString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        return trim((string) $payload[$key]) !== ''
            ? trim((string) $payload[$key])
            : null;
    }

    private function toMoney(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new BookingDomainException('Monetary fields must be numeric.', 422);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function generateBookingNumber(Site $site): string
    {
        $prefix = 'BKG-'.now()->format('Ymd');

        do {
            $candidate = $prefix.'-'.Str::upper(Str::random(6));
            $exists = Booking::query()
                ->where('site_id', $site->id)
                ->where('booking_number', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }

    private function normalizeStatus(mixed $status): string
    {
        if (! is_string($status) || trim($status) === '') {
            return Booking::STATUS_PENDING;
        }

        $normalized = strtolower(trim($status));
        $allowed = [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_IN_PROGRESS,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_NO_SHOW,
        ];

        return in_array($normalized, $allowed, true)
            ? $normalized
            : Booking::STATUS_PENDING;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(Site $site, Booking $booking, string $type, array $payload, ?User $actor = null): void
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
     * @param  array<string, mixed>  $context
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
}
