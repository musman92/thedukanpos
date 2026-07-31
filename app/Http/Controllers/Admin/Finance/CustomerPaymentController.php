<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerPaymentRequest;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function __construct(protected CustomerPaymentService $payments) {}

    public function index(Request $request): Response
    {
        $editingPayment = null;
        if ($request->filled('form_payment_id')) {
            $editingPayment = CustomerPayment::query()
                ->with([
                    'customer:id,name,balance',
                    'moneySource:id,name,type',
                    'sales:id,number',
                    'branch:id,name',
                    'receiver:id,name,username',
                ])
                ->where('branch_id', BranchContext::ensure()->id)
                ->find($request->integer('form_payment_id'));
        }

        $formCustomerId = $editingPayment?->customer_id
            ?? ($request->filled('form_customer_id') ? $request->integer('form_customer_id') : null);
        $formOpen = $request->boolean('open') || $formCustomerId !== null || $editingPayment !== null;

        return Inertia::render('Admin/CustomerPayments/Index', [
            ...$this->payments->paginate([
                'q' => $request->input('q'),
                'customer_id' => $request->input('customer_id'),
                'money_source_id' => $request->input('money_source_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->payments->formContext($formCustomerId, $editingPayment),
            'form_open' => $formOpen,
            'editing_payment' => $editingPayment
                ? $this->payments->serialize($editingPayment)
                : null,
        ]);
    }

    public function store(StoreCustomerPaymentRequest $request): RedirectResponse
    {
        try {
            $this->payments->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['total_amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.finance.customer-payments.index')
            ->with('status', 'Customer payment recorded.');
    }

    public function update(StoreCustomerPaymentRequest $request, CustomerPayment $customerPayment): RedirectResponse
    {
        try {
            $this->payments->update($customerPayment, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['total_amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.finance.customer-payments.index')
            ->with('status', 'Customer payment updated.');
    }

    public function destroy(CustomerPayment $customerPayment): RedirectResponse
    {
        try {
            $this->payments->delete($customerPayment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete payment.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Customer payment deleted.');
    }
}
