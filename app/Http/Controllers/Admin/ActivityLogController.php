<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogs) {}

    public function index(Request $request): Response
    {
        return Inertia::render(
            'Admin/Activity/Index',
            $this->activityLogs->paginate([
                'q' => $request->input('q'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'action' => $request->input('action'),
                'user_id' => $request->input('user_id'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
        );
    }

    public function toggle(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $this->activityLogs->setLoggingEnabled($enabled);

        return back()->with(
            'status',
            $enabled
                ? 'Activity logging enabled. New actions will be recorded.'
                : 'Activity logging disabled.',
        );
    }
}
