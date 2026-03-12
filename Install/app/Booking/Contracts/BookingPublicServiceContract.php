<?php

namespace App\Booking\Contracts;

use App\Models\Site;
use App\Models\User;
use App\Models\Booking;

interface BookingPublicServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array{site_id:string,services:array<int,array<string,mixed>>}
     */
    public function listServices(Site $site, array $filters = [], ?User $viewer = null): array;

    /**
     * @return array{site_id:string,service:array<string,mixed>}
     */
    public function getService(Site $site, string $slug, ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{site_id:string,staff:array<int,array<string,mixed>>}
     */
    public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{site_id:string,date:string,timezone:string,service:array<string,mixed>,slots:array<int,array<string,mixed>>}
     */
    public function slots(Site $site, array $filters = [], ?User $viewer = null): array;

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
    public function calendar(Site $site, array $filters = [], ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function createBooking(Site $site, array $payload = [], ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{site_id:string,bookings:array<int,array<string,mixed>>}
     */
    public function listBookings(Site $site, array $filters = [], ?User $viewer = null): array;

    /**
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function showBooking(Site $site, Booking $booking, ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function updateBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{site_id:string,booking:array<string,mixed>}
     */
    public function rescheduleBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;
}
