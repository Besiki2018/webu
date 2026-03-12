<?php

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Booking\Support\BookingPermissions;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PanelBookingController extends Controller
{
    public function __construct(
        protected BookingPanelServiceContract $booking,
        protected BookingAuthorizationServiceContract $bookingAuthorization
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->listBookings($site, [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'limit' => $request->query('limit'),
        ]));
    }

    public function calendar(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::CALENDAR_VIEW);

        try {
            $payload = $this->booking->calendar($site, [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ]);
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    public function show(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $site->project);
        Gate::authorize('view', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        return response()->json($this->booking->showBooking($site, $booking));
    }

    public function searchCustomers(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::READ);

        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([
                'site_id' => $site->id,
                'users' => [],
            ]);
        }

        $users = User::query()
            ->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();

        return response()->json([
            'site_id' => $site->id,
            'users' => $users,
        ]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::CREATE);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::ASSIGN);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'source' => ['nullable', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'customer_user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'register_customer_if_missing' => ['nullable', 'boolean'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'paid_total' => ['nullable', 'numeric', 'min:0'],
            'outstanding_total' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'meta_json' => ['nullable', 'array'],
        ]);
        $validated = $this->enrichCustomerIdentityPayload($validated);

        try {
            $booking = $this->booking->createBooking($site, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking created successfully.',
            'booking' => $booking,
        ], 201);
    }

    public function updateStatus(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::STATUS_UPDATE);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->booking->updateBookingStatus($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking status updated successfully.',
            'booking' => $updated,
        ]);
    }

    public function reschedule(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::RESCHEDULE);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::ASSIGN);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'customer_user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'register_customer_if_missing' => ['nullable', 'boolean'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);
        $validated = $this->enrichCustomerIdentityPayload($validated);

        try {
            $updated = $this->booking->rescheduleBooking($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking rescheduled successfully.',
            'booking' => $updated,
        ]);
    }

    public function cancel(Request $request, Site $site, Booking $booking): JsonResponse
    {
        Gate::authorize('update', $site->project);
        Gate::authorize('update', $booking);
        $this->bookingAuthorization->authorize($request->user(), $site, BookingPermissions::CANCEL);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->booking->cancelBooking($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $updated,
        ]);
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    protected function enrichCustomerIdentityPayload(array $validated): array
    {
        $name = array_key_exists('customer_name', $validated) ? trim((string) ($validated['customer_name'] ?? '')) : '';
        $email = array_key_exists('customer_email', $validated) ? strtolower(trim((string) ($validated['customer_email'] ?? ''))) : '';
        $phone = array_key_exists('customer_phone', $validated) ? trim((string) ($validated['customer_phone'] ?? '')) : '';
        $customerUserId = isset($validated['customer_user_id']) ? (int) $validated['customer_user_id'] : null;
        $registerIfMissing = (bool) ($validated['register_customer_if_missing'] ?? false);

        /** @var User|null $user */
        $user = null;

        if ($customerUserId && $customerUserId > 0) {
            $user = User::query()->find($customerUserId);
        }

        if (! $user && $email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user && $registerIfMissing && $email !== '' && $name !== '') {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
                'role' => 'user',
                'status' => 'active',
            ]);
        }

        if ($user) {
            $validated['customer_name'] = trim((string) ($user->name ?? $name));
            $validated['customer_email'] = strtolower(trim((string) ($user->email ?? $email)));

            $meta = is_array($validated['meta_json'] ?? null) ? $validated['meta_json'] : [];
            $meta['customer_user_id'] = (int) $user->id;
            if ($registerIfMissing) {
                $meta['customer_auto_registered'] = true;
            }
            $validated['meta_json'] = $meta;
        } else {
            $validated['customer_name'] = $name !== '' ? $name : null;
            $validated['customer_email'] = $email !== '' ? $email : null;
        }

        if (array_key_exists('customer_phone', $validated)) {
            $validated['customer_phone'] = $phone !== '' ? $phone : null;
        }

        unset($validated['customer_user_id'], $validated['register_customer_if_missing']);

        return $validated;
    }
}
