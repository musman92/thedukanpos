<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\SeedTenantAdmin;
use App\Models\SupportLoginToken;
use App\Models\Tenant;
use App\Services\DemoSeedService;
use App\Services\SettingService;
use App\Support\PageLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = strtolower(trim((string) $request->input('status', 'all')));
        if (! in_array($status, ['all', 'active', 'suspended'], true)) {
            $status = 'all';
        }
        $billing = strtolower(trim((string) $request->input('billing_status', 'all')));
        if (! in_array($billing, ['all', 'trial', 'active', 'past_due', 'cancelled'], true)) {
            $billing = 'all';
        }

        $perPage = PageLimit::resolve($request->input('per_page'), PageLimit::DEFAULT);
        [$sort, $direction] = $this->resolveSort(
            $request->input('sort'),
            $request->input('direction'),
        );

        $tenants = Tenant::query()
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('plan', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'suspended', fn (Builder $query) => $query->where('is_active', false))
            ->when($billing !== 'all', fn (Builder $query) => $query->where('billing_status', $billing))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderBy('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $t) => $this->tenantPayload($t));

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'billing_status' => $billing,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
                'company_page_limit' => PageLimit::DEFAULT,
            ],
            'form_open' => $request->boolean('open'),
            'edit_tenant_id' => $request->input('edit'),
            'form_meta' => $this->formMeta(),
            'auth' => [
                'user' => Auth::guard('platform')->user()?->only(['id', 'name', 'email']),
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => $this->tenantPayload($tenant),
            'demo_seed' => app(DemoSeedService::class)->status($tenant),
            'form_meta' => $this->formMeta(),
            'auth' => [
                'user' => Auth::guard('platform')->user()?->only(['id', 'name', 'email']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('tenants', 'code')],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(SettingService::CURRENCIES)],
            'timezone' => ['nullable', 'string', 'timezone'],
            'status' => ['required', 'in:active,suspended,inactive'],
            'trial_days' => ['nullable', 'integer', 'in:0,7,14,30,60,90'],
            'trial_ends_at' => ['nullable', 'date'],
            'plan' => ['nullable', 'string', 'max:50'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'billing_notes' => ['nullable', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'create_default_branch' => ['boolean'],
            'is_demo' => ['boolean'],
        ]);

        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $this->uniqueCodeFromName($data['name']);
        }

        $trialDays = (int) ($data['trial_days'] ?? 14);
        $trialEndsAt = ! empty($data['trial_ends_at'])
            ? $data['trial_ends_at']
            : ($trialDays > 0 ? now()->addDays($trialDays)->toDateString() : null);

        $isActive = ($data['status'] ?? 'active') === 'active';
        $billingStatus = $trialDays > 0 || $trialEndsAt ? 'trial' : 'active';
        if (! $isActive) {
            $billingStatus = 'cancelled';
        }

        $currency = strtoupper((string) ($data['currency'] ?? 'PKR'));
        $timezone = (string) ($data['timezone'] ?? 'Asia/Karachi');

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'currency' => $currency,
            'timezone' => $timezone,
            'is_active' => $isActive,
            'is_demo' => $request->boolean('is_demo'),
            'plan' => $data['plan'] ?? 'starter',
            'billing_status' => $billingStatus,
            'monthly_fee' => $data['monthly_fee'] ?? 0,
            'trial_ends_at' => $trialEndsAt,
            'billing_notes' => $data['billing_notes'] ?? null,
        ]);

        (new SeedTenantAdmin(
            $tenant,
            [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ],
            [
                'shop_name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? '',
                'address' => $data['address'] ?? '',
                'tax_id' => $data['tax_id'] ?? '',
                'currency' => $currency,
                'timezone' => $timezone,
                'create_default_branch' => $request->boolean('create_default_branch', true),
                'branch_name' => $data['name'],
            ],
        ))->handle();

        return redirect()
            ->route('platform.tenants.index')
            ->with('status', "Tenant {$tenant->code} created. Login as admin@{$tenant->code}");
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('tenants', 'code')->ignore($tenant->id),
            ],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(SettingService::CURRENCIES)],
            'timezone' => ['nullable', 'string', 'timezone'],
            'status' => ['required', 'in:active,suspended,inactive'],
            'plan' => ['nullable', 'string', 'max:50'],
            'billing_status' => ['nullable', 'in:trial,active,past_due,cancelled'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'trial_ends_at' => ['nullable', 'date'],
            'billing_notes' => ['nullable', 'string'],
            'is_demo' => ['boolean'],
        ]);

        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $tenant->code;
        }

        $isActive = ($data['status'] ?? 'active') === 'active';

        $tenant->update([
            'code' => $code,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? $tenant->currency ?? 'PKR')),
            'timezone' => (string) ($data['timezone'] ?? $tenant->timezone ?? 'Asia/Karachi'),
            'is_active' => $isActive,
            'is_demo' => $request->boolean('is_demo'),
            'plan' => $data['plan'] ?? $tenant->plan,
            'billing_status' => $data['billing_status'] ?? $tenant->billing_status,
            'monthly_fee' => $data['monthly_fee'] ?? $tenant->monthly_fee,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'billing_notes' => $data['billing_notes'] ?? null,
        ]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('status', "Tenant {$tenant->code} updated.");
    }

    public function updateBilling(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:50'],
            'billing_status' => ['required', 'in:trial,active,past_due,cancelled'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'trial_ends_at' => ['nullable', 'date'],
            'billing_notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_demo' => ['boolean'],
        ]);

        $tenant->update([
            ...$data,
            'is_active' => $request->boolean('is_active', $tenant->is_active),
            'is_demo' => $request->boolean('is_demo', $tenant->is_demo),
        ]);

        return back()->with('status', "Billing updated for {$tenant->code}.");
    }

    public function createSupportLogin(Tenant $tenant): RedirectResponse
    {
        if (! $tenant->is_active) {
            return back()->withErrors(['tenant' => 'Tenant is suspended.']);
        }

        $token = SupportLoginToken::query()->create([
            'token' => Str::random(48),
            'tenant_id' => $tenant->id,
            'created_by' => Auth::guard('platform')->id(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $url = url('/support-login/'.$token->token);

        return back()->with('status', "Support login (15 min): {$url}");
    }

    public function seedDemo(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $tenant->is_demo) {
            return back()->withErrors(['tenant' => 'Only demo tenants can be seeded.']);
        }

        try {
            app(DemoSeedService::class)->dispatch(
                $tenant,
                Auth::guard('platform')->id(),
                force: $request->boolean('force'),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['tenant' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Demo seed started. Keep this page open a moment, then refresh — status should move to running, then completed (can take a few minutes).',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function tenantPayload(Tenant $t): array
    {
        return [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'email' => $t->email,
            'phone' => $t->phone,
            'address' => $t->address,
            'tax_id' => $t->tax_id,
            'currency' => $t->currency ?: 'PKR',
            'timezone' => $t->timezone ?: 'Asia/Karachi',
            'plan' => $t->plan,
            'billing_status' => $t->billing_status,
            'monthly_fee' => round((float) $t->monthly_fee, 2),
            'trial_ends_at' => optional($t->trial_ends_at)?->toDateString(),
            'billing_notes' => $t->billing_notes,
            'is_active' => (bool) $t->is_active,
            'is_demo' => (bool) $t->is_demo,
            'status' => $t->is_active ? 'active' : 'suspended',
            'created_at' => optional($t->created_at)?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formMeta(): array
    {
        return [
            'currencies' => SettingService::CURRENCIES,
            'timezones' => $this->timezoneOptions(),
            'trial_options' => [
                ['value' => 0, 'label' => 'No trial'],
                ['value' => 7, 'label' => '7 days'],
                ['value' => 14, 'label' => '14 days'],
                ['value' => 30, 'label' => '30 days'],
                ['value' => 60, 'label' => '60 days'],
                ['value' => 90, 'label' => '90 days'],
            ],
            'default_trial_days' => 14,
        ];
    }

    protected function uniqueCodeFromName(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'shop';
        }

        $code = $base;
        $counter = 1;
        while (Tenant::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$counter;
            $counter++;
        }

        return $code;
    }

    /**
     * @return list<array{value:string, label:string}>
     */
    protected function timezoneOptions(): array
    {
        $zones = [
            'Asia/Karachi' => 'Karachi (PKT)',
            'Asia/Dubai' => 'Dubai (GST)',
            'Asia/Riyadh' => 'Riyadh (AST)',
            'Asia/Kolkata' => 'Mumbai/New Delhi (IST)',
            'Asia/Singapore' => 'Singapore (SGT)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Asia/Hong_Kong' => 'Hong Kong (HKT)',
            'Europe/London' => 'London (GMT)',
            'Europe/Paris' => 'Paris (CET)',
            'Europe/Berlin' => 'Berlin (CET)',
            'America/New_York' => 'Eastern Time (ET)',
            'America/Chicago' => 'Central Time (CT)',
            'America/Denver' => 'Mountain Time (MT)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'America/Toronto' => 'Toronto (ET)',
            'Australia/Sydney' => 'Sydney (AEDT)',
            'UTC' => 'UTC',
        ];

        return collect($zones)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => "{$label} ({$value})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['code', 'name', 'plan', 'billing_status', 'monthly_fee', 'created_at', 'id'];
        $sort = strtolower(trim((string) ($sort ?? 'code')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'code';
        }
        $direction = strtolower(trim((string) ($direction ?? 'asc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return [$sort, $direction];
    }
}
