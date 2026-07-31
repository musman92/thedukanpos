<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ActivityLogService
{
    public function __construct(protected SettingService $settings) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   action?:string|null,
     *   user_id?:int|string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   logs: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   actions: list<string>,
     *   users: Collection,
     *   logging_enabled: bool
     * }
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $userId = $filters['user_id'] !== null && $filters['user_id'] !== ''
            ? (int) $filters['user_id']
            : null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $logs = ActivityLog::query()
            ->with('user:id,name,username')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('description', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%");
                });
            })
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->serialize($log));

        $actions = ActivityLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values()
            ->all();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ]);

        return [
            'logs' => $logs,
            'filters' => [
                'q' => $q,
                'from' => $from,
                'to' => $to,
                'action' => $action,
                'user_id' => $userId ? (string) $userId : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'actions' => $actions,
            'users' => $users,
            'logging_enabled' => $this->settings->activityLoggingEnabled(),
        ];
    }

    public function setLoggingEnabled(bool $enabled): void
    {
        $this->settings->update([
            'activity_logging_enabled' => $enabled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'properties' => $log->properties,
            'ip_address' => $log->ip_address,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'created_at' => format_company_datetime($log->created_at),
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'username' => $log->user->username,
            ] : null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'created_at', 'action'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }
}
