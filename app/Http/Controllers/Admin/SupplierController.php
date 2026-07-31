<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $suppliers) {}

    public function index(Request $request): Response
    {
        $result = $this->suppliers->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Suppliers/Index', $result);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->suppliers->create($request->payload());

        return back()->with('status', 'Supplier created.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->suppliers->update($supplier, $request->payload());

        return back()->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $this->suppliers->delete($supplier);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete supplier.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Supplier deleted.');
    }
}
