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
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportHubController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.sales');
    }

    public function dailySales(Request $request): Response
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = $this->completedSales($branch->id)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count, SUM(total) as total, SUM(tax_total) as tax, SUM(discount_total) as discount, SUM(paid_total) as paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        return $this->generic('daily-sales', 'Daily Sales', compact('from', 'to'), $rows, [
            ['key' => 'day', 'label' => 'Date'],
            ['key' => 'count', 'label' => 'Sales'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'discount', 'label' => 'Discount'],
            ['key' => 'tax', 'label' => 'Tax'],
            ['key' => 'paid', 'label' => 'Paid'],
        ]);
    }

    public function paymentMethods(Request $request): Response
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
            ->get();

        return $this->generic('payment-methods', 'Payment Methods', compact('from', 'to'), $rows, [
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'count', 'label' => 'Count'],
            ['key' => 'total', 'label' => 'Total'],
        ]);
    }

    public function moneySourceTxns(Request $request, \App\Services\MoneySourceTxnReportService $report): Response
    {
        $payload = $report->build($request->all());

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

    public function foc(Request $request): Response
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
            'foc_value' => round((float) (clone $base)->sum('discount_total'), 2),
        ];

        $sales = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'created_at' => format_company_datetime($sale->created_at),
                'customer' => $sale->customer?->name ?: 'Walk-in',
                'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
                'item_count' => (int) $sale->items_count,
                'foc_value' => round((float) $sale->discount_total, 2),
                'total' => round((float) $sale->total, 2),
                'paid' => round((float) $sale->paid_total, 2),
            ]);

        return Inertia::render('Admin/Reports/Foc', [
            'reportKey' => 'foc',
            'title' => 'FOC',
            'filters' => compact('from', 'to') + ['per_page' => $perPage],
            'summary' => $summary,
            'sales' => $sales,
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    public function orderHistory(Request $request): Response
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
            'total' => round((float) (clone $base)->sum('total'), 2),
            'paid' => round((float) (clone $base)->sum('paid_total'), 2),
            'discount' => round((float) (clone $base)->sum('discount_total'), 2),
        ];
        $summary['due'] = round(max(0, $summary['total'] - $summary['paid']), 2);

        $sales = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Sale $sale) {
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
                    'total' => round($total, 2),
                    'paid' => round($paid, 2),
                    'discount' => round((float) $sale->discount_total, 2),
                ];
            });

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

    public function topItems(Request $request): Response
    {
        return $this->itemSalesReport($request, 'top-items', 'Top Selling Items', 'qty');
    }

    public function salesByCategory(Request $request): Response
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
                'qty' => round((float) $row->qty, 2),
                'amount' => round((float) $row->amount, 2),
            ]);

        return $this->generic('sales-by-category', 'Sales by Category', compact('from', 'to'), $rows, [
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'qty', 'label' => 'Qty'],
            ['key' => 'amount', 'label' => 'Amount'],
        ]);
    }

    public function discounts(Request $request): Response
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $base = $this->completedSales($branch->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('discount_total', '>', 0.01);

        $summary = [
            'orders' => (clone $base)->count(),
            'total_discount' => round((float) (clone $base)->sum('discount_total'), 2),
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
                'discount' => round((float) $sale->discount_total, 2),
                'total' => round((float) $sale->total, 2),
            ]);

        return $this->generic('discounts', 'Discounts', compact('from', 'to'), $rows, [
            ['key' => 'number', 'label' => 'Sale'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'cashier', 'label' => 'Cashier'],
            ['key' => 'discount', 'label' => 'Discount'],
            ['key' => 'total', 'label' => 'Total'],
        ], $summary);
    }

    public function taxSummary(Request $request): Response
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->selectRaw('COALESCE(sale_items.tax_name, "No tax") as tax_name, SUM(sale_items.tax_amount) as tax_amount, SUM(sale_items.line_total) as taxable')
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
                'taxable' => round((float) $row->taxable, 2),
                'tax_amount' => round((float) $row->tax_amount, 2),
            ]);

        return $this->generic('tax-summary', 'Tax Summary', compact('from', 'to'), $rows, [
            ['key' => 'tax_name', 'label' => 'Tax'],
            ['key' => 'taxable', 'label' => 'Taxable'],
            ['key' => 'tax_amount', 'label' => 'Tax amount'],
        ]);
    }

    public function receivables(): Response
    {
        $rows = Customer::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => round((float) $c->balance, 2),
            ]);

        return $this->generic('receivables', 'Accounts Receivable', [], $rows, [
            ['key' => 'name', 'label' => 'Customer'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance'],
        ]);
    }

    public function payables(): Response
    {
        $rows = Supplier::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Supplier $s) => [
                'name' => $s->name,
                'phone' => $s->phone,
                'balance' => round((float) $s->balance, 2),
            ]);

        return $this->generic('payables', 'Accounts Payable', [], $rows, [
            ['key' => 'name', 'label' => 'Supplier'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance'],
        ]);
    }

    public function customerCredits(Request $request): Response
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
                'amount' => round((float) $p->amount, 2),
                'notes' => $p->notes,
            ]);

        return $this->generic('customer-credits', 'Customer Credits', compact('from', 'to'), $rows, [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function supplierPayments(Request $request): Response
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
                'amount' => round((float) $p->amount, 2),
                'notes' => $p->notes,
            ]);

        return $this->generic('supplier-payments', 'Supplier Payments', compact('from', 'to'), $rows, [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function purchases(Request $request): Response
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
                'total' => round((float) $p->total, 2),
                'paid' => round((float) ($p->paid_amount ?? 0), 2),
                'status' => $p->payment_status ?? $p->status,
            ]);

        return $this->generic('purchases', 'Purchases', compact('from', 'to'), $rows, [
            ['key' => 'number', 'label' => 'Purchase'],
            ['key' => 'purchase_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'paid', 'label' => 'Paid'],
            ['key' => 'status', 'label' => 'Status'],
        ]);
    }

    public function expenses(Request $request): Response
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
                'amount' => round((float) $t->amount, 2),
                'notes' => $t->notes,
            ]);

        return $this->generic('expenses', 'Expenses', compact('from', 'to'), $rows, [
            ['key' => 'txn_date', 'label' => 'Date'],
            ['key' => 'account', 'label' => 'Account'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'notes', 'label' => 'Notes'],
        ]);
    }

    public function accountStatement(Request $request, \App\Services\AccountStatementService $statements): Response
    {
        return Inertia::render(
            'Admin/Reports/AccountStatement',
            $statements->build([
                'type' => $request->input('type'),
                'party_id' => $request->input('party_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'branch_id' => $request->input('branch_id'),
            ]),
        );
    }

    public function weeklyClosing(Request $request): Response
    {
        return $this->periodClosing($request, 'weekly-closing', 'Weekly Closing', 'YEARWEEK(created_at, 3)');
    }

    public function monthlyClosing(Request $request): Response
    {
        return $this->periodClosing($request, 'monthly-closing', 'Monthly Closing', "DATE_FORMAT(created_at, '%Y-%m')");
    }

    public function grossMargin(Request $request): Response
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
                    'qty' => round((float) $row->qty, 2),
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($margin, 2),
                    'margin_pct' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0,
                ];
            });

        return $this->generic('gross-margin', 'Gross Margin', [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], $rows, [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty'],
            ['key' => 'revenue', 'label' => 'Revenue'],
            ['key' => 'cost', 'label' => 'Cost'],
            ['key' => 'margin', 'label' => 'Margin'],
            ['key' => 'margin_pct', 'label' => 'Margin %'],
        ]);
    }

    public function profitLoss(Request $request): Response
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
            ['label' => 'Sales revenue', 'amount' => round($sales, 2)],
            ['label' => 'Cost of goods', 'amount' => round($cogs, 2)],
            ['label' => 'Gross profit', 'amount' => round($sales - $cogs, 2)],
            ['label' => 'Other income (ledger)', 'amount' => round($income, 2)],
            ['label' => 'Expenses (ledger)', 'amount' => round($expense, 2)],
            ['label' => 'Net (approx)', 'amount' => round($sales - $cogs + $income - $expense, 2)],
        ];

        return $this->generic('profit-loss', 'Profit & Loss', compact('from', 'to'), $rows, [
            ['key' => 'label', 'label' => 'Line'],
            ['key' => 'amount', 'label' => 'Amount'],
        ]);
    }

    public function shiftsZ(Request $request): Response
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
                    'opening_cash' => round((float) $shift->opening_cash, 2),
                    'closing_cash' => round((float) ($shift->closing_cash ?? 0), 2),
                    'expected_cash' => round((float) ($shift->expected_cash ?? 0), 2),
                    'sales_total' => round($salesTotal, 2),
                    'paid_total' => round($paidTotal, 2),
                ];
            });

        return $this->generic('shifts-z', 'Z Report', compact('from', 'to'), $rows, [
            ['key' => 'id', 'label' => 'Shift'],
            ['key' => 'opened_at', 'label' => 'Opened'],
            ['key' => 'closed_at', 'label' => 'Closed'],
            ['key' => 'opener', 'label' => 'Opened by'],
            ['key' => 'opening_cash', 'label' => 'Opening'],
            ['key' => 'expected_cash', 'label' => 'Expected'],
            ['key' => 'closing_cash', 'label' => 'Closing'],
            ['key' => 'sales_total', 'label' => 'Sales'],
            ['key' => 'paid_total', 'label' => 'Paid'],
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

    protected function itemSalesReport(Request $request, string $key, string $title, string $orderBy): Response
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
                'qty' => round((float) $row->qty, 2),
                'amount' => round((float) $row->amount, 2),
            ]);

        return $this->generic($key, $title, [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], $rows, [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty'],
            ['key' => 'amount', 'label' => 'Amount'],
        ]);
    }

    protected function periodClosing(Request $request, string $key, string $title, string $periodExpr): Response
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($request);

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
                'total' => round((float) $row->total, 2),
                'discount' => round((float) $row->discount, 2),
                'tax' => round((float) $row->tax, 2),
                'paid' => round((float) $row->paid, 2),
            ]);

        return $this->generic($key, $title, compact('from', 'to'), $rows, [
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'count', 'label' => 'Sales'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'discount', 'label' => 'Discount'],
            ['key' => 'tax', 'label' => 'Tax'],
            ['key' => 'paid', 'label' => 'Paid'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  iterable<int, mixed>  $rows
     * @param  list<array{key: string, label: string}>  $columns
     * @param  array<string, mixed>|null  $summary
     */
    protected function generic(string $reportKey, string $title, array $filters, iterable $rows, array $columns, ?array $summary = null): Response
    {
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
}
