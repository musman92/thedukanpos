<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierPaymentRequest;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function __construct(protected SupplierPaymentService $payments) {}

    public function index(Request $request): Response
    {
        $editingPayment = null;
        if ($request->filled('form_payment_id')) {
            $editingPayment = SupplierPayment::query()
                ->with([
                    'supplier:id,name,balance',
                    'moneySource:id,name,type',
                    'purchases:id,number',
                    'branch:id,name',
                    'creator:id,name,username',
                ])
                ->where('branch_id', BranchContext::ensure()->id)
                ->find($request->integer('form_payment_id'));
        }

        $formSupplierId = $editingPayment?->supplier_id
            ?? ($request->filled('form_supplier_id') ? $request->integer('form_supplier_id') : null);
        $formOpen = $request->boolean('open') || $formSupplierId !== null || $editingPayment !== null;

        return Inertia::render('Admin/SupplierPayments/Index', [
            ...$this->payments->paginate([
                'q' => $request->input('q'),
                'supplier_id' => $request->input('supplier_id'),
                'money_source_id' => $request->input('money_source_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->payments->formContext($formSupplierId, $editingPayment),
            'form_open' => $formOpen,
            'editing_payment' => $editingPayment
                ? $this->payments->serialize($editingPayment)
                : null,
        ]);
    }

    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        try {
            $this->payments->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['total_amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.finance.supplier-payments.index')
            ->with('status', 'Supplier payment recorded.');
    }

    public function update(StoreSupplierPaymentRequest $request, SupplierPayment $supplierPayment): RedirectResponse
    {
        try {
            $this->payments->update($supplierPayment, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['total_amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.finance.supplier-payments.index')
            ->with('status', 'Supplier payment updated.');
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        try {
            $this->payments->delete($supplierPayment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete payment.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Supplier payment deleted.');
    }
}
