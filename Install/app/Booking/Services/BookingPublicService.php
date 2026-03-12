<?php

namespace App\Booking\Services;

use App\Booking\Contracts\BookingCollisionServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Contracts\BookingPublicServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffTimeOff;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;

class BookingPublicService implements BookingPublicServiceContract
{
    public function __construct(
        protected BookingCollisionServiceContract $collisions,
        protected CmsModuleRegistryServiceContract $moduleRegistry,
        protected BookingNotificationService $notifications,
        protected BookingPanelServiceContract $panel
    ) {}

    public function listServices(Site $site, array $filters = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $prepaymentEnabled = $this->isPrepaymentEnabledForSite($site);

        $search = trim((string) ($filters['search'] ?? ''));

        $query = BookingService::query()
            ->where('site_id', $site->id)
            ->where('status', BookingService::STATUS_ACTIVE)
            ->orderBy('name');

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(name) like ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(slug) like ?', ['%'.$needle.'%'])
                    ->orWhereRaw("LOWER(COALESCE(description, '')) like ?", ['%'.$needle.'%']);
            });
        }

        $services = $query->get()
            ->map(fn (BookingService $service): array => $this->serializeService($service, $prepaymentEnabled))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'services' => $services,
        ];
    }

    public function getService(Site $site, string $slug, ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $prepaymentEnabled = $this->isPrepaymentEnabledForSite($site);

        $service = BookingService::query()
            ->where('site_id', $site->id)
            ->where('status', BookingService::STATUS_ACTIVE)
            ->where('slug', $slug)
            ->first();

        if (! $service) {
            throw new BookingDomainException('Booking service not found.', 404);
        }

        return [
            'site_id' => $site->id,
            'service' => $this->serializeService($service, $prepaymentEnabled),
        ];
    }

    public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);

        $payload = $this->panel->listStaff($site);
        $staff = array_values(array_filter(
            (array) ($payload['staff'] ?? []),
            function ($row) use ($filters): bool {
                if (! is_array($row)) {
                    return false;
                }

                $search = trim((string) ($filters['search'] ?? ''));
                if ($search === '') {
                    return true;
                }

                $needle = mb_strtolower($search);
                $haystacks = [
                    mb_strtolower((string) ($row['name'] ?? '')),
                    mb_strtolower((string) ($row['slug'] ?? '')),
                    mb_strtolower((string) ($row['email'] ?? '')),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        return [
            'site_id' => $site->id,
            'staff' => $staff,
        ];
    }

    public function slots(Site $site, array $filters = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $prepaymentEnabled = $this->isPrepaymentEnabledForSite($site);

        $serviceId = (int) ($filters['service_id'] ?? 0);
        if ($serviceId <= 0) {
            throw new BookingDomainException('service_id is required.', 422);
        }

        $service = BookingService::query()
            ->where('site_id', $site->id)
            ->whereKey($serviceId)
            ->where('status', BookingService::STATUS_ACTIVE)
            ->first();

        if (! $service) {
            throw new BookingDomainException('Booking service not found.', 404);
        }

        $date = trim((string) ($filters['date'] ?? ''));
        if ($date === '') {
            throw new BookingDomainException('date is required.', 422);
        }

        try {
            $targetDay = CarbonImmutable::parse($date)->startOfDay();
        } catch (\Throwable) {
            throw new BookingDomainException('date must be a valid date.', 422);
        }

        $timezone = trim((string) ($filters['timezone'] ?? '')) ?: (string) config('app.timezone', 'UTC');
        $requestedStaffId = isset($filters['staff_resource_id']) ? (int) $filters['staff_resource_id'] : null;

        $duration = max(1, (int) ($filters['duration_minutes'] ?? $service->duration_minutes ?? 60));
        $bufferBefore = max(0, (int) ($service->buffer_before_minutes ?? 0));
        $bufferAfter = max(0, (int) ($service->buffer_after_minutes ?? 0));
        $slotStep = max(1, min(240, (int) ($service->slot_step_minutes ?? 30)));

        $resourceWindows = $this->resolveResourceWindows($site, $service, $targetDay, $requestedStaffId);
        $fallbackWindow = [
            'resource' => null,
            'start' => $targetDay->setTime(9, 0),
            'end' => $targetDay->setTime(18, 0),
        ];

        if (! $service->requires_staff && $resourceWindows === []) {
            $resourceWindows = [$fallbackWindow];
        }

        if ($resourceWindows === []) {
            return [
                'site_id' => $site->id,
                'date' => $targetDay->toDateString(),
                'timezone' => $timezone,
                'service' => $this->serializeService($service, $prepaymentEnabled),
                'slots' => [],
            ];
        }

        $windowStart = collect($resourceWindows)
            ->map(fn (array $window) => CarbonImmutable::instance($window['start']))
            ->sort()
            ->first();
        $windowEnd = collect($resourceWindows)
            ->map(fn (array $window) => CarbonImmutable::instance($window['end']))
            ->sortDesc()
            ->first();

        if (! $windowStart || ! $windowEnd) {
            return [
                'site_id' => $site->id,
                'date' => $targetDay->toDateString(),
                'timezone' => $timezone,
                'service' => $this->serializeService($service, $prepaymentEnabled),
                'slots' => [],
            ];
        }

        $overlapStart = $windowStart->subMinutes($bufferBefore + 1);
        $overlapEnd = $windowEnd->addMinutes($bufferAfter + $duration + 1);

        $activeBookings = Booking::query()
            ->where('site_id', $site->id)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('collision_starts_at', '<', $overlapEnd)
            ->where('collision_ends_at', '>', $overlapStart)
            ->get(['id', 'service_id', 'staff_resource_id', 'collision_starts_at', 'collision_ends_at']);

        $resourceIds = collect($resourceWindows)
            ->map(fn (array $window) => $window['resource']?->id)
            ->filter()
            ->values()
            ->all();

        $timeOff = BookingStaffTimeOff::query()
            ->where('site_id', $site->id)
            ->when($resourceIds !== [], fn ($query) => $query->whereIn('staff_resource_id', $resourceIds))
            ->where('status', 'approved')
            ->where('starts_at', '<', $overlapEnd)
            ->where('ends_at', '>', $overlapStart)
            ->get(['staff_resource_id', 'starts_at', 'ends_at']);

        $slots = [];

        foreach ($resourceWindows as $window) {
            $resource = $window['resource'];
            $cursor = CarbonImmutable::instance($window['start']);
            $windowEndAt = CarbonImmutable::instance($window['end']);
            $latestStart = $windowEndAt->subMinutes($duration);

            while ($cursor->lessThanOrEqualTo($latestStart)) {
                $startsAt = $cursor;
                $endsAt = $startsAt->addMinutes($duration);
                $collisionStartsAt = $startsAt->subMinutes($bufferBefore);
                $collisionEndsAt = $endsAt->addMinutes($bufferAfter);

                $serviceOverlapCount = $activeBookings
                    ->filter(function (Booking $booking) use ($service, $collisionStartsAt, $collisionEndsAt): bool {
                        if ((int) $booking->service_id !== (int) $service->id) {
                            return false;
                        }

                        return $this->intervalsOverlap(
                            CarbonImmutable::instance($booking->collision_starts_at),
                            CarbonImmutable::instance($booking->collision_ends_at),
                            $collisionStartsAt,
                            $collisionEndsAt,
                        );
                    })
                    ->count();

                $serviceLimit = max(1, (int) $service->max_parallel_bookings);
                $serviceAvailable = $serviceOverlapCount < $serviceLimit;

                $staffAvailable = true;
                if ($resource) {
                    $resourceTimeOff = $timeOff
                        ->where('staff_resource_id', $resource->id)
                        ->contains(function (BookingStaffTimeOff $entry) use ($collisionStartsAt, $collisionEndsAt): bool {
                            return $this->intervalsOverlap(
                                CarbonImmutable::instance($entry->starts_at),
                                CarbonImmutable::instance($entry->ends_at),
                                $collisionStartsAt,
                                $collisionEndsAt,
                            );
                        });

                    if ($resourceTimeOff) {
                        $staffAvailable = false;
                    }

                    if ($staffAvailable) {
                        $staffOverlapCount = $activeBookings
                            ->filter(function (Booking $booking) use ($resource, $collisionStartsAt, $collisionEndsAt): bool {
                                if ((int) ($booking->staff_resource_id ?? 0) !== (int) $resource->id) {
                                    return false;
                                }

                                return $this->intervalsOverlap(
                                    CarbonImmutable::instance($booking->collision_starts_at),
                                    CarbonImmutable::instance($booking->collision_ends_at),
                                    $collisionStartsAt,
                                    $collisionEndsAt,
                                );
                            })
                            ->count();

                        $staffLimit = max(1, (int) $resource->max_parallel_bookings);
                        $staffAvailable = $staffOverlapCount < $staffLimit;
                    }
                }

                if ($serviceAvailable && $staffAvailable) {
                    $slots[] = [
                        'starts_at' => $startsAt->toISOString(),
                        'ends_at' => $endsAt->toISOString(),
                        'timezone' => $timezone,
                        'duration_minutes' => $duration,
                        'staff_resource' => $resource ? [
                            'id' => $resource->id,
                            'name' => $resource->name,
                            'type' => $resource->type,
                        ] : null,
                    ];
                }

                $cursor = $cursor->addMinutes($slotStep);
            }
        }

        $uniqueSlots = collect($slots)
            ->unique(fn (array $slot): string => (string) ($slot['starts_at'] ?? '').':'.(string) data_get($slot, 'staff_resource.id', 0))
            ->sortBy(fn (array $slot): string => (string) ($slot['starts_at'] ?? ''))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'date' => $targetDay->toDateString(),
            'timezone' => $timezone,
            'service' => $this->serializeService($service, $prepaymentEnabled),
            'slots' => $uniqueSlots,
        ];
    }

    public function calendar(Site $site, array $filters = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);

        $normalized = [];
        foreach (['from', 'to'] as $key) {
            if (is_string($filters[$key] ?? null) && trim((string) $filters[$key]) !== '') {
                $normalized[$key] = trim((string) $filters[$key]);
            }
        }

        $payload = $this->panel->calendar($site, $normalized);

        $staffId = isset($filters['staff_resource_id']) ? (int) $filters['staff_resource_id'] : null;
        if ($staffId && $staffId > 0) {
            $payload['events'] = array_values(array_filter((array) ($payload['events'] ?? []), function ($row) use ($staffId): bool {
                return (int) data_get($row, 'staff_resource.id', 0) === $staffId;
            }));
            $payload['staff_schedule_blocks'] = array_values(array_filter((array) ($payload['staff_schedule_blocks'] ?? []), function ($row) use ($staffId): bool {
                return (int) data_get($row, 'staff_resource.id', 0) === $staffId;
            }));
            $payload['time_off_blocks'] = array_values(array_filter((array) ($payload['time_off_blocks'] ?? []), function ($row) use ($staffId): bool {
                return (int) data_get($row, 'staff_resource.id', 0) === $staffId;
            }));
        }

        return $payload;
    }

    public function createBooking(Site $site, array $payload = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);

        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId <= 0) {
            throw new BookingDomainException('service_id is required.', 422);
        }

        $service = BookingService::query()
            ->where('site_id', $site->id)
            ->whereKey($serviceId)
            ->where('status', BookingService::STATUS_ACTIVE)
            ->first();

        if (! $service) {
            throw new BookingDomainException('Booking service not found.', 404);
        }

        $startsAt = trim((string) ($payload['starts_at'] ?? ''));
        if ($startsAt === '') {
            throw new BookingDomainException('starts_at is required.', 422);
        }

        $customerEmail = $this->nullableString($payload['customer_email'] ?? null);
        $customerPhone = $this->nullableString($payload['customer_phone'] ?? null);
        if (! $customerEmail && ! $customerPhone) {
            throw new BookingDomainException('customer_email or customer_phone is required.', 422);
        }

        $prepaymentEnabled = $this->isPrepaymentEnabledForSite($site);
        $prepaymentAmount = $this->nullableMoney($payload['prepayment_amount'] ?? null);
        $prepaymentRequested = $prepaymentAmount !== null && $prepaymentAmount > 0;
        $serviceFee = $this->moneyString($service->price);
        $serviceFeeAmount = $this->decimalValue($serviceFee);
        $currency = strtoupper((string) ($service->currency ?: 'GEL'));
        $prepaymentCurrency = strtoupper((string) ($payload['prepayment_currency'] ?? $currency));

        if ($prepaymentRequested && ! $service->allow_online_payment) {
            throw new BookingDomainException('This service does not allow online prepayment.', 422, [
                'reason' => 'prepayment_unavailable',
                'service_id' => $service->id,
            ]);
        }

        if ($prepaymentRequested && ! $prepaymentEnabled) {
            throw new BookingDomainException('Booking prepayment is not enabled for this plan.', 422, [
                'reason' => 'prepayment_not_enabled',
            ]);
        }

        if ($prepaymentRequested && $prepaymentCurrency !== $currency) {
            throw new BookingDomainException('Prepayment currency must match service currency.', 422, [
                'reason' => 'invalid_prepayment_currency',
                'expected_currency' => $currency,
                'received_currency' => $prepaymentCurrency,
            ]);
        }

        if ($prepaymentRequested && $prepaymentAmount > $serviceFeeAmount) {
            throw new BookingDomainException('Prepayment amount cannot exceed service fee.', 422, [
                'reason' => 'invalid_prepayment_amount',
                'service_fee' => $serviceFee,
                'prepayment_amount' => $this->moneyString($prepaymentAmount),
            ]);
        }

        $paidTotal = $prepaymentRequested ? $this->moneyString($prepaymentAmount) : $this->moneyString(0);
        $outstandingTotal = $this->moneyString(max(0, $serviceFeeAmount - $this->decimalValue($paidTotal)));

        $meta = is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [];
        $meta['prepayment'] = [
            'enabled' => $prepaymentEnabled,
            'requested' => $prepaymentRequested,
            'available' => (bool) $service->allow_online_payment,
            'amount' => $paidTotal,
            'currency' => $currency,
            'status' => $prepaymentRequested ? 'paid' : 'not_requested',
        ];

        $booking = $this->collisions->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => isset($payload['staff_resource_id']) ? (int) $payload['staff_resource_id'] : null,
            'starts_at' => $startsAt,
            'ends_at' => $this->nullableString($payload['ends_at'] ?? null),
            'duration_minutes' => isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : null,
            'timezone' => $this->nullableString($payload['timezone'] ?? null) ?? (string) config('app.timezone', 'UTC'),
            'status' => 'pending',
            'source' => 'public_widget',
            'customer_name' => $this->nullableString($payload['customer_name'] ?? null),
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_notes' => $this->nullableString($payload['customer_notes'] ?? null),
            'service_fee' => $serviceFee,
            'grand_total' => $serviceFee,
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstandingTotal,
            'currency' => $currency,
            'meta_json' => $meta,
        ], $viewer);

        $this->notifications->sendConfirmation($booking);

        return [
            'site_id' => $site->id,
            'booking' => $this->serializePublicBooking($booking),
        ];
    }

    public function listBookings(Site $site, array $filters = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $this->assertViewerCanManageCustomerBookings($site, $viewer);

        $query = Booking::query()
            ->where('site_id', $site->id)
            ->with(['service', 'staffResource'])
            ->orderByDesc('starts_at');

        if (is_string($filters['status'] ?? null) && trim((string) $filters['status']) !== '') {
            $query->where('status', trim((string) $filters['status']));
        }

        $this->applyViewerBookingScope($query, $site, $viewer);

        $limit = max(1, min((int) ($filters['limit'] ?? 50), 200));

        $bookings = $query->limit($limit)
            ->get()
            ->map(fn (Booking $booking): array => $this->serializePublicBooking($booking))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'bookings' => $bookings,
        ];
    }

    public function showBooking(Site $site, Booking $booking, ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $target = $this->resolveViewerBooking($site, $booking, $viewer);

        return [
            'site_id' => $site->id,
            'booking' => $this->serializePublicBooking($target),
        ];
    }

    public function updateBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array
    {
        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $status = strtolower(trim((string) ($payload['status'] ?? '')));

        if ($action === 'cancel' || $status === Booking::STATUS_CANCELLED) {
            return $this->cancelBooking($site, $booking, $payload, $viewer);
        }

        if ($action === 'reschedule' || array_key_exists('starts_at', $payload) || array_key_exists('ends_at', $payload)) {
            return $this->rescheduleBooking($site, $booking, $payload, $viewer);
        }

        throw new BookingDomainException('Supported public booking updates are cancel or reschedule only.', 422, [
            'allowed_actions' => ['cancel', 'reschedule'],
        ]);
    }

    public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $target = $this->resolveViewerBooking($site, $booking, $viewer);
        $this->assertViewerCanManageCustomerBookings($site, $viewer);

        $updated = $this->panel->cancelBooking($site, $target, $payload, $viewer);

        return [
            'site_id' => $site->id,
            'booking' => $this->serializePublicBooking($updated),
        ];
    }

    public function rescheduleBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array
    {
        $this->assertStorefrontAccessible($site, $viewer);
        $target = $this->resolveViewerBooking($site, $booking, $viewer);
        $this->assertViewerCanManageCustomerBookings($site, $viewer);

        if (isset($payload['staff_id']) && ! isset($payload['staff_resource_id'])) {
            $payload['staff_resource_id'] = $payload['staff_id'];
        }

        $updated = $this->panel->rescheduleBooking($site, $target, $payload, $viewer);

        return [
            'site_id' => $site->id,
            'booking' => $this->serializePublicBooking($updated),
        ];
    }

    /**
     * @return array<int,array{resource:BookingStaffResource|null,start:CarbonImmutable,end:CarbonImmutable}>
     */
    private function resolveResourceWindows(
        Site $site,
        BookingService $service,
        CarbonImmutable $day,
        ?int $requestedStaffId
    ): array {
        if (! $service->requires_staff) {
            return [];
        }

        $staffQuery = BookingStaffResource::query()
            ->where('site_id', $site->id)
            ->where('status', BookingStaffResource::STATUS_ACTIVE)
            ->where('type', BookingStaffResource::TYPE_STAFF)
            ->orderBy('name');

        if ($requestedStaffId !== null && $requestedStaffId > 0) {
            $staffQuery->whereKey($requestedStaffId);
        }

        $resources = $staffQuery->get();
        if ($resources->isEmpty()) {
            return [];
        }

        $dayOfWeek = (int) $day->dayOfWeek;
        $defaultStart = $day->setTime(9, 0);
        $defaultEnd = $day->setTime(18, 0);

        $windows = [];

        foreach ($resources as $resource) {
            $schedules = $resource->workSchedules()
                ->where('site_id', $site->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->where(function ($query) use ($day): void {
                    $date = $day->toDateString();
                    $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
                })
                ->where(function ($query) use ($day): void {
                    $date = $day->toDateString();
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
                })
                ->orderBy('start_time')
                ->get();

            if ($schedules->isEmpty()) {
                $windows[] = [
                    'resource' => $resource,
                    'start' => $defaultStart,
                    'end' => $defaultEnd,
                ];

                continue;
            }

            foreach ($schedules as $schedule) {
                $start = CarbonImmutable::parse($day->toDateString().' '.$schedule->start_time);
                $end = CarbonImmutable::parse($day->toDateString().' '.$schedule->end_time);

                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }

                $windows[] = [
                    'resource' => $resource,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return $windows;
    }

    private function assertStorefrontAccessible(Site $site, ?User $viewer): void
    {
        if ($site->status === 'archived') {
            throw new BookingDomainException('Booking storefront not found.', 404);
        }

        $site->loadMissing(['project.user', 'project.template']);
        $project = $site->project;
        if (! $project || ! $project->published_at) {
            throw new BookingDomainException('Booking storefront not found.', 404);
        }

        if ($project->published_visibility === 'private') {
            $isOwner = $viewer && (string) $viewer->id === (string) $project->user_id;
            if (! $isOwner) {
                throw new BookingDomainException('Booking storefront not found.', 404);
            }
        }

        $modulesPayload = $this->moduleRegistry->modules($site, $project->user);
        $isEnabled = false;
        foreach ($modulesPayload['modules'] ?? [] as $module) {
            if (($module['key'] ?? null) === 'booking') {
                $isEnabled = (bool) ($module['available'] ?? false);
                break;
            }
        }

        if (! $isEnabled) {
            throw new BookingDomainException('Booking module is not enabled for this site.', 404);
        }
    }

    private function assertViewerCanManageCustomerBookings(Site $site, ?User $viewer): void
    {
        if (! $viewer) {
            throw new BookingDomainException('Authentication is required.', 401);
        }

        $site->loadMissing('project');
        $isSiteOwner = (string) ($site->project?->user_id ?? '') !== ''
            && (string) $viewer->id === (string) $site->project?->user_id;

        $email = mb_strtolower(trim((string) ($viewer->email ?? '')));

        if (! $isSiteOwner && $email === '') {
            throw new BookingDomainException('Customer booking access is unavailable for this account.', 403);
        }
    }

    private function resolveViewerBooking(Site $site, Booking $booking, ?User $viewer): Booking
    {
        $this->assertViewerCanManageCustomerBookings($site, $viewer);

        $target = Booking::query()
            ->where('site_id', $site->id)
            ->whereKey($booking->id)
            ->with(['service', 'staffResource'])
            ->first();

        if (! $target) {
            throw new BookingDomainException('Booking not found.', 404);
        }

        $site->loadMissing('project');
        $isSiteOwner = (string) ($site->project?->user_id ?? '') !== ''
            && (string) $viewer?->id === (string) $site->project?->user_id;

        if ($isSiteOwner) {
            return $target;
        }

        $viewerEmail = mb_strtolower(trim((string) ($viewer?->email ?? '')));
        $bookingEmail = mb_strtolower(trim((string) ($target->customer_email ?? '')));
        $createdByViewer = $viewer && $target->created_by !== null && (int) $target->created_by === (int) $viewer->id;

        if ($createdByViewer || ($viewerEmail !== '' && $bookingEmail !== '' && $viewerEmail === $bookingEmail)) {
            return $target;
        }

        throw new BookingDomainException('Booking not found.', 404);
    }

    private function applyViewerBookingScope($query, Site $site, ?User $viewer): void
    {
        $site->loadMissing('project');
        $isSiteOwner = $viewer
            && (string) ($site->project?->user_id ?? '') !== ''
            && (string) $viewer->id === (string) $site->project?->user_id;

        if ($isSiteOwner) {
            return;
        }

        $viewerEmail = mb_strtolower(trim((string) ($viewer?->email ?? '')));
        $query->where(function ($scope) use ($viewer, $viewerEmail): void {
            if ($viewerEmail !== '') {
                $scope->whereRaw("LOWER(COALESCE(customer_email, '')) = ?", [$viewerEmail]);
            }

            if ($viewer) {
                if ($viewerEmail !== '') {
                    $scope->orWhere('created_by', $viewer->id);
                } else {
                    $scope->where('created_by', $viewer->id);
                }
            }
        });
    }

    private function intervalsOverlap(
        CarbonImmutable $firstStart,
        CarbonImmutable $firstEnd,
        CarbonImmutable $secondStart,
        CarbonImmutable $secondEnd
    ): bool {
        return $firstStart->lessThan($secondEnd) && $firstEnd->greaterThan($secondStart);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeService(BookingService $service, bool $prepaymentEnabled): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'duration_minutes' => (int) $service->duration_minutes,
            'buffer_before_minutes' => (int) $service->buffer_before_minutes,
            'buffer_after_minutes' => (int) $service->buffer_after_minutes,
            'slot_step_minutes' => $service->slot_step_minutes !== null ? (int) $service->slot_step_minutes : null,
            'requires_staff' => (bool) $service->requires_staff,
            'allow_online_payment' => (bool) $service->allow_online_payment,
            'prepayment_enabled' => $prepaymentEnabled,
            'prepayment_available' => $prepaymentEnabled && (bool) $service->allow_online_payment,
            'price' => (string) $service->price,
            'currency' => $service->currency,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializePublicBooking(Booking $booking): array
    {
        $booking->loadMissing(['service', 'staffResource']);

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'source' => $booking->source,
            'starts_at' => $booking->starts_at?->toISOString(),
            'ends_at' => $booking->ends_at?->toISOString(),
            'duration_minutes' => (int) $booking->duration_minutes,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'service_fee' => (string) $booking->service_fee,
            'grand_total' => (string) $booking->grand_total,
            'paid_total' => (string) $booking->paid_total,
            'outstanding_total' => (string) $booking->outstanding_total,
            'currency' => $booking->currency,
            'prepayment' => [
                'amount' => (string) $booking->paid_total,
                'requested' => ((float) $booking->paid_total) > 0,
                'status' => ((float) $booking->paid_total) > 0 ? 'paid' : 'not_requested',
            ],
            'service' => [
                'id' => $booking->service?->id,
                'name' => $booking->service?->name,
            ],
            'staff_resource' => [
                'id' => $booking->staffResource?->id,
                'name' => $booking->staffResource?->name,
            ],
            'created_at' => $booking->created_at?->toISOString(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function isPrepaymentEnabledForSite(Site $site): bool
    {
        $site->loadMissing('project.user.plan');

        return (bool) $site->project?->user?->canUseBookingPrepayment();
    }

    private function nullableMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (! is_numeric($normalized)) {
            throw new BookingDomainException('prepayment_amount must be numeric.', 422);
        }

        $amount = (float) $normalized;
        if ($amount < 0) {
            throw new BookingDomainException('prepayment_amount must be greater than or equal to zero.', 422);
        }

        return round($amount, 2);
    }

    private function decimalValue(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->decimalValue($value), 2, '.', '');
    }
}
