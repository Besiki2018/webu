<?php

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingPublicServiceContract;
use App\Booking\Exceptions\BookingDomainException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        protected BookingPublicServiceContract $booking
    ) {}

    public function services(Request $request, Site $site): JsonResponse
    {
        try {
            $payload = $this->booking->listServices($site, [
                'search' => $request->query('search'),
            ], $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function service(Request $request, Site $site, string $slug): JsonResponse
    {
        try {
            $payload = $this->booking->getService($site, $slug, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function staff(Request $request, Site $site): JsonResponse
    {
        try {
            $payload = $this->booking->listStaff($site, [
                'search' => $request->query('search'),
            ], $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function slots(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        try {
            $payload = $this->booking->slots($site, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function calendar(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'staff_id' => ['nullable', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['staff_id']) && ! isset($validated['staff_resource_id'])) {
            $validated['staff_resource_id'] = $validated['staff_id'];
        }

        try {
            $payload = $this->booking->calendar($site, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function createBooking(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'prepayment_amount' => ['nullable', 'numeric', 'min:0'],
            'prepayment_currency' => ['nullable', 'string', 'size:3'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $payload = $this->booking->createBooking($site, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload, 201);
    }

    public function myBookings(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            $payload = $this->booking->listBookings($site, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function booking(Request $request, Site $site, Booking $booking): JsonResponse
    {
        try {
            $payload = $this->booking->showBooking($site, $booking, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function updateBooking(Request $request, Site $site, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:cancel,reschedule'],
            'status' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'staff_id' => ['nullable', 'integer', 'min:1'],
            'staff_resource_id' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($validated['staff_id']) && ! isset($validated['staff_resource_id'])) {
            $validated['staff_resource_id'] = $validated['staff_id'];
        }

        try {
            $payload = $this->booking->updateBooking($site, $booking, $validated, $request->user());
        } catch (BookingDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    private function corsJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');
    }
}
