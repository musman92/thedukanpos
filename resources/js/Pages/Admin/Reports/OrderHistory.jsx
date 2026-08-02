import ReportsShell from '@/Components/Reports/ReportsShell';
import Pagination from '@/Components/Ui/Pagination';
import { Link, router } from '@inertiajs/react';
import { Eye, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function PaymentBadge({ status }) {
    if (status === 'paid') {
        return (
            <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                Paid
            </span>
        );
    }
    if (status === 'partial') {
        return (
            <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                Partial
            </span>
        );
    }
    if (status === 'void') {
        return (
            <span className="rounded-full bg-rose-500/15 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                Cancelled
            </span>
        );
    }
    return (
        <span className="rounded-full bg-theme-bg px-2 py-0.5 text-[11px] font-semibold text-theme-ink-muted ring-1 ring-theme-border">
            Pending
        </span>
    );
}

const fieldClass =
    'mt-1 block h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function OrderHistory({
    filters: initial = {},
    summary = {},
    sales,
    customers = [],
    branch,
}) {
    const [from, setFrom] = useState(initial.from || '');
    const [to, setTo] = useState(initial.to || '');
    const [customerId, setCustomerId] = useState(
        initial.customer_id != null ? String(initial.customer_id) : '',
    );
    const [orderNumber, setOrderNumber] = useState(initial.order_number || '');

    useEffect(() => {
        setFrom(initial.from || '');
        setTo(initial.to || '');
        setCustomerId(initial.customer_id != null ? String(initial.customer_id) : '');
        setOrderNumber(initial.order_number || '');
    }, [initial.from, initial.to, initial.customer_id, initial.order_number]);

    const apply = (e) => {
        e.preventDefault();
        router.get(
            route('admin.reports.order-history'),
            {
                from,
                to,
                customer_id: customerId || undefined,
                order_number: orderNumber || undefined,
                per_page: initial.per_page || 25,
            },
            { preserveState: true },
        );
    };

    const rows = sales?.data || [];

    return (
        <ReportsShell
            activeKey="order-history"
            title="Order History"
            branch={branch}
            filters={initial}
            suppressFilters
            filterBar={
                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <div className="min-w-[10rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Branch
                        </label>
                        <div className={`${fieldClass} flex items-center`}>
                            {branch?.name || 'Current'}
                        </div>
                    </div>
                    <div>
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            From
                        </label>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className={fieldClass}
                        />
                    </div>
                    <div>
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            To
                        </label>
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className={fieldClass}
                        />
                    </div>
                    <div className="min-w-[12rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Customer
                        </label>
                        <select
                            value={customerId}
                            onChange={(e) => setCustomerId(e.target.value)}
                            className={`dp-select-reset ${fieldClass}`}
                        >
                            <option value="">All customers</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="min-w-[10rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Order #
                        </label>
                        <input
                            value={orderNumber}
                            onChange={(e) => setOrderNumber(e.target.value)}
                            placeholder="SL-…"
                            className={fieldClass}
                        />
                    </div>
                    <button
                        type="submit"
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)]"
                    >
                        <RefreshCw className="h-3.5 w-3.5" />
                        Apply
                    </button>
                </form>
            }
            csvColumns={[
                { key: 'number', label: 'Order #' },
                { key: 'created_at', label: 'Date' },
                { key: 'status', label: 'Status' },
                { key: 'payment_status', label: 'Payment' },
                { key: 'customer', label: 'Customer' },
                { key: 'cashier', label: 'Cashier' },
                { key: 'item_count', label: 'Items' },
                { key: 'total', label: 'Total' },
                { key: 'paid', label: 'Paid' },
                { key: 'discount', label: 'Discount' },
            ]}
            csvRows={rows}
        >
            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Orders
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {summary.orders ?? 0}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Total sale
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-primary">
                        {money(summary.total)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Paid
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-emerald-600">
                        {money(summary.paid)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Due
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-amber-700">
                        {money(summary.due)}
                    </p>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-theme-border">
                <div className="border-b border-theme-border bg-theme-bg px-4 py-2.5">
                    <h3 className="text-sm font-semibold text-theme-ink">Orders</h3>
                </div>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg/70 text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Order #
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Date
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Customer
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Cashier
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Status
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Items
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Total
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Paid
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Detail
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((s) => (
                            <tr key={s.id} className="border-t border-theme-border/80">
                                <td className="px-4 py-2.5">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Link
                                            href={route('admin.orders.show', s.id)}
                                            className="font-mono text-xs font-semibold text-theme-primary hover:underline"
                                        >
                                            {s.number}
                                        </Link>
                                        {s.is_delivery && (
                                            <span className="rounded-full bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                                Delivery
                                            </span>
                                        )}
                                    </div>
                                </td>
                                <td className="px-4 py-2.5 text-theme-ink-soft">{s.created_at}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.customer}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.cashier || '—'}</td>
                                <td className="px-4 py-2.5">
                                    {s.status === 'void' ? (
                                        <PaymentBadge status="void" />
                                    ) : (
                                        <PaymentBadge status={s.payment_status} />
                                    )}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                    {s.item_count}
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-ink">
                                    {money(s.total)}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink-soft">
                                    {money(s.paid)}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    <Link
                                        href={route('admin.orders.show', s.id)}
                                        className="inline-flex items-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-2.5 py-1.5 text-xs font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink"
                                    >
                                        <Eye className="h-3.5 w-3.5" />
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={9}
                                    className="px-4 py-10 text-center text-theme-ink-muted"
                                >
                                    No orders for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {sales && (
                <div className="mt-4 print:hidden">
                    <Pagination paginator={sales} />
                </div>
            )}
        </ReportsShell>
    );
}
