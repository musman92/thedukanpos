<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Orders/Index', $this->sales->paginate([
            'q' => $request->input('q'),
            'customer_id' => $request->input('customer_id'),
            'payment_status' => $request->input('payment_status'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]));
    }

    public function show(Sale $sale): Response
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        return Inertia::render('Admin/Orders/Show', $this->sales->show($sale));
    }
}
