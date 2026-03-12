<?php

namespace App\Booking\Contracts;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonInterface;

interface BookingCollisionServiceContract
{
    /**
     * Create a booking in site scope with collision validation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createBooking(Site $site, array $payload, ?User $actor = null): Booking;

    /**
     * Reschedule an existing booking with collision validation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function rescheduleBooking(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking;

    /**
     * Assert target slot is free for selected service/staff scope.
     */
    public function assertNoCollision(
        Site $site,
        BookingService $service,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?BookingStaffResource $staffResource = null,
        ?int $ignoreBookingId = null
    ): void;
}
