<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Http\Requests\Admin\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    public function index(Request $request): Response
    {
        $editing = null;
        if ($request->filled('edit')) {
            $purchase = Purchase::query()->findOrFail($request->integer('edit'));
            $editing = $this->purchases->serializeForForm($purchase);
        }

        return Inertia::render('Admin/Purchases/Index', [
            ...$this->purchases->paginate([
                'q' => $request->input('q'),
                'supplier_id' => $request->input('supplier_id'),
                'payment_status' => $request->input('payment_status'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->purchases->formOptions(),
            'editing' => $editing,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.purchases.index', ['open' => 1]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        try {
            $purchase = $this->purchases->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.purchases.show', $purchase)
            ->with('status', "Purchase {$purchase->number} received.");
    }

    public function show(Purchase $purchase): Response
    {
        return Inertia::render('Admin/Purchases/Show', [
            ...$this->purchases->show($purchase),
            ...$this->purchases->formOptions(),
            'form_purchase' => $this->purchases->serializeForForm($purchase),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $purchase = $this->purchases->update($purchase, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.purchases.show', $purchase)
            ->with('status', "Purchase {$purchase->number} updated.");
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        try {
            $number = $purchase->number;
            $this->purchases->delete($purchase);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.purchases.index')
            ->with('status', "Purchase {$number} deleted.");
    }
}
