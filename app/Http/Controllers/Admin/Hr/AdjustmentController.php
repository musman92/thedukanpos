<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePayrollAdjustmentRequest;
use App\Http\Requests\Admin\UpdatePayrollAdjustmentRequest;
use App\Models\PayrollAdjustment;
use App\Services\PayrollAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentController extends Controller
{
    public function __construct(protected PayrollAdjustmentService $adjustments) {}

    public function index(Request $request): Response
    {
        $result = $this->adjustments->paginate([
            'q' => $request->input('q'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'user_id' => $request->input('user_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Hr/Adjustments/Index', $result);
    }

    public function store(StorePayrollAdjustmentRequest $request): RedirectResponse
    {
        try {
            $this->adjustments->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Bonus / deduction saved.');
    }

    public function update(UpdatePayrollAdjustmentRequest $request, PayrollAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->update($adjustment, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Bonus / deduction updated.');
    }

    public function destroy(PayrollAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->delete($adjustment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Bonus / deduction deleted.');
    }
}
