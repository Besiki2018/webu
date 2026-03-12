<?php

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Booking\Support\BookingPermissions;
use App\Http\Controllers\Controller;
use App\Models\BookingService;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelServiceController extends Controller
{
    public function __construct(
        protected BookingPanelServiceContract $booking,
        protected BookingAuthorizationServiceContract $bookingAuthorization
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->listServices($site));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_SERVICES);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('booking_services')
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'slot_step_minutes' => ['nullable', 'integer', 'min:1', 'max:360'],
            'max_parallel_bookings' => ['nullable', 'integer', 'min:1', 'max:20'],
            'requires_staff' => ['nullable', 'boolean'],
            'allow_online_payment' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $service = $this->booking->createService($site, $validated);

        return response()->json([
            'message' => 'Booking service created successfully.',
            'service' => $service,
        ], 201);
    }

    public function update(Request $request, Site $site, BookingService $service): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $service);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_SERVICES);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('booking_services')
                    ->ignore($service->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'buffer_after_minutes' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'slot_step_minutes' => ['nullable', 'integer', 'min:1', 'max:360'],
            'max_parallel_bookings' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'requires_staff' => ['sometimes', 'boolean'],
            'allow_online_payment' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->booking->updateService($site, $service, $validated);
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking service updated successfully.',
            'service' => $updated,
        ]);
    }

    public function destroy(Request $request, Site $site, BookingService $service): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $service);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::MANAGE_SERVICES);

        try {
            $this->booking->deleteService($site, $service);
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking service deleted successfully.',
        ]);
    }
}
