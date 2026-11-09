<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Sale;
use App\Services\OperationalReportService;
use App\Services\ReportPdfService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request, OperationalReportService $reports): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $reports->dateRange($request->all());
        $base = $reports->salesQuery($branch->id, $from, $to);
        $summary = $reports->salesSummary($branch->id, $from, $to);

        if ($request->input('export') === 'pdf') {
            $columns = [
                ['key' => 'number', 'label' => 'Sale'],
                ['key' => 'created_at', 'label' => 'Date'],
                ['key' => 'cashier_name', 'label' => 'Cashier'],
                ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
                ['key' => 'paid_total', 'label' => 'Paid', 'format' => 'money', 'total' => true],
            ];

            $rows = (clone $base)
                ->with('cashier')
                ->latest('id')
                ->get()
                ->map(fn (Sale $sale) => $reports->mapSalesListRow($sale))
                ->all();

            return app(ReportPdfService::class)->download([
                'key' => 'sales',
                'title' => 'Sales',
                'meta' => ReportPdfService::periodMeta($from, $to, $branch->name),
                'columns' => $columns,
                'rows' => $rows,
                'summary' => [
                    ['label' => 'Sales', 'value' => $summary['count'], 'format' => 'int'],
                    ['label' => 'Total', 'value' => $summary['total'], 'format' => 'money'],
                    ['label' => 'Paid', 'value' => $summary['paid'], 'format' => 'money'],
                    ['label' => 'Discount', 'value' => $summary['discount'], 'format' => 'money'],
                    ['label' => 'Tax', 'value' => $summary['tax'], 'format' => 'money'],
                ],
                'totals' => [
                    'total' => $summary['total'],
                    'paid_total' => $summary['paid'],
                ],
            ]);
        }

        $sales = (clone $base)
            ->with('cashier')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Sale $sale) => $reports->mapSalesListRow($sale));

        return Inertia::render('Admin/Reports/Sales', [
            'sales' => $sales,
            'summary' => $summary,
            'filters' => compact('from', 'to'),
            'categories' => $this->categoryOptions(),
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    public function salesExport(Request $request, OperationalReportService $reports): StreamedResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $reports->dateRange($request->all());

        $filename = "sales-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($reports, $branch, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Subtotal', 'Tax', 'Total', 'Paid', 'Cashier']);
            $reports->salesQuery($branch->id, $from, $to)
                ->with('cashier')
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

    public function productSales(Request $request, OperationalReportService $reports): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $reports->dateRange($request->all());
        $categoryId = $request->input('category_id');
        $rows = $reports->productSalesItems($branch->id, $from, $to, $categoryId);

        if ($request->input('export') === 'pdf') {
            $columns = [
                ['key' => 'product', 'label' => 'Product'],
                ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
                ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ];

            $pdfRows = $rows->map(fn ($row) => [
                'product' => $row->variant?->displayName() ?? $row->product?->name,
                'qty' => round((float) $row->qty, 4),
                'amount' => money_round($row->amount),
            ])->all();

            return app(ReportPdfService::class)->download([
                'key' => 'sales-by-item',
                'title' => 'Sales by Item',
                'meta' => ReportPdfService::periodMeta($from, $to, $branch->name),
                'columns' => $columns,
                'rows' => $pdfRows,
                'totals' => [
                    'qty' => round(array_sum(array_column($pdfRows, 'qty')), 4),
                    'amount' => money_round(array_sum(array_column($pdfRows, 'amount'))),
                ],
            ]);
        }

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
