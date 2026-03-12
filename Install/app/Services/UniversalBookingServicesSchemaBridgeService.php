<?php

namespace App\Services;

use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffTimeOff;
use App\Models\BookingStaffWorkSchedule;
use App\Models\Site;

class UniversalBookingServicesSchemaBridgeService
{
    public const SCHEMA_NAME = 'universal_booking_services_schema_bridge';

    public const SCHEMA_VERSION = 1;

    /**
     * Build a read-only canonical snapshot over current booking services/staff/resource tables.
     *
     * This is the P5-F3-01 baseline and intentionally does not replace existing
     * booking panel/public services. It provides a stable universal contract over
     * current storage + API surface metadata.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function snapshot(Site $site, array $options = []): array
    {
        $includeMeta = (bool) ($options['include_meta'] ?? true);
        $includeApiSurface = ! array_key_exists('include_api_surface', $options) || (bool) $options['include_api_surface'];

        $site = $site->fresh() ?? $site;
        $site->loadMissing([
            'project',
            'bookingServices',
            'bookingStaffResources.workSchedules',
            'bookingStaffResources.timeOff',
        ]);

        $services = $site->bookingServices
            ->sortBy(fn (BookingService $service): string => sprintf('%s|%020d', (string) $service->slug, (int) $service->id))
            ->values()
            ->map(fn (BookingService $service): array => $this->normalizeService($service, $includeMeta))
            ->all();

        $staffResources = $site->bookingStaffResources
            ->sortBy(fn (BookingStaffResource $resource): string => sprintf('%s|%s|%020d', (string) $resource->type, (string) $resource->slug, (int) $resource->id))
            ->values()
            ->map(fn (BookingStaffResource $resource): array => $this->normalizeStaffResource($resource, $includeMeta))
            ->all();

        return [
            'schema' => [
                'name' => self::SCHEMA_NAME,
                'version' => self::SCHEMA_VERSION,
                'task' => 'P5-F3-01',
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
                'services' => ['booking_services'],
                'staff_resources' => ['booking_staff_resources'],
                'work_schedules' => ['booking_staff_work_schedules'],
                'time_off' => ['booking_staff_time_off'],
            ],
            'counts' => [
                'services' => count($services),
                'staff_resources' => count($staffResources),
                'work_schedules' => array_sum(array_map(fn (array $row): int => (int) ($row['schedule_count'] ?? 0), $staffResources)),
                'time_off' => array_sum(array_map(fn (array $row): int => (int) ($row['time_off_count'] ?? 0), $staffResources)),
            ],
            'catalog' => [
                'services' => $services,
                'staff_resources' => $staffResources,
            ],
            'api_surface' => $includeApiSurface ? $this->apiSurface() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeService(BookingService $service, bool $includeMeta): array
    {
        return [
            'kind' => 'booking_service',
            'source' => [
                'table' => 'booking_services',
                'id' => (int) $service->id,
            ],
            'id' => (int) $service->id,
            'site_id' => (string) $service->site_id,
            'name' => (string) $service->name,
            'slug' => (string) $service->slug,
            'status' => (string) $service->status,
            'description' => $service->description,
            'duration_minutes' => (int) $service->duration_minutes,
            'buffer_before_minutes' => (int) $service->buffer_before_minutes,
            'buffer_after_minutes' => (int) $service->buffer_after_minutes,
            'slot_step_minutes' => $service->slot_step_minutes !== null ? (int) $service->slot_step_minutes : null,
            'max_parallel_bookings' => (int) $service->max_parallel_bookings,
            'requires_staff' => (bool) $service->requires_staff,
            'allow_online_payment' => (bool) $service->allow_online_payment,
            'price' => number_format((float) $service->price, 2, '.', ''),
            'currency' => (string) $service->currency,
            'meta_json' => $includeMeta ? ($service->meta_json ?? []) : null,
            'updated_at' => $service->updated_at?->toISOString(),
            'created_at' => $service->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStaffResource(BookingStaffResource $resource, bool $includeMeta): array
    {
        $schedules = ($resource->workSchedules ?? collect())
            ->sortBy(fn (BookingStaffWorkSchedule $schedule): string => sprintf('%d|%s|%020d', (int) $schedule->day_of_week, (string) $schedule->start_time, (int) $schedule->id))
            ->values()
            ->map(fn (BookingStaffWorkSchedule $schedule): array => $this->normalizeWorkSchedule($schedule, $includeMeta))
            ->all();

        $timeOff = ($resource->timeOff ?? collect())
            ->sortByDesc(fn (BookingStaffTimeOff $entry): int => (int) ($entry->starts_at?->getTimestamp() ?? 0))
            ->values()
            ->map(fn (BookingStaffTimeOff $entry): array => $this->normalizeTimeOff($entry, $includeMeta))
            ->all();

        return [
            'kind' => 'booking_staff_resource',
            'source' => [
                'table' => 'booking_staff_resources',
                'id' => (int) $resource->id,
            ],
            'id' => (int) $resource->id,
            'site_id' => (string) $resource->site_id,
            'name' => (string) $resource->name,
            'slug' => (string) $resource->slug,
            'type' => (string) $resource->type,
            'status' => (string) $resource->status,
            'email' => $resource->email,
            'phone' => $resource->phone,
            'timezone' => (string) $resource->timezone,
            'max_parallel_bookings' => (int) $resource->max_parallel_bookings,
            'buffer_minutes' => (int) $resource->buffer_minutes,
            'meta_json' => $includeMeta ? ($resource->meta_json ?? []) : null,
            'schedule_count' => count($schedules),
            'time_off_count' => count($timeOff),
            'schedules' => $schedules,
            'time_off' => $timeOff,
            'updated_at' => $resource->updated_at?->toISOString(),
            'created_at' => $resource->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeWorkSchedule(BookingStaffWorkSchedule $schedule, bool $includeMeta): array
    {
        return [
            'kind' => 'booking_staff_work_schedule',
            'source' => [
                'table' => 'booking_staff_work_schedules',
                'id' => (int) $schedule->id,
            ],
            'id' => (int) $schedule->id,
            'staff_resource_id' => (int) $schedule->staff_resource_id,
            'day_of_week' => (int) $schedule->day_of_week,
            'start_time' => (string) $schedule->start_time,
            'end_time' => (string) $schedule->end_time,
            'is_available' => (bool) $schedule->is_available,
            'timezone' => (string) $schedule->timezone,
            'effective_from' => $schedule->effective_from?->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
            'meta_json' => $includeMeta ? ($schedule->meta_json ?? []) : null,
            'updated_at' => $schedule->updated_at?->toISOString(),
            'created_at' => $schedule->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTimeOff(BookingStaffTimeOff $entry, bool $includeMeta): array
    {
        return [
            'kind' => 'booking_staff_time_off',
            'source' => [
                'table' => 'booking_staff_time_off',
                'id' => (int) $entry->id,
            ],
            'id' => (int) $entry->id,
            'staff_resource_id' => (int) $entry->staff_resource_id,
            'status' => (string) $entry->status,
            'reason' => $entry->reason,
            'starts_at' => $entry->starts_at?->toISOString(),
            'ends_at' => $entry->ends_at?->toISOString(),
            'created_by' => $entry->created_by ? (int) $entry->created_by : null,
            'meta_json' => $includeMeta ? ($entry->meta_json ?? []) : null,
            'updated_at' => $entry->updated_at?->toISOString(),
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiSurface(): array
    {
        return [
            'panel' => [
                'controllers' => [
                    'services' => 'App\\Http\\Controllers\\Booking\\PanelServiceController',
                    'staff' => 'App\\Http\\Controllers\\Booking\\PanelStaffController',
                ],
                'routes' => [
                    ['name' => 'panel.sites.booking.services.index', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/services'],
                    ['name' => 'panel.sites.booking.services.store', 'method' => 'POST', 'path' => '/panel/sites/{site}/booking/services'],
                    ['name' => 'panel.sites.booking.services.update', 'method' => 'PUT', 'path' => '/panel/sites/{site}/booking/services/{service}'],
                    ['name' => 'panel.sites.booking.services.destroy', 'method' => 'DELETE', 'path' => '/panel/sites/{site}/booking/services/{service}'],
                    ['name' => 'panel.sites.booking.staff.index', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/staff'],
                    ['name' => 'panel.sites.booking.staff.store', 'method' => 'POST', 'path' => '/panel/sites/{site}/booking/staff'],
                    ['name' => 'panel.sites.booking.staff.update', 'method' => 'PUT', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}'],
                    ['name' => 'panel.sites.booking.staff.destroy', 'method' => 'DELETE', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}'],
                    ['name' => 'panel.sites.booking.staff.work-schedules.index', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/work-schedules'],
                    ['name' => 'panel.sites.booking.staff.work-schedules.sync', 'method' => 'PUT', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/work-schedules'],
                    ['name' => 'panel.sites.booking.staff.time-off.index', 'method' => 'GET', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/time-off'],
                    ['name' => 'panel.sites.booking.staff.time-off.store', 'method' => 'POST', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/time-off'],
                    ['name' => 'panel.sites.booking.staff.time-off.update', 'method' => 'PUT', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/time-off/{timeOff}'],
                    ['name' => 'panel.sites.booking.staff.time-off.destroy', 'method' => 'DELETE', 'path' => '/panel/sites/{site}/booking/staff/{staffResource}/time-off/{timeOff}'],
                ],
            ],
            'deferred_to_next_tasks' => [
                'P5-F3-02' => ['availability_rules', 'slot_generation', 'availability/slots APIs'],
                'P5-F3-03' => ['public booking flow orchestration', 'booking events', 'optional payment linkage'],
            ],
        ];
    }
}
