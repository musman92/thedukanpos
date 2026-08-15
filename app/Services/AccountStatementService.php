<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\EmployeePayment;
use App\Models\EmployeeProfile;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountStatementService
{
    /**
     * @param  array{
     *   type?:string|null,
     *   party_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   branch_id?:int|string|null
     * }  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $activeBranch = BranchContext::ensure();
        $branchId = isset($input['branch_id']) && $input['branch_id'] !== ''
            ? (int) $input['branch_id']
            : (int) $activeBranch->id;

        $branch = Branch::query()->where('is_active', true)->find($branchId)
            ?? $activeBranch;
        $branchId = (int) $branch->id;

        $type = strtolower(trim((string) ($input['type'] ?? 'customer')));
        if (! in_array($type, ['customer', 'supplier', 'employee'], true)) {
            $type = 'customer';
        }

        $from = (string) ($input['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($input['to'] ?? now()->toDateString());
        $partyId = isset($input['party_id']) && $input['party_id'] !== ''
            ? (int) $input['party_id']
            : null;

        $party = null;
        $statement = null;
        $partyBalance = 0.0;
        $partyBalanceHint = '';

        if ($partyId) {
            if ($type === 'customer') {
                $party = Customer::query()->find($partyId);
                if ($party) {
                    $partyBalance = money_round($party->balance);
                    $partyBalanceHint = 'Amount customer owes';
                    $statement = $this->customerStatement($party, $branchId, $from, $to);
                }
            } elseif ($type === 'supplier') {
                $party = Supplier::query()->find($partyId);
                if ($party) {
                    $partyBalance = money_round($party->balance);
                    $partyBalanceHint = 'Amount you owe supplier';
                    $statement = $this->supplierStatement($party, $branchId, $from, $to);
                }
            } else {
                $party = User::query()
                    ->whereHas('employeeProfile', fn ($q) => $q->active())
                    ->find($partyId);
                if ($party) {
                    $partyBalance = 0.0;
                    $partyBalanceHint = 'Employee payments (no AR/AP balance tracked)';
                    $statement = $this->employeeStatement($party, $branchId, $from, $to);
                }
            }
        }

        $typeLabel = match ($type) {
            'supplier' => 'Supplier',
            'employee' => 'Employee',
            default => 'Customer',
        };

        return [
            'filters' => [
                'type' => $type,
                'party_id' => $party?->id,
                'from' => $from,
                'to' => $to,
                'branch_id' => $branchId,
            ],
            'type' => $type,
            'type_label' => $typeLabel,
            'party' => $party ? [
                'id' => $party->id,
                'name' => $party->name,
                'phone' => $party->phone ?? null,
            ] : null,
            'party_balance' => $partyBalance,
            'party_balance_hint' => $partyBalanceHint,
            'statement' => $statement,
            'parties' => [
                'customer' => $this->customerOptions(),
                'supplier' => $this->supplierOptions(),
                'employee' => $this->employeeOptions(),
            ],
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])
                ->values()
                ->all(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{lines: list<array<string, mixed>>, opening_balance: float, closing_balance: float}
     */
    public function customerStatement(Customer $customer, int $branchId, ?string $from, ?string $to): array
    {
        $lines = collect();

        $sales = Sale::query()
            ->with(['payments.moneySource:id,name'])
            ->where('branch_id', $branchId)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', Sale::STATUS_VOID)
            ->where('status', '!=', Sale::STATUS_PARKED)
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $total = money_round($sale->total);
            if ($total <= 0) {
                continue;
            }

            $at = Carbon::parse($sale->created_at);
            $display = $at->copy()->startOfDay();

            $lines->push($this->line(
                date: $display,
                type: 'sale',
                label: 'Sale',
                reference: $sale->number,
                url: route('admin.orders.show', $sale),
                debit: $total,
                credit: 0.0,
                sortAt: $at,
                sortId: (int) $sale->id,
                sortSequence: 10,
            ));

            $paidAtSale = money_round($sale->payments->sum('amount'));
            if ($paidAtSale > 0) {
                $source = $sale->payments->first()?->moneySource?->name;
                $lines->push($this->line(
                    date: $display,
                    type: 'sale_payment',
                    label: 'Payment at sale',
                    reference: $sale->number,
                    url: route('admin.orders.show', $sale),
                    debit: 0.0,
                    credit: min($paidAtSale, $total),
                    sortAt: $at->copy()->addMicrosecond(),
                    sortId: (int) $sale->id,
                    sortSequence: 20,
                    moneySource: $source,
                ));
            }
        }

        $payments = CustomerPayment::query()
            ->with('moneySource:id,name')
            ->where('branch_id', $branchId)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $applied = money_round((float) $payment->amount + (float) ($payment->discount_amount ?? 0));
            if ($applied <= 0) {
                continue;
            }

            $business = Carbon::parse($payment->payment_date)->startOfDay();
            $recorded = Carbon::parse($payment->created_at);
            $label = (float) ($payment->discount_amount ?? 0) > 0
                ? 'Payment received (incl. write-off)'
                : 'Payment received';

            $lines->push($this->line(
                date: $business,
                type: 'customer_payment',
                label: $label,
                reference: 'CP-'.$payment->id,
                url: null,
                debit: 0.0,
                credit: $applied,
                sortAt: $recorded,
                sortId: (int) $payment->id,
                sortSequence: 30,
                moneySource: $payment->moneySource?->name,
            ));
        }

        $returns = SaleReturn::query()
            ->where('branch_id', $branchId)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $amount = money_round($ret->total);
            if ($amount <= 0) {
                continue;
            }

            $business = Carbon::parse($ret->return_date ?? $ret->created_at)->startOfDay();
            $recorded = Carbon::parse($ret->created_at);

            $lines->push($this->line(
                date: $business,
                type: 'sale_return',
                label: 'Sale return',
                reference: $ret->number,
                url: null,
                debit: 0.0,
                credit: $amount,
                sortAt: $recorded,
                sortId: (int) $ret->id,
                sortSequence: 40,
            ));
        }

        return $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'customer',
            money_round($customer->balance),
            Carbon::parse($customer->created_at ?? now())->startOfDay(),
        );
    }

    /**
     * @return array{lines: list<array<string, mixed>>, opening_balance: float, closing_balance: float}
     */
    public function supplierStatement(Supplier $supplier, int $branchId, ?string $from, ?string $to): array
    {
        $lines = collect();

        $purchases = Purchase::query()
            ->where('branch_id', $branchId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            $total = money_round($purchase->total);
            if ($total <= 0) {
                continue;
            }

            $business = Carbon::parse($purchase->purchase_date ?? $purchase->created_at)->startOfDay();
            $recorded = Carbon::parse($purchase->created_at);

            $lines->push($this->line(
                date: $business,
                type: 'purchase',
                label: 'Purchase',
                reference: $purchase->number,
                url: route('admin.purchases.show', $purchase),
                debit: 0.0,
                credit: $total,
                sortAt: $recorded,
                sortId: (int) $purchase->id,
                sortSequence: 10,
            ));
        }

        $payments = SupplierPayment::query()
            ->with('moneySource:id,name')
            ->where('branch_id', $branchId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $amount = money_round($payment->amount);
            if ($amount <= 0) {
                continue;
            }

            $business = Carbon::parse($payment->payment_date)->startOfDay();
            $recorded = Carbon::parse($payment->created_at);

            $lines->push($this->line(
                date: $business,
                type: 'supplier_payment',
                label: 'Payment made',
                reference: 'SP-'.$payment->id,
                url: null,
                debit: $amount,
                credit: 0.0,
                sortAt: $recorded,
                sortId: (int) $payment->id,
                sortSequence: 30,
                moneySource: $payment->moneySource?->name,
            ));
        }

        $returns = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->where('supplier_id', $supplier->id)
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $amount = money_round($ret->total);
            if ($amount <= 0) {
                continue;
            }

            $business = Carbon::parse($ret->return_date ?? $ret->created_at)->startOfDay();
            $recorded = Carbon::parse($ret->created_at);

            $lines->push($this->line(
                date: $business,
                type: 'purchase_return',
                label: 'Purchase return',
                reference: $ret->number,
                url: null,
                debit: $amount,
                credit: 0.0,
                sortAt: $recorded,
                sortId: (int) $ret->id,
                sortSequence: 40,
            ));
        }

        return $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'supplier',
            money_round($supplier->balance),
            Carbon::parse($supplier->created_at ?? now())->startOfDay(),
        );
    }

    /**
     * @return array{lines: list<array<string, mixed>>, opening_balance: float, closing_balance: float}
     */
    public function employeeStatement(User $employee, int $branchId, ?string $from, ?string $to): array
    {
        $lines = collect();

        $payments = EmployeePayment::query()
            ->with('moneySource:id,name')
            ->where('branch_id', $branchId)
            ->where('user_id', $employee->id)
            ->orderBy('id')
            ->get();

        $net = 0.0;
        foreach ($payments as $payment) {
            $amount = money_round($payment->amount);
            if ($amount <= 0) {
                continue;
            }

            $business = Carbon::parse($payment->payment_date)->startOfDay();
            $recorded = Carbon::parse($payment->created_at);
            $label = EmployeePayment::KIND_LABELS[$payment->kind] ?? ucfirst((string) $payment->kind);

            $lines->push($this->line(
                date: $business,
                type: 'employee_payment',
                label: $label,
                reference: 'EP-'.$payment->id,
                url: null,
                debit: $amount,
                credit: 0.0,
                sortAt: $recorded,
                sortId: (int) $payment->id,
                sortSequence: 10,
                moneySource: $payment->moneySource?->name,
            ));

            $net = money_round($net - $amount);
        }

        return $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'employee',
            $net,
            Carbon::parse($employee->created_at ?? now())->startOfDay(),
        );
    }

    /**
     * @return Collection<int, array{value:int, label:string, meta:?string}>
     */
    protected function customerOptions(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'value' => $c->id,
                'label' => $c->name.($c->phone ? ' · '.$c->phone : ''),
                'meta' => abs((float) $c->balance) >= 0.01
                    ? 'Bal '.format_amount($c->balance)
                    : null,
            ]);
    }

    /**
     * @return Collection<int, array{value:int, label:string, meta:?string}>
     */
    protected function supplierOptions(): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Supplier $s) => [
                'value' => $s->id,
                'label' => $s->name.($s->phone ? ' · '.$s->phone : ''),
                'meta' => abs((float) $s->balance) >= 0.01
                    ? 'Bal '.format_amount($s->balance)
                    : null,
            ]);
    }

    /**
     * @return Collection<int, array{value:int, label:string, meta:?string}>
     */
    protected function employeeOptions(): Collection
    {
        return EmployeeProfile::query()
            ->active()
            ->with('user:id,name,username,phone')
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeProfile $p) => $p->user !== null)
            ->map(function (EmployeeProfile $p) {
                $name = $p->user->name ?: $p->user->username;

                return [
                    'value' => $p->user_id,
                    'label' => $name
                        .($p->designation ? ' · '.$p->designation : '')
                        .($p->user->phone ? ' · '.$p->user->phone : ''),
                    'meta' => $p->employee_number,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, opening_balance: float, closing_balance: float}
     */
    protected function finalizeStatement(
        Collection $lines,
        ?string $from,
        ?string $to,
        string $partyType,
        float $partyBalance,
        Carbon $seedDate,
    ): array {
        $sorted = $lines->sortBy('sort_key')->values();

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $allNet = 0.0;
        foreach ($sorted as $row) {
            $allNet = money_round($allNet + $this->balanceDelta($row, $partyType));
        }
        $seed = money_round($partyBalance - $allNet);

        $balanceBeforePeriod = $seed;
        $running = 0.0;
        $periodLines = [];
        $inPeriod = false;

        foreach ($sorted as $row) {
            /** @var Carbon $rowDate */
            $rowDate = $row['date'];
            $delta = $this->balanceDelta($row, $partyType);

            if ($toDate && $rowDate->gt($toDate)) {
                continue;
            }

            if ($fromDate && $rowDate->lt($fromDate)) {
                $balanceBeforePeriod = money_round($balanceBeforePeriod + $delta);

                continue;
            }

            if (! $inPeriod) {
                $running = $balanceBeforePeriod;
                $inPeriod = true;
            }

            $running = money_round($running + $delta);
            $row['balance'] = $running;
            $row['date_display'] = format_company_date($rowDate);
            $periodLines[] = $row;
        }

        $openingBalance = $balanceBeforePeriod;
        $closingBalance = $periodLines !== []
            ? (float) end($periodLines)['balance']
            : $openingBalance;

        if ($fromDate !== null || abs($openingBalance) >= 0.01) {
            $openingDate = $fromDate?->copy() ?? $seedDate->copy();
            array_unshift($periodLines, [
                'date' => $openingDate,
                'date_display' => format_company_date($openingDate),
                'type' => 'opening_balance',
                'label' => 'Opening balance',
                'reference' => $fromDate ? 'Brought forward' : 'Opening balance',
                'url' => null,
                'money_source' => null,
                'debit' => 0.0,
                'credit' => 0.0,
                'balance' => $openingBalance,
            ]);
        }

        $serialized = array_map(function (array $row) {
            unset($row['date'], $row['sort_at'], $row['sort_id'], $row['sort_sequence'], $row['sort_key']);

            return $row;
        }, $periodLines);

        return [
            'lines' => $serialized,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(
        Carbon $date,
        string $type,
        string $label,
        string $reference,
        ?string $url,
        float $debit,
        float $credit,
        Carbon $sortAt,
        int $sortId,
        int $sortSequence = 50,
        ?string $moneySource = null,
    ): array {
        return [
            'date' => $date,
            'date_display' => $date->toDateString(),
            'type' => $type,
            'label' => $label,
            'reference' => $reference,
            'url' => $url,
            'money_source' => $moneySource,
            'debit' => money_round($debit),
            'credit' => money_round($credit),
            'sort_at' => $sortAt,
            'sort_id' => $sortId,
            'sort_sequence' => $sortSequence,
            'sort_key' => $sortAt->format('Y-m-d H:i:s.u')
                .'-'.str_pad((string) $sortSequence, 3, '0', STR_PAD_LEFT)
                .'-'.str_pad((string) $sortId, 10, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function balanceDelta(array $row, string $partyType): float
    {
        $debit = (float) $row['debit'];
        $credit = (float) $row['credit'];

        if (in_array($partyType, ['supplier', 'employee'], true)) {
            return money_round($credit - $debit);
        }

        return money_round($debit - $credit);
    }
}
