<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\PayrollAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PayrollAdjustmentService
{
    /**
     * @param  array{
     *   q?:string|null,
     *   type?:string|null,
     *   status?:string|null,
     *   user_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   adjustments: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   employees: Collection
     * }
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $type = strtolower(trim((string) ($filters['type'] ?? '')));
        if (! in_array($type, PayrollAdjustment::TYPES, true)) {
            $type = '';
        }
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (! in_array($status, PayrollAdjustment::STATUSES, true)) {
            $status = '';
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

        $adjustments = PayrollAdjustment::query()
            ->with(['user:id,name,username', 'creator:id,name,username'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('name', 'like', "%{$q}%")
                                ->orWhere('username', 'like', "%{$q}%");
                        });
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($from, fn ($query) => $query->whereDate('effective_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('effective_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PayrollAdjustment $adj) => $this->serialize($adj));

        return [
            'adjustments' => $adjustments,
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
                'user_id' => $userId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'employees' => EmployeeProfile::query()
                ->active()
                ->with('user:id,name,username')
                ->orderBy('id')
                ->get()
                ->filter(fn (EmployeeProfile $p) => $p->user !== null)
                ->map(fn (EmployeeProfile $p) => [
                    'id' => $p->user_id,
                    'name' => $p->user->name ?: $p->user->username,
                    'employee_number' => $p->employee_number,
                ])
                ->values(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'effective_date', 'amount', 'type', 'status'];
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
     *   type:string,
     *   amount:float|int|string,
     *   effective_date:string,
     *   notes?:string|null
     * }  $data
     */
    public function create(array $data): PayrollAdjustment
    {
        $this->assertEmployee((int) $data['user_id']);

        return PayrollAdjustment::query()->create([
            'user_id' => (int) $data['user_id'],
            'type' => (string) $data['type'],
            'amount' => (float) $data['amount'],
            'effective_date' => $data['effective_date'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @param  array{
     *   user_id:int,
     *   type:string,
     *   amount:float|int|string,
     *   effective_date:string,
     *   notes?:string|null
     * }  $data
     */
    public function update(PayrollAdjustment $adjustment, array $data): PayrollAdjustment
    {
        $this->assertPending($adjustment);
        $this->assertEmployee((int) $data['user_id']);

        $adjustment->update([
            'user_id' => (int) $data['user_id'],
            'type' => (string) $data['type'],
            'amount' => (float) $data['amount'],
            'effective_date' => $data['effective_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $adjustment->refresh();
    }

    public function delete(PayrollAdjustment $adjustment): void
    {
        $this->assertPending($adjustment);
        $adjustment->delete();
    }

    protected function assertPending(PayrollAdjustment $adjustment): void
    {
        if (! $adjustment->isPending()) {
            throw ValidationException::withMessages([
                'adjustment' => 'Only pending bonuses / deductions can be changed or deleted.',
            ]);
        }
    }

    protected function assertEmployee(int $userId): void
    {
        $exists = EmployeeProfile::query()
            ->active()
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user_id' => 'Select an active employee.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(PayrollAdjustment $adj): array
    {
        return [
            'id' => $adj->id,
            'user_id' => $adj->user_id,
            'type' => $adj->type,
            'amount' => round((float) $adj->amount, 2),
            'effective_date' => $adj->effective_date?->format('Y-m-d'),
            'status' => $adj->status,
            'notes' => $adj->notes,
            'can_edit' => $adj->isPending(),
            'user' => $adj->user
                ? [
                    'id' => $adj->user->id,
                    'name' => $adj->user->name ?: $adj->user->username,
                ]
                : null,
            'creator' => $adj->creator
                ? [
                    'id' => $adj->creator->id,
                    'name' => $adj->creator->name ?: $adj->creator->username,
                ]
                : null,
        ];
    }
}
