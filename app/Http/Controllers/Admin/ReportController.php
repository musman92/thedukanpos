<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request): Response
    {
        $branch = BranchContext::ensure();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $base = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $sales = (clone $base)
            ->with('cashier')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'created_at' => format_company_datetime($sale->created_at),
                'total' => (float) $sale->total,
                'paid_total' => (float) $sale->paid_total,
                'cashier' => $sale->cashier
                    ? ['id' => $sale->cashier->id, 'name' => $sale->cashier->name ?: $sale->cashier->username]
                    : null,
            ]);

        $summary = [
            'count' => (clone $base)->count(),
            'total' => (float) (clone $base)->sum('total'),
            'paid' => (float) (clone $base)->sum('paid_total'),
            'tax' => (float) (clone $base)->sum('tax_total'),
            'discount' => (float) (clone $base)->sum('discount_total'),
        ];

        return Inertia::render('Admin/Reports/Sales', [
            'sales' => $sales,
            'summary' => $summary,
            'filters' => compact('from', 'to'),
            'categories' => $this->categoryOptions(),
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    public function salesExport(Request $request): StreamedResponse
    {
        $branch = BranchContext::ensure();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $filename = "sales-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($branch, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Subtotal', 'Tax', 'Total', 'Paid', 'Cashier']);
            Sale::query()
                ->with('cashier')
                ->where('branch_id', $branch->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $sale) {
                        fputcsv($out, [
                            $sale->number,
                            format_company_datetime($sale->created_at),
                            $sale->subtotal,
                            $sale->tax_total,
                            $sale->total,
                            $sale->paid_total,
                            $sale->cashier?->name ?: $sale->cashier?->username,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function productSales(Request $request): Response
    {
        $branch = BranchContext::ensure();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $categoryId = $request->input('category_id');

        $rows = SaleItem::query()
            ->selectRaw('sale_items.variant_id, sale_items.product_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as amount')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', (int) $categoryId))
            ->with(['variant', 'product'])
            ->groupBy('sale_items.variant_id', 'sale_items.product_id')
            ->orderByDesc('amount')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Reports/ProductSales', [
            'rows' => $rows,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'category_id' => $categoryId,
            ],
            'categories' => $this->categoryOptions(),
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    protected function categoryOptions(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }
}
