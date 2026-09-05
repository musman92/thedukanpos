<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Http\Requests\Admin\UpdateSaleRequest;
use App\Models\Sale;
use App\Services\SaleInvoicePdfService;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        protected SaleService $sales,
        protected SaleInvoicePdfService $invoices,
        protected SettingService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $editing = null;
        if ($request->filled('edit')) {
            $sale = Sale::query()->findOrFail($request->integer('edit'));
            $branch = BranchContext::ensure();
            if ((int) $sale->branch_id !== (int) $branch->id) {
                abort(404);
            }
            $editing = $this->sales->serializeForForm($sale);
        }

        return Inertia::render('Admin/Orders/Index', [
            ...$this->sales->paginate([
                'q' => $request->input('q'),
                'customer_id' => $request->input('customer_id'),
                'payment_status' => $request->input('payment_status'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->sales->formOptions(),
            'editing' => $editing,
            'form_open' => $request->boolean('open') || $editing !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.orders.index', ['open' => 1]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $branch = BranchContext::ensure();

        try {
            $sale = $this->sales->checkout($request->payload(
                $branch->id,
                $this->sales->resolveOpenShiftId($branch->id),
                $this->settings->allowPosCredit(),
            ));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.orders.show', $sale)
            ->with('status', "Order {$sale->number} created.");
    }

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        try {
            $sale = $this->sales->update($sale, $request->payload(
                $branch->id,
                $sale->shift_id,
                $this->settings->allowPosCredit(),
            ));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.orders.index')
            ->with('status', "Order {$sale->number} updated.");
    }

    public function receipt(Request $request, Sale $sale): Response
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        return Inertia::render('Pos/Receipt', [
            ...$this->sales->receiptPageProps($sale),
            'context' => 'admin',
            'back_url' => route('admin.orders.show', $sale),
            'auto_print' => $request->boolean('print'),
        ]);
    }

    public function invoice(Sale $sale): HttpResponse
    {
        return $this->invoices->stream($sale);
    }

    public function show(Sale $sale): Response
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        return Inertia::render('Admin/Orders/Show', $this->sales->show($sale));
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        try {
            $voided = $this->sales->voidSale($sale);
        } catch (\Throwable $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.orders.index')
            ->with('status', "Order {$voided->number} deleted. Stock was restored.");
    }
}
