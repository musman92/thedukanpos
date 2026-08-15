<?php

namespace App\Services;

use App\Models\MoneySource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   supplier_id?:int|string|null,
     *   money_source_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   payments: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   suppliers: Collection,
     *   money_sources: Collection,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $this->finance->seedDefaultAccounts();

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $supplierId = $filters['supplier_id'] !== null && $filters['supplier_id'] !== ''
            ? (int) $filters['supplier_id']
            : null;
        $moneySourceId = $filters['money_source_id'] !== null && $filters['money_source_id'] !== ''
            ? (int) $filters['money_source_id']
            : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $branch = BranchContext::ensure();

        $payments = SupplierPayment::query()
            ->with([
                'supplier:id,name,balance',
                'moneySource:id,name,type',
                'creator:id,name,username',
                'branch:id,name',
                'purchases:id,number',
            ])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('moneySource', fn ($m) => $m->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($moneySourceId, fn ($query) => $query->where('money_source_id', $moneySourceId))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SupplierPayment $payment) => $this->serialize($payment));

        return [
            'payments' => $payments,
            'filters' => [
                'q' => $q,
                'supplier_id' => $supplierId,
                'money_source_id' => $moneySourceId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'balance'])
                ->map(fn (Supplier $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'balance' => round((float) $s->balance, 2),
                ]),
            'money_sources' => MoneySource::query()
                ->forPayments()
                ->forBranch($branch->id)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance'])
                ->map(fn (MoneySource $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'type' => $s->type,
                    'balance' => round((float) $s->balance, 2),
                ]),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   form_supplier_id: int|null,
     *   unpaid_purchases: Collection,
     *   balance_summary: array{
     *     amount_owed: float,
     *     purchases_pending: float,
     *     other_outstanding: float,
     *     prepayment_available: float
     *   }
     * }
     */
    public function formContext(?int $supplierId = null, ?SupplierPayment $editing = null): array
    {
        $branch = BranchContext::ensure();
        $unpaidPurchases = collect();
        $purchasesPending = 0.0;
        $amountOwed = 0.0;
        $otherOutstanding = 0.0;
        $prepaymentAvailable = 0.0;

        /** @var array<int, float> $restoredAlloc */
        $restoredAlloc = [];
        if ($editing) {
            $editing->loadMissing('purchases');
            foreach ($editing->purchases as $purchase) {
                $restoredAlloc[(int) $purchase->id] = (float) $purchase->pivot->amount;
            }
        }

        if ($supplierId) {
            $supplier = Supplier::query()->find($supplierId);
            if ($supplier) {
                $balance = (float) $supplier->balance;
                if ($editing && (int) $editing->supplier_id === (int) $supplierId) {
                    // Show balances as if this payment were reversed.
                    $balance += (float) $editing->amount;
                }
                $amountOwed = round(max(0, $balance), 2);
                $prepaymentAvailable = round(max(0, -$balance), 2);
            }

            $restoredIds = array_keys($restoredAlloc);

            $unpaidPurchases = Purchase::query()
                ->where('branch_id', $branch->id)
                ->where('supplier_id', $supplierId)
                ->where(function ($query) use ($restoredIds) {
                    $query->where('payment_status', 'pending')
                        ->orWhere('payment_status', 'partial')
                        ->orWhereRaw('(total - returned_amount - paid_amount) > 0.0001');
                    if ($restoredIds !== []) {
                        $query->orWhereIn('id', $restoredIds);
                    }
                })
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get()
                ->map(function (Purchase $purchase) use ($restoredAlloc) {
                    $restored = $restoredAlloc[(int) $purchase->id] ?? 0.0;
                    $pending = round($purchase->balanceDue() + $restored, 2);
                    if ($pending <= 0.0001) {
                        return null;
                    }

                    return [
                        'id' => $purchase->id,
                        'number' => $purchase->number,
                        'purchase_date' => $purchase->purchase_date?->format('Y-m-d'),
                        'total' => round((float) $purchase->total, 2),
                        'paid_amount' => round(max(0, (float) $purchase->paid_amount - $restored), 2),
                        'returned_amount' => round((float) $purchase->returned_amount, 2),
                        'pending_amount' => $pending,
                    ];
                })
                ->filter()
                ->values();

            $purchasesPending = round((float) $unpaidPurchases->sum('pending_amount'), 2);
            $otherOutstanding = round(max(0, $amountOwed - $purchasesPending), 2);
        }

        return [
            'form_supplier_id' => $supplierId,
            'unpaid_purchases' => $unpaidPurchases,
            'balance_summary' => [
                'amount_owed' => $amountOwed,
                'purchases_pending' => $purchasesPending,
                'other_outstanding' => $otherOutstanding,
                'prepayment_available' => $prepaymentAvailable,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'payment_date', 'amount'];
        $sort = strtolower(trim((string) ($sort ?? 'payment_date')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'payment_date';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{
     *   supplier_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes?:string|null,
     *   total_amount?:float|int|string|null,
     *   purchase_amounts?:array<int|string, float|int|string|null>
     * }  $data
     */
    public function create(array $data): SupplierPayment
    {
        $branch = BranchContext::ensure();
        $supplierId = (int) $data['supplier_id'];

        $unpaidPurchases = Purchase::query()
            ->where('branch_id', $branch->id)
            ->where('supplier_id', $supplierId)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Purchase $p) => $p->balanceDue() > 0.0001)
            ->values();

        $allocations = [];
        $totalPaymentAmount = 0.0;
        $allocatedToPurchases = 0.0;

        $hasTotal = isset($data['total_amount']) && $data['total_amount'] !== '' && $data['total_amount'] !== null;
        $purchaseAmounts = $data['purchase_amounts'] ?? [];

        if ($hasTotal) {
            $requestedAmount = round((float) $data['total_amount'], 4);
            if ($requestedAmount <= 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Payment amount must be greater than zero.',
                ]);
            }

            $remaining = $requestedAmount;
            foreach ($unpaidPurchases as $purchase) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $pending = $purchase->balanceDue();
                $paymentAmount = round(min($remaining, $pending), 4);
                if ($paymentAmount > 0.0001) {
                    $allocations[] = [
                        'purchase_id' => $purchase->id,
                        'amount' => $paymentAmount,
                    ];
                    $allocatedToPurchases += $paymentAmount;
                    $remaining = round($remaining - $paymentAmount, 4);
                }
            }
            $totalPaymentAmount = $requestedAmount;
        } elseif (is_array($purchaseAmounts) && $purchaseAmounts !== []) {
            foreach ($purchaseAmounts as $purchaseId => $amount) {
                $amount = round((float) ($amount ?? 0), 4);
                if ($amount <= 0) {
                    continue;
                }

                $purchase = $unpaidPurchases->firstWhere('id', (int) $purchaseId);
                if (! $purchase) {
                    throw ValidationException::withMessages([
                        "purchase_amounts.{$purchaseId}" => 'This purchase is not unpaid for the selected supplier.',
                    ]);
                }

                $pending = $purchase->balanceDue();
                if ($amount > $pending + 0.01) {
                    throw ValidationException::withMessages([
                        "purchase_amounts.{$purchaseId}" => 'Payment amount cannot exceed purchase pending ('.format_amount($pending).'). Use total payment amount to pay opening balance or advance.',
                    ]);
                }

                $allocations[] = [
                    'purchase_id' => (int) $purchaseId,
                    'amount' => $amount,
                ];
                $totalPaymentAmount += $amount;
                $allocatedToPurchases += $amount;
            }

            if ($totalPaymentAmount <= 0) {
                throw ValidationException::withMessages([
                    'purchase_amounts' => 'Enter at least one purchase payment amount, or a total payment amount.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'total_amount' => 'Enter a total payment amount, or individual purchase amounts.',
            ]);
        }

        try {
            return $this->finance->paySupplier([
                'supplier_id' => $supplierId,
                'money_source_id' => (int) $data['money_source_id'],
                'amount' => $totalPaymentAmount,
                'branch_id' => $branch->id,
                'allocations' => $allocations,
                'apply_purchase_paid' => true,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'total_amount' => $e->getMessage(),
            ]);
        }
    }

    public function delete(SupplierPayment $payment): void
    {
        try {
            $this->finance->reverseSupplierPayment($payment);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'payment' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{
     *   supplier_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes?:string|null,
     *   total_amount?:float|int|string|null,
     *   purchase_amounts?:array<int|string, float|int|string|null>
     * }  $data
     */
    public function update(SupplierPayment $payment, array $data): SupplierPayment
    {
        try {
            return DB::transaction(function () use ($payment, $data) {
                $this->finance->reverseSupplierPayment($payment);

                return $this->create($data);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'total_amount' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => round((float) $payment->amount, 2),
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'notes' => $payment->notes,
            'supplier_id' => $payment->supplier_id,
            'money_source_id' => $payment->money_source_id,
            'purchase_id' => $payment->purchase_id,
            'supplier' => $payment->supplier
                ? [
                    'id' => $payment->supplier->id,
                    'name' => $payment->supplier->name,
                    'balance' => round((float) $payment->supplier->balance, 2),
                ]
                : null,
            'money_source' => $payment->moneySource
                ? [
                    'id' => $payment->moneySource->id,
                    'name' => $payment->moneySource->name,
                    'type' => $payment->moneySource->type,
                ]
                : null,
            'purchases' => $payment->relationLoaded('purchases')
                ? $payment->purchases->map(fn (Purchase $p) => [
                    'id' => $p->id,
                    'number' => $p->number,
                    'amount' => round((float) $p->pivot->amount, 2),
                ])->values()
                : [],
            'branch' => $payment->branch
                ? ['id' => $payment->branch->id, 'name' => $payment->branch->name]
                : null,
            'creator' => $payment->creator
                ? [
                    'id' => $payment->creator->id,
                    'name' => $payment->creator->name ?: $payment->creator->username,
                ]
                : null,
        ];
    }
}
