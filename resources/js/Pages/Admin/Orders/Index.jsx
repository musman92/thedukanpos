import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Monitor, Search } from 'lucide-react';
import { useState } from 'react';

function hasRoute(name) {
    try {
        route(name);
        return true;
    } catch {
        return false;
    }
}

function PaymentBadge({ status }) {
    if (status === 'paid') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Paid
            </span>
        );
    }
    if (status === 'partial') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Partial
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
            Pending
        </span>
    );
}

export default function Index({ sales, filters, customers = [], branch }) {
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        customer_id: filters.customer_id || '',
        payment_status: filters.payment_status || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';
    const posAvailable = hasRoute('pos.index');

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        customer_id: filters.customer_id || '',
        payment_status: filters.payment_status || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}) => {
        router.get(route('admin.orders.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
        });
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, ...localFilters });
    };

    return (
        <AdminLayout
            title="Orders"
            description={
                branch?.name
                    ? `Sales completed at POS for ${branch.name}.`
                    : 'Sales completed at POS.'
            }
            actions={
                posAvailable ? (
                    <a
                        href="/pos"
                        onClick={(e) => {
                            e.preventDefault();
                            window.location.assign('/pos');
                        }}
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-transparent bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)]"
                    >
                        <Monitor className="h-4 w-4" strokeWidth={2.25} />
                        Open POS
                    </a>
                ) : null
            }
        >
            <Head title="Orders" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="orders"
                            routeName="admin.orders.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="relative w-full sm:w-auto">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search number, customer…"
                                    className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2">
                        <select
                            value={localFilters.customer_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, customer_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All customers</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.payment_status}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, payment_status: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All payments</option>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <Button type="submit" variant="secondary" size="sm" className="shrink-0">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="When"
                                    column="created_at"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Number"
                                    column="number"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Customer</th>
                                <SortableTh
                                    label="Total"
                                    column="total"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Paid"
                                    column="paid_total"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Payment</th>
                                <th className="px-3 py-3 font-semibold">Cashier</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No orders yet. Complete a sale on POS to see it here.
                                    </td>
                                </tr>
                            )}
                            {sales.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(sales.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.created_at}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        <span className="inline-flex flex-wrap items-center gap-1.5">
                                            {row.number}
                                            {row.is_delivery && (
                                                <span className="rounded-full bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                                    Delivery
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink">
                                        {row.customer?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {money(row.paid_total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <PaymentBadge status={row.payment_status} />
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.cashier?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.orders.show', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={sales} />
            </div>
        </AdminLayout>
    );
}
