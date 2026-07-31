<?php

namespace App\Support;

use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\MoneySourceTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MoneySourceFundLedger
{
    /**
     * @return array{
     *     filters: array{branch_id:?int, from_money_source_id:?int, to_money_source_id:?int, movement_kind:string, from:?string, to:?string},
     *     rows: Collection<int, array<string, mixed>>,
     *     summary: array{internal_total: float, owner_withdrawal_total: float, total: int}
     * }
     */
    public static function build(Request $request): array
    {
        $filters = self::filtersFromRequest($request);
        $rows = self::queryRows($filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'summary' => [
                'internal_total' => round((float) $rows->where('movement_kind', 'internal_transfer')->sum('amount'), 2),
                'owner_withdrawal_total' => round((float) $rows->where('movement_kind', 'owner_withdrawal')->sum('amount'), 2),
                'total' => $rows->count(),
            ],
        ];
    }

    /**
     * @return array{branch_id: ?int, from_money_source_id: ?int, to_money_source_id: ?int, movement_kind: string, from: ?string, to: ?string}
     */
    public static function filtersFromRequest(Request $request): array
    {
        return [
            'branch_id' => $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            'from_money_source_id' => $request->filled('from_money_source_id') ? (int) $request->input('from_money_source_id') : null,
            'to_money_source_id' => $request->filled('to_money_source_id') ? (int) $request->input('to_money_source_id') : null,
            'movement_kind' => in_array($request->input('movement_kind'), ['all', 'internal_transfer', 'owner_withdrawal'], true)
                ? (string) $request->input('movement_kind')
                : 'all',
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function queryRows(array $filters): Collection
    {
        $rows = collect();

        if ($filters['movement_kind'] === 'all' || $filters['movement_kind'] === 'internal_transfer') {
            $rows = $rows->concat(self::internalTransferRows($filters));
        }

        if ($filters['movement_kind'] === 'all' || $filters['movement_kind'] === 'owner_withdrawal') {
            $rows = $rows->concat(self::ownerWithdrawalRows($filters));
        }

        return $rows->sortByDesc(fn (array $row) => $row['sort_key'])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function internalTransferRows(array $filters): Collection
    {
        $query = MoneySourceTransfer::query()
            ->with(['fromMoneySource', 'toMoneySource', 'branch', 'creator']);

        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['from_money_source_id']) {
            $query->where('from_money_source_id', $filters['from_money_source_id']);
        }
        if ($filters['to_money_source_id']) {
            $query->where('to_money_source_id', $filters['to_money_source_id']);
        }
        if ($filters['from']) {
            $query->whereDate('transfer_date', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('transfer_date', '<=', $filters['to']);
        }

        return $query->get()->map(function (MoneySourceTransfer $transfer) {
            $date = $transfer->transfer_date?->format('Y-m-d') ?? '';

            return [
                'id' => 'transfer-'.$transfer->id,
                'movement_kind' => 'internal_transfer',
                'movement_label' => 'Internal transfer',
                'date' => $date,
                'from_name' => $transfer->fromMoneySource?->name ?? '—',
                'to_name' => $transfer->toMoneySource?->name ?? '—',
                'amount' => (float) $transfer->amount,
                'branch_name' => $transfer->branch?->name ?? '—',
                'notes' => $transfer->notes,
                'created_by' => $transfer->creator?->name ?? $transfer->creator?->username ?? '—',
                'sort_key' => $date.'-t-'.$transfer->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function ownerWithdrawalRows(array $filters): Collection
    {
        $query = MoneySourceFundMovement::query()
            ->with(['fromMoneySource', 'toMoneySource', 'branch', 'creator'])
            ->where('movement_type', MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL);

        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['from_money_source_id']) {
            $query->where('from_money_source_id', $filters['from_money_source_id']);
        }
        if ($filters['to_money_source_id']) {
            $query->where('to_money_source_id', $filters['to_money_source_id']);
        }
        if ($filters['from']) {
            $query->whereDate('movement_date', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('movement_date', '<=', $filters['to']);
        }

        return $query->get()->map(function (MoneySourceFundMovement $movement) {
            $date = $movement->movement_date?->format('Y-m-d') ?? '';

            return [
                'id' => 'ow-'.$movement->id,
                'movement_kind' => 'owner_withdrawal',
                'movement_label' => 'Owner withdrawal',
                'date' => $date,
                'from_name' => $movement->fromMoneySource?->name ?? '—',
                'to_name' => $movement->toMoneySource?->name ?? '—',
                'amount' => (float) $movement->amount,
                'branch_name' => $movement->branch?->name ?? '—',
                'notes' => $movement->notes,
                'created_by' => $movement->creator?->name ?? $movement->creator?->username ?? '—',
                'sort_key' => $date.'-o-'.$movement->id,
            ];
        });
    }

    /**
     * @return Collection<int, array{id:int,name:string}>
     */
    public static function sourceOptions(): Collection
    {
        return MoneySource::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
