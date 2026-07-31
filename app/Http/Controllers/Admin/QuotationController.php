<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuotationRequest;
use App\Http\Requests\Admin\UpdateQuotationRequest;
use App\Models\Quotation;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotations,
        protected QuotationPdfService $pdfs,
    ) {}

    public function index(Request $request): Response
    {
        $editing = null;
        if ($request->filled('edit')) {
            $quotation = Quotation::query()->findOrFail($request->integer('edit'));
            $editing = $this->quotations->serializeForForm($quotation);
        }

        return Inertia::render('Admin/Quotations/Index', [
            ...$this->quotations->paginate([
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            ...$this->quotations->formOptions(),
            'editing' => $editing,
            'form_open' => $request->boolean('open') || $editing !== null,
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        try {
            $quotation = $this->quotations->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.quotations.index')
            ->with('status', "Quotation {$quotation->number} saved.");
    }

    public function show(Quotation $quotation): Response
    {
        $this->assertBranch($quotation);

        return Inertia::render('Admin/Quotations/Show', $this->quotations->show($quotation));
    }

    public function pdf(Quotation $quotation): HttpResponse
    {
        return $this->pdfs->stream($quotation);
    }

    public function download(Quotation $quotation): HttpResponse
    {
        return $this->pdfs->download($quotation);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        try {
            $quotation = $this->quotations->update($quotation, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.quotations.index')
            ->with('status', "Quotation {$quotation->number} updated.");
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        try {
            $number = $quotation->number;
            $this->quotations->delete($quotation);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.quotations.index')
            ->with('status', "Quotation {$number} deleted.");
    }

    protected function assertBranch(Quotation $quotation): void
    {
        $branch = BranchContext::ensure();
        if ((int) $quotation->branch_id !== (int) $branch->id) {
            abort(404);
        }
    }
}
