<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public const STATUSES = ['present', 'absent', 'paid_leave', 'unpaid_leave', 'holiday'];

    public const LIVE_ACTIONS = [
        'check_in',
        'start_break',
        'end_break',
        'check_out',
        'absent',
    ];

    /**
     * @param  array{date?:string|null}  $filters
     * @return array{
     *   records: Collection,
     *   employees: Collection,
     *   board: array{date:string, employees:Collection},
     *   filters: array{date:string},
     *   branch: array<string, mixed>,
     *   statuses: list<string>
     * }
     */
    public function listForDate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $date = $filters['date'] ?? now()->toDateString();
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $employees = $this->employeeOptions($branch->id);
        $boardDate = now()->toDateString();

        $records = AttendanceRecord::query()
            ->with('user:id,name,username')
            ->where('branch_id', $branch->id)
            ->whereDate('attendance_date', $date)
            ->orderBy('id')
            ->get()
            ->map(fn (AttendanceRecord $r) => $this->serialize($r));

        $todayByUser = AttendanceRecord::query()
            ->where('branch_id', $branch->id)
            ->whereDate('attendance_date', $boardDate)
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $boardEmployees = $employees->map(function (array $employee) use ($todayByUser) {
            $record = $todayByUser->get($employee['id']);

            return [
                ...$employee,
                'record' => $record ? $this->serialize($record) : null,
                'phase' => $this->boardPhase($record),
            ];
        });

        return [
            'records' => $records,
            'employees' => $employees,
            'board' => [
                'date' => $boardDate,
                'employees' => $boardEmployees,
            ],
            'filters' => ['date' => $date],
            'branch' => $branch->only(['id', 'name']),
            'statuses' => self::STATUSES,
        ];
    }

    /**
     * @param  array{
     *   user_id:int,
     *   attendance_date:string,
     *   clock_in?:string|null,
     *   clock_out?:string|null,
     *   status?:string,
     *   notes?:string|null
     * }  $data
     */
    public function mark(array $data): AttendanceRecord
    {
        $branch = BranchContext::ensure();
        $this->assertEmployee((int) $data['user_id'], $branch->id);

        $date = Carbon::parse($data['attendance_date'])->toDateString();
        $clockIn = $this->normalizeDateTime($data['clock_in'] ?? null, $date);
        $clockOut = $this->normalizeDateTime($data['clock_out'] ?? null, $date);
        $status = $data['status'] ?? 'present';
        $worked = AttendanceRecord::calculateWorkedMinutes($clockIn, $clockOut, 0, $status);

        return AttendanceRecord::query()->updateOrCreate(
            [
                'user_id' => (int) $data['user_id'],
                'attendance_date' => $date,
            ],
            [
                'branch_id' => $branch->id,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_minutes' => 0,
                'break_started_at' => null,
                'worked_minutes' => $worked['worked'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ],
        );
    }

    /**
     * @param  array{
     *   user_id:int,
     *   attendance_date:string,
     *   clock_in?:string|null,
     *   clock_out?:string|null,
     *   status?:string,
     *   notes?:string|null
     * }  $data
     */
    public function update(AttendanceRecord $record, array $data): AttendanceRecord
    {
        $branch = BranchContext::ensure();
        $this->assertEmployee((int) $data['user_id'], $branch->id);

        $date = Carbon::parse($data['attendance_date'])->toDateString();
        $clockIn = $this->normalizeDateTime($data['clock_in'] ?? null, $date);
        $clockOut = $this->normalizeDateTime($data['clock_out'] ?? null, $date);
        $status = $data['status'] ?? 'present';
        $breakMinutes = (int) ($record->break_minutes ?? 0);
        $worked = AttendanceRecord::calculateWorkedMinutes($clockIn, $clockOut, $breakMinutes, $status);

        $conflict = AttendanceRecord::query()
            ->where('user_id', (int) $data['user_id'])
            ->whereDate('attendance_date', $date)
            ->where('id', '!=', $record->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'user_id' => 'This employee already has attendance on that date.',
            ]);
        }

        $record->update([
            'branch_id' => $branch->id,
            'user_id' => (int) $data['user_id'],
            'attendance_date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'break_started_at' => null,
            'worked_minutes' => $worked['worked'],
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        return $record->refresh();
    }

    public function delete(AttendanceRecord $record): void
    {
        $record->delete();
    }

    /**
     * @param  'check_in'|'start_break'|'end_break'|'check_out'|'absent'  $action
     */
    public function liveAction(int $userId, string $action): AttendanceRecord
    {
        if (! in_array($action, self::LIVE_ACTIONS, true)) {
            throw ValidationException::withMessages([
                'action' => 'Invalid attendance action.',
            ]);
        }

        $branch = BranchContext::ensure();
        $this->assertEmployee($userId, $branch->id);
        $date = now()->toDateString();
        $now = now();

        return DB::transaction(function () use ($userId, $action, $branch, $date, $now) {
            $record = AttendanceRecord::query()
                ->where('user_id', $userId)
                ->whereDate('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $record = new AttendanceRecord([
                    'user_id' => $userId,
                    'attendance_date' => $date,
                    'branch_id' => $branch->id,
                ]);
            }

            match ($action) {
                'check_in' => $this->checkIn($record, $branch->id, $userId, $date, $now),
                'start_break' => $this->startBreak($record, $now),
                'end_break' => $this->endBreak($record, $now),
                'check_out' => $this->checkOut($record, $now),
                'absent' => $this->markAbsent($record, $branch->id, $userId, $date),
            };

            return $record->refresh();
        });
    }

    protected function checkIn(
        AttendanceRecord $record,
        int $branchId,
        int $userId,
        string $date,
        Carbon $now,
    ): void {
        if ($record->exists && ($record->clock_in || $record->status !== 'present')) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance has already been recorded for this employee today.',
            ]);
        }

        $record->fill([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'attendance_date' => $date,
            'clock_in' => $now,
            'clock_out' => null,
            'break_minutes' => 0,
            'break_started_at' => null,
            'worked_minutes' => 0,
            'status' => 'present',
            'created_by' => Auth::id(),
        ])->save();
    }

    protected function startBreak(AttendanceRecord $record, Carbon $now): void
    {
        $this->assertOpenShift($record);
        if ($record->break_started_at) {
            throw ValidationException::withMessages([
                'attendance' => 'This employee is already on break.',
            ]);
        }

        $record->update(['break_started_at' => $now]);
    }

    protected function endBreak(AttendanceRecord $record, Carbon $now): void
    {
        $this->assertOpenShift($record);
        if (! $record->break_started_at) {
            throw ValidationException::withMessages([
                'attendance' => 'This employee is not currently on break.',
            ]);
        }

        $record->update([
            'break_minutes' => (int) $record->break_minutes + $this->elapsedBreakMinutes($record, $now),
            'break_started_at' => null,
        ]);
    }

    protected function checkOut(AttendanceRecord $record, Carbon $now): void
    {
        $this->assertOpenShift($record);
        $breakMinutes = (int) $record->break_minutes;
        if ($record->break_started_at) {
            $breakMinutes += $this->elapsedBreakMinutes($record, $now);
        }

        $worked = AttendanceRecord::calculateWorkedMinutes(
            $record->clock_in?->toDateTimeString(),
            $now->toDateTimeString(),
            $breakMinutes,
            'present',
        );

        $record->update([
            'clock_out' => $now,
            'break_minutes' => $breakMinutes,
            'break_started_at' => null,
            'worked_minutes' => $worked['worked'],
        ]);
    }

    protected function markAbsent(
        AttendanceRecord $record,
        int $branchId,
        int $userId,
        string $date,
    ): void {
        if ($record->exists && ($record->clock_in || $record->status !== 'present')) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance has already been recorded for this employee today.',
            ]);
        }

        $record->fill([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'attendance_date' => $date,
            'clock_in' => null,
            'clock_out' => null,
            'break_minutes' => 0,
            'break_started_at' => null,
            'worked_minutes' => 0,
            'status' => 'absent',
            'created_by' => Auth::id(),
        ])->save();
    }

    protected function assertOpenShift(AttendanceRecord $record): void
    {
        if (! $record->exists || ! $record->isOpenShift()) {
            throw ValidationException::withMessages([
                'attendance' => 'The employee must be checked in and not checked out.',
            ]);
        }
    }

    protected function elapsedBreakMinutes(AttendanceRecord $record, Carbon $now): int
    {
        if (! $record->break_started_at) {
            return 0;
        }

        return max(0, (int) floor($record->break_started_at->diffInSeconds($now) / 60));
    }

    protected function boardPhase(?AttendanceRecord $record): string
    {
        if (! $record) {
            return 'not_marked';
        }
        if (in_array($record->status, ['paid_leave', 'unpaid_leave'], true)) {
            return 'leave';
        }
        if ($record->status === 'absent') {
            return 'absent';
        }
        if ($record->clock_out) {
            return 'finished';
        }
        if ($record->break_started_at) {
            return 'on_break';
        }
        if ($record->clock_in) {
            return 'working';
        }

        return 'recorded';
    }

    protected function normalizeDateTime(mixed $value, string $date): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
            return Carbon::parse("{$date} {$raw}")->toDateTimeString();
        }

        return Carbon::parse($raw)->toDateTimeString();
    }

    protected function assertEmployee(int $userId, int $branchId): void
    {
        $exists = EmployeeProfile::query()
            ->active()
            ->where('user_id', $userId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user_id' => 'Select an active employee.',
            ]);
        }
    }

    /**
     * @return Collection<int, array{id:int, name:string, employee_number:?string, designation:?string}>
     */
    protected function employeeOptions(int $branchId): Collection
    {
        return EmployeeProfile::query()
            ->active()
            ->with('user:id,name,username')
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeProfile $p) => $p->user !== null)
            ->map(fn (EmployeeProfile $p) => [
                'id' => $p->user_id,
                'name' => $p->user->name ?: $p->user->username,
                'employee_number' => $p->employee_number,
                'designation' => $p->designation,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'user_id' => $record->user_id,
            'attendance_date' => $record->attendance_date?->format('Y-m-d'),
            'clock_in' => $record->clock_in?->format('H:i'),
            'clock_out' => $record->clock_out?->format('H:i'),
            'break_minutes' => (int) ($record->break_minutes ?? 0),
            'break_started_at' => $record->break_started_at?->format('H:i'),
            'on_break' => $record->isOnBreak(),
            'worked_minutes' => (int) $record->worked_minutes,
            'status' => $record->status,
            'notes' => $record->notes,
            'user' => $record->user
                ? [
                    'id' => $record->user->id,
                    'name' => $record->user->name ?: $record->user->username,
                ]
                : null,
        ];
    }
}
