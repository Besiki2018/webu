<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use App\Services\OperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOperationLogController extends Controller
{
    public function __construct(
        protected OperationLogService $operationLogs
    ) {}

    /**
     * Display operation logs page.
     */
    public function index(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return Inertia::render('Admin/OperationLogs');
    }

    /**
     * Get operation logs for admin table.
     */
    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = OperationLog::query()
            ->with(['user:id,name,email', 'project:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%");
            });
        }

        if ($request->filled('channel') && $request->input('channel') !== 'all') {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }

        $perPage = max(10, min((int) $request->integer('per_page', 20), 100));
        $logs = $query->paginate($perPage);

        $logs->setCollection(
            $logs->getCollection()->map(fn ($log) => $this->operationLogs->transform($log))
        );

        return response()->json($logs);
    }
}
