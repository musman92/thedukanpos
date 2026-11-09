<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttendanceRequest;
use App\Http\Requests\Admin\StoreCustomerPaymentRequest;
use App\Http\Requests\Admin\StoreEmployeePaymentRequest;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Http\Requests\Admin\StoreLeaveRequest;
use App\Http\Requests\Admin\StoreMoneySourceRequest;
use App\Http\Requests\Admin\StorePayrollAdjustmentRequest;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Http\Requests\Admin\StoreQuotationRequest;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Http\Requests\Admin\StoreSaleReturnRequest;
use App\Http\Requests\Admin\StoreStockAdjustmentRequest;
use App\Http\Requests\Admin\StoreStockDamageRequest;
use App\Http\Requests\Admin\StoreStockTransferRequest;
use App\Http\Requests\Admin\StoreSupplierPaymentRequest;
use App\Http\Requests\Admin\StoreTransactionRequest;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Http\Requests\Admin\UpdateExpenseRequest;
use App\Http\Requests\Admin\UpdateMoneySourceRequest;
use App\Http\Requests\Admin\UpdatePayrollAdjustmentRequest;
use App\Http\Requests\Admin\UpdatePurchaseRequest;
use App\Http\Requests\Admin\UpdatePurchaseReturnRequest;
use App\Http\Requests\Admin\UpdateQuotationRequest;
use App\Http\Requests\Admin\UpdateSaleRequest;
use App\Http\Requests\Admin\UpdateTransactionRequest;
use App\Models\AttendanceRecord;
use App\Models\CustomerPayment;
use App\Models\EmployeePayment;
use App\Models\LeaveRequest;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\StockDamage;
use App\Models\StockTransfer;
use App\Models\SupplierPayment;
use App\Services\AttendanceService;
use App\Services\CustomerPaymentService;
use App\Services\EmployeePaymentService;
use App\Services\ExpenseService;
use App\Services\HrService;
use App\Services\LeaveService;
use App\Services\MoneySourceService;
use App\Services\PayrollAdjustmentService;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use App\Services\QuotationService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Services\StockAdjustmentService;
use App\Services\StockDamageService;
use App\Services\StockTransferService;
use App\Services\SupplierPaymentService;
use App\Services\TransactionService;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModuleController extends Controller
{
    public function options(Request $request, string $resource): JsonResponse
    {
        $saleId = $request->filled('sale_id') ? $request->integer('sale_id') : null;
        $purchaseId = $request->filled('purchase_id') ? $request->integer('purchase_id') : null;
        $supplierId = $request->filled('supplier_id') ? $request->integer('supplier_id') : null;

        $payload = match ($resource) {
            'orders' => app(SaleService::class)->formOptions(),
            'sale-returns' => app(SaleReturnService::class)->formOptions(
                $saleId,
                $request->filled('id') ? SaleReturn::query()->find($request->integer('id')) : null,
            ),
            'quotations' => app(QuotationService::class)->formOptions(),
            'purchases' => app(PurchaseService::class)->formOptions(),
            'purchase-returns' => app(PurchaseReturnService::class)->formOptions(
                $purchaseId,
                $supplierId,
                $request->filled('id') ? PurchaseReturn::query()->find($request->integer('id')) : null,
            ),
            'adjustments' => $this->withoutList(app(StockAdjustmentService::class)->paginate(['per_page' => 1]), ['adjustments']),
            'damages' => $this->withoutList(app(StockDamageService::class)->paginate(['per_page' => 1]), ['damages']),
            'transfers' => $this->withoutList(app(StockTransferService::class)->paginate(['per_page' => 1]), ['transfers']),
            'expenses' => $this->withoutList(app(ExpenseService::class)->paginate(['per_page' => 1]), ['expenses']),
            'customer-payments' => $this->withoutList(app(CustomerPaymentService::class)->paginate(['per_page' => 1]), ['payments']),
            'supplier-payments' => $this->withoutList(app(SupplierPaymentService::class)->paginate(['per_page' => 1]), ['payments']),
            'employee-payments' => $this->withoutList(app(EmployeePaymentService::class)->paginate(['per_page' => 1]), ['payments']),
            'transactions' => $this->withoutList(app(TransactionService::class)->paginate(['per_page' => 1]), ['transactions']),
            'money-sources' => $this->withoutList(app(MoneySourceService::class)->paginate(['per_page' => 1]), ['money_sources', 'system_sources']),
            'attendance' => $this->attendanceOptions($request),
            'leaves' => $this->withoutList(app(LeaveService::class)->paginate(['per_page' => 1]), ['leaves']),
            'payroll' => ['today' => company_today()],
            'payroll-adjustments' => $this->withoutList(app(PayrollAdjustmentService::class)->paginate(['per_page' => 1]), ['adjustments']),
            default => abort(404, 'That form is not available.'),
        };

        return response()->json($payload);
    }

    public function show(string $resource, int $id): JsonResponse
    {
        $data = $this->present($resource, $id);

        return response()->json([
            'data' => $data,
            'access' => $this->access($resource, $data),
        ]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $this->perform($resource, null, $request);

        return response()->json(['message' => 'Saved.'], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $this->perform($resource, $id, $request);

        return response()->json(['message' => 'Saved.']);
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $this->guard(function () use ($resource, $id) {
            match ($resource) {
                'orders' => app(SaleService::class)->voidSale($this->sale($id)),
                'quotations' => app(QuotationService::class)->delete($this->quotation($id)),
                'purchases' => app(PurchaseService::class)->delete($this->purchase($id)),
                'purchase-returns' => app(PurchaseReturnService::class)->delete($this->purchaseReturn($id)),
                'adjustments' => app(StockAdjustmentService::class)->delete($this->adjustment($id)),
                'damages' => app(StockDamageService::class)->delete($this->damage($id)),
                'transfers' => app(StockTransferService::class)->delete($this->transfer($id)),
                'expenses' => app(ExpenseService::class)->delete($this->expense($id)),
                'customer-payments' => app(CustomerPaymentService::class)->delete($this->customerPayment($id)),
                'supplier-payments' => app(SupplierPaymentService::class)->delete($this->supplierPayment($id)),
                'employee-payments' => app(EmployeePaymentService::class)->delete($this->employeePayment($id)),
                'transactions' => app(TransactionService::class)->delete($this->transaction($id)),
                'money-sources' => app(MoneySourceService::class)->delete($this->moneySource($id)),
                'attendance' => app(AttendanceService::class)->delete($this->attendance($id)),
                'leaves' => app(LeaveService::class)->cancel($this->leave($id)),
                'payroll-adjustments' => app(PayrollAdjustmentService::class)->delete($this->payrollAdjustment($id)),
                default => abort(404, 'That record cannot be deleted.'),
            };
        });

        return response()->json(['message' => 'Deleted.']);
    }

    public function action(Request $request, string $resource, int $id): JsonResponse
    {
        $action = (string) $request->input('action', '');

        $this->guard(function () use ($request, $resource, $id, $action) {
            if ($resource === 'leaves' && in_array($action, ['approved', 'rejected'], true)) {
                app(LeaveService::class)->review(
                    $this->leave($id),
                    $action,
                    $request->input('notes'),
                );

                return;
            }

            if ($resource === 'payroll' && $action === 'finalize') {
                app(HrService::class)->finalizePayrollRun($this->payroll($id));

                return;
            }

            abort(404, 'That action is not available.');
        });

        return response()->json(['message' => 'Saved.']);
    }

    private function perform(string $resource, ?int $id, Request $request): void
    {
        $this->guard(function () use ($resource, $id, $request) {
            $branch = BranchContext::ensure();

            match ($resource) {
                'orders' => $id === null
                    ? app(SaleService::class)->checkout($this->form($request, StoreSaleRequest::class)->payload(
                        $branch->id,
                        app(SaleService::class)->resolveOpenShiftId($branch->id),
                        app(SettingService::class)->allowPosCredit(),
                    ))
                    : app(SaleService::class)->update($this->sale($id), $this->form($request, UpdateSaleRequest::class)->payload(
                        $branch->id,
                        $this->sale($id)->shift_id,
                        app(SettingService::class)->allowPosCredit(),
                    )),
                'sale-returns' => $id === null
                    ? app(SaleReturnService::class)->create($this->form($request, StoreSaleReturnRequest::class)->payload())
                    : abort(404, 'A refund cannot be edited.'),
                'quotations' => $id === null
                    ? app(QuotationService::class)->create($this->form($request, StoreQuotationRequest::class)->payload())
                    : app(QuotationService::class)->update(
                        $quotation = $this->quotation($id),
                        $this->form($request, UpdateQuotationRequest::class, ['quotation' => $quotation])->payload(),
                    ),
                'purchases' => $id === null
                    ? app(PurchaseService::class)->create($this->form($request, StorePurchaseRequest::class)->payload())
                    : app(PurchaseService::class)->update(
                        $purchase = $this->purchase($id),
                        $this->form($request, UpdatePurchaseRequest::class, ['purchase' => $purchase])->payload(),
                    ),
                'purchase-returns' => $id === null
                    ? app(PurchaseReturnService::class)->create($this->form($request, StorePurchaseReturnRequest::class)->payload())
                    : app(PurchaseReturnService::class)->update(
                        $this->purchaseReturn($id),
                        $this->form($request, UpdatePurchaseReturnRequest::class)->payload(),
                    ),
                'adjustments' => $id === null
                    ? app(StockAdjustmentService::class)->create($this->form($request, StoreStockAdjustmentRequest::class)->payload())
                    : app(StockAdjustmentService::class)->update(
                        $this->adjustment($id),
                        $this->form($request, StoreStockAdjustmentRequest::class)->payload(),
                    ),
                'damages' => $id === null
                    ? app(StockDamageService::class)->create($this->form($request, StoreStockDamageRequest::class)->payload())
                    : app(StockDamageService::class)->update(
                        $this->damage($id),
                        $this->form($request, StoreStockDamageRequest::class)->payload(),
                    ),
                'transfers' => $id === null
                    ? app(StockTransferService::class)->create($this->form($request, StoreStockTransferRequest::class)->payload())
                    : abort(404, 'A transfer cannot be edited. Delete it and record a new one.'),
                'expenses' => $id === null
                    ? app(ExpenseService::class)->create($this->form($request, StoreExpenseRequest::class)->payload())
                    : app(ExpenseService::class)->update(
                        $this->expense($id),
                        $this->form($request, UpdateExpenseRequest::class)->payload(),
                    ),
                'customer-payments' => $id === null
                    ? app(CustomerPaymentService::class)->create($this->form($request, StoreCustomerPaymentRequest::class)->payload())
                    : app(CustomerPaymentService::class)->update(
                        $this->customerPayment($id),
                        $this->form($request, StoreCustomerPaymentRequest::class)->payload(),
                    ),
                'supplier-payments' => $id === null
                    ? app(SupplierPaymentService::class)->create($this->form($request, StoreSupplierPaymentRequest::class)->payload())
                    : app(SupplierPaymentService::class)->update(
                        $this->supplierPayment($id),
                        $this->form($request, StoreSupplierPaymentRequest::class)->payload(),
                    ),
                'employee-payments' => $id === null
                    ? app(EmployeePaymentService::class)->create($this->form($request, StoreEmployeePaymentRequest::class)->payload())
                    : abort(404, 'An employee payment cannot be edited. Delete it and record a new one.'),
                'transactions' => $id === null
                    ? app(TransactionService::class)->create($this->form($request, StoreTransactionRequest::class)->payload())
                    : app(TransactionService::class)->update(
                        $this->transaction($id),
                        $this->form($request, UpdateTransactionRequest::class)->payload(),
                    ),
                'money-sources' => $id === null
                    ? app(MoneySourceService::class)->create($this->form($request, StoreMoneySourceRequest::class)->payload())
                    : app(MoneySourceService::class)->update(
                        $this->moneySource($id),
                        $this->form($request, UpdateMoneySourceRequest::class, [
                            'moneySource' => $this->moneySource($id),
                        ])->payload(),
                    ),
                'attendance' => $id === null
                    ? app(AttendanceService::class)->mark($this->form($request, StoreAttendanceRequest::class)->payload())
                    : app(AttendanceService::class)->update(
                        $this->attendance($id),
                        $this->form($request, UpdateAttendanceRequest::class)->payload(),
                    ),
                'leaves' => $id === null
                    ? app(LeaveService::class)->create($this->form($request, StoreLeaveRequest::class)->payload())
                    : abort(404, 'A leave request cannot be edited. Cancel it and request again.'),
                'payroll' => $id === null
                    ? app(HrService::class)->generatePayrollRun(
                        (string) $request->validate([
                            'period_start' => ['required', 'date'],
                            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                        ])['period_start'],
                        (string) $request->validate([
                            'period_start' => ['required', 'date'],
                            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                        ])['period_end'],
                        $branch->id,
                    )
                    : abort(404, 'A payroll run cannot be edited.'),
                'payroll-adjustments' => $id === null
                    ? app(PayrollAdjustmentService::class)->create($this->form($request, StorePayrollAdjustmentRequest::class)->payload())
                    : app(PayrollAdjustmentService::class)->update(
                        $this->payrollAdjustment($id),
                        $this->form($request, UpdatePayrollAdjustmentRequest::class)->payload(),
                    ),
                default => abort(404, 'That record cannot be saved.'),
            };
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $resource, int $id): array
    {
        return match ($resource) {
            'orders' => app(SaleService::class)->serializeForForm($this->sale($id)),
            'sale-returns' => app(SaleReturnService::class)->serializeForForm($this->saleReturn($id)),
            'quotations' => app(QuotationService::class)->serializeForForm($this->quotation($id)),
            'purchases' => app(PurchaseService::class)->serializeForForm($this->purchase($id)),
            'purchase-returns' => app(PurchaseReturnService::class)->serializeForForm($this->purchaseReturn($id)),
            'adjustments' => app(StockAdjustmentService::class)->findForEdit($id)
                ?? abort(404),
            'damages' => app(StockDamageService::class)->serialize($this->damage($id)),
            'transfers' => app(StockTransferService::class)->serialize($this->transfer($id), BranchContext::ensure()->id),
            'expenses' => app(ExpenseService::class)->serialize($this->expense($id)),
            'customer-payments' => app(CustomerPaymentService::class)->serialize($this->customerPayment($id)),
            'supplier-payments' => app(SupplierPaymentService::class)->serialize($this->supplierPayment($id)),
            'employee-payments' => app(EmployeePaymentService::class)->serialize($this->employeePayment($id)),
            'transactions' => app(TransactionService::class)->serialize($this->transaction($id)),
            'money-sources' => app(MoneySourceService::class)->serializeSource($this->moneySource($id)),
            'attendance' => app(AttendanceService::class)->serialize($this->attendance($id)),
            'leaves' => app(LeaveService::class)->serialize($this->leave($id)),
            'payroll' => $this->payrollPayload($this->payroll($id)),
            'payroll-adjustments' => app(PayrollAdjustmentService::class)->serialize($this->payrollAdjustment($id)),
            default => abort(404, 'That record is not available.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{update: bool, delete: bool, review: bool, finalize: bool}
     */
    private function access(string $resource, array $data): array
    {
        $update = ! in_array($resource, ['sale-returns', 'transfers', 'employee-payments', 'leaves', 'payroll'], true);
        $delete = ! in_array($resource, ['sale-returns', 'payroll'], true);
        $review = $resource === 'leaves' && ! empty($data['can_review']);
        $finalize = $resource === 'payroll' && ($data['status'] ?? '') === 'draft';

        if ($resource === 'transactions' && empty($data['is_manual'])) {
            $update = false;
            $delete = false;
        }
        if ($resource === 'purchases' && ! empty($data['has_returns'])) {
            $update = false;
            $delete = false;
        }
        if ($resource === 'orders' && array_key_exists('can_edit', $data) && ! $data['can_edit']) {
            $update = false;
            $delete = false;
        }
        if ($resource === 'money-sources' && ! empty($data['is_system'])) {
            $update = false;
            $delete = false;
        }
        if ($resource === 'leaves') {
            $delete = ! empty($data['can_cancel']);
        }
        if ($resource === 'payroll-adjustments' && array_key_exists('can_edit', $data) && ! $data['can_edit']) {
            $update = false;
            $delete = false;
        }

        return [
            'update' => $update,
            'delete' => $delete,
            'review' => $review,
            'finalize' => $finalize,
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  list<string>  $drop
     * @return array<string, mixed>
     */
    private function withoutList(array $page, array $drop): array
    {
        foreach ([...$drop, 'filters'] as $key) {
            unset($page[$key]);
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceOptions(Request $request): array
    {
        $listed = app(AttendanceService::class)->listForDate([
            'date' => $request->input('date'),
        ]);

        return [
            'employees' => $listed['employees'],
            'statuses' => collect(AttendanceService::STATUSES)->map(fn (string $status) => [
                'value' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ])->values(),
            'date' => $listed['filters']['date'] ?? company_today(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollPayload(PayrollRun $payroll): array
    {
        $payroll->load(['items.user:id,name,username', 'branch:id,name']);

        return [
            'id' => $payroll->id,
            'number' => $payroll->number,
            'period_start' => $payroll->period_start?->format('Y-m-d'),
            'period_end' => $payroll->period_end?->format('Y-m-d'),
            'status' => $payroll->status,
            'employee_count' => $payroll->employee_count,
            'gross_total' => round((float) $payroll->gross_total, 2),
            'deduction_total' => round((float) $payroll->deduction_total, 2),
            'net_total' => round((float) $payroll->net_total, 2),
            'branch' => $payroll->branch?->name,
            'items' => $payroll->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->user?->name ?: $item->user?->username,
                'net_pay' => round((float) $item->net_pay, 2),
                'status' => $item->status,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $routeParams
     */
    private function form(Request $request, string $class, array $routeParams = []): FormRequest
    {
        foreach ($routeParams as $key => $value) {
            $request->route()?->setParameter($key, $value);
        }

        /** @var FormRequest $form */
        $form = $class::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form;
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'form' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not save.',
            ]);
        }
    }

    private function sale(int $id): Sale
    {
        return Sale::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function saleReturn(int $id): SaleReturn
    {
        return SaleReturn::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function quotation(int $id): Quotation
    {
        return Quotation::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function purchase(int $id): Purchase
    {
        return Purchase::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function purchaseReturn(int $id): PurchaseReturn
    {
        return PurchaseReturn::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function adjustment(int $id): StockAdjustment
    {
        return StockAdjustment::query()->where('branch_id', BranchContext::ensure()->id)->findOrFail($id);
    }

    private function damage(int $id): StockDamage
    {
        return StockDamage::query()
            ->with([
                'branch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where('branch_id', BranchContext::ensure()->id)
            ->findOrFail($id);
    }

    private function transfer(int $id): StockTransfer
    {
        $branchId = BranchContext::ensure()->id;

        return StockTransfer::query()
            ->with([
                'fromBranch:id,name',
                'toBranch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where(function ($query) use ($branchId) {
                $query->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId);
            })
            ->findOrFail($id);
    }

    private function expense(int $id): LedgerTransaction
    {
        return LedgerTransaction::query()
            ->with(['account:id,name,type', 'moneySource:id,name,type', 'creator:id,name,username', 'branch:id,name'])
            ->where('branch_id', BranchContext::ensure()->id)
            ->where('reference_type', 'expense')
            ->findOrFail($id);
    }

    private function transaction(int $id): LedgerTransaction
    {
        return LedgerTransaction::query()
            ->with(['account:id,name,type', 'moneySource:id,name,type', 'creator:id,name,username', 'branch:id,name'])
            ->where('branch_id', BranchContext::ensure()->id)
            ->findOrFail($id);
    }

    private function customerPayment(int $id): CustomerPayment
    {
        return CustomerPayment::query()
            ->with(['customer:id,name,balance', 'moneySource:id,name,type', 'sales:id,number', 'branch:id,name', 'receiver:id,name,username'])
            ->where('branch_id', BranchContext::ensure()->id)
            ->findOrFail($id);
    }

    private function supplierPayment(int $id): SupplierPayment
    {
        return SupplierPayment::query()
            ->with(['supplier:id,name,balance', 'moneySource:id,name,type', 'purchases:id,number', 'branch:id,name'])
            ->where('branch_id', BranchContext::ensure()->id)
            ->findOrFail($id);
    }

    private function employeePayment(int $id): EmployeePayment
    {
        return EmployeePayment::query()
            ->with([
                'user:id,name,username',
                'moneySource:id,name,type',
                'payrollItem.payrollRun:id,number',
                'branch:id,name',
                'creator:id,name,username',
            ])
            ->where('branch_id', BranchContext::ensure()->id)
            ->findOrFail($id);
    }

    private function moneySource(int $id): MoneySource
    {
        return MoneySource::query()->with('branches:id,name')->withCount('branches')->findOrFail($id);
    }

    private function attendance(int $id): AttendanceRecord
    {
        return AttendanceRecord::query()->with('user:id,name,username')->findOrFail($id);
    }

    private function leave(int $id): LeaveRequest
    {
        return LeaveRequest::query()->with(['user:id,name,username', 'reviewer:id,name,username'])->findOrFail($id);
    }

    private function payroll(int $id): PayrollRun
    {
        $branchId = BranchContext::ensure()->id;

        return PayrollRun::query()
            ->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->findOrFail($id);
    }

    private function payrollAdjustment(int $id): PayrollAdjustment
    {
        return PayrollAdjustment::query()->with(['user:id,name,username', 'creator:id,name,username'])->findOrFail($id);
    }
}
