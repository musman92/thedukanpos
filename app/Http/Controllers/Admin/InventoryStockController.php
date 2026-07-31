<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductLedgerService;
use App\Services\StockOnHandService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryStockController extends Controller
{
    public function __construct(
        protected StockOnHandService $stocks,
        protected ProductLedgerService $ledger,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render($request, lowOnly: false);
    }

    public function lowStock(Request $request): Response
    {
        return $this->render($request, lowOnly: true);
    }

    public function productLedger(Request $request): Response
    {
        return Inertia::render('Admin/Inventory/ProductLedger', $this->ledger->build([
            'branch_id' => $request->input('branch_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'product_id' => $request->input('product_id'),
            'per_page' => $request->input('per_page'),
            'page' => $request->input('page'),
        ]));
    }

    protected function render(Request $request, bool $lowOnly): Response
    {
        return Inertia::render('Admin/Inventory/Stock', [
            ...$this->stocks->paginate([
                'q' => $request->input('q'),
                'category_id' => $request->input('category_id'),
                'low' => $lowOnly ? true : $request->input('low'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            'listRoute' => $lowOnly ? 'admin.inventory.low-stock' : 'admin.inventory.stock',
            'lowOnly' => $lowOnly,
        ]);
    }
}
