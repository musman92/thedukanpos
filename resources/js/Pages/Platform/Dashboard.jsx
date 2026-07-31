import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CircleDollarSign,
    FileText,
    ShieldOff,
    Users,
} from 'lucide-react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StatCard({ label, value, hint, icon: Icon, tone = 'ink' }) {
    const tones = {
        ink: 'text-theme-ink',
        success: 'text-emerald-700',
        warning: 'text-amber-700',
        danger: 'text-rose-700',
        primary: 'text-theme-primary',
    };

    return (
        <div className="dp-card overflow-hidden p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        {label}
                    </p>
                    <p className={`mt-1 text-2xl font-semibold tabular-nums ${tones[tone] || tones.ink}`}>
                        {value}
                    </p>
                    {hint && <p className="mt-1 text-xs text-theme-ink-muted">{hint}</p>}
                </div>
                {Icon && (
                    <div className="rounded-lg bg-theme-bg p-2 text-theme-ink-muted">
                        <Icon className="h-4 w-4" strokeWidth={1.75} />
                    </div>
                )}
            </div>
        </div>
    );
}

function BillingBadge({ status }) {
    const styles = {
        trial: 'bg-amber-500/15 text-amber-700',
        active: 'bg-emerald-500/15 text-emerald-700',
        past_due: 'bg-rose-500/15 text-rose-700',
        cancelled: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    };

    return (
        <span
            className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                styles[status] || styles.cancelled
            }`}
        >
            {status || '—'}
        </span>
    );
}

export default function Dashboard({
    stats = {},
    recent_tenants: recentTenants = [],
    recent_invoices: recentInvoices = [],
}) {
    return (
        <PlatformLayout
            title="Dashboard"
            description="Overview of tenants, billing health, and recent invoices."
            actions={
                <Link
                    href={route('platform.tenants.index')}
                    className="inline-flex h-9 items-center rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)]"
                >
                    Manage tenants
                </Link>
            }
        >
            <Head title="Platform · Dashboard" />

            <div className="space-y-5">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Tenants"
                        value={stats.tenants_total ?? 0}
                        hint={`${stats.tenants_active ?? 0} active`}
                        icon={Building2}
                    />
                    <StatCard
                        label="Est. MRR"
                        value={money(stats.mrr)}
                        hint="Active + trial monthly fees"
                        icon={CircleDollarSign}
                        tone="primary"
                    />
                    <StatCard
                        label="Open invoices"
                        value={stats.open_invoices ?? 0}
                        hint={`${money(stats.open_invoice_amount)} outstanding`}
                        icon={FileText}
                        tone="warning"
                    />
                    <StatCard
                        label="Paid this month"
                        value={money(stats.paid_this_month)}
                        hint="Marked paid in current month"
                        icon={Users}
                        tone="success"
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        label="On trial"
                        value={stats.tenants_trial ?? 0}
                        icon={AlertTriangle}
                        tone="warning"
                    />
                    <StatCard
                        label="Past due"
                        value={stats.tenants_past_due ?? 0}
                        icon={AlertTriangle}
                        tone="danger"
                    />
                    <StatCard
                        label="Suspended"
                        value={stats.tenants_suspended ?? 0}
                        icon={ShieldOff}
                        tone="danger"
                    />
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    <div className="dp-card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-theme-border px-4 py-3">
                            <h2 className="text-sm font-semibold text-theme-ink">Recent tenants</h2>
                            <Link
                                href={route('platform.tenants.index')}
                                className="text-xs font-semibold text-theme-primary hover:underline"
                            >
                                View all
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-theme-bg/70 text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Tenant
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Plan
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Billing
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentTenants.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-4 py-8 text-center text-sm text-theme-ink-muted"
                                            >
                                                No tenants yet.
                                            </td>
                                        </tr>
                                    )}
                                    {recentTenants.map((t) => (
                                        <tr
                                            key={t.id}
                                            className="border-t border-theme-border/80"
                                        >
                                            <td className="px-4 py-2.5">
                                                <p className="font-medium text-theme-ink">{t.name}</p>
                                                <p className="font-mono text-[11px] text-theme-ink-muted">
                                                    {t.code}
                                                </p>
                                            </td>
                                            <td className="px-4 py-2.5 text-theme-ink-soft">
                                                {t.plan || '—'}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <BillingBadge status={t.billing_status} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="dp-card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-theme-border px-4 py-3">
                            <h2 className="text-sm font-semibold text-theme-ink">Recent invoices</h2>
                            <Link
                                href={route('platform.invoices.index')}
                                className="text-xs font-semibold text-theme-primary hover:underline"
                            >
                                View all
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-theme-bg/70 text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Invoice
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Tenant
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                            Amount
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentInvoices.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-4 py-8 text-center text-sm text-theme-ink-muted"
                                            >
                                                No invoices yet.
                                            </td>
                                        </tr>
                                    )}
                                    {recentInvoices.map((inv) => (
                                        <tr
                                            key={inv.id}
                                            className="border-t border-theme-border/80"
                                        >
                                            <td className="px-4 py-2.5 font-mono text-xs text-theme-ink">
                                                {inv.number}
                                            </td>
                                            <td className="px-4 py-2.5 text-theme-ink-soft">
                                                {inv.tenant_code || '—'}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                                {money(inv.amount)}
                                            </td>
                                            <td className="px-4 py-2.5 capitalize text-theme-ink-soft">
                                                {inv.status}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </PlatformLayout>
    );
}
