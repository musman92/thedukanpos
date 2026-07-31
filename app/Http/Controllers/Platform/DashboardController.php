<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tenants = Tenant::query()->get([
            'id',
            'code',
            'name',
            'is_active',
            'plan',
            'billing_status',
            'monthly_fee',
            'trial_ends_at',
            'created_at',
        ]);

        $openInvoices = PlatformInvoice::query()
            ->where('status', 'open')
            ->get(['id', 'amount', 'status']);

        $paidThisMonth = (float) PlatformInvoice::query()
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $mrr = (float) $tenants
            ->where('is_active', true)
            ->whereIn('billing_status', ['active', 'trial'])
            ->sum('monthly_fee');

        $recentTenants = $tenants
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'plan' => $t->plan,
                'billing_status' => $t->billing_status,
                'is_active' => (bool) $t->is_active,
                'created_at' => optional($t->created_at)?->toDateString(),
            ]);

        $recentInvoices = PlatformInvoice::query()
            ->with('tenant:id,code,name')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PlatformInvoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'amount' => round((float) $inv->amount, 2),
                'status' => $inv->status,
                'tenant_code' => $inv->tenant?->code,
                'invoice_date' => optional($inv->invoice_date)?->toDateString(),
            ]);

        return Inertia::render('Platform/Dashboard', [
            'auth' => [
                'user' => Auth::guard('platform')->user()?->only(['id', 'name', 'email']),
            ],
            'stats' => [
                'tenants_total' => $tenants->count(),
                'tenants_active' => $tenants->where('is_active', true)->count(),
                'tenants_suspended' => $tenants->where('is_active', false)->count(),
                'tenants_trial' => $tenants->where('billing_status', 'trial')->count(),
                'tenants_past_due' => $tenants->where('billing_status', 'past_due')->count(),
                'open_invoices' => $openInvoices->count(),
                'open_invoice_amount' => round((float) $openInvoices->sum('amount'), 2),
                'paid_this_month' => round($paidThisMonth, 2),
                'mrr' => round($mrr, 2),
            ],
            'recent_tenants' => $recentTenants,
            'recent_invoices' => $recentInvoices,
        ]);
    }
}
