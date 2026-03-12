<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Project;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdminBookingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', 'all'));
        $source = trim((string) $request->input('source', 'all'));
        $projectId = trim((string) $request->input('project_id', ''));
        $siteId = trim((string) $request->input('site_id', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $sort = trim((string) $request->input('sort', 'starts_desc'));
        $perPage = max(10, min((int) $request->input('per_page', 20), 100));

        $statusOptions = [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_IN_PROGRESS,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_NO_SHOW,
        ];

        if ($status !== 'all' && ! in_array($status, $statusOptions, true)) {
            $status = 'all';
        }

        if (! in_array($sort, ['starts_desc', 'starts_asc', 'updated_desc'], true)) {
            $sort = 'starts_desc';
        }

        $query = Booking::query()
            ->with([
                'service:id,site_id,name',
                'staffResource:id,site_id,name,type',
                'site:id,project_id,name,subdomain,primary_domain',
                'site.project:id,name,user_id',
                'site.project.user:id,name,email',
                'events:id,site_id,booking_id,event_type,event_key,payload_json,occurred_at,created_by,created_at',
            ])
            ->withCount('events');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($source !== '' && $source !== 'all') {
            $query->where('source', $source);
        }

        if ($projectId !== '') {
            $query->whereHas('site', function ($builder) use ($projectId): void {
                $builder->where('project_id', $projectId);
            });
        }

        if ($siteId !== '') {
            $query->where('site_id', $siteId);
        }

        if ($dateFrom !== '') {
            $query->where('starts_at', '>=', CarbonImmutable::parse($dateFrom)->startOfDay());
        }

        if ($dateTo !== '') {
            $query->where('starts_at', '<=', CarbonImmutable::parse($dateTo)->endOfDay());
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = "%{$search}%";
                $builder
                    ->where('booking_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhereHas('service', function ($serviceQuery) use ($like): void {
                        $serviceQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('staffResource', function ($staffQuery) use ($like): void {
                        $staffQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('site', function ($siteQuery) use ($like): void {
                        $siteQuery
                            ->where('name', 'like', $like)
                            ->orWhere('subdomain', 'like', $like)
                            ->orWhere('primary_domain', 'like', $like)
                            ->orWhereHas('project', function ($projectQuery) use ($like): void {
                                $projectQuery->where('name', 'like', $like);
                            });
                    });
            });
        }

        $query = match ($sort) {
            'starts_asc' => $query->orderBy('starts_at')->orderBy('id'),
            'updated_desc' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            default => $query->orderByDesc('starts_at')->orderByDesc('id'),
        };

        $bookings = $query->paginate($perPage)->withQueryString();

        $bookingsData = $bookings->getCollection()
            ->map(fn (Booking $booking): array => $this->serializeBooking($booking))
            ->values();

        $sourceOptions = Booking::query()
            ->select('source')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->values();

        $projectOptions = $this->projectOptions($projectId);
        $siteOptions = $this->siteOptions($projectId, $siteId);

        return Inertia::render('Admin/Bookings', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'role'),
            'bookings' => [
                'data' => $bookingsData,
            ],
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'source' => $source === '' ? 'all' : $source,
                'project_id' => $projectId,
                'site_id' => $siteId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
            'status_options' => $statusOptions,
            'source_options' => $sourceOptions,
            'projects' => $projectOptions,
            'sites' => $siteOptions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBooking(Booking $booking): array
    {
        $timeline = $booking->events instanceof Collection
            ? $booking->events
                ->sortByDesc(fn ($event): int => $event->occurred_at?->getTimestamp() ?? 0)
                ->take(12)
                ->values()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_key' => $event->event_key,
                    'occurred_at' => $event->occurred_at?->toISOString(),
                    'created_by' => $event->created_by,
                    'payload_json' => is_array($event->payload_json) ? $event->payload_json : [],
                ])
                ->all()
            : [];

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'source' => $booking->source,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'starts_at' => $booking->starts_at?->toISOString(),
            'ends_at' => $booking->ends_at?->toISOString(),
            'service' => [
                'id' => $booking->service?->id,
                'name' => $booking->service?->name,
            ],
            'staff_resource' => [
                'id' => $booking->staffResource?->id,
                'name' => $booking->staffResource?->name,
                'type' => $booking->staffResource?->type,
            ],
            'site' => [
                'id' => $booking->site?->id,
                'name' => $booking->site?->name,
                'subdomain' => $booking->site?->subdomain,
                'primary_domain' => $booking->site?->primary_domain,
            ],
            'project' => [
                'id' => $booking->site?->project?->id,
                'name' => $booking->site?->project?->name,
                'owner' => $booking->site?->project?->user ? [
                    'id' => $booking->site->project->user->id,
                    'name' => $booking->site->project->user->name,
                    'email' => $booking->site->project->user->email,
                ] : null,
            ],
            'events_count' => (int) ($booking->events_count ?? count($timeline)),
            'timeline' => $timeline,
            'created_at' => $booking->created_at?->toISOString(),
            'updated_at' => $booking->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id:string,name:string,owner_name:string|null,owner_email:string|null}>
     */
    private function projectOptions(string $selectedProjectId): array
    {
        $projects = Project::query()
            ->whereHas('site.bookings')
            ->with(['user:id,name,email'])
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'name', 'user_id']);

        if ($selectedProjectId !== '' && ! $projects->contains('id', $selectedProjectId)) {
            $selected = Project::query()
                ->with(['user:id,name,email'])
                ->find($selectedProjectId, ['id', 'name', 'user_id']);
            if ($selected) {
                $projects->push($selected);
            }
        }

        return $projects
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'owner_name' => $project->user?->name,
                'owner_email' => $project->user?->email,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:string,project_id:string,name:string,subdomain:string|null,primary_domain:string|null}>
     */
    private function siteOptions(string $projectId, string $selectedSiteId): array
    {
        $query = Site::query()
            ->whereHas('bookings')
            ->orderBy('name');

        if ($projectId !== '') {
            $query->where('project_id', $projectId);
        }

        $sites = $query
            ->limit(300)
            ->get(['id', 'project_id', 'name', 'subdomain', 'primary_domain']);

        if ($selectedSiteId !== '' && ! $sites->contains('id', $selectedSiteId)) {
            $selected = Site::query()->find($selectedSiteId, ['id', 'project_id', 'name', 'subdomain', 'primary_domain']);
            if ($selected) {
                $sites->push($selected);
            }
        }

        return $sites
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'project_id' => $site->project_id,
                'name' => $site->name,
                'subdomain' => $site->subdomain,
                'primary_domain' => $site->primary_domain,
            ])
            ->all();
    }
}

