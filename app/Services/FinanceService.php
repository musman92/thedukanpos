<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\EmployeePayment;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\MoneySourceTransfer;
use App\Models\PayrollItem;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\MoneyBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function seedDefaultAccounts(): void
    {
        if (Account::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Sales', 'type' => 'income'],
            ['name' => 'Other Income', 'type' => 'income'],
            ['name' => 'Purchase', 'type' => 'expense'],
            ['name' => 'Salary', 'type' => 'expense'],
            ['name' => 'Rent', 'type' => 'expense'],
            ['name' => 'Utilities', 'type' => 'expense'],
            ['name' => 'Transport', 'type' => 'expense'],
            ['name' => 'Maintenance', 'type' => 'expense'],
            ['name' => 'Miscellaneous', 'type' => 'expense'],
        ];

        foreach ($defaults as $row) {
            Account::query()->create([
                ...$row,
                'is_active' => true,
                'is_system' => true,
            ]);
        }
    }

    /**
     * @param  array{
     *   account_id:int,
     *   money_source_id?:int|null,
     *   branch_id?:int|null,
     *   shift_id?:int|null,
     *   direction:string,
     *   amount:float|int|string,
     *   txn_date?:string|null,
     *   reference_type?:string|null,
     *   reference_id?:int|null,
     *   notes?:string|null
     * }  $data
     */
    public function createTransaction(array $data): LedgerTransaction
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \RuntimeException('Amount must be positive.');
            }

            $direction = $data['direction'];
            if (! in_array($direction, ['in', 'out'], true)) {
                throw new \RuntimeException('Direction must be in or out.');
            }

            Account::query()->findOrFail($data['account_id']);

            $txn = LedgerTransaction::query()->create([
                'branch_id' => $data['branch_id'] ?? null,
                'account_id' => $data['account_id'],
                'money_source_id' => $data['money_source_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'direction' => $direction,
                'amount' => $amount,
                'txn_date' => $data['txn_date'] ?? now()->toDateString(),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'is_manual' => (bool) ($data['is_manual'] ?? false),
            ]);

            if (! empty($data['money_source_id'])) {
                $source = MoneySource::query()->lockForUpdate()->findOrFail($data['money_source_id']);
                $source->balance = (float) $source->balance + ($direction === 'in' ? $amount : -$amount);
                $source->save();
            }

            return $txn;
        });
    }

    public function transferBetweenSources(
        int $fromId,
        int $toId,
        float $amount,
        ?int $branchId = null,
        ?string $date = null,
        ?string $notes = null,
    ): MoneySourceTransfer {
        if ($fromId === $toId) {
            throw new \RuntimeException('Choose different money sources.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Transfer amount must be positive.');
        }

        return DB::transaction(function () use ($fromId, $toId, $amount, $branchId, $date, $notes) {
            $from = MoneySource::query()->lockForUpdate()->findOrFail($fromId);
            $to = MoneySource::query()->lockForUpdate()->findOrFail($toId);

            if (! $from->isSelectableForPayment() || ! $to->isSelectableForPayment()) {
                throw new \RuntimeException('Invalid money source selection.');
            }

            try {
                $amount = \App\Support\MoneyBalance::resolveDebitAmount(
                    $amount,
                    (float) $from->balance,
                    $from->name,
                );
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException($e->getMessage());
            }

            $from->balance = (float) $from->balance - $amount;
            $from->save();

            $to->balance = (float) $to->balance + $amount;
            $to->save();

            return MoneySourceTransfer::query()->create([
                'from_money_source_id' => $from->id,
                'to_money_source_id' => $to->id,
                'branch_id' => $branchId,
                'amount' => $amount,
                'transfer_date' => $date ?? now()->toDateString(),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * @param  array{
     *   supplier_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   branch_id?:int|null,
     *   purchase_id?:int|null,
     *   allocations?:list<array{purchase_id:int, amount:float|int|string}>,
     *   apply_purchase_paid?:bool,
     *   payment_date?:string|null,
     *   notes?:string|null
     * }  $data
     */
    public function paySupplier(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \RuntimeException('Payment amount must be positive.');
            }

            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
            $source = MoneySource::query()->lockForUpdate()->findOrFail($data['money_source_id']);

            if (! $source->isSelectableForPayment()) {
                throw new \RuntimeException('Invalid or inactive payment source. Owner Withdrawal cannot be used for payments.');
            }

            try {
                $amount = MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException($e->getMessage());
            }

            /** @var list<array{purchase_id:int, amount:float}> $allocations */
            $allocations = [];
            foreach ($data['allocations'] ?? [] as $row) {
                $allocAmount = round((float) ($row['amount'] ?? 0), 4);
                if ($allocAmount <= 0) {
                    continue;
                }
                $allocations[] = [
                    'purchase_id' => (int) $row['purchase_id'],
                    'amount' => $allocAmount,
                ];
            }

            if ($allocations === [] && ! empty($data['purchase_id'])) {
                $allocations[] = [
                    'purchase_id' => (int) $data['purchase_id'],
                    'amount' => $amount,
                ];
            }

            $applyPurchasePaid = array_key_exists('apply_purchase_paid', $data)
                ? (bool) $data['apply_purchase_paid']
                : true;

            // Positive balance = we owe supplier; payment may create prepaid (negative).
            $supplier->balance = (float) $supplier->balance - $amount;
            $supplier->save();

            $source->balance = (float) $source->balance - $amount;
            $source->save();

            $legacyPurchaseId = count($allocations) === 1
                ? $allocations[0]['purchase_id']
                : ($data['purchase_id'] ?? null);

            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'branch_id' => $data['branch_id'] ?? null,
                'money_source_id' => $source->id,
                'purchase_id' => $legacyPurchaseId,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($allocations as $row) {
                $purchase = Purchase::query()->lockForUpdate()->findOrFail($row['purchase_id']);
                if ((int) $purchase->supplier_id !== (int) $supplier->id) {
                    throw new \RuntimeException('A purchase does not belong to this supplier.');
                }

                if ($applyPurchasePaid) {
                    $net = $purchase->netAmount();
                    $newPaid = round(min($net, (float) $purchase->paid_amount + $row['amount']), 4);
                    $purchase->paid_amount = $newPaid;
                    $purchase->save();
                    $purchase->refreshPaymentStatus();
                }

                $payment->purchases()->attach($purchase->id, ['amount' => $row['amount']]);
            }

            $purchaseAccount = Account::query()
                ->where('type', 'expense')
                ->where('name', 'Purchase')
                ->first();

            if ($purchaseAccount) {
                LedgerTransaction::query()->create([
                    'branch_id' => $data['branch_id'] ?? null,
                    'account_id' => $purchaseAccount->id,
                    'money_source_id' => $source->id,
                    'direction' => 'out',
                    'amount' => $amount,
                    'txn_date' => $payment->payment_date,
                    'reference_type' => 'supplier_payment',
                    'reference_id' => $payment->id,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                    'is_manual' => false,
                ]);
            }

            return $payment;
        });
    }

    public function reverseSupplierPayment(SupplierPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->trashed()) {
                throw new \RuntimeException('This payment has already been deleted.');
            }

            $amount = (float) $payment->amount;

            $payment->loadMissing('purchases');

            $allocations = $payment->purchases
                ->map(fn (Purchase $purchase) => [
                    'purchase_id' => $purchase->id,
                    'amount' => (float) $purchase->pivot->amount,
                ])
                ->values()
                ->all();

            if ($allocations === [] && $payment->purchase_id) {
                $allocations[] = [
                    'purchase_id' => (int) $payment->purchase_id,
                    'amount' => $amount,
                ];
            }

            foreach ($allocations as $row) {
                $purchase = Purchase::query()->lockForUpdate()->find($row['purchase_id']);
                if (! $purchase) {
                    continue;
                }

                $purchase->paid_amount = max(
                    0,
                    round((float) $purchase->paid_amount - (float) $row['amount'], 4),
                );
                $purchase->save();
                $purchase->refreshPaymentStatus();
            }

            $payment->purchases()->detach();

            $supplier = Supplier::query()->lockForUpdate()->findOrFail($payment->supplier_id);
            $supplier->balance = (float) $supplier->balance + $amount;
            $supplier->save();

            if ($payment->money_source_id) {
                $source = MoneySource::query()->lockForUpdate()->find($payment->money_source_id);
                if ($source) {
                    $source->balance = (float) $source->balance + $amount;
                    $source->save();
                }
            }

            LedgerTransaction::query()
                ->where('reference_type', 'supplier_payment')
                ->where('reference_id', $payment->id)
                ->get()
                ->each(function (LedgerTransaction $txn) {
                    $txn->delete();
                });

            $payment->delete();
        });
    }

    /**
     * @param  array{
     *   customer_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   discount_amount?:float|int|string,
     *   branch_id?:int|null,
     *   shift_id?:int|null,
     *   payment_date?:string|null,
     *   notes?:string|null,
     *   allocations?:list<array{sale_id:int, amount:float|int|string}>,
     *   apply_sale_paid?:bool
     * }  $data
     */
    public function receiveCustomerPayment(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $amount = round((float) $data['amount'], 4);
            $discountAmount = round(max(0, (float) ($data['discount_amount'] ?? 0)), 4);
            $totalApplied = round($amount + $discountAmount, 4);

            if ($amount <= 0) {
                throw new \RuntimeException('Payment amount must be positive.');
            }

            if ($totalApplied <= 0) {
                throw new \RuntimeException('Amount received plus discount must be greater than zero.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            $source = MoneySource::query()->lockForUpdate()->findOrFail($data['money_source_id']);

            if (! $source->isSelectableForPayment()) {
                throw new \RuntimeException('Invalid or inactive payment source. Owner Withdrawal cannot be used for payments.');
            }

            /** @var list<array{sale_id:int, amount:float}> $allocations */
            $allocations = [];
            foreach ($data['allocations'] ?? [] as $row) {
                $allocAmount = round((float) ($row['amount'] ?? 0), 4);
                if ($allocAmount <= 0) {
                    continue;
                }
                $allocations[] = [
                    'sale_id' => (int) $row['sale_id'],
                    'amount' => $allocAmount,
                ];
            }

            $applySalePaid = array_key_exists('apply_sale_paid', $data)
                ? (bool) $data['apply_sale_paid']
                : true;

            // Positive balance = customer owes us; overpay becomes prepaid (negative).
            // Cash + discount/write-off both clear balance; only cash hits the money source.
            $customer->balance = (float) $customer->balance - $totalApplied;
            $customer->save();

            $source->balance = (float) $source->balance + $amount;
            $source->save();

            $branchId = $data['branch_id'] ?? null;
            $shiftId = $data['shift_id'] ?? null;
            if ($shiftId === null && $branchId) {
                $shiftId = Shift::query()
                    ->where('branch_id', $branchId)
                    ->open()
                    ->value('id');
            }

            $paymentDate = $data['payment_date'] ?? now()->toDateString();

            $payment = CustomerPayment::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'money_source_id' => $source->id,
                'shift_id' => $shiftId,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'discount_amount' => $discountAmount,
                'balance_after' => $customer->balance,
                'notes' => $data['notes'] ?? null,
                'received_by' => Auth::id(),
            ]);

            foreach ($allocations as $row) {
                $sale = Sale::query()->lockForUpdate()->findOrFail($row['sale_id']);
                if ((int) $sale->customer_id !== (int) $customer->id) {
                    throw new \RuntimeException('A sale does not belong to this customer.');
                }

                if ($applySalePaid) {
                    $due = $sale->balanceDue();
                    $apply = min($due, $row['amount']);
                    $sale->paid_total = round((float) $sale->paid_total + $apply, 4);
                    $sale->save();
                }

                $payment->sales()->attach($sale->id, ['amount' => $row['amount']]);
            }

            $salesAccount = Account::query()
                ->where('type', 'income')
                ->where('name', 'Sales')
                ->first();

            if ($salesAccount) {
                $ledgerNotes = $data['notes'] ?? null;
                if ($discountAmount > 0) {
                    $discountNote = 'Includes '.number_format($discountAmount, 2).' discount/write-off';
                    $ledgerNotes = $ledgerNotes
                        ? $ledgerNotes.' ('.$discountNote.')'
                        : $discountNote;
                }

                LedgerTransaction::query()->create([
                    'branch_id' => $branchId,
                    'account_id' => $salesAccount->id,
                    'money_source_id' => $source->id,
                    'shift_id' => $shiftId,
                    'direction' => 'in',
                    'amount' => $amount,
                    'txn_date' => $paymentDate,
                    'reference_type' => 'customer_payment',
                    'reference_id' => $payment->id,
                    'notes' => $ledgerNotes,
                    'created_by' => Auth::id(),
                    'is_manual' => false,
                ]);
            }

            return $payment;
        });
    }

    public function reverseCustomerPayment(CustomerPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = CustomerPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->trashed()) {
                throw new \RuntimeException('This payment has already been deleted.');
            }

            $amount = (float) $payment->amount;
            $discountAmount = (float) ($payment->discount_amount ?? 0);
            $totalApplied = round($amount + $discountAmount, 4);

            $payment->loadMissing('sales');

            foreach ($payment->sales as $sale) {
                $alloc = (float) $sale->pivot->amount;
                $locked = Sale::query()->lockForUpdate()->find($sale->id);
                if (! $locked) {
                    continue;
                }
                $locked->paid_total = max(0, round((float) $locked->paid_total - $alloc, 4));
                $locked->save();
            }

            $payment->sales()->detach();

            $customer = Customer::query()->lockForUpdate()->findOrFail($payment->customer_id);
            $customer->balance = (float) $customer->balance + $totalApplied;
            $customer->save();

            if ($payment->money_source_id) {
                $source = MoneySource::query()->lockForUpdate()->find($payment->money_source_id);
                if ($source) {
                    try {
                        MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
                    } catch (\InvalidArgumentException $e) {
                        throw new \RuntimeException($e->getMessage());
                    }

                    $source->balance = (float) $source->balance - $amount;
                    $source->save();
                }
            }

            LedgerTransaction::query()
                ->where('reference_type', 'customer_payment')
                ->where('reference_id', $payment->id)
                ->get()
                ->each(function (LedgerTransaction $txn) {
                    $txn->delete();
                });

            $payment->delete();
        });
    }

    /**
     * @param  array{
     *   user_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   kind?:string|null,
     *   branch_id?:int|null,
     *   payroll_item_id?:int|null,
     *   payment_date?:string|null,
     *   notes?:string|null
     * }  $data
     */
    public function payEmployee(array $data): EmployeePayment
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \RuntimeException('Payment amount must be positive.');
            }

            $kind = strtolower(trim((string) ($data['kind'] ?? 'wage')));
            if (! in_array($kind, EmployeePayment::KINDS, true)) {
                throw new \RuntimeException('Invalid payment type.');
            }

            $payrollItemId = ! empty($data['payroll_item_id']) ? (int) $data['payroll_item_id'] : null;

            if ($kind === 'payroll') {
                if (! $payrollItemId) {
                    throw new \RuntimeException('Select a finalized payslip to pay.');
                }

                $item = PayrollItem::query()->lockForUpdate()->findOrFail($payrollItemId);

                if ((int) $item->user_id !== (int) $data['user_id']) {
                    throw new \RuntimeException('Payslip does not belong to the selected employee.');
                }

                if (! in_array($item->status, ['finalized', 'partial'], true)) {
                    throw new \RuntimeException('Only finalized or partially paid payslips can be paid.');
                }

                $remaining = $item->remainingAmount();
                if ($amount > $remaining + 0.0001) {
                    throw new \RuntimeException('Payment exceeds remaining payslip amount ('.number_format($remaining, 2).').');
                }
            } else {
                $payrollItemId = null;
            }

            $source = MoneySource::query()->lockForUpdate()->findOrFail($data['money_source_id']);

            if (! $source->isSelectableForPayment()) {
                throw new \RuntimeException('Invalid or inactive payment source. Owner Withdrawal cannot be used for payments.');
            }

            try {
                $amount = MoneyBalance::resolveDebitAmount($amount, (float) $source->balance, $source->name);
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException($e->getMessage());
            }

            $source->balance = (float) $source->balance - $amount;
            $source->save();

            $payment = EmployeePayment::query()->create([
                'user_id' => $data['user_id'],
                'payroll_item_id' => $payrollItemId,
                'money_source_id' => $source->id,
                'branch_id' => $data['branch_id'] ?? null,
                'kind' => $kind,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if ($payrollItemId) {
                $item = PayrollItem::query()->lockForUpdate()->findOrFail($payrollItemId);
                $item->paid_amount = (float) $item->paid_amount + $amount;
                if ($item->remainingAmount() <= 0.0001) {
                    $item->status = 'paid';
                } else {
                    $item->status = 'partial';
                }
                $item->save();
            }

            $salaryAccount = Account::query()
                ->where('type', 'expense')
                ->where('name', 'Salary')
                ->first();

            if ($salaryAccount) {
                $kindNote = EmployeePayment::kindLabel($kind);
                $notes = trim((string) ($data['notes'] ?? ''));
                $ledgerNotes = $notes !== '' ? "{$kindNote}: {$notes}" : $kindNote;

                LedgerTransaction::query()->create([
                    'branch_id' => $data['branch_id'] ?? null,
                    'account_id' => $salaryAccount->id,
                    'money_source_id' => $source->id,
                    'direction' => 'out',
                    'amount' => $amount,
                    'txn_date' => $payment->payment_date,
                    'reference_type' => 'employee_payment',
                    'reference_id' => $payment->id,
                    'notes' => $ledgerNotes,
                    'created_by' => Auth::id(),
                    'is_manual' => false,
                ]);
            }

            return $payment;
        });
    }

    public function reverseEmployeePayment(EmployeePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = EmployeePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->trashed()) {
                throw new \RuntimeException('This payment has already been deleted.');
            }

            $amount = (float) $payment->amount;

            if ($payment->money_source_id) {
                $source = MoneySource::query()->lockForUpdate()->find($payment->money_source_id);
                if ($source) {
                    $source->balance = (float) $source->balance + $amount;
                    $source->save();
                }
            }

            if ($payment->payroll_item_id) {
                $item = PayrollItem::query()->lockForUpdate()->find($payment->payroll_item_id);
                if ($item) {
                    $item->paid_amount = max(0, (float) $item->paid_amount - $amount);
                    if ((float) $item->paid_amount <= 0.0001) {
                        $item->paid_amount = 0;
                        $item->status = 'finalized';
                    } elseif ($item->remainingAmount() > 0.0001) {
                        $item->status = 'partial';
                    } else {
                        $item->status = 'paid';
                    }
                    $item->save();
                }
            }

            LedgerTransaction::query()
                ->where('reference_type', 'employee_payment')
                ->where('reference_id', $payment->id)
                ->get()
                ->each(function (LedgerTransaction $txn) {
                    $txn->delete();
                });

            $payment->delete();
        });
    }
}