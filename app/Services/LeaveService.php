<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public const TYPES = ['annual', 'casual', 'sick', 'unpaid', 'other'];

    /**
     * @param  array{
     *   q?:string|null,
     *   status?:string|null,
     *   leave_type?:string|null,
     *   user_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   leaves: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   employees: Collection,
     *   branch: array<string, mixed>
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (! in_array($status, LeaveRequest::STATUSES, true)) {
            $status = '';
        }
        $leaveType = strtolower(trim((string) ($filters['leave_type'] ?? '')));
        if (! in_array($leaveType, self::TYPES, true)) {
            $leaveType = '';
        }
        $userId = $filters['user_id'] !== null && $filters['user_id'] !== ''
            ? (int) $filters['user_id']
            : null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $leaves = LeaveRequest::query()
            ->with(['user:id,name,username', 'reviewer:id,name,username'])
            ->where(function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)
                    ->orWhereNull('branch_id');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('reason', 'like', "%{$q}%")
                        ->orWhere('review_notes', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('name', 'like', "%{$q}%")
                                ->orWhere('username', 'like', "%{$q}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($leaveType !== '', fn ($query) => $query->where('leave_type', $leaveType))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($from, fn ($query) => $query->whereDate('start_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('end_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeaveRequest $leave) => $this->serialize($leave));

        return [
            'leaves' => $leaves,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'leave_type' => $leaveType,
                'user_id' => $userId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'employees' => $this->employeeOptions($branch->id),
            'branch' => $branch->only(['id', 'name']),
            'leave_types' => self::TYPES,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'start_date', 'end_date', 'status', 'leave_type', 'days'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sort === 'id' ? 'desc' : 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{
     *   user_id:int,
     *   leave_type?:string|null,
     *   start_date:string,
     *   end_date:string,
     *   reason?:string|null
     * }  $data
     */
    public function create(array $data): LeaveRequest
    {
        $branch = BranchContext::ensure();
        $this->assertEmployee((int) $data['user_id'], $branch->id);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after start date.',
            ]);
        }

        return LeaveRequest::query()->create([
            'branch_id' => $branch->id,
            'user_id' => (int) $data['user_id'],
            'leave_type' => $data['leave_type'] ?? 'annual',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $start->diffInDays($end) + 1,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function review(LeaveRequest $leave, string $status, ?string $notes = null): LeaveRequest
    {
        $this->assertPending($leave);

        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid review status.',
            ]);
        }

        $leave->update([
            'status' => $status,
            'review_notes' => $notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $leave->refresh();
    }

    public function cancel(LeaveRequest $leave): void
    {
        $this->assertPending($leave);

        $leave->update([
            'status' => 'cancelled',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    protected function assertPending(LeaveRequest $leave): void
    {
        if (! $leave->isPending()) {
            throw ValidationException::withMessages([
                'leave' => 'Only pending leave requests can be changed.',
            ]);
        }
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
     * @return Collection<int, array{id:int, name:string, employee_number:?string}>
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
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'user_id' => $leave->user_id,
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
            'days' => (int) $leave->days,
            'status' => $leave->status,
            'reason' => $leave->reason,
            'review_notes' => $leave->review_notes,
            'can_review' => $leave->isPending(),
            'can_cancel' => $leave->isPending(),
            'user' => $leave->user
                ? [
                    'id' => $leave->user->id,
                    'name' => $leave->user->name ?: $leave->user->username,
                ]
                : null,
            'reviewer' => $leave->reviewer
                ? [
                    'id' => $leave->reviewer->id,
                    'name' => $leave->reviewer->name ?: $leave->reviewer->username,
                ]
                : null,
        ];
    }
}
