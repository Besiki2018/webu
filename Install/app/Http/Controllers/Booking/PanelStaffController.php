<?php

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Booking\Support\BookingPermissions;
use App\Http\Controllers\Controller;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffTimeOff;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelStaffController extends Controller
{
    public function __construct(
        protected BookingPanelServiceContract $booking,
        protected BookingAuthorizationServiceContract $bookingAuthorization
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->listStaff($site));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('booking_staff_resources')
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'type' => ['nullable', 'string', Rule::in(['staff', 'resource'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'max_parallel_bookings' => ['nullable', 'integer', 'min:1', 'max:20'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $resource = $this->booking->createStaff($site, $validated);

        return response()->json([
            'message' => 'Booking staff/resource created successfully.',
            'staff_resource' => $resource,
        ], 201);
    }

    public function update(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('booking_staff_resources')
                    ->ignore($staffResource->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'type' => ['sometimes', 'string', Rule::in(['staff', 'resource'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'max_parallel_bookings' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->booking->updateStaff($site, $staffResource, $validated);
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking staff/resource updated successfully.',
            'staff_resource' => $updated,
        ]);
    }

    public function destroy(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF);

        try {
            $this->booking->deleteStaff($site, $staffResource);
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking staff/resource deleted successfully.',
        ]);
    }

    public function indexWorkSchedules(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->listStaffSchedules($site, $staffResource));
    }

    public function syncWorkSchedules(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF_SCHEDULE);

        $validated = $request->validate([
            'schedules' => ['required', 'array', 'max:35'],
            'schedules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.is_available' => ['nullable', 'boolean'],
            'schedules.*.timezone' => ['nullable', 'string', 'max:64'],
            'schedules.*.effective_from' => ['nullable', 'date'],
            'schedules.*.effective_to' => ['nullable', 'date'],
            'schedules.*.meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->booking->syncStaffSchedules(
                $site,
                $staffResource,
                $validated['schedules'],
                $request->user()
            );
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Staff work schedules synced successfully.',
            ...$payload,
        ]);
    }

    public function indexTimeOff(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->listStaffTimeOff($site, $staffResource, [
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'limit' => $request->query('limit'),
        ]));
    }

    public function storeTimeOff(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF_TIME_OFF);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $entry = $this->booking->createStaffTimeOff($site, $staffResource, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Staff time-off entry created successfully.',
            'time_off' => $entry,
        ], 201);
    }

    public function updateTimeOff(
        Request $request,
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff
    ): JsonResponse {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF_TIME_OFF);

        $validated = $request->validate([
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $entry = $this->booking->updateStaffTimeOff(
                $site,
                $staffResource,
                $timeOff,
                $validated,
                $request->user()
            );
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Staff time-off entry updated successfully.',
            'time_off' => $entry,
        ]);
    }

    public function destroyTimeOff(
        Site $site,
        BookingStaffResource $staffResource,
        BookingStaffTimeOff $timeOff,
        Request $request
    ): JsonResponse {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $staffResource);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_STAFF_TIME_OFF);

        try {
            $this->booking->deleteStaffTimeOff($site, $staffResource, $timeOff, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Staff time-off entry deleted successfully.',
        ]);
    }
}
