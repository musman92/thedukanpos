<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewLeaveRequest;
use App\Http\Requests\Admin\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(protected LeaveService $leaves) {}

    public function index(Request $request): Response
    {
        $result = $this->leaves->paginate([
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'leave_type' => $request->input('leave_type'),
            'user_id' => $request->input('user_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Hr/Leaves/Index', $result);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        try {
            $this->leaves->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Leave request submitted.');
    }

    public function review(ReviewLeaveRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $payload = $request->payload();

        try {
            $this->leaves->review($leave, $payload['status'], $payload['review_notes']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Leave request reviewed.');
    }

    public function destroy(LeaveRequest $leave): RedirectResponse
    {
        try {
            $this->leaves->cancel($leave);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot cancel.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Leave request cancelled.');
    }
}
