<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\EmployeePayment;
use App\Models\LedgerTransaction;
use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\MoneySourceTransfer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SupplierPayment;
use App\Support\BranchContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MoneySourceTxnReportService
{
    /**
     * @param  array{
     *   from?:string|null,
     *   to?:string|null,
     *   money_source_id?:int|string|null,
     *   direction?:string|null,
     *   include_transfers?:string|bool|null,
     *   page?:int|string|null,
     *   per_page?:int|string|null
     * }  $input
     * @return array{
     *   filters: array<string, mixed>,
     *   summary: array{transactions:int, total_in:float, total_out:float, net:float},
     *   by_source: list<array{money_source:string, in:float, out:float, net:float}>,
     *   rows: LengthAwarePaginator,
     *   money_sources: list<array{id:int, name:string}>
     * }
     */
    public function build(array $input): array
    {
        $branch = BranchContext::ensure();
        $from = (string) ($input['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($input['to'] ?? now()->toDateString());
        $moneySourceId = isset($input['money_source_id']) && $input['money_source_id'] !== ''
            ? (int) $input['money_source_id']
            : null;
        $direction = strtolower(trim((string) ($input['direction'] ?? 'all')));
        if (! in_array($direction, ['all', 'in', 'out'], true)) {
            $direction = 'all';
        }
        $includeTransfers = ! in_array(
            strtolower((string) ($input['include_transfers'] ?? 'include')),
            ['0', 'false', 'exclude', 'no'],
            true,
        );
        $perPage = resolve_page_limit($input['per_page'] ?? null, 25);
        $page = max(1, (int) ($input['page'] ?? 1));

        $all = $this->collectRows(
            branchId: (int) $branch->id,
            from: $from,
            to: $to,
            moneySourceId: $moneySourceId,
            includeTransfers: $includeTransfers,
        );

        if ($direction === 'in') {
            $all = $all->where('direction', 'in')->values();
        } elseif ($direction === 'out') {
            $all = $all->where('direction', 'out')->values();
        }

        $all = $all->sortByDesc(fn (array $row) => $row['sort_key'])->values();

        $totalIn = round((float) $all->where('direction', 'in')->sum('amount'), 2);
        $totalOut = round((float) $all->where('direction', 'out')->sum('amount'), 2);

        $bySource = $all
            ->groupBy('money_source')
            ->map(function (Collection $group, string $name) {
                $in = round((float) $group->where('direction', 'in')->sum('amount'), 2);
                $out = round((float) $group->where('direction', 'out')->sum('amount'), 2);

                return [
                    'money_source' => $name,
                    'in' => $in,
                    'out' => $out,
                    'net' => round($in - $out, 2),
                ];
            })
            ->sortByDesc('net')
            ->values()
            ->all();

        $paginator = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values()->all(),
            $all->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );

        return [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'money_source_id' => $moneySourceId,
                'direction' => $direction,
                'include_transfers' => $includeTransfers ? 'include' : 'exclude',
                'per_page' => $perPage,
            ],
            'summary' => [
                'transactions' => $all->count(),
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net' => round($totalIn - $totalOut, 2),
            ],
            'by_source' => $bySource,
            'rows' => $paginator,
            'money_sources' => MoneySource::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (MoneySource $s) => ['id' => $s->id, 'name' => $s->name])
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectRows(int $branchId, string $from, string $to, ?int $moneySourceId, bool $includeTransfers): Collection
    {
        $rows = collect();

        $rows = $rows->concat($this->salePaymentRows($branchId, $from, $to, $moneySourceId));
        $rows = $rows->concat($this->customerPaymentRows($branchId, $from, $to, $moneySourceId));
        $rows = $rows->concat($this->supplierPaymentRows($branchId, $from, $to, $moneySourceId));
        $rows = $rows->concat($this->employeePaymentRows($branchId, $from, $to, $moneySourceId));
        $rows = $rows->concat($this->ledgerRows($branchId, $from, $to, $moneySourceId));

        if ($includeTransfers) {
            $rows = $rows->concat($this->transferRows($branchId, $from, $to, $moneySourceId));
            $rows = $rows->concat($this->ownerWithdrawalRows($branchId, $from, $to, $moneySourceId));
        }

        return $rows->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function salePaymentRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        return SalePayment::query()
            ->with(['moneySource:id,name', 'sale:id,number,branch_id,status,created_at'])
            ->whereHas('sale', function ($q) use ($branchId, $from, $to) {
                $q->where('branch_id', $branchId)
                    ->where('status', Sale::STATUS_COMPLETED)
                    ->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            })
            ->when($moneySourceId, fn ($q) => $q->where('money_source_id', $moneySourceId))
            ->get()
            ->map(function (SalePayment $p) {
                $date = $p->sale?->created_at;

                return $this->row(
                    date: $date,
                    moneySource: $p->moneySource?->name ?: 'Unknown',
                    direction: 'in',
                    amount: (float) $p->amount,
                    reference: 'Sale '.($p->sale?->number ?: '#'.$p->sale_id),
                    type: 'Sale payment',
                    branch: null,
                    id: $p->id,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function customerPaymentRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        return CustomerPayment::query()
            ->with(['moneySource:id,name', 'customer:id,name', 'branch:id,name'])
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->when($moneySourceId, fn ($q) => $q->where('money_source_id', $moneySourceId))
            ->get()
            ->map(function (CustomerPayment $p) {
                return $this->row(
                    date: $p->payment_date,
                    moneySource: $p->moneySource?->name ?: 'Unknown',
                    direction: 'in',
                    amount: (float) $p->amount,
                    reference: 'Customer '.($p->customer?->name ?: 'payment'),
                    type: 'Customer payment',
                    branch: $p->branch?->name,
                    id: $p->id,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function supplierPaymentRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        return SupplierPayment::query()
            ->with(['moneySource:id,name', 'supplier:id,name', 'branch:id,name'])
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->when($moneySourceId, fn ($q) => $q->where('money_source_id', $moneySourceId))
            ->get()
            ->map(function (SupplierPayment $p) {
                return $this->row(
                    date: $p->payment_date,
                    moneySource: $p->moneySource?->name ?: 'Unknown',
                    direction: 'out',
                    amount: (float) $p->amount,
                    reference: 'Supplier '.($p->supplier?->name ?: 'payment'),
                    type: 'Supplier payment',
                    branch: $p->branch?->name,
                    id: $p->id,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function employeePaymentRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        return EmployeePayment::query()
            ->with(['moneySource:id,name', 'user:id,name,username', 'branch:id,name'])
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->when($moneySourceId, fn ($q) => $q->where('money_source_id', $moneySourceId))
            ->get()
            ->map(function (EmployeePayment $p) {
                $name = $p->user?->name ?: $p->user?->username ?: 'Employee';

                return $this->row(
                    date: $p->payment_date,
                    moneySource: $p->moneySource?->name ?: 'Unknown',
                    direction: 'out',
                    amount: (float) $p->amount,
                    reference: 'Employee '.$name,
                    type: 'Employee payment',
                    branch: $p->branch?->name,
                    id: $p->id,
                );
            });
    }

    /**
     * Ledger rows that are not already covered by payment domain tables.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function ledgerRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        $skip = ['supplier_payment', 'customer_payment', 'employee_payment', 'transfer'];

        return LedgerTransaction::query()
            ->with(['moneySource:id,name', 'account:id,name', 'branch:id,name'])
            ->whereNotNull('money_source_id')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('txn_date', '>=', $from)
            ->whereDate('txn_date', '<=', $to)
            ->when($moneySourceId, fn ($q) => $q->where('money_source_id', $moneySourceId))
            ->where(function ($q) use ($skip) {
                $q->whereNull('reference_type')
                    ->orWhereNotIn('reference_type', $skip);
            })
            ->get()
            ->map(function (LedgerTransaction $t) {
                $direction = strtolower((string) $t->direction) === 'in' ? 'in' : 'out';
                $ref = $t->account?->name
                    ?: ($t->reference_type ? ucfirst(str_replace('_', ' ', $t->reference_type)) : 'Ledger');

                return $this->row(
                    date: $t->txn_date,
                    moneySource: $t->moneySource?->name ?: 'Unknown',
                    direction: $direction,
                    amount: (float) $t->amount,
                    reference: $ref.($t->notes ? ' · '.$t->notes : ''),
                    type: $t->reference_type === 'expense' ? 'Expense' : 'Ledger',
                    branch: $t->branch?->name,
                    id: $t->id,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function transferRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        $query = MoneySourceTransfer::query()
            ->with(['fromMoneySource:id,name', 'toMoneySource:id,name', 'branch:id,name'])
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('transfer_date', '>=', $from)
            ->whereDate('transfer_date', '<=', $to);

        if ($moneySourceId) {
            $query->where(function ($q) use ($moneySourceId) {
                $q->where('from_money_source_id', $moneySourceId)
                    ->orWhere('to_money_source_id', $moneySourceId);
            });
        }

        $rows = collect();

        foreach ($query->get() as $transfer) {
            $date = $transfer->transfer_date;
            $amount = (float) $transfer->amount;
            $ref = 'Transfer';
            $branch = $transfer->branch?->name;

            if (! $moneySourceId || (int) $transfer->from_money_source_id === $moneySourceId) {
                $rows->push($this->row(
                    date: $date,
                    moneySource: $transfer->fromMoneySource?->name ?: 'Unknown',
                    direction: 'out',
                    amount: $amount,
                    reference: $ref.' → '.($transfer->toMoneySource?->name ?: ''),
                    type: 'Transfer',
                    branch: $branch,
                    id: $transfer->id * 10,
                ));
            }

            if (! $moneySourceId || (int) $transfer->to_money_source_id === $moneySourceId) {
                $rows->push($this->row(
                    date: $date,
                    moneySource: $transfer->toMoneySource?->name ?: 'Unknown',
                    direction: 'in',
                    amount: $amount,
                    reference: $ref.' ← '.($transfer->fromMoneySource?->name ?: ''),
                    type: 'Transfer',
                    branch: $branch,
                    id: $transfer->id * 10 + 1,
                ));
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function ownerWithdrawalRows(int $branchId, string $from, string $to, ?int $moneySourceId): Collection
    {
        return MoneySourceFundMovement::query()
            ->with(['fromMoneySource:id,name', 'toMoneySource:id,name', 'branch:id,name'])
            ->where('movement_type', MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('movement_date', '>=', $from)
            ->whereDate('movement_date', '<=', $to)
            ->when($moneySourceId, fn ($q) => $q->where('from_money_source_id', $moneySourceId))
            ->get()
            ->map(function (MoneySourceFundMovement $m) {
                return $this->row(
                    date: $m->movement_date,
                    moneySource: $m->fromMoneySource?->name ?: 'Unknown',
                    direction: 'out',
                    amount: (float) $m->amount,
                    reference: 'Owner withdrawal'.($m->notes ? ' · '.$m->notes : ''),
                    type: 'Owner withdrawal',
                    branch: $m->branch?->name,
                    id: $m->id,
                );
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(
        mixed $date,
        string $moneySource,
        string $direction,
        float $amount,
        string $reference,
        string $type,
        ?string $branch,
        int|string $id = 0,
    ): array {
        $carbon = null;
        if ($date instanceof \Carbon\CarbonInterface) {
            $carbon = $date->copy();
        } elseif ($date) {
            try {
                $carbon = \Illuminate\Support\Carbon::parse($date);
            } catch (\Throwable) {
                $carbon = null;
            }
        }

        // Date-only values (payments, transfers) use end-of-day so they
        // still sort toward the top of that day when mixed with datetimes.
        if ($carbon && $carbon->format('H:i:s') === '00:00:00') {
            $carbon = $carbon->endOfDay();
        }

        $timestamp = $carbon ? $carbon->getTimestamp() : 0;

        return [
            'date' => $date ? format_company_datetime($date) : '',
            'date_raw' => $date ? format_company_date($date) : '',
            'money_source' => $moneySource,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'reference' => $reference,
            'type' => $type,
            'branch' => $branch,
            'sort_key' => sprintf('%015d-%020d', $timestamp, (int) $id),
        ];
    }
}
