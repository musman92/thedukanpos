<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Http\Requests\Admin\StoreSaleReturnRequest;
use App\Http\Requests\Admin\UpdatePurchaseReturnRequest;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Services\PurchaseReturnService;
use App\Services\SaleReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function __construct(
        protected PurchaseReturnService $purchaseReturns,
        protected SaleReturnService $saleReturns,
    ) {}

    public function purchaseIndex(Request $request): Response
    {
        $purchaseId = $request->filled('purchase_id') ? $request->integer('purchase_id') : null;
        $formSupplierId = $request->filled('form_supplier_id')
            ? $request->integer('form_supplier_id')
            : null;
        $editingReturn = null;
        $editing = null;

        if ($request->filled('edit')) {
            $editingReturn = PurchaseReturn::query()->findOrFail($request->integer('edit'));
            $editing = $this->purchaseReturns->serializeForForm($editingReturn);
            $purchaseId = $purchaseId ?: (int) $editingReturn->purchase_id;
            $formSupplierId = $formSupplierId ?: ($editingReturn->supplier_id
                ? (int) $editingReturn->supplier_id
                : null);
        }

        $formOpen = $request->boolean('open') || $purchaseId !== null || $editing !== null;

        return Inertia::render('Admin/PurchaseReturns/Index', [
            ...$this->purchaseReturns->paginate([
                'q' => $request->input('q'),
                'supplier_id' => $request->input('supplier_id'),
                'purchase_id' => null,
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->purchaseReturns->formOptions($purchaseId, $formSupplierId, $editingReturn),
            'form_open' => $formOpen,
            'editing' => $editing,
        ]);
    }

    public function purchaseCreate(Request $request): RedirectResponse
    {
        $params = ['open' => 1];
        if ($request->filled('purchase_id')) {
            $params['purchase_id'] = $request->integer('purchase_id');
        }
        if ($request->filled('supplier_id')) {
            $params['form_supplier_id'] = $request->integer('supplier_id');
        }
        if ($request->filled('form_supplier_id')) {
            $params['form_supplier_id'] = $request->integer('form_supplier_id');
        }

        return redirect()->route('admin.returns.purchases.index', $params);
    }

    public function purchaseStore(StorePurchaseReturnRequest $request): RedirectResponse
    {
        try {
            $doc = $this->purchaseReturns->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.returns.purchases.show', $doc)
            ->with('status', "Purchase return {$doc->number} saved.");
    }

    public function purchaseShow(PurchaseReturn $purchaseReturn): Response
    {
        return Inertia::render('Admin/PurchaseReturns/Show', [
            ...$this->purchaseReturns->show($purchaseReturn),
            ...$this->purchaseReturns->formOptions(
                (int) $purchaseReturn->purchase_id,
                $purchaseReturn->supplier_id ? (int) $purchaseReturn->supplier_id : null,
                $purchaseReturn,
            ),
            'editing' => $this->purchaseReturns->serializeForForm($purchaseReturn),
        ]);
    }

    public function purchaseUpdate(
        UpdatePurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn,
    ): RedirectResponse {
        try {
            $doc = $this->purchaseReturns->update($purchaseReturn, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.returns.purchases.show', $doc)
            ->with('status', "Purchase return {$doc->number} updated.");
    }

    public function purchaseDestroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        try {
            $number = $purchaseReturn->number;
            $this->purchaseReturns->delete($purchaseReturn);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.returns.purchases.index')
            ->with('status', "Purchase return {$number} deleted.");
    }

    public function saleIndex(Request $request): Response
    {
        $saleId = $request->filled('sale_id') ? $request->integer('sale_id') : null;
        $formOpen = $request->boolean('open') || $saleId !== null;

        return Inertia::render('Admin/SaleReturns/Index', [
            ...$this->saleReturns->paginate([
                'q' => $request->input('q'),
                'customer_id' => $request->input('customer_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->saleReturns->formOptions($saleId),
            'form_open' => $formOpen,
            'editing' => null,
        ]);
    }

    public function saleCreate(Request $request): RedirectResponse
    {
        $params = ['open' => 1];
        if ($request->filled('sale_id')) {
            $params['sale_id'] = $request->integer('sale_id');
        }

        return redirect()->route('admin.returns.sales.index', $params);
    }

    public function saleStore(StoreSaleReturnRequest $request): RedirectResponse
    {
        try {
            $doc = $this->saleReturns->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.returns.sales.show', $doc)
            ->with('status', "Refund {$doc->number} saved.");
    }

    public function saleShow(SaleReturn $saleReturn): Response
    {
        return Inertia::render('Admin/SaleReturns/Show', [
            ...$this->saleReturns->show($saleReturn),
        ]);
    }
}
