<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeePaymentRequest;
use App\Models\EmployeePayment;
use App\Services\EmployeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePaymentController extends Controller
{
    public function __construct(protected EmployeePaymentService $payments) {}

    public function index(Request $request): Response
    {
        $result = $this->payments->paginate([
            'q' => $request->input('q'),
            'kind' => $request->input('kind'),
            'user_id' => $request->input('user_id'),
            'money_source_id' => $request->input('money_source_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/EmployeePayments/Index', [
            ...$result,
            'prefill' => [
                'kind' => $request->input('kind'),
                'user_id' => $request->input('user_id'),
                'payroll_item_id' => $request->input('payroll_item_id'),
                'amount' => $request->input('amount'),
                'open' => $request->boolean('open'),
            ],
        ]);
    }

    public function store(StoreEmployeePaymentRequest $request): RedirectResponse
    {
        try {
            $this->payments->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Employee payment recorded.');
    }

    public function destroy(EmployeePayment $employeePayment): RedirectResponse
    {
        try {
            $this->payments->delete($employeePayment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete payment.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Employee payment deleted.');
    }
}
