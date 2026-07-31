<?php

namespace App\Support;

use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    /**
     * @return array{
     *     revenue: float,
     *     cost_of_goods: float,
     *     net_profit: float,
     *     net_profit_breakdown: array<string, mixed>,
     *     transactions: int,
     *     customers: int,
     *     average_receipt: float,
     *     label: string,
     *     start_date: string,
     *     end_date: string
     * }
     */
    public static function summaryForRange(int $branchId, string $startDate, string $endDate): array
    {
        $salesQuery = self::salesQuery($branchId, $startDate, $endDate);

        $revenue = round((float) (clone $salesQuery)->sum('total'), 2);
        $transactions = (int) (clone $salesQuery)->count();
        $customers = self::countUniqueCustomers($salesQuery);
        $averageReceipt = $transactions > 0 ? round($revenue / $transactions, 2) : 0.0;
        $costOfGoods = self::costOfGoodsForSales((clone $salesQuery)->pluck('id'));

        $breakdown = self::dashboardNetProfitBreakdown(
            $branchId,
            $startDate,
            $endDate,
            $revenue,
            $costOfGoods,
        );

        return [
            'revenue' => $revenue,
            'cost_of_goods' => $costOfGoods,
            'net_profit' => $breakdown['net_profit'],
            'net_profit_breakdown' => $breakdown,
            'transactions' => $transactions,
            'customers' => $customers,
            'average_receipt' => $averageReceipt,
            'label' => self::periodLabel($startDate, $endDate),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * @return array{
     *     revenue: float,
     *     cost_of_goods: float,
     *     net_profit: float,
     *     net_profit_breakdown: array<string, mixed>,
     *     transactions: int,
     *     customers: int,
     *     average_receipt: float,
     *     label: string,
     *     start_date: string,
     *     end_date: string
     * }
     */
    public static function summaryForToday(int $branchId): array
    {
        $today = self::todayDateString();

        return self::summaryForRange($branchId, $today, $today);
    }

    /**
     * @return array{labels: list<string>, revenue: list<float>, dates: list<string>}
     */
    public static function dailyRevenueSeries(int $branchId, string $startDate, string $endDate): array
    {
        $byDay = self::salesQuery($branchId, $startDate, $endDate)
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(total), 0) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('revenue', 'day');

        return self::fillDailySeries($startDate, $endDate, $byDay, 'revenue');
    }

    /**
     * @return array{labels: list<string>, expenses: list<float>, dates: list<string>}
     */
    public static function dailyExpensesSeries(int $branchId, string $startDate, string $endDate): array
    {
        $byDay = self::expenseQuery($branchId, $startDate, $endDate)
            ->selectRaw('DATE(txn_date) as day, COALESCE(SUM(amount), 0) as expenses')
            ->groupBy(DB::raw('DATE(txn_date)'))
            ->pluck('expenses', 'day');

        return self::fillDailySeries($startDate, $endDate, $byDay, 'expenses');
    }

    /**
     * Sales volume by product category (retail stand-in for FoodPOS order types).
     *
     * @return array{
     *     labels: list<string>,
     *     amounts: list<float>,
     *     counts: list<float>,
     *     total: float
     * }
     */
    public static function salesByCategoryBreakdown(int $branchId, string $startDate, string $endDate): array
    {
        $rows = SaleItem::query()
            ->selectRaw('COALESCE(categories.name, ?) as category, SUM(sale_items.line_total) as amount, SUM(sale_items.quantity_in_sale_unit) as qty', ['Uncategorized'])
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate)
            ->groupBy('categories.name')
            ->orderByDesc('amount')
            ->get();

        $labels = [];
        $amounts = [];
        $counts = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $amount = round((float) $row->amount, 2);
            $labels[] = (string) $row->category;
            $amounts[] = $amount;
            $counts[] = round((float) $row->qty, 2);
            $total += $amount;
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
            'counts' => $counts,
            'total' => round($total, 2),
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     values: list<float>,
     *     keys: list<string>,
     *     cash_inflow: float,
     *     cash_outflow: float,
     *     net_flow: float
     * }
     */
    public static function operationalComparison(int $branchId, string $startDate, string $endDate): array
    {
        $purchases = round((float) Purchase::query()
            ->where('branch_id', $branchId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('total'), 2);

        $sales = round((float) self::salesQuery($branchId, $startDate, $endDate)->sum('total'), 2);

        $expenses = round((float) self::expenseQuery($branchId, $startDate, $endDate)->sum('amount'), 2);

        $supplierPayments = round((float) SupplierPayment::query()
            ->where('branch_id', $branchId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount'), 2);

        $customerReceived = round((float) CustomerPayment::query()
            ->where(fn (Builder $q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount'), 2);

        $cashFromSales = round((float) SalePayment::query()
            ->whereHas('sale', function (Builder $q) use ($branchId, $startDate, $endDate) {
                $q->where('branch_id', $branchId)
                    ->where('status', Sale::STATUS_COMPLETED)
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            })
            ->sum('amount'), 2);

        $cashInflow = round($cashFromSales + $customerReceived, 2);
        $cashOutflow = round($supplierPayments + $expenses, 2);

        return [
            'labels' => ['Purchases', 'Sales', 'Expenses', 'Supplier Payments', 'Customer Received'],
            'values' => [$purchases, $sales, $expenses, $supplierPayments, $customerReceived],
            'keys' => ['purchases', 'sales', 'expenses', 'supplier_payments', 'customer_received'],
            'cash_inflow' => $cashInflow,
            'cash_outflow' => $cashOutflow,
            'net_flow' => round($cashInflow - $cashOutflow, 2),
        ];
    }

    /**
     * @return array{
     *     rows: list<array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int
     * }
     */
    public static function customerReceivables(): array
    {
        $rows = Customer::query()
            ->where('balance', '>', 0)
            ->where('is_active', true)
            ->orderByDesc('balance')
            ->limit(25)
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'contact' => $c->phone,
                'balance' => round((float) $c->balance, 2),
            ])
            ->all();

        $total = round(array_sum(array_column($rows, 'balance')), 2);

        return [
            'rows' => $rows,
            'total' => $total,
            'party_count' => count($rows),
        ];
    }

    /**
     * @return array{
     *     rows: list<array{id: int, name: string, contact: ?string, balance: float}>,
     *     total: float,
     *     party_count: int
     * }
     */
    public static function supplierPayables(): array
    {
        $rows = Supplier::query()
            ->where('balance', '>', 0)
            ->where('is_active', true)
            ->orderByDesc('balance')
            ->limit(25)
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'contact' => $s->phone,
                'balance' => round((float) $s->balance, 2),
            ])
            ->all();

        $total = round(array_sum(array_column($rows, 'balance')), 2);

        return [
            'rows' => $rows,
            'total' => $total,
            'party_count' => count($rows),
        ];
    }

    /**
     * @return array{
     *     items: list<array{name: string, qty: float, revenue: float}>,
     *     label: string,
     *     total_quantity: float,
     *     total_revenue: float
     * }
     */
    public static function topSellingItems(int $branchId, string $startDate, string $endDate, int $limit = 10): array
    {
        $items = SaleItem::query()
            ->selectRaw('sale_items.product_id, sale_items.variant_id, SUM(sale_items.quantity_in_sale_unit) as qty, SUM(sale_items.line_total) as revenue')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate)
            ->with(['product:id,name', 'variant:id,name,product_id'])
            ->groupBy('sale_items.product_id', 'sale_items.variant_id')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->variant?->displayName() ?? $row->product?->name ?? 'Unknown',
                    'qty' => round((float) $row->qty, 2),
                    'revenue' => round((float) $row->revenue, 2),
                ];
            })
            ->all();

        return [
            'items' => $items,
            'label' => self::periodLabel($startDate, $endDate),
            'total_quantity' => round(array_sum(array_column($items, 'qty')), 2),
            'total_revenue' => round(array_sum(array_column($items, 'revenue')), 2),
        ];
    }

    /**
     * @return array{
     *     rows: list<array{name: string, current: float, min_level: float, unit: string}>,
     *     total: int
     * }
     */
    public static function lowStockItems(int $branchId, int $limit = 15): array
    {
        $rows = BranchStock::query()
            ->with([
                'variant.product:id,name,min_qty_alert,track_stock',
                'variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branchId)
            ->whereHas('variant.product', fn (Builder $p) => $p->where('track_stock', true)->where('min_qty_alert', '>', 0))
            ->whereRaw(
                'branch_stocks.quantity <= (
                    select products.min_qty_alert
                    from product_variants
                    inner join products on products.id = product_variants.product_id
                    where product_variants.id = branch_stocks.variant_id
                    limit 1
                )',
            )
            ->orderBy('quantity')
            ->limit($limit)
            ->get()
            ->map(function (BranchStock $stock) {
                $variant = $stock->variant;
                $product = $variant?->product;

                return [
                    'name' => $variant?->displayName() ?? $product?->name ?? 'Unknown',
                    'current' => round((float) $stock->quantity, 2),
                    'min_level' => round((float) ($product?->min_qty_alert ?? 0), 2),
                    'unit' => $variant?->saleUnit?->name ?? 'units',
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @return list<array{id: int, name: string, type: string, balance: float}>
     */
    public static function moneySourceBalances(int $branchId): array
    {
        return MoneySource::query()
            ->forPayments()
            ->forBranch($branchId)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'balance'])
            ->map(fn (MoneySource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'balance' => $source->currentBalance(),
            ])
            ->all();
    }

    public static function todayDateString(): string
    {
        $tz = (string) (company_settings()['timezone'] ?? config('app.timezone', 'UTC'));

        return Carbon::now($tz)->toDateString();
    }

    private static function salesQuery(int $branchId, string $startDate, string $endDate): Builder
    {
        return Sale::query()
            ->where('branch_id', $branchId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
    }

    private static function expenseQuery(int $branchId, string $startDate, string $endDate): Builder
    {
        return LedgerTransaction::query()
            ->where('branch_id', $branchId)
            ->where('direction', 'out')
            ->where('reference_type', 'expense')
            ->whereDate('txn_date', '>=', $startDate)
            ->whereDate('txn_date', '<=', $endDate);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string>  $saleIds
     */
    private static function costOfGoodsForSales($saleIds): float
    {
        if ($saleIds->isEmpty()) {
            return 0.0;
        }

        $cogs = (float) SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('COALESCE(SUM(quantity_in_sale_unit * cost_per_unit), 0) as cogs')
            ->value('cogs');

        return round($cogs, 2);
    }

    private static function countUniqueCustomers(Builder $salesQuery): int
    {
        $row = (clone $salesQuery)
            ->selectRaw("COUNT(DISTINCT CASE
                WHEN customer_id IS NOT NULL THEN CONCAT('c', customer_id)
                ELSE CONCAT('s', id)
            END) as customer_count")
            ->first();

        return (int) ($row->customer_count ?? 0);
    }

    /**
     * @return array{
     *     total_sale: float,
     *     cogs: float,
     *     expenses_total: float,
     *     expenses: list<array{date: string, label: string, detail: string, amount: float}>,
     *     payouts_total: float,
     *     payouts: list<array{date: string, label: string, account: string, detail: string, amount: float}>,
     *     payout_groups: list<array{label: string, total: float, rows: list}>,
     *     net_profit: float
     * }
     */
    private static function dashboardNetProfitBreakdown(
        int $branchId,
        string $startDate,
        string $endDate,
        float $netSales,
        float $cogs,
    ): array {
        $flaggedIds = MoneySource::query()
            ->where('exclude_from_dashboard_profit', true)
            ->pluck('id');

        $expenseRows = self::expenseQuery($branchId, $startDate, $endDate)
            ->with(['account:id,name', 'moneySource:id,name'])
            ->orderBy('txn_date')
            ->orderBy('id')
            ->get();

        $expenses = $expenseRows->map(function (LedgerTransaction $txn) {
            $account = trim((string) ($txn->account?->name ?? ''));
            $notes = trim((string) ($txn->notes ?? ''));
            $source = trim((string) ($txn->moneySource?->name ?? ''));
            $label = $account !== '' ? $account : 'Expense';
            $detailParts = array_values(array_filter([
                $source !== '' ? $source : null,
                $notes !== '' ? $notes : null,
            ]));

            return [
                'date' => $txn->txn_date?->format('Y-m-d') ?? (string) $txn->txn_date,
                'label' => $label,
                'detail' => implode(' · ', $detailParts),
                'amount' => round((float) $txn->amount, 2),
            ];
        })->all();

        $expensesTotal = round((float) $expenseRows->sum('amount'), 2);

        $outsQuery = LedgerTransaction::query()
            ->with(['moneySource:id,name', 'account:id,name'])
            ->where('branch_id', $branchId)
            ->whereDate('txn_date', '>=', $startDate)
            ->whereDate('txn_date', '<=', $endDate)
            ->where('direction', 'out')
            ->where(function (Builder $query) {
                $query->whereNull('reference_type')
                    ->orWhereNotIn('reference_type', ['transfer', 'expense']);
            });

        if ($flaggedIds->isNotEmpty()) {
            $outsQuery->where(function (Builder $query) use ($flaggedIds) {
                $query->whereNull('money_source_id')
                    ->orWhereNotIn('money_source_id', $flaggedIds);
            });
        }

        $outRows = $outsQuery
            ->orderBy('txn_date')
            ->orderBy('id')
            ->get();

        $payouts = $outRows->map(function (LedgerTransaction $txn) {
            $ref = (string) ($txn->reference_type ?? '');
            $label = self::payoutReferenceLabel($ref);
            $source = trim((string) ($txn->moneySource?->name ?? ''));
            $account = trim((string) ($txn->account?->name ?? ''));
            $notes = trim((string) ($txn->notes ?? ''));
            $detailParts = array_values(array_filter([
                $source !== '' ? $source : null,
                $notes !== '' ? $notes : null,
            ]));

            return [
                'date' => $txn->txn_date?->format('Y-m-d') ?? (string) $txn->txn_date,
                'label' => $label,
                'account' => $account !== '' ? $account : 'Unassigned',
                'detail' => implode(' · ', $detailParts),
                'amount' => round((float) $txn->amount, 2),
            ];
        })->all();

        $payoutGroups = collect($payouts)
            ->groupBy('account')
            ->map(fn ($rows, string $account) => [
                'label' => $account,
                'total' => round((float) $rows->sum('amount'), 2),
                'rows' => $rows->values()->all(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $payoutsTotal = round((float) $outRows->sum('amount'), 2);
        $totalSale = round($netSales, 2);
        $cogsRounded = round($cogs, 2);

        return [
            'total_sale' => $totalSale,
            'cogs' => $cogsRounded,
            'expenses_total' => $expensesTotal,
            'expenses' => $expenses,
            'payouts_total' => $payoutsTotal,
            'payouts' => $payouts,
            'payout_groups' => $payoutGroups,
            'net_profit' => round($totalSale - $cogsRounded - $expensesTotal - $payoutsTotal, 2),
        ];
    }

    private static function payoutReferenceLabel(string $referenceType): string
    {
        return match ($referenceType) {
            'sale' => 'Sale',
            'purchase' => 'Purchase',
            'refund' => 'Refund',
            'expense' => 'Expense',
            'customer_payment' => 'Customer payment',
            'supplier_payment' => 'Supplier payment',
            'employee_payment' => 'Employee payment',
            'transfer' => 'Transfer',
            'reconciliation' => 'Reconciliation',
            'adjustment' => 'Adjustment',
            '' => 'Payout',
            default => ucwords(str_replace('_', ' ', $referenceType)),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $byDay
     * @return array{labels: list<string>, dates: list<string>}&array<string, list<float>>
     */
    private static function fillDailySeries(string $startDate, string $endDate, $byDay, string $valueKey): array
    {
        $labels = [];
        $values = [];
        $dates = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $dates[] = $dateStr;
            $labels[] = $date->format('d M');
            $values[] = round((float) ($byDay[$dateStr] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            $valueKey => $values,
            'dates' => $dates,
        ];
    }

    private static function periodLabel(string $startDate, string $endDate): string
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        return $start->isSameDay($end)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');
    }
}
