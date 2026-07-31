<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Support\BranchContext;
use App\Support\DashboardMetrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $branch = BranchContext::ensure();
        $tz = (string) (company_settings()['timezone'] ?? config('app.timezone', 'UTC'));
        $today = Carbon::now($tz);

        $defaultStart = $today->copy()->startOfMonth()->toDateString();
        $defaultEnd = $today->copy()->endOfMonth()->toDateString();

        $startDate = $this->validDate($request->input('start_date'), $defaultStart);
        $endDate = $this->validDate($request->input('end_date'), $defaultEnd);

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $hasOpenShift = Shift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->exists();

        return Inertia::render('Admin/Dashboard', [
            'tenant' => [
                'code' => tenant('code'),
                'name' => tenant('name'),
            ],
            'selected_branch' => $branch->only(['id', 'name', 'code']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'today_label' => $today->format('j F'),
            'show_shift_reminder' => ! $hasOpenShift,
            'today_stats' => DashboardMetrics::summaryForToday($branch->id),
            'period_stats' => DashboardMetrics::summaryForRange($branch->id, $startDate, $endDate),
            'revenue_chart_daily' => DashboardMetrics::dailyRevenueSeries($branch->id, $startDate, $endDate),
            'expenses_chart_daily' => DashboardMetrics::dailyExpensesSeries($branch->id, $startDate, $endDate),
            'category_breakdown' => DashboardMetrics::salesByCategoryBreakdown($branch->id, $startDate, $endDate),
            'operational_comparison' => DashboardMetrics::operationalComparison($branch->id, $startDate, $endDate),
            'customer_receivables' => DashboardMetrics::customerReceivables(),
            'supplier_payables' => DashboardMetrics::supplierPayables(),
            'top_items' => DashboardMetrics::topSellingItems($branch->id, $startDate, $endDate),
            'low_stock_items' => DashboardMetrics::lowStockItems($branch->id),
            'money_source_balances' => DashboardMetrics::moneySourceBalances($branch->id),
        ]);
    }

    private function validDate(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? $value : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback;
        }

        return $value;
    }
}
