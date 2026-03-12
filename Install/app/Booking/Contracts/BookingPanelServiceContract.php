<?php

namespace App\Booking\Contracts;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffTimeOff;
use App\Models\Site;
use App\Models\User;

interface BookingPanelServiceContract
{
    /**
     * @return array{site_id:string,services:array<int,array<string,mixed>>}
     */
    public function listServices(Site $site): array;

    public function createService(Site $site, array $payload): BookingService;

    public function updateService(Site $site, BookingService $service, array $payload): BookingService;

    public function deleteService(Site $site, BookingService $service): void;

    /**
     * @return array{site_id:string,staff:array<int,array<string,mixed>>}
     */
    public function listStaff(Site $site): array;

    public function createStaff(Site $site, array $payload): BookingStaffResource;

    public function updateStaff(Site $site, BookingStaffResource $staffResource, array $payload): BookingStaffResource;

    public function deleteStaff(Site $site, BookingStaffResource $staffResource): void;

    /**
     * @return array{
     *   site_id:string,
     *   staff_resource:array<string,mixed>,
     *   schedules:array<int,array<string,mixed>>
     * }
     */
    public function listStaffSchedules(Site $site, BookingStaffResource $staffResource): array;

    /**
     * @param  array<int,array<string,mixed>>  $schedules
     * @return array{
     *   site_id:string,
     *   staff_resource:array<string,mixed>,
     *   schedules:array<int,array<string,mixed>>
     * }
     */
    public function syncStaffSchedules(
        Site $site,
        BookingStaffResource $staffResource,
        array $schedules,
        ?User $actor = null
    ): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *   site_id:string,
     *   staff_resource:array<string,mixed>,
     *   time_off:array<int,array<string,mixed>>
     * }
     */
    public function listStaffTimeOff(Site $site, BookingStaffResource $staffResource, array $filters = []): array;

    public function createStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        array $payload,
        ?User $actor = null
    ): BookingStaffTimeOff;

    public function updateStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff,
        array $payload,
        ?User $actor = null
    ): BookingStaffTimeOff;

    public function deleteStaffTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff,
        ?User $actor = null
    ): void;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{site_id:string,bookings:array<int,array<string,mixed>>,inbox_counts:array<string,int>}
     */
    public function listBookings(Site $site, array $filters = []): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *   site_id:string,
     *   from:string,
     *   to:string,
     *   events:array<int,array<string,mixed>>,
     *   staff_schedule_blocks:array<int,array<string,mixed>>,
     *   time_off_blocks:array<int,array<string,mixed>>
     * }
     */
    public function calendar(Site $site, array $filters = []): array;

    /**
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function showBooking(Site $site, Booking $booking): array;

    public function createBooking(Site $site, array $payload, ?User $actor = null): Booking;

    public function updateBookingStatus(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking;

    public function rescheduleBooking(Site $site, Booking $booking, array $payload, ?User $actor = null): Booking;

    public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $actor = null): Booking;
}
