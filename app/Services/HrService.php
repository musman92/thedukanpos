<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeePayment;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HrService
{
    public function __construct(protected FinanceService $finance) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertEmployeeProfile(array $data): EmployeeProfile
    {
        $userId = (int) $data['user_id'];

        return EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'branch_id' => $data['branch_id'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'designation' => $data['designation'] ?? null,
                'department' => $data['department'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'employment_status' => $data['employment_status'] ?? 'active',
                'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
                'pay_rate' => $data['pay_rate'] ?? 0,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
        );
    }

    /**
     * @param  array{
     *   user_id:int,
     *   branch_id:int,
     *   attendance_date?:string,
     *   clock_in?:string|null,
     *   clock_out?:string|null,
     *   status?:string,
     *   notes?:string|null
     * }  $data
     */
    public function markAttendance(array $data): AttendanceRecord
    {
        $date = $data['attendance_date'] ?? now()->toDateString();
        $clockIn = $data['clock_in'] ?? null;
        $clockOut = $data['clock_out'] ?? null;
        $worked = 0;

        if ($clockIn && $clockOut) {
            $start = Carbon::parse($clockIn);
            $end = Carbon::parse($clockOut);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $worked = max(0, (int) $start->diffInMinutes($end));
        }

        return AttendanceRecord::query()->updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'attendance_date' => $date,
            ],
            [
                'branch_id' => $data['branch_id'],
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'worked_minutes' => $worked,
                'status' => $data['status'] ?? 'present',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ],
        );
    }

    /**
     * @param  array{
     *   user_id:int,
     *   branch_id?:int|null,
     *   leave_type?:string,
     *   start_date:string,
     *   end_date:string,
     *   reason?:string|null
     * }  $data
     */
    public function requestLeave(array $data): LeaveRequest
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        if ($end->lt($start)) {
            throw new \RuntimeException('End date must be on or after start date.');
        }

        $days = $start->diffInDays($end) + 1;

        return LeaveRequest::query()->create([
            'branch_id' => $data['branch_id'] ?? null,
            'user_id' => $data['user_id'],
            'leave_type' => $data['leave_type'] ?? 'annual',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function reviewLeave(LeaveRequest $leave, string $status, ?string $notes = null): LeaveRequest
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw new \RuntimeException('Invalid leave status.');
        }

        if ($leave->status !== 'pending') {
            throw new \RuntimeException('Leave request already reviewed.');
        }

        $leave->update([
            'status' => $status,
            'review_notes' => $notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $leave->refresh();
    }

    public function generatePayrollRun(string $periodStart, string $periodEnd, ?int $branchId = null): PayrollRun
    {
        return DB::transaction(function () use ($periodStart, $periodEnd, $branchId) {
            $profiles = EmployeeProfile::query()
                ->active()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->get();

            $run = PayrollRun::query()->create([
                'number' => PayrollRun::generateNumber(),
                'branch_id' => $branchId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'generated_by' => Auth::id(),
            ]);

            $grossTotal = 0;
            $deductionTotal = 0;
            $netTotal = 0;

            foreach ($profiles as $profile) {
                $payRate = (float) $profile->pay_rate;
                $adjustments = PayrollAdjustment::query()
                    ->where('user_id', $profile->user_id)
                    ->where('status', 'pending')
                    ->whereDate('effective_date', '>=', $periodStart)
                    ->whereDate('effective_date', '<=', $periodEnd)
                    ->get();

                $bonus = 0.0;
                $deduction = 0.0;
                foreach ($adjustments as $adj) {
                    if ($adj->type === 'bonus') {
                        $bonus += (float) $adj->amount;
                    } else {
                        $deduction += (float) $adj->amount;
                    }
                }

                $gross = $payRate + $bonus;
                $net = max(0, $gross - $deduction);

                PayrollItem::query()->create([
                    'payroll_run_id' => $run->id,
                    'user_id' => $profile->user_id,
                    'pay_rate' => $payRate,
                    'bonus_amount' => $bonus,
                    'deduction_amount' => $deduction,
                    'gross_pay' => $gross,
                    'net_pay' => $net,
                    'paid_amount' => 0,
                    'status' => 'draft',
                ]);

                foreach ($adjustments as $adj) {
                    $adj->update(['status' => 'applied']);
                }

                $grossTotal += $gross;
                $deductionTotal += $deduction;
                $netTotal += $net;
            }

            $run->update([
                'employee_count' => $profiles->count(),
                'gross_total' => $grossTotal,
                'deduction_total' => $deductionTotal,
                'net_total' => $netTotal,
            ]);

            return $run->refresh()->load('items.user');
        });
    }

    public function finalizePayrollRun(PayrollRun $run): PayrollRun
    {
        if (! $run->isDraft()) {
            throw new \RuntimeException('Only draft payroll runs can be finalized.');
        }

        $run->update([
            'status' => 'finalized',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        $run->items()->where('status', 'draft')->update(['status' => 'finalized']);

        return $run->refresh();
    }

    /**
     * @param  array{
     *   user_id:int,
     *   money_source_id:int,
     *   amount:float|int|string,
     *   branch_id?:int|null,
     *   payroll_item_id?:int|null,
     *   payment_date?:string|null,
     *   notes?:string|null
     * }  $data
     */
    public function recordEmployeePayment(array $data): EmployeePayment
    {
        return $this->finance->payEmployee($data);
    }
}
