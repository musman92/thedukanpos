<?php

namespace App\Services;

use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Shift;
use App\Support\BranchContext;
use App\Support\MoneyBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerWithdrawalService
{
    /**
     * Record owner withdrawal: operational source → Owner Withdrawal bucket.
     */
    public function record(
        int $fromMoneySourceId,
        float $amount,
        int $branchId,
        string $date,
        ?string $notes = null,
    ): MoneySourceFundMovement {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $fromSource = MoneySource::query()->findOrFail($fromMoneySourceId);

        if (! $fromSource->isSelectableForPayment()) {
            throw ValidationException::withMessages([
                'from_money_source_id' => 'Invalid source money source.',
            ]);
        }

        $ownerBucket = MoneySource::ownerWithdrawal();
        if (! $ownerBucket) {
            throw ValidationException::withMessages([
                'from_money_source_id' => 'Owner Withdrawal source is not configured.',
            ]);
        }

        try {
            $amount = MoneyBalance::resolveDebitAmount(
                $amount,
                (float) $fromSource->balance,
                $fromSource->name,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        $shiftId = Shift::query()
            ->where('branch_id', $branchId)
            ->whereNull('closed_at')
            ->value('id');

        return DB::transaction(function () use ($fromSource, $ownerBucket, $amount, $branchId, $date, $notes, $shiftId) {
            $from = MoneySource::query()->lockForUpdate()->findOrFail($fromSource->id);
            $to = MoneySource::query()->lockForUpdate()->findOrFail($ownerBucket->id);

            try {
                $debit = MoneyBalance::resolveDebitAmount($amount, (float) $from->balance, $from->name);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'amount' => $e->getMessage(),
                ]);
            }

            $from->balance = (float) $from->balance - $debit;
            $from->save();

            $to->balance = (float) $to->balance + $debit;
            $to->save();

            return MoneySourceFundMovement::query()->create([
                'branch_id' => $branchId,
                'from_money_source_id' => $from->id,
                'to_money_source_id' => $to->id,
                'movement_type' => MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL,
                'amount' => round($debit, 4),
                'movement_date' => $date,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'shift_id' => $shiftId,
            ]);
        });
    }

    public function recordForCurrentBranch(
        int $fromMoneySourceId,
        float $amount,
        string $date,
        ?string $notes = null,
    ): MoneySourceFundMovement {
        $branch = BranchContext::ensure();

        return $this->record($fromMoneySourceId, $amount, $branch->id, $date, $notes);
    }
}
