<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AccountStatementService;
use App\Services\MoneySourceTxnReportService;
use App\Services\OperationalReportService;
use App\Services\ReportPdfService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ReportHubController extends Controller
{
    public function __construct(private OperationalReportService $reports) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.sales');
    }

    public function dailySales(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('daily-sales', $request->all()));
    }

    public function paymentMethods(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('payment-methods', $request->all()));
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
                'totals' => $this->reports->columnTotals($payload['all_rows'], $columns),
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
        $doc = $this->reports->document('foc', $request->all());
        $perPage = resolve_page_limit($request->input('per_page'), 25);

        if ($this->wantsPdf()) {
            return app(ReportPdfService::class)->download([
                'key' => 'foc',
                'title' => 'FOC',
                'meta' => ReportPdfService::periodMeta(
                    $doc['filters']['from'] ?? null,
                    $doc['filters']['to'] ?? null,
                    BranchContext::ensure()->name,
                ),
                'columns' => $doc['columns'],
                'rows' => $doc['rows'],
                'summary' => $this->pdfSummary($doc['summary']),
                'totals' => $this->reports->columnTotals($doc['rows'], $doc['columns']),
            ]);
        }

        return Inertia::render('Admin/Reports/Foc', [
            'reportKey' => 'foc',
            'title' => 'FOC',
            'filters' => $doc['filters'] + ['per_page' => $perPage],
            'summary' => $doc['summary'],
            'sales' => $this->paginateRows($doc['rows'], $perPage),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function orderHistory(Request $request): Response|HttpResponse
    {
        $doc = $this->reports->document('order-history', $request->all());
        $perPage = resolve_page_limit($request->input('per_page'), 25);
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

        if ($this->wantsPdf()) {
            return app(ReportPdfService::class)->download([
                'key' => 'order-history',
                'title' => 'Order History',
                'meta' => ReportPdfService::periodMeta(
                    $doc['filters']['from'] ?? null,
                    $doc['filters']['to'] ?? null,
                    BranchContext::ensure()->name,
                ),
                'columns' => $columns,
                'rows' => $doc['rows'],
                'summary' => $this->pdfSummary($doc['summary']),
                'totals' => $this->reports->columnTotals($doc['rows'], $columns),
            ]);
        }

        return Inertia::render('Admin/Reports/OrderHistory', [
            'reportKey' => 'order-history',
            'title' => 'Order History',
            'filters' => $doc['filters'] + ['per_page' => $perPage],
            'summary' => $doc['summary'],
            'sales' => $this->paginateRows($doc['rows'], $perPage),
            'customers' => $doc['customers'],
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function topItems(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('top-items', $request->all()));
    }

    public function salesByCategory(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('sales-by-category', $request->all()));
    }

    public function discounts(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('discounts', $request->all()));
    }

    public function taxSummary(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('tax-summary', $request->all()));
    }

    public function receivables(): Response|HttpResponse
    {
        return $this->present($this->reports->document('receivables', []));
    }

    public function payables(): Response|HttpResponse
    {
        return $this->present($this->reports->document('payables', []));
    }

    public function customerCredits(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('customer-credits', $request->all()));
    }

    public function supplierPayments(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('supplier-payments', $request->all()));
    }

    public function purchases(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('purchases', $request->all()));
    }

    public function expenses(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('expenses', $request->all()));
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

    protected function accountStatementDocument(array $payload): array
    {
        return $this->reports->accountStatementPdf($payload);
    }

    public function weeklyClosing(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('weekly-closing', $request->all()));
    }

    public function monthlyClosing(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('monthly-closing', $request->all()));
    }

    public function grossMargin(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('gross-margin', $request->all()));
    }

    public function profitLoss(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('profit-loss', $request->all()));
    }

    public function shiftsZ(Request $request): Response|HttpResponse
    {
        return $this->present($this->reports->document('shifts-z', $request->all()));
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    protected function present(array $doc): Response|HttpResponse
    {
        return $this->generic(
            $doc['key'],
            $doc['title'],
            $doc['filters'],
            $doc['rows'],
            $doc['columns'],
            $doc['summary'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function paginateRows(array $rows, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->input('page', 1));

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    protected function categoryOptions(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

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
                'totals' => $this->reports->columnTotals($rows, $columns),
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

}
