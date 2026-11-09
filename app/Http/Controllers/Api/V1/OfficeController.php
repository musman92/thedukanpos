<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\AttendanceService;
use App\Services\BranchService;
use App\Services\CustomerPaymentService;
use App\Services\EmployeePaymentService;
use App\Services\ExpenseService;
use App\Services\LeaveService;
use App\Services\MoneySourceService;
use App\Services\PayrollAdjustmentService;
use App\Services\ProductLedgerService;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use App\Services\QuotationService;
use App\Services\RoleService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Services\StockAdjustmentService;
use App\Services\StockDamageService;
use App\Services\StockOnHandService;
use App\Services\StockTransferService;
use App\Services\SupplierPaymentService;
use App\Services\TransactionService;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $filters = [
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page', 40),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
            'sort_direction' => $request->input('sort_direction'),
            'low' => $resource === 'stock-low',
            'date' => $request->input('date'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'product_id' => $request->input('product_id'),
            'customer_id' => $request->input('customer_id'),
            'supplier_id' => $request->input('supplier_id'),
            'purchase_id' => $request->input('purchase_id'),
            'payment_status' => $request->input('payment_status'),
            'status' => $request->input('status'),
            'money_source_id' => $request->input('money_source_id'),
            'user_id' => $request->input('user_id'),
            'kind' => $request->input('kind'),
            'account_id' => $request->input('account_id'),
            'leave_type' => $request->input('leave_type'),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'category_id' => $request->input('category_id'),
            'reference_type' => $request->input('reference_type'),
        ];

        $page = match ($resource) {
            'orders' => app(SaleService::class)->paginate($filters)['sales'],
            'sale-returns' => app(SaleReturnService::class)->paginate($filters)['returns'],
            'quotations' => app(QuotationService::class)->paginate($filters)['quotations'],
            'stock', 'stock-low' => app(StockOnHandService::class)->paginate($filters)['stocks'],
            'purchases' => app(PurchaseService::class)->paginate($filters)['purchases'],
            'purchase-returns' => app(PurchaseReturnService::class)->paginate($filters)['returns'],
            'adjustments' => app(StockAdjustmentService::class)->paginate($filters)['adjustments'],
            'damages' => app(StockDamageService::class)->paginate($filters)['damages'],
            'transfers' => app(StockTransferService::class)->paginate($filters)['transfers'],
            'money-sources' => app(MoneySourceService::class)->paginate($filters)['money_sources'],
            'transactions' => app(TransactionService::class)->paginate($filters)['transactions'],
            'expenses' => app(ExpenseService::class)->paginate($filters)['expenses'],
            'supplier-payments' => app(SupplierPaymentService::class)->paginate($filters)['payments'],
            'customer-payments' => app(CustomerPaymentService::class)->paginate($filters)['payments'],
            'employee-payments' => app(EmployeePaymentService::class)->paginate($filters)['payments'],
            'leaves' => app(LeaveService::class)->paginate($filters)['leaves'],
            'payroll-adjustments' => app(PayrollAdjustmentService::class)->paginate($filters)['adjustments'],
            'branches' => app(BranchService::class)->paginate($filters)['branches'],
            'roles' => $this->roles($filters),
            'activity' => app(ActivityLogService::class)->paginate($filters)['logs'],
            'serials' => $this->serials($filters),
            'product-ledger' => $this->ledger($filters),
            'payroll' => $this->payroll($filters),
            'attendance' => $this->attendance($filters),
            'settings' => $this->settings(),
            default => abort(404, 'That list is not available.'),
        };

        if ($page instanceof LengthAwarePaginator) {
            return $this->envelope($page);
        }

        return $page;
    }

    private function envelope(LengthAwarePaginator $page): JsonResponse
    {
        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function serials(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return SerialNumber::query()
            ->with(['product:id,name', 'variant:id,name,product_id'])
            ->when($q !== '', fn ($query) => $query->where('serial', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 40))
            ->through(fn (SerialNumber $serial) => [
                'id' => $serial->id,
                'name' => $serial->serial,
                'status' => $serial->status,
                'product' => $serial->product?->name,
                'notes' => $serial->notes,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ledger(array $filters): LengthAwarePaginator|JsonResponse
    {
        $productId = $filters['product_id'] ?? null;
        if ($productId) {
            $built = app(ProductLedgerService::class)->build($filters);
            $rows = $built['rows'] ?? null;
            if ($rows instanceof LengthAwarePaginator) {
                return $rows;
            }
        }

        $branch = BranchContext::ensure();
        $q = trim((string) ($filters['q'] ?? ''));

        return StockMovement::query()
            ->with(['product:id,name'])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('product', fn ($product) => $product->where('name', 'like', "%{$q}%"));
            })
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 40))
            ->through(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'name' => $movement->product?->name ?? 'Stock movement',
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'created_at' => $movement->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function payroll(array $filters): LengthAwarePaginator
    {
        $branch = BranchContext::ensure();

        return PayrollRun::query()
            ->with('branch:id,name')
            ->where(function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)->orWhereNull('branch_id');
            })
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 40))
            ->through(fn (PayrollRun $run) => [
                'id' => $run->id,
                'number' => $run->number,
                'status' => $run->status,
                'period_start' => $run->period_start?->format('Y-m-d'),
                'period_end' => $run->period_end?->format('Y-m-d'),
                'employee_count' => $run->employee_count,
                'net_total' => round((float) $run->net_total, 2),
                'branch' => $run->branch?->name,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function attendance(array $filters): JsonResponse
    {
        $result = app(AttendanceService::class)->listForDate($filters);
        $rows = collect($result['board']['employees'] ?? [])->map(function ($employee) {
            $employee = is_array($employee) ? $employee : (array) $employee;
            $record = $employee['record'] ?? null;

            return [
                'id' => $employee['id'] ?? null,
                'name' => $employee['name'] ?? 'Employee',
                'phase' => $employee['phase'] ?? null,
                'status' => is_array($record) ? ($record['status'] ?? null) : null,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => $rows->count(),
            ],
        ]);
    }

    /**
     * Spatie counts users through the default auth guard. API requests use
     * Sanctum, which has no user model, so the count has to run as the web guard.
     *
     * @param  array<string, mixed>  $filters
     */
    private function roles(array $filters): LengthAwarePaginator
    {
        $guard = config('auth.defaults.guard');
        config(['auth.defaults.guard' => 'web']);
        try {
            return app(RoleService::class)->paginate($filters)['roles'];
        } finally {
            config(['auth.defaults.guard' => $guard]);
        }
    }

    private function settings(): JsonResponse
    {
        $all = app(SettingService::class)->all();
        $labels = [
            'shop_name' => 'Shop name',
            'phone' => 'Phone',
            'email' => 'Email',
            'address' => 'Address',
            'tax_id' => 'Tax ID',
            'currency' => 'Currency',
            'timezone' => 'Timezone',
            'locale' => 'Language',
            'date_format' => 'Date format',
            'receipt_footer' => 'Receipt footer',
            'pos_allow_credit' => 'POS credit sales',
            'pos_enable_delivery' => 'POS delivery',
            'pos_show_stock' => 'Show stock on POS',
        ];
        $rows = [];
        foreach ($labels as $key => $label) {
            $value = $all[$key] ?? '';
            $rows[] = [
                'id' => $key,
                'name' => $label,
                'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value,
            ];
        }

        $printLogo = $all['logo_print'] ?? null;
        $colorLogo = $all['logo'] ?? null;
        $logoPath = is_string($printLogo) && $printLogo !== ''
            ? $printLogo
            : (is_string($colorLogo) && $colorLogo !== '' ? $colorLogo : null);
        $rows[] = [
            'id' => 'logo_url',
            'name' => 'Receipt logo',
            'value' => $logoPath ? route('api.v1.media.show', ['path' => $logoPath]) : '',
        ];

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => count($rows),
            ],
        ]);
    }
}
