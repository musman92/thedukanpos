<?php

namespace App\Services;

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
use App\Support\AddonRegistry;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the operational reports. The web screens and the phone API both
 * read this, so a report on the phone is the same list and the same totals as
 * the back office.
 */
class OperationalReportService
{
    /**
     * Same order and labels as resources/js/lib/reportCatalog.js.
     *
     * @return list<array{id: string, name: string, group: string}>
     */
    public function catalog(): array
    {
        $items = [
            ['id' => 'sales', 'name' => 'Sales', 'group' => 'sales'],
            ['id' => 'products', 'name' => 'Sales by Item', 'group' => 'sales'],
            ['id' => 'top-items', 'name' => 'Top Selling Items', 'group' => 'sales'],
            ['id' => 'daily-sales', 'name' => 'Daily Sales', 'group' => 'sales'],
            ['id' => 'sales-by-category', 'name' => 'Sales by Category', 'group' => 'sales'],
            ['id' => 'shifts-z', 'name' => 'Z Report', 'group' => 'closings'],
            ['id' => 'payment-methods', 'name' => 'Payment Methods', 'group' => 'sales'],
            ['id' => 'money-source-txns', 'name' => 'Transactions by Money Source', 'group' => 'sales'],
            ['id' => 'foc', 'name' => 'FOC', 'group' => 'sales'],
            ['id' => 'order-history', 'name' => 'Order History', 'group' => 'sales'],
            ['id' => 'discounts', 'name' => 'Discounts', 'group' => 'sales'],
            ['id' => 'tax-summary', 'name' => 'Tax Summary', 'group' => 'sales'],
            ['id' => 'gross-margin', 'name' => 'Gross Margin', 'group' => 'financial'],
            ['id' => 'profit-loss', 'name' => 'Profit & Loss', 'group' => 'financial'],
            ['id' => 'expenses', 'name' => 'Expenses', 'group' => 'financial'],
            ['id' => 'account-statement', 'name' => 'Account Statement', 'group' => 'financial'],
            ['id' => 'weekly-closing', 'name' => 'Weekly Closing', 'group' => 'closings'],
            ['id' => 'monthly-closing', 'name' => 'Monthly Closing', 'group' => 'closings'],
            ['id' => 'receivables', 'name' => 'Accounts Receivable', 'group' => 'outstanding'],
            ['id' => 'payables', 'name' => 'Accounts Payable', 'group' => 'outstanding'],
            ['id' => 'customer-credits', 'name' => 'Customer Credits', 'group' => 'outstanding'],
            ['id' => 'supplier-payments', 'name' => 'Supplier Payments', 'group' => 'outstanding'],
            ['id' => 'purchases', 'name' => 'Purchases', 'group' => 'purchasing'],
        ];

        if (! app(AddonRegistry::class)->capability('shifts.enabled', false)) {
            $items = array_values(array_filter($items, fn (array $item) => $item['id'] !== 'shifts-z'));
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function document(string $key, array $input): array
    {
        $known = collect($this->catalog())->contains(fn (array $item) => $item['id'] === $key);
        if (! $known) {
            abort(404, 'That report is not available.');
        }

        return match ($key) {
            'sales' => $this->sales($input),
            'products' => $this->products($input),
            'top-items' => $this->itemSales($input, 'top-items', 'Top Selling Items', 'qty'),
            'daily-sales' => $this->dailySales($input),
            'sales-by-category' => $this->salesByCategory($input),
            'shifts-z' => $this->shiftsZ($input),
            'payment-methods' => $this->paymentMethods($input),
            'money-source-txns' => $this->moneySourceTxns($input),
            'foc' => $this->foc($input),
            'order-history' => $this->orderHistory($input),
            'discounts' => $this->discounts($input),
            'tax-summary' => $this->taxSummary($input),
            'gross-margin' => $this->grossMargin($input),
            'profit-loss' => $this->profitLoss($input),
            'expenses' => $this->expenses($input),
            'account-statement' => $this->accountStatement($input),
            'weekly-closing' => $this->periodClosing($input, 'weekly-closing', 'Weekly Closing', 'week'),
            'monthly-closing' => $this->periodClosing($input, 'monthly-closing', 'Monthly Closing', 'month'),
            'receivables' => $this->receivables(),
            'payables' => $this->payables(),
            'customer-credits' => $this->customerCredits($input),
            'supplier-payments' => $this->supplierPayments($input),
            'purchases' => $this->purchases($input),
            default => abort(404, 'That report is not available.'),
        };
    }

    /**
     * Sum only the columns that explicitly opt in with `'total' => true`.
     * Running balances and derived lines (P&L subtotals) must never be summed,
     * so this is deliberately not automatic. Web PDFs and the phone share this.
     *
     * @param  array<int, mixed>  $rows
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, float>
     */
    public function columnTotals(array $rows, array $columns): array
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

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string}
     */
    public function dateRange(array $input): array
    {
        $from = (string) ($input['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($input['to'] ?? now()->toDateString());

        return [$from, $to];
    }

    /**
     * @return array{count: int, total: float, paid: float, tax: float, discount: float}
     */
    public function salesSummary(int $branchId, string $from, string $to): array
    {
        $base = $this->completedSales($branchId, $from, $to);

        return [
            'count' => (clone $base)->count(),
            'total' => money_round((clone $base)->sum('total')),
            'paid' => money_round((clone $base)->sum('paid_total')),
            'tax' => money_round((clone $base)->sum('tax_total')),
            'discount' => money_round((clone $base)->sum('discount_total')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapSalesListRow(Sale $sale): array
    {
        $cashierName = $sale->cashier?->name ?: $sale->cashier?->username;

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'created_at' => format_company_datetime($sale->created_at),
            'cashier_name' => $cashierName,
            'cashier' => $sale->cashier
                ? ['id' => $sale->cashier->id, 'name' => $cashierName]
                : null,
            'total' => money_round($sale->total),
            'paid_total' => money_round($sale->paid_total),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function productSalesItems(int $branchId, string $from, string $to, mixed $categoryId)
    {
        return SaleItem::query()
            ->selectRaw('sale_items.variant_id, sale_items.product_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as amount')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', (int) $categoryId))
            ->with(['variant', 'product'])
            ->groupBy('sale_items.variant_id', 'sale_items.product_id')
            ->orderByDesc('amount')
            ->limit(100)
            ->get();
    }

    /**
     * PDF payload for an account statement. Same lines the statement screen lists.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function accountStatementPdf(array $payload): array
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

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sales(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $summary = $this->salesSummary($branch->id, $from, $to);

        $rows = $this->completedSales($branch->id, $from, $to)
            ->with('cashier')
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (Sale $sale) => $this->mapSalesListRow($sale))
            ->all();

        $note = $summary['count'] > count($rows)
            ? 'Showing the latest 500 sales. Totals above include every sale in this period.'
            : null;

        return $this->doc('sales', 'Sales', compact('from', 'to'), ['dates'], [
            ['key' => 'number', 'label' => 'Number'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'cashier_name', 'label' => 'Cashier'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money'],
            ['key' => 'paid_total', 'label' => 'Paid', 'format' => 'money'],
        ], $rows, $summary, $this->cards([
            ['Orders', $summary['count'], 'int'],
            ['Total sale', $summary['total'], 'money'],
            ['Paid', $summary['paid'], 'money'],
            ['Discount', $summary['discount'], 'money'],
            ['Tax', $summary['tax'], 'money'],
        ]), [
            'total' => $summary['count'],
            'note' => $note,
        ]);
    }

    /**
     * Sales in this branch and period. Shared by the web list and the phone.
     */
    public function salesQuery(int $branchId, string $from, string $to)
    {
        return $this->completedSales($branchId, $from, $to);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function products(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $categoryId = $input['category_id'] ?? null;

        $rows = $this->productSalesItems($branch->id, $from, $to, $categoryId)
            ->map(function ($row) {
                $name = $row->variant?->displayName() ?? $row->product?->name;
                $code = $row->variant?->short_code;
                if ($code) {
                    $name = trim($name.' '.$code);
                }

                return [
                    'product' => $name,
                    'qty' => round((float) $row->qty, 4),
                    'amount' => money_round($row->amount),
                ];
            })
            ->all();

        return $this->doc('products', 'Sales by Item', [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], ['dates', 'category'], [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty sold', 'format' => 'qty'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
        ], $rows, null, [], ['categories' => $this->categoryOptions()]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function itemSales(array $input, string $key, string $title, string $orderBy): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $categoryId = $input['category_id'] ?? null;

        $rows = SaleItem::query()
            ->selectRaw('sale_items.variant_id, sale_items.product_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as amount')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
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
            ])
            ->all();

        return $this->doc($key, $title, [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], ['dates', 'category'], [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
        ], $rows, null, [], ['categories' => $this->categoryOptions()]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function dailySales(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $rows = $this->completedSales($branch->id, $from, $to)
            ->selectRaw('business_date as day, COUNT(*) as count, SUM(total) as total, SUM(tax_total) as tax, SUM(discount_total) as discount, SUM(paid_total) as paid')
            ->groupBy('business_date')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => format_company_date($row->day),
                'count' => (int) $row->count,
                'total' => money_round($row->total),
                'discount' => money_round($row->discount),
                'tax' => money_round($row->tax),
                'paid' => money_round($row->paid),
            ])
            ->all();

        return $this->doc('daily-sales', 'Daily Sales', compact('from', 'to'), ['dates'], [
            ['key' => 'day', 'label' => 'Date'],
            ['key' => 'count', 'label' => 'Sales', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'tax', 'label' => 'Tax', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function salesByCategory(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $rows = SaleItem::query()
            ->selectRaw('categories.name as category, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as amount')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->groupBy('categories.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category ?: 'Uncategorized',
                'qty' => round((float) $row->qty, 4),
                'amount' => money_round($row->amount),
            ])
            ->all();

        return $this->doc('sales-by-category', 'Sales by Category', compact('from', 'to'), ['dates'], [
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function paymentMethods(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $rows = SalePayment::query()
            ->selectRaw('money_sources.name as money_source, SUM(sale_payments.amount) as total, COUNT(*) as count')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('money_sources', 'money_sources.id', '=', 'sale_payments.money_source_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->groupBy('money_sources.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'money_source' => $row->money_source,
                'count' => (int) $row->count,
                'total' => money_round($row->total),
            ])
            ->all();

        return $this->doc('payment-methods', 'Payment Methods', compact('from', 'to'), ['dates'], [
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'count', 'label' => 'Count', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function moneySourceTxns(array $input): array
    {
        $payload = app(MoneySourceTxnReportService::class)->build($input + ['export' => 'pdf']);
        $txns = array_map(function (array $row) {
            unset($row['sort_key'], $row['date_raw']);

            return $row;
        }, $payload['all_rows']);

        $sourceColumns = [
            ['key' => 'money_source', 'label' => 'Money source'],
            ['key' => 'in', 'label' => 'In', 'format' => 'money'],
            ['key' => 'out', 'label' => 'Out', 'format' => 'money'],
            ['key' => 'net', 'label' => 'Net', 'format' => 'money'],
        ];
        $txnColumns = [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'money_source', 'label' => 'Money source'],
            ['key' => 'direction', 'label' => 'Type'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'type', 'label' => 'Txn type'],
            ['key' => 'branch', 'label' => 'Branch'],
        ];
        $summary = $payload['summary'];

        return $this->doc(
            'money-source-txns',
            'Transactions by Money Source',
            $payload['filters'],
            ['dates', 'money_source', 'direction', 'transfers'],
            $txnColumns,
            $txns,
            $summary,
            $this->cards([
                ['Transactions', $summary['transactions'], 'int'],
                ['Total in', $summary['total_in'], 'money'],
                ['Total out', $summary['total_out'], 'money'],
                ['Net', $summary['net'], 'money'],
            ]),
            [
                'total' => $summary['transactions'],
                'money_sources' => $payload['money_sources'],
                'sections' => [
                    ['title' => 'Totals by money source', 'columns' => $sourceColumns, 'rows' => $payload['by_source']],
                    ['title' => 'Transactions', 'columns' => $txnColumns, 'rows' => $txns],
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function foc(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $base = $this->completedSales($branch->id, $from, $to)
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

        $rows = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'created_at' => format_company_datetime($sale->created_at),
                'customer' => $sale->customer?->name ?: 'Walk-in',
                'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
                'item_count' => (int) $sale->items_count,
                'foc_value' => money_round($sale->discount_total),
                'total' => money_round($sale->total),
                'paid' => money_round($sale->paid_total),
            ])
            ->all();

        return $this->doc('foc', 'FOC', compact('from', 'to'), ['dates'], [
            ['key' => 'number', 'label' => 'Sale'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'cashier', 'label' => 'Cashier'],
            ['key' => 'item_count', 'label' => 'Items', 'format' => 'int', 'total' => true],
            ['key' => 'foc_value', 'label' => 'FOC value', 'format' => 'money', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ], $rows, $summary, $this->cards([
            ['FOC orders', $summary['orders'], 'int'],
            ['Total FOC value', $summary['foc_value'], 'money'],
        ]), ['total' => $summary['orders']]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function orderHistory(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $customerId = isset($input['customer_id']) && $input['customer_id'] !== ''
            ? (int) $input['customer_id']
            : null;
        $orderNumber = trim((string) ($input['order_number'] ?? ''));

        $base = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('status', '!=', Sale::STATUS_PARKED)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($orderNumber !== '', fn ($q) => $q->where('number', 'like', '%'.$orderNumber.'%'));

        $summary = [
            'orders' => (clone $base)->count(),
            'total' => money_round((clone $base)->sum('total')),
            'paid' => money_round((clone $base)->sum('paid_total')),
            'discount' => money_round((clone $base)->sum('discount_total')),
        ];
        $summary['due'] = money_round(max(0, $summary['total'] - $summary['paid']));

        $rows = (clone $base)
            ->with(['cashier:id,name,username', 'customer:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get()
            ->map(function (Sale $sale) {
                $total = (float) $sale->total;
                $paid = (float) $sale->paid_total;
                $payment = $paid + 0.01 >= $total ? 'Paid' : ($paid > 0.01 ? 'Partial' : 'Pending');

                return [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'created_at' => format_company_datetime($sale->created_at),
                    'status' => $sale->status,
                    'is_delivery' => (bool) $sale->is_delivery,
                    'payment_status' => $paid + 0.01 >= $total
                        ? 'paid'
                        : ($paid > 0.01 ? 'partial' : 'pending'),
                    'status_label' => $sale->status === Sale::STATUS_VOID ? 'Cancelled' : $payment,
                    'customer' => $sale->customer?->name ?: 'Walk-in',
                    'cashier' => $sale->cashier?->name ?: $sale->cashier?->username,
                    'item_count' => (int) $sale->items_count,
                    'total' => money_round($total),
                    'paid' => money_round($paid),
                    'discount' => money_round($sale->discount_total),
                ];
            })
            ->all();

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        return $this->doc('order-history', 'Order History', [
            'from' => $from,
            'to' => $to,
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
        ], ['dates', 'customer', 'order_number'], [
            ['key' => 'number', 'label' => 'Order #'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'cashier', 'label' => 'Cashier'],
            ['key' => 'status_label', 'label' => 'Status'],
            ['key' => 'item_count', 'label' => 'Items', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ], $rows, $summary, $this->cards([
            ['Orders', $summary['orders'], 'int'],
            ['Total sale', $summary['total'], 'money'],
            ['Paid', $summary['paid'], 'money'],
            ['Due', $summary['due'], 'money'],
        ]), [
            'total' => $summary['orders'],
            'customers' => $customers,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function discounts(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $base = $this->completedSales($branch->id, $from, $to)
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
            ])
            ->all();

        return $this->doc('discounts', 'Discounts', compact('from', 'to'), ['dates'], [
            ['key' => 'number', 'label' => 'Sale'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'cashier', 'label' => 'Cashier'],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
        ], $rows, $summary, $this->cards([
            ['Orders with discount', $summary['orders'], 'int'],
            ['Total discount', $summary['total_discount'], 'money'],
        ]), ['total' => $summary['orders']]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function taxSummary(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $rows = SaleItem::query()
            ->selectRaw('COALESCE(sale_items.tax_name, ?) as tax_name, SUM(sale_items.tax_amount) as tax_amount, SUM(sale_items.line_total) as taxable', ['No tax'])
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->groupBy('sale_items.tax_name')
            ->orderByDesc('tax_amount')
            ->get()
            ->map(fn ($row) => [
                'tax_name' => $row->tax_name,
                'taxable' => money_round($row->taxable),
                'tax_amount' => money_round($row->tax_amount),
            ])
            ->all();

        return $this->doc('tax-summary', 'Tax Summary', compact('from', 'to'), ['dates'], [
            ['key' => 'tax_name', 'label' => 'Tax'],
            ['key' => 'taxable', 'label' => 'Taxable', 'format' => 'money', 'total' => true],
            ['key' => 'tax_amount', 'label' => 'Tax amount', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function receivables(): array
    {
        $rows = Customer::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => money_round($c->balance),
            ])
            ->all();

        return $this->doc('receivables', 'Accounts Receivable', [], [], [
            ['key' => 'name', 'label' => 'Customer'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function payables(): array
    {
        $rows = Supplier::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Supplier $s) => [
                'name' => $s->name,
                'phone' => $s->phone,
                'balance' => money_round($s->balance),
            ])
            ->all();

        return $this->doc('payables', 'Accounts Payable', [], [], [
            ['key' => 'name', 'label' => 'Supplier'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function customerCredits(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

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
            ])
            ->all();

        return $this->doc('customer-credits', 'Customer Credits', compact('from', 'to'), ['dates'], [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function supplierPayments(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

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
            ])
            ->all();

        return $this->doc('supplier-payments', 'Supplier Payments', compact('from', 'to'), ['dates'], [
            ['key' => 'payment_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function purchases(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

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
            ])
            ->all();

        return $this->doc('purchases', 'Purchases', compact('from', 'to'), ['dates'], [
            ['key' => 'number', 'label' => 'Purchase'],
            ['key' => 'purchase_date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
            ['key' => 'status', 'label' => 'Status'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function expenses(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

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
            ])
            ->all();

        return $this->doc('expenses', 'Expenses', compact('from', 'to'), ['dates'], [
            ['key' => 'txn_date', 'label' => 'Date'],
            ['key' => 'account', 'label' => 'Account'],
            ['key' => 'money_source', 'label' => 'Source'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'total' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function accountStatement(array $input): array
    {
        $payload = app(AccountStatementService::class)->build([
            'type' => $input['type'] ?? null,
            'party_id' => $input['party_id'] ?? null,
            'from' => $input['from'] ?? null,
            'to' => $input['to'] ?? null,
            'branch_id' => $input['branch_id'] ?? null,
        ]);

        $types = [
            ['id' => 'customer', 'name' => 'Customer'],
            ['id' => 'supplier', 'name' => 'Supplier'],
            ['id' => 'employee', 'name' => 'Employee'],
        ];

        if (empty($payload['statement'])) {
            $type = $payload['type'] ?? 'customer';
            $options = $payload['parties'][$type] ?? [];
            $rows = collect($options)->map(fn (array $party) => [
                'id' => $party['value'],
                'name' => $party['label'],
                'detail' => $party['meta'],
            ])->all();

            return $this->doc('account-statement', 'Account Statement', $payload['filters'], ['dates', 'party', 'branch'], [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'detail', 'label' => 'Detail'],
            ], $rows, null, [], [
                'pick' => 'party_id',
                'party_types' => $types,
                'branch' => $payload['branch'],
                'branches' => $payload['branches'],
                'note' => 'Choose a '.$payload['type_label'].' to see the statement.',
            ]);
        }

        $pdf = $this->accountStatementPdf($payload);

        return $this->doc(
            'account-statement',
            'Account Statement',
            $payload['filters'],
            ['dates', 'party', 'branch'],
            $pdf['columns'],
            $pdf['rows'],
            null,
            $pdf['summary'],
            [
                'party_types' => $types,
                'branch' => $payload['branch'],
                'branches' => $payload['branches'],
                'note' => $pdf['note'],
                'party_name' => $payload['party']['name'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function grossMargin(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $categoryId = $input['category_id'] ?? null;

        $rows = SaleItem::query()
            ->selectRaw('sale_items.product_id, sale_items.variant_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity_in_sale_unit * sale_items.cost_per_unit) as cost')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
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
            })
            ->all();

        return $this->doc('gross-margin', 'Gross Margin', [
            'from' => $from,
            'to' => $to,
            'category_id' => $categoryId,
        ], ['dates', 'category'], [
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'format' => 'qty', 'total' => true],
            ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'money', 'total' => true],
            ['key' => 'cost', 'label' => 'Cost', 'format' => 'money', 'total' => true],
            ['key' => 'margin', 'label' => 'Margin', 'format' => 'money', 'total' => true],
            ['key' => 'margin_pct', 'label' => 'Margin %'],
        ], $rows, null, [], ['categories' => $this->categoryOptions()]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function profitLoss(array $input): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

        $sales = (float) $this->completedSales($branch->id, $from, $to)->sum('total');

        $cogs = (float) SaleItem::query()
            ->whereHas('sale', function ($q) use ($branch, $from, $to) {
                $q->where('branch_id', $branch->id)
                    ->where('status', Sale::STATUS_COMPLETED)
                    ->whereDate('business_date', '>=', $from)
                    ->whereDate('business_date', '<=', $to);
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

        return $this->doc('profit-loss', 'Profit & Loss', compact('from', 'to'), ['dates'], [
            ['key' => 'label', 'label' => 'Line'],
            ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function shiftsZ(array $input): array
    {
        if (! app(AddonRegistry::class)->capability('shifts.enabled', false)) {
            abort(404);
        }

        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);

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
            })
            ->all();

        return $this->doc('shifts-z', 'Z Report', compact('from', 'to'), ['dates'], [
            ['key' => 'id', 'label' => 'Shift'],
            ['key' => 'opened_at', 'label' => 'Opened'],
            ['key' => 'closed_at', 'label' => 'Closed'],
            ['key' => 'opener', 'label' => 'Opened by'],
            ['key' => 'opening_cash', 'label' => 'Opening', 'format' => 'money'],
            ['key' => 'expected_cash', 'label' => 'Expected', 'format' => 'money'],
            ['key' => 'closing_cash', 'label' => 'Closing', 'format' => 'money'],
            ['key' => 'sales_total', 'label' => 'Sales', 'format' => 'money', 'total' => true],
            ['key' => 'paid_total', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function periodClosing(array $input, string $key, string $title, string $unit): array
    {
        $branch = BranchContext::ensure();
        [$from, $to] = $this->dateRange($input);
        $periodExpr = $this->periodExpression($unit, 'business_date');

        $rows = $this->completedSales($branch->id, $from, $to)
            ->selectRaw("{$periodExpr} as period, COUNT(*) as count, SUM(total) as total, SUM(tax_total) as tax, SUM(discount_total) as discount, SUM(paid_total) as paid")
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
            ])
            ->all();

        return $this->doc($key, $title, compact('from', 'to'), ['dates'], [
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'count', 'label' => 'Sales', 'format' => 'int', 'total' => true],
            ['key' => 'total', 'label' => 'Total', 'format' => 'money', 'total' => true],
            ['key' => 'discount', 'label' => 'Discount', 'format' => 'money', 'total' => true],
            ['key' => 'tax', 'label' => 'Tax', 'format' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'format' => 'money', 'total' => true],
        ], $rows);
    }

    private function completedSales(int $branchId, string $from, string $to)
    {
        return Sale::query()
            ->where('branch_id', $branchId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
    }

    private function periodExpression(string $unit, string $column): string
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
     * @return list<array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * @param  list<array{0: string, 1: mixed, 2: string}>  $items
     * @return list<array{label: string, value: mixed, format: string}>
     */
    private function cards(array $items): array
    {
        return array_map(fn (array $item) => [
            'label' => $item[0],
            'value' => $item[1],
            'format' => $item[2],
        ], $items);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $summary
     * @param  list<array{label: string, value: mixed, format: string}>  $cards
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function doc(
        string $key,
        string $title,
        array $filters,
        array $controls,
        array $columns,
        array $rows,
        ?array $summary = null,
        array $cards = [],
        array $extra = [],
    ): array {
        return array_merge([
            'key' => $key,
            'title' => $title,
            'filters' => $filters,
            'controls' => $controls,
            'columns' => $columns,
            'rows' => array_values($rows),
            'summary' => $summary,
            'summary_cards' => $cards,
            'total' => count($rows),
        ], $extra);
    }
}
