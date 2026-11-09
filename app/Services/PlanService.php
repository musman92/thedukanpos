<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PageLimit;
use App\Support\PlanLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PlanService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{plans: \Illuminate\Contracts\Pagination\LengthAwarePaginator, filters: array<string, mixed>}
     */
    public function page(array $filters = []): array
    {
        $perPage = PageLimit::resolve($filters['per_page'] ?? null, PageLimit::DEFAULT);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort($filters['sort'] ?? null, $filters['direction'] ?? null);

        $plans = Plan::query()
            ->withCount('tenants')
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderBy('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Plan $plan) => $this->payload($plan));

        return [
            'plans' => $plans,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => PageLimit::DEFAULT,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        return Plan::query()->create($this->attributes($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($this->attributes($data));
        Tenant::query()->where('plan_id', $plan->id)->update(['plan' => $plan->code]);

        return $plan->refresh();
    }

    public function delete(Plan $plan): void
    {
        if ($plan->tenants()->exists()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is assigned to a company. Move those companies to another plan first.',
            ]);
        }

        $plan->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function options(): array
    {
        return Plan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'is_active' => $plan->is_active,
                'product_limit' => $plan->product_limit,
                'user_limit' => $plan->user_limit,
                'branch_limit' => $plan->branch_limit,
                'ads_enabled' => $plan->ads_enabled,
                'monthly_price' => round((float) $plan->monthly_price, 2),
                'quarterly_price' => round((float) $plan->quarterly_price, 2),
                'yearly_price' => round((float) $plan->yearly_price, 2),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function signupAttributes(): array
    {
        $plan = Plan::query()->where('code', 'free')->where('is_active', true)->first()
            ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();

        return [
            'plan_id' => $plan?->id,
            'plan' => $plan?->code ?? 'free',
            'billing_interval' => 'monthly',
            'monthly_fee' => $plan ? $plan->priceFor('monthly') : 0,
            'override_limits' => false,
            'product_limit' => null,
            'user_limit' => null,
            'branch_limit' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentRules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_interval' => ['required', 'in:monthly,quarterly,yearly'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'override_limits' => ['boolean'],
            'product_limit' => ['nullable', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'branch_limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assignment(array $data): array
    {
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $interval = strtolower((string) ($data['billing_interval'] ?? 'monthly'));
        if (! in_array($interval, Plan::INTERVALS, true)) {
            $interval = 'monthly';
        }
        $override = $this->bool($data, 'override_limits');
        $fee = $data['monthly_fee'] ?? null;
        if ($fee === null || $fee === '') {
            $fee = $plan->priceFor($interval);
        }

        return [
            'plan_id' => $plan->id,
            'plan' => $plan->code,
            'billing_interval' => $interval,
            'monthly_fee' => $fee,
            'override_limits' => $override,
            'product_limit' => $override ? $this->nullableInt($data['product_limit'] ?? null) : null,
            'user_limit' => $override ? $this->nullableInt($data['user_limit'] ?? null) : null,
            'branch_limit' => $override ? $this->nullableInt($data['branch_limit'] ?? null) : null,
        ];
    }

    /**
     * @return array{product_limit: ?int, user_limit: ?int, branch_limit: ?int, ads_enabled: bool}
     */
    public function effectiveLimits(Tenant $tenant): array
    {
        $plan = $tenant->relationLoaded('planRecord')
            ? $tenant->planRecord
            : $tenant->planRecord()->first();

        if (! $plan && $tenant->plan) {
            $plan = Plan::query()->where('code', $tenant->plan)->first();
        }

        $limits = [
            'product_limit' => $plan?->product_limit,
            'user_limit' => $plan?->user_limit,
            'branch_limit' => $plan?->branch_limit,
            'ads_enabled' => (bool) ($plan?->ads_enabled ?? false),
        ];

        if ($tenant->override_limits) {
            $limits['product_limit'] = $tenant->product_limit;
            $limits['user_limit'] = $tenant->user_limit;
            $limits['branch_limit'] = $tenant->branch_limit;
        }

        return $limits;
    }

    /**
     * Current shop usage against the plan that applies right now.
     *
     * Login users are people who can sign in. Employees who cannot sign in
     * do not use a seat. A blank limit means there is no cap.
     *
     * @return array{
     *     code: ?string,
     *     name: string,
     *     product_limit: ?int,
     *     user_limit: ?int,
     *     branch_limit: ?int,
     *     ads_enabled: bool,
     *     product_count: int,
     *     user_count: int,
     *     branch_count: int
     * }
     */
    public function usage(): array
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $tenant->loadMissing('planRecord');
        $limits = $this->effectiveLimits($tenant);
        $name = trim((string) ($tenant->planRecord?->name ?: ''));
        if ($name === '') {
            $name = $tenant->plan ? ucfirst((string) $tenant->plan) : 'current';
        }

        return [
            'code' => $tenant->plan,
            'name' => $name,
            'product_limit' => $limits['product_limit'],
            'user_limit' => $limits['user_limit'],
            'branch_limit' => $limits['branch_limit'],
            'ads_enabled' => $limits['ads_enabled'],
            'product_count' => Product::query()->count(),
            'user_count' => User::query()->where('can_login', true)->where('is_active', true)->count(),
            'branch_count' => Branch::query()->count(),
        ];
    }

    public function assertRoom(string $resource, string $errorKey): void
    {
        $usage = $this->usage();
        $rawLimit = $usage[$resource.'_limit'] ?? null;
        $limit = $rawLimit === null || $rawLimit === '' ? null : (int) $rawLimit;
        $count = (int) ($usage[$resource.'_count'] ?? 0);
        if (! PlanLimit::exceeded($limit, $count)) {
            return;
        }

        $nouns = [
            'product' => ['product', 'products'],
            'user' => ['login user', 'login users'],
            'branch' => ['branch', 'branches'],
        ];
        [$singular, $plural] = $nouns[$resource] ?? [$resource, $resource];
        $noun = $limit === 1 ? $singular : $plural;

        throw ValidationException::withMessages([
            $errorKey => PlanLimit::message($usage['name'], $noun, $limit, $count),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'code' => strtolower(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'description' => $this->nullableString($data['description'] ?? null),
            'product_limit' => $this->nullableInt($data['product_limit'] ?? null),
            'user_limit' => $this->nullableInt($data['user_limit'] ?? null),
            'branch_limit' => $this->nullableInt($data['branch_limit'] ?? null),
            'ads_enabled' => $this->bool($data, 'ads_enabled'),
            'monthly_price' => $data['monthly_price'] ?? 0,
            'quarterly_price' => $data['quarterly_price'] ?? 0,
            'yearly_price' => $data['yearly_price'] ?? 0,
            'is_active' => $this->bool($data, 'is_active', true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'product_limit' => $plan->product_limit,
            'user_limit' => $plan->user_limit,
            'branch_limit' => $plan->branch_limit,
            'ads_enabled' => $plan->ads_enabled,
            'monthly_price' => round((float) $plan->monthly_price, 2),
            'quarterly_price' => round((float) $plan->quarterly_price, 2),
            'yearly_price' => round((float) $plan->yearly_price, 2),
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'tenants_count' => (int) ($plan->tenants_count ?? $plan->tenants()->count()),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name', 'code', 'sort_order', 'monthly_price', 'is_active'];
        $sort = strtolower(trim((string) ($sort ?? 'sort_order')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'sort_order';
        }
        $direction = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function bool(array $data, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return $default;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
