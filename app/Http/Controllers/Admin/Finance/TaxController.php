<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaxRequest;
use App\Http\Requests\Admin\UpdateTaxRequest;
use App\Models\Tax;
use App\Services\TaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function __construct(protected TaxService $taxes) {}

    public function index(Request $request): Response
    {
        $result = $this->taxes->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Taxes/Index', $result);
    }

    public function store(StoreTaxRequest $request): RedirectResponse
    {
        $this->taxes->create($request->payload());

        return back()->with('status', 'Tax created.');
    }

    public function update(UpdateTaxRequest $request, Tax $tax): RedirectResponse
    {
        $this->taxes->update($tax, $request->payload());

        return back()->with('status', 'Tax updated.');
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        try {
            $this->taxes->delete($tax);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete tax.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Tax deleted.');
    }
}
