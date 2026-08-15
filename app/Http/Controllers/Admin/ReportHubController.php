<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LedgerTransaction;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AccountStatementService;
use App\Services\MoneySourceTxnReportService;
use App\Services\ReportPdfService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportHubController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.sales');
    }

    public function dailySales(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = $this->completedSales($branch->id)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count, SUM(total) as total, SUM(tax_total) as tax, SUM(discount_total) as discount, SUM(paid_total) as paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => format_company_date($row->day),
                'count' => (int) $row->count,
                'total' => money_round($row->total),
                'discount' => money_round($row->discount),
                'tax' => money_round($row->tax),
                'paid' => money_round($row->paid),
            ]);

        return $this->generic('daily-sales', 'Daily Sales', compact('from', 'to'), $rows, [
            ['key' => 'day', 'label' => 'Date'],
            ['key' => 'count', 'label' => 'Sales', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'tax', 'label' => 'Tax', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ]);
    }

    public function paymentMethods(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = SalePayment::query()
            ->selectRaw('money_sources.name as money_source, SUM(sale_payments.amount) as total, COUNT(*) as count')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('money_sources', 'money_sources.id', '=', 'sale_payments.money_source_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->groupBy('money_sources.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'money_source' => $row->money_source,
                'count' => (int) $row->count,
                'total' => money_round($row->total),
            ]);

        return $this->generic('payment-methods', 'Payment Methods', compact('from', 'to'), $rows, [
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'count', 'label' => 'Count', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
        ]);
    }

    public function moneySourceTxns(Request $request, MoneySourceTxnReportService $report): Response|HttpResponse
    {
        $payload = $report->build($request->all());

        if ($this->wantsPdf()) {
            $columns = [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'money_source', 'label' => 'Money source'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'reference', 'label' => 'Reference'],
                ['key' => 'direction', 'label' => 'Direction'],
                ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ];

            return app(ReportPdfService::class)->download([
                'key' => 'money-source-txns',
                'title' => 'Transactions by Money Source',
                'meta' => ReportPdfService::periodMeta(
                    $payload['filters']['from'] ?? null,
                    $payload['filters']['to'] ?? null,
                    BranchContext::ensure()->name,
                ),
                'columns' => $columns,
                'rows' => $payload['all_rows'],
                'summary' => [
                    ['label' => 'Transactions', 'value' => $payload['summary']['transactions'], 'format' => 'int'],
                    ['label' => 'Money in', 'value' => $payload['summary']['total_in'], 'format' => 'money'],
                    ['label' => 'Money out', 'value' => $payload['summary']['total_out'], 'format' => 'money'],
                    ['label' => 'Net', 'value' => $payload['summary']['net'], 'format' => 'money'],
                ],
                'totals' => $this->columnTotals($payload['all_rows'], $columns),
            ]);
        }

        return Inertia::render('Admin/Reports/MoneySourceTxns', [
            'reportKey' => 'money-source-txns',
            'title' => 'Transactions by Money Source',
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'by_source' => $payload['by_source'],
            'rows' => $payload['rows'],
            'money_sources' => $payload['money_sources'],
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function foc(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);
        $perPage = resolve_page_limit($request->input('per_page'), 25);

        $base = $this->completedSales($branch->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where(function ($q) {
                $q->where('notes', 'like', '%FOC%')
                    ->orWhere(function ($q2) {
                        $q2->where('paid_total', '<=', 0.01)
                            ->where('discount_total', '>', 0.01);
                    });
            });

        $summary = [
            'orders' => (clone $base)->count(),
            'foc_value' => money_round((clone $base)->sum('discount_total')),
        ];

        if ($this->wantsPdf()) {
            $focColumns = [
                ['key' => 'number', 'label' => 'Sale'],
                ['key' => 'created_at', 'label' => 'Date'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'cashier', 'label' => 'Cashier'],
                ['key' => 'item_count', 'label' => 'Items', 'format' => 'int', 'total' => true],
                ['key' => 'foc_value', 'label' => 'FOC value', 'format' => 'money', 'total' => true],
                ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
                ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
            ];

            $all = (clone $base)
                ->with(['cashier:id,name,username', 'customer:id,name'])
                ->withCount('items')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Sale $sale) => $this->focRow($sale))
                ->all();

            return app(ReportPdfService::class)->download([
                'key' => 'foc',
                'title' => 'FOC',
                'meta' => ReportPdfService::periodMeta($from, $to, $branch->name),
                'columns' => $focColumns,
                'rows' => $all,
                'summary' => $this->pdfSummary($summary),
                'totals' => $this->columnTotals($all, $focColumns),
            ]);
        }

        $sales = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sale $sale) => $this->focRow($sale));

        return Inertia::render('Admin/Reports/Foc', [
            'reportKey' => 'foc',
            'title' => 'FOC',
            'filters' => compact('from', 'to') + ['per_page' => $perPage],
            'summary' => $summary,
            'sales' => $sales,
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function focRow(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'created_at' => format_company_datetime($sale->created_at),
            'customer' => $sale->customer?->name ?: 'Walk-in',
            'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
            'item_count' => (int) $sale->items_count,
            'foc_value' => money_round($sale->discount_total),
            'total' => money_round($sale->total),
            'paid' => money_round($sale->paid_total),
        ];
    }

    public function orderHistory(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);
        $perPage = resolve_page_limit($request->input('per_page'), 25);
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $orderNumber = trim((string) $request->input('order_number', ''));

        $base = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('status', '!=', Sale::STATUS_PARKED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($orderNumber !== '', fn ($q) => $q->where('number', 'like', '%'.$orderNumber.'%'));

        $summary = [
            'orders' => (clone $base)->count(),
            'total' => money_round((clone $base)->sum('total')),
            'paid' => money_round((clone $base)->sum('paid_total')),
            'discount' => money_round((clone $base)->sum('discount_total')),
        ];
        $summary['due'] = money_round(max(0, $summary['total'] - $summary['paid']));

        if ($this->wantsPdf()) {
            $columns = [
                ['key' => 'number', 'label' => 'Order'],
                ['key' => 'created_at', 'label' => 'Date'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'cashier', 'label' => 'Cashier'],
                ['key' => 'payment_status', 'label' => 'Payment'],
                ['key' => 'item_count', 'label' => 'Items', 'format' => 'int', 'total' => true],
                ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
                ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
                ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
            ];

            $all = (clone $base)
                ->with(['cashier:id,name,username', 'customer:id,name'])
                ->withCount('items')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Sale $sale) => $this->orderHistoryRow($sale))
                ->all();

            return app(ReportPdfService::class)->download([
                'key' => 'order-history',
                'title' => 'Order History',
                'meta' => ReportPdfService::periodMeta($from, $to, $branch->name),
                'columns' => $columns,
                'rows' => $all,
                'summary' => $this->pdfSummary($summary),
                'totals' => $this->columnTotals($all, $columns),
            ]);
        }

        $sales = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sale $sale) => $this->orderHistoryRow($sale));

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        return Inertia::render('Admin/Reports/OrderHistory', [
            'reportKey' => 'order-history',
            'title' => 'Order History',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
            'sales' => $sales,
            'customers' => $customers,
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderHistoryRow(Sale $sale): array
    {
        $total = (float) $sale->total;
        $paid = (float) $sale->paid_total;

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'created_at' => format_company_datetime($sale->created_at),
            'status' => $sale->status,
            'is_delivery' => (bool) $sale->is_delivery,
            'payment_status' => $paid + 0.01 >= $total
                ? 'paid'
                : ($paid > 0.01 ? 'partial' : 'pending'),
            'customer' => $sale->customer?->name ?: 'Walk-in',
            'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
            'item_count' => (int) $sale->items_count,
            'total' => money_round($total),
            'paid' => money_round($paid),
            'discount' => money_round($sale->discount_total),
        ];
    }

    public function topItems(Request $request): Response|HttpResponse
    {
        return $this->itemSalesReport($request, 'top-items', 'Top Selling Items', 'qty');
    }

    public function salesByCategory(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->selectRaw('categories.name as category, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as amount')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->groupBy('categories.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category ?: 'Uncategorized',
                'qty' => round((float) $row->qty, 4),
                'amount' => money_round($row->amount),
            ]);

        return $this->generic('sales-by-category', 'Sales by Category', compact('from', 'to'), $rows, [
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
        ]);
    }

    public function discounts(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $base = $this->completedSales($branch->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('discount_total', '>', 0.01);

        $summary = [
            'orders' => (clone $base)->count(),
            'total_discount' => money_round((clone $base)->sum('discount_total')),
        ];

        $rows = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->orderByDesc('discount_total')
            ->limit(500)
            ->get()
            ->map(fn (Sale $sale) => [
                'number' => $sale->number,
                'created_at' => format_company_datetime($sale->created_at),
                'customer' => $sale->customer?->name ?: 'Walk-in',
                'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
                'discount' => money_round($sale->discount_total),
                'total' => money_round($sale->total),
            ]);

        return $this->generic('discounts', 'Discounts', compact('from', 'to'), $rows, [
            ['key' => 'number', 'label' => 'Sale'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'cashier', 'label' => 'Cashier'],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
        ], $summary);
    }

    public function taxSummary(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->selectRaw('COALESCE(sale_items.tax_name, ?) as tax_name, SUM(sale_items.tax_amount) as tax_amount, SUM(sale_items.line_total) as taxable', ['No tax'])
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->groupBy('sale_items.tax_name')
            ->orderByDesc('tax_amount')
            ->get()
            ->map(fn ($row) => [
                'tax_name' => $row->tax_name,
                'taxable' => money_round($row->taxable),
                'tax_amount' => money_round($row->tax_amount),
            ]);

        return $this->generic('tax-summary', 'Tax Summary', compact('from', 'to'), $rows, [
            ['key' => 'tax_name', 'label' => 'Tax'],
            ['key' => 'taxable', 'label' => 'Taxable', 'format' => 'money', 'total' => true],
            ['key' => 'tax_amount', 'label' => 'Tax amount', 'format' => 'money', 'total' => true],
        ]);
    }

    public function receivables(): Response|HttpResponse
    {
        $rows = Customer::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => money_round($c->balance),
            ]);

        return $this->generic('receivables', 'Accounts Receivable', [], $rows, [
            ['key' => 'name', 'label' => 'Customer'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'money', 'total' => true],
        ]);
    }

    public function payables(): Response|HttpResponse
    {
        $rows = Supplier::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Supplier $s) => [
                'name' => $s->name,
                'phone' => $s->phone,
                'balance' => money_round($s->balance),
            ]);

        return $this->generic('payables', 'Accounts Payable', [], $rows, [
            ['key' => 'name', 'label' => 'Supplier'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'money', 'total' => true],
        ]);
    }

    public function customerCredits(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = CustomerPayment::query()
            ->with(['customer:id,name', 'moneySource:id,name'])
            ->when(
                $branch->id,
                fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branch->id)->orWhereNull('branch_id')),
            )
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (CustomerPayment $p) => [
                'payment_date' => format_company_date($p->payment_date),
                'customer' => $p->customer?->name,
                'money_source' => $p->moneySource?->name,
                'amount' => money_round($p->amount),
                'notes' => $p->notes,
            ]);

        return $this->generic('customer-credits', 'Customer Credits', compact('from', 'to'), $rows, [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function supplierPayments(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = SupplierPayment::query()
            ->with(['supplier:id,name', 'moneySource:id,name'])
            ->when(
                $branch->id,
                fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branch->id)->orWhereNull('branch_id')),
            )
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (SupplierPayment $p) => [
                'payment_date' => format_company_date($p->payment_date),
                'supplier' => $p->supplier?->name,
                'money_source' => $p->moneySource?->name,
                'amount' => money_round($p->amount),
                'notes' => $p->notes,
            ]);

        return $this->generic('supplier-payments', 'Supplier Payments', compact('from', 'to'), $rows, [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function purchases(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = Purchase::query()
            ->with(['supplier:id,name'])
            ->where('branch_id', $branch->id)
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Purchase $p) => [
                'number' => $p->number,
                'purchase_date' => format_company_date($p->purchase_date),
                'supplier' => $p->supplier?->name,
                'total' => money_round($p->total),
                'paid' => money_round($p->paid_amount ?? 0),
                'status' => $p->payment_status ?? $p->status,
            ]);

        return $this->generic('purchases', 'Purchases', compact('from', 'to'), $rows, [
            ['key' => 'number', 'label' => 'Purchase'],
            ['key' => 'purchase_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
            ['key' => 'status', 'label' => 'Status'],
        ]);
    }

    public function expenses(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = LedgerTransaction::query()
            ->with(['account:id,name', 'moneySource:id,name'])
            ->where('reference_type', 'expense')
            ->whereDate('txn_date', '>=', $from)
            ->whereDate('txn_date', '<=', $to)
            ->when(
                $branch->id,
                fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branch->id)->orWhereNull('branch_id')),
            )
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (LedgerTransaction $t) => [
                'txn_date' => format_company_date($t->txn_date),
                'account' => $t->account?->name,
                'money_source' => $t->moneySource?->name,
                'amount' => money_round($t->amount),
                'notes' => $t->notes,
            ]);

        return $this->generic('expenses', 'Expenses', compact('from', 'to'), $rows, [
            ['key' => 'txn_date', 'label' => 'Date'],
            ['key' => 'account', 'label' => 'Account'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function accountStatement(Request $request, AccountStatementService $statements): Response|HttpResponse
    {
        $payload = $statements->build([
            'type' => $request->input('type'),
            'party_id' => $request->input('party_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'branch_id' => $request->input('branch_id'),
        ]);

        if ($this->wantsPdf() && ! empty($payload['statement'])) {
            return app(ReportPdfService::class)->download($this->accountStatementDocument($payload));
        }

        return Inertia::render('Admin/Reports/AccountStatement', $payload);
    }

    /**
     * The statement is a ledger, not a plain list: the running balance column
     * must never be summed, and opening/closing belong in the summary band.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function accountStatementDocument(array $payload): array
    {
        $statement = $payload['statement'];
        $lines = $statement['lines'] ?? [];

        $rows = array_map(fn (array $line) => [
            'date_display' => $line['date_display'] ?? '',
            'particulars' => trim(
                ($line['label'] ?? '').(! empty($line['money_source']) ? ' ('.$line['money_source'].')' : ''),
            ),
            'reference' => $line['reference'] ?? '',
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0,
            'balance' => $line['balance'] ?? 0,
        ], $lines);

        $totalDebit = money_round(array_sum(array_column($rows, 'debit')));
        $totalCredit = money_round(array_sum(array_column($rows, 'credit')));

        return [
            'key' => 'account-statement',
            'title' => 'Account Statement',
            'subtitle' => $payload['party']['name'] ?? null,
            'meta' => array_merge(
                [
                    ['label' => $payload['type_label'], 'value' => $payload['party']['name'] ?? null],
                    ['label' => 'Phone', 'value' => $payload['party']['phone'] ?? null],
                ],
                ReportPdfService::periodMeta(
                    $payload['filters']['from'] ?? null,
                    $payload['filters']['to'] ?? null,
                    $payload['branch']['name'] ?? null,
                ),
            ),
            'summary' => [
                ['label' => 'Opening balance', 'value' => $statement['opening_balance'] ?? 0, 'format' => 'money'],
                ['label' => 'Total debit', 'value' => $totalDebit, 'format' => 'money'],
                ['label' => 'Total credit', 'value' => $totalCredit, 'format' => 'money'],
                ['label' => 'Closing balance', 'value' => $statement['closing_balance'] ?? 0, 'format' => 'money'],
            ],
            'columns' => [
                ['key' => 'date_display', 'label' => 'Date', 'width' => '13%'],
                ['key' => 'particulars', 'label' => 'Particulars', 'width' => '34%'],
                ['key' => 'reference', 'label' => 'Reference', 'width' => '15%'],
                ['key' => 'debit', 'label' => 'Debit', 'format' => 'money', 'width' => '12%'],
                ['key' => 'credit', 'label' => 'Credit', 'format' => 'money', 'width' => '12%'],
                ['key' => 'balance', 'label' => 'Balance', 'format' => 'money', 'width' => '14%'],
            ],
            'rows' => $rows,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'balance' => money_round($statement['closing_balance'] ?? 0),
            ],
            'totals_label' => 'Closing',
            'note' => $payload['party_balance_hint'] ?? null,
        ];
    }

    public function weeklyClosing(Request $request): Response|HttpResponse
    {
        return $this->periodClosing($request, 'weekly-closing', 'Weekly Closing', 'week');
    }

    public function monthlyClosing(Request $request): Response|HttpResponse
    {
        return $this->periodClosing($request, 'monthly-closing', 'Monthly Closing', 'month');
    }

    public function grossMargin(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');

        $rows = SaleItem::query()
            ->selectRaw('sale_items.product_id, sale_items.variant_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity_in_sale_unit * sale_items.cost_per_unit) as cost')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', (int) $categoryId))
            ->with(['product:id,name', 'variant:id,name'])
            ->groupBy('sale_items.product_id', 'sale_items.variant_id')
            ->orderByDesc('revenue')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $cost = (float) $row->cost;
                $margin = $revenue - $cost;

                return [
                    'product' => $row->variant?->displayName() ?? $row->product?->name,
                    'qty' => round((float) $row->qty, 4),
                    'revenue' => money_round($revenue),
                    'cost' => money_round($cost),
                    'margin' => money_round($margin),
                    'margin_pct' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0,
                ];
            });

        return $this->generic('gross-margin', 'Gross Margin', [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], $rows, [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'money', 'total' => true],
            ['key' => 'cost', 'label' => 'Cost', 'format' => 'money', 'total' => true],
            ['key' => 'margin', 'label' => 'Margin', 'format' => 'money', 'total' => true],
            ['key' => 'margin_pct', 'label' => 'Margin %', 'align' => 'right'],
        ]);
    }

    public function profitLoss(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $sales = (float) $this->completedSales($branch->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('total');

        $cogs = (float) SaleItem::query()
            ->whereHas('sale', function ($q) use ($branch, $from, $to) {
                $q->where('branch_id', $branch->id)
                    ->where('status', Sale::STATUS_COMPLETED)
                    ->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            })
            ->selectRaw('COALESCE(SUM(quantity_in_sale_unit * cost_per_unit), 0) as cogs')
            ->value('cogs');

        $income = (float) LedgerTransaction::query()
            ->where('direction', 'in')
            ->whereDate('txn_date', '>=', $from)
            ->whereDate('txn_date', '<=', $to)
            ->when($branch->id, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branch->id)->orWhereNull('branch_id')))
            ->sum('amount');

        $expense = (float) LedgerTransaction::query()
            ->where('direction', 'out')
            ->whereDate('txn_date', '>=', $from)
            ->whereDate('txn_date', '<=', $to)
            ->when($branch->id, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branch->id)->orWhereNull('branch_id')))
            ->sum('amount');

        $rows = [
            ['label' => 'Sales revenue', 'amount' => money_round($sales)],
            ['label' => 'Cost of goods', 'amount' => money_round($cogs)],
            ['label' => 'Gross profit', 'amount' => money_round($sales - $cogs)],
            ['label' => 'Other income (ledger)', 'amount' => money_round($income)],
            ['label' => 'Expenses (ledger)', 'amount' => money_round($expense)],
            ['label' => 'Net (approx)', 'amount' => money_round($sales - $cogs + $income - $expense)],
        ];

        // No column totals: these lines are subtotals of each other, so summing them is meaningless.
        return $this->generic('profit-loss', 'Profit & Loss', compact('from', 'to'), $rows, [
            ['key' => 'label', 'label' => 'Line'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
        ]);
    }

    public function shiftsZ(Request $request): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = Shift::query()
            ->with(['opener:id,name'])
            ->where('branch_id', $branch->id)
            ->whereDate('opened_at', '>=', $from)
            ->whereDate('opened_at', '<=', $to)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (Shift $shift) {
                $salesTotal = (float) $shift->sales()->where('status', Sale::STATUS_COMPLETED)->sum('total');
                $paidTotal = (float) $shift->sales()->where('status', Sale::STATUS_COMPLETED)->sum('paid_total');

                return [
                    'id' => $shift->id,
                    'opened_at' => $shift->opened_at ? format_company_datetime($shift->opened_at) : null,
                    'closed_at' => $shift->closed_at ? format_company_datetime($shift->closed_at) : null,
                    'opener' => $shift->opener?->name,
                    'opening_cash' => money_round($shift->opening_cash),
                    'closing_cash' => money_round($shift->closing_cash ?? 0),
                    'expected_cash' => money_round($shift->expected_cash ?? 0),
                    'sales_total' => money_round($salesTotal),
                    'paid_total' => money_round($paidTotal),
                ];
            });

        return $this->generic('shifts-z', 'Z Report', compact('from', 'to'), $rows, [
            ['key' => 'id', 'label' => 'Shift'],
            ['key' => 'opened_at', 'label' => 'Opened'],
            ['key' => 'closed_at', 'label' => 'Closed'],
            ['key' => 'opener', 'label' => 'Opened by'],
            ['key' => 'opening_cash', 'label' => 'Opening', 'format' => 'money'],
            ['key' => 'expected_cash', 'label' => 'Expected', 'format' => 'money'],
            ['key' => 'closing_cash', 'label' => 'Closing', 'format' => 'money'],
            ['key' => 'sales_total', 'label' => 'Sales', 'format' => 'money', 'total' => true],
            ['key' => 'paid_total', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function dateRange(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return [(string) $from, (string) $to];
    }

    protected function completedSales(int $branchId)
    {
        return Sale::query()
            ->where('branch_id', $branchId)
            ->where('status', Sale::STATUS_COMPLETED);
    }

    /**
     * Grouping expression that buckets a timestamp column into ISO weeks ("2026-W33")
     * or calendar months ("2026-08"), using the syntax of the active database driver.
     */
    protected function periodExpression(string $unit, string $column): string
    {
        $driver = (new Sale)->getConnection()->getDriverName();
        $isWeek = $unit === 'week';

        return match ($driver) {
            'pgsql' => $isWeek
                ? "to_char({$column}, 'IYYY-\"W\"IW')"
                : "to_char({$column}, 'YYYY-MM')",
            'sqlite' => $isWeek
                ? "strftime('%Y-W%W', {$column})"
                : "strftime('%Y-%m', {$column})",
            default => $isWeek
                ? "DATE_FORMAT({$column}, '%x-W%v')"
                : "DATE_FORMAT({$column}, '%Y-%m')",
        };
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

    protected function itemSalesReport(Request $request, string $key, string $title, string $orderBy): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);
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
            ->orderByDesc($orderBy === 'qty' ? 'qty' : 'amount')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'product' => $row->variant?->displayName() ?? $row->product?->name,
                'qty' => round((float) $row->qty, 4),
                'amount' => money_round($row->amount),
            ]);

        return $this->generic($key, $title, [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], $rows, [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
        ]);
    }

    protected function periodClosing(Request $request, string $key, string $title, string $unit): Response|HttpResponse
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);
        $periodExpr = $this->periodExpression($unit, 'created_at');

        $rows = $this->completedSales($branch->id)
            ->selectRaw("{$periodExpr} as period, COUNT(*) as count, SUM(total) as total, SUM(tax_total) as tax, SUM(discount_total) as discount, SUM(paid_total) as paid")
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy(DB::raw($periodExpr))
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'count' => (int) $row->count,
                'total' => money_round($row->total),
                'discount' => money_round($row->discount),
                'tax' => money_round($row->tax),
                'paid' => money_round($row->paid),
            ]);

        return $this->generic($key, $title, compact('from', 'to'), $rows, [
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'count', 'label' => 'Sales', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'tax', 'label' => 'Tax', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  iterable<int, mixed>  $rows
     * @param  list<array{key: string, label: string}>  $columns
     * @param  array<string, mixed>|null  $summary
     */
    protected function generic(string $reportKey, string $title, array $filters, iterable $rows, array $columns, ?array $summary = null): Response|HttpResponse
    {
        $rows = collect($rows)->all();

        if ($this->wantsPdf()) {
            return app(ReportPdfService::class)->download([
                'key' => $reportKey,
                'title' => $title,
                'meta' => ReportPdfService::periodMeta(
                    $filters['from'] ?? null,
                    $filters['to'] ?? null,
                    BranchContext::ensure()->name,
                ),
                'columns' => $columns,
                'rows' => $rows,
                'summary' => $this->pdfSummary($summary),
                'totals' => $this->columnTotals($rows, $columns),
            ]);
        }

        return Inertia::render('Admin/Reports/Generic', [
            'reportKey' => $reportKey,
            'title' => $title,
            'filters' => $filters,
            'rows' => $rows,
            'columns' => $columns,
            'summary' => $summary,
            'categories' => $this->categoryOptions(),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    /**
     * Reports render as Inertia pages by default and as a PDF download when the
     * screen's own URL is re-requested with ?export=pdf, so the document always
     * matches the filters the user is looking at.
     */
    protected function wantsPdf(): bool
    {
        return request()->input('export') === 'pdf';
    }

    /**
     * Convert a report's keyed summary block into the label/value pairs the
     * PDF header band expects.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<int, array{label: string, value: mixed, format: string}>
     */
    protected function pdfSummary(?array $summary): array
    {
        if (! $summary) {
            return [];
        }

        $labels = [
            'orders' => 'Orders',
            'total_discount' => 'Total discount',
            'foc_value' => 'FOC value',
        ];

        $out = [];

        foreach ($summary as $key => $value) {
            $isCount = in_array($key, ['orders', 'count', 'sales'], true);

            $out[] = [
                'label' => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'value' => $value,
                'format' => $isCount ? ReportPdfService::FORMAT_INT : ReportPdfService::FORMAT_MONEY,
            ];
        }

        return $out;
    }

    /**
     * Sum only the columns that explicitly opt in with `'total' => true`.
     * Running balances and derived lines (P&L subtotals) must never be summed,
     * so this is deliberately not automatic.
     *
     * @param  array<int, mixed>  $rows
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, float>
     */
    protected function columnTotals(array $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            if (empty($column['total'])) {
                continue;
            }

            $sum = 0.0;
            foreach ($rows as $row) {
                $row = is_array($row) ? $row : (array) $row;
                $sum += (float) ($row[$column['key']] ?? 0);
            }

            $totals[$column['key']] = $column['format'] === ReportPdfService::FORMAT_MONEY
                ? money_round($sum)
                : round($sum, 4);
        }

        return $totals;
    }
}
