<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogs) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->activityLogs->paginate([
            'q' => $request->input('q'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);
        $page = $result['logs'];

        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'actions' => $result['actions'],
            'users' => $result['users'],
            'logging_enabled' => $result['logging_enabled'],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        $this->activityLogs->setLoggingEnabled($enabled);

        return response()->json([
            'logging_enabled' => $enabled,
            'message' => $enabled
                ? 'Activity logging enabled. New actions will be recorded.'
                : 'Activity logging disabled.',
        ]);
    }
}
