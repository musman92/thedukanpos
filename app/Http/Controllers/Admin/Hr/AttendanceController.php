<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LiveAttendanceActionRequest;
use App\Http\Requests\Admin\StoreAttendanceRequest;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    public function index(Request $request): Response
    {
        $result = $this->attendance->listForDate([
            'date' => $request->input('date'),
        ]);

        return Inertia::render('Admin/Hr/Attendance/Index', $result);
    }

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        try {
            $this->attendance->mark($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Attendance saved.');
    }

    public function update(UpdateAttendanceRequest $request, AttendanceRecord $record): RedirectResponse
    {
        try {
            $this->attendance->update($record, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Attendance updated.');
    }

    public function destroy(AttendanceRecord $record): RedirectResponse
    {
        $this->attendance->delete($record);

        return back()->with('status', 'Attendance deleted.');
    }

    public function action(LiveAttendanceActionRequest $request, User $user): RedirectResponse
    {
        $action = (string) $request->validated('action');

        try {
            $this->attendance->liveAction($user->id, $action);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Could not update attendance.';

            return back()->with('error', $message);
        }

        $message = match ($action) {
            'check_in' => 'Employee checked in.',
            'start_break' => 'Break started.',
            'end_break' => 'Employee returned from break.',
            'check_out' => 'Employee checked out.',
            'absent' => 'Employee marked absent.',
            default => 'Attendance updated.',
        };

        return back()->with('status', $message);
    }
}
