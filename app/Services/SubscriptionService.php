<?php

namespace App\Services;

use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionService
{
    /**
     * @param  array{
     *   q?:string|null,
     *   status?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   subscription: array<string, mixed>,
     *   invoices: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *   filters: array<string, mixed>
     * }
     */
    public function page(array $filters = []): array
    {
        $tenantId = (string) tenant('id');
        $tenant = Tenant::query()->findOrFail($tenantId);

        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if (! in_array($status, ['all', 'open', 'paid', 'void'], true)) {
            $status = 'all';
        }
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $openTotal = (float) PlatformInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->sum('amount');

        $openCount = (int) PlatformInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();

        $invoices = PlatformInvoice::query()
            ->where('tenant_id', $tenantId)
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PlatformInvoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'amount' => round((float) $inv->amount, 2),
                'status' => $inv->status,
                'invoice_date' => optional($inv->invoice_date)?->toDateString(),
                'due_date' => optional($inv->due_date)?->toDateString(),
                'paid_at' => optional($inv->paid_at)?->toDateString(),
                'notes' => $inv->notes,
            ]);

        return [
            'subscription' => [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'plan' => $tenant->plan,
                'billing_status' => $tenant->billing_status,
                'monthly_fee' => round((float) $tenant->monthly_fee, 2),
                'currency' => $tenant->currency,
                'trial_ends_at' => optional($tenant->trial_ends_at)?->toDateString(),
                'billing_notes' => $tenant->billing_notes,
                'open_invoice_count' => $openCount,
                'open_invoice_total' => round($openTotal, 2),
            ],
            'invoices' => $invoices,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'number', 'invoice_date', 'due_date', 'amount', 'status', 'paid_at'];
        $sort = strtolower(trim((string) ($sort ?? 'invoice_date')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'invoice_date';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }
}
